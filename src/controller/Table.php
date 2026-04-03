<?php
declare(strict_types=1);

namespace plugin\backup\controller;

use plugin\backup\model\PluginBackupTable;
use think\admin\Controller;
use think\admin\helper\QueryHelper;
use think\facade\Db;

/**
 * 数据库表管理
 * @class Table
 * @package plugin\backup\controller
 */
class Table extends Controller
{
    /**
     * 数据库表列表
     * @auth true
     * @menu true
     */
    public function index(): void
    {
        $this->title = '数据表管理';

        // layTable 分页/排序请求（POST 或 GET 带 output=layui.table）
        if ($this->request->isPost() || input('output') === 'layui.table') {
            $page = max(1, intval(input('page', 1)));
            $limit = max(1, min(200, intval(input('limit', 20))));
            $offset = ($page - 1) * $limit;
            $keyword = trim(input('keyword', ''));

            $db = config('database.connections.mysql');
            $dbname = $db['database'] ?? '';

            $where = "WHERE TABLE_SCHEMA = ?";
            $params = [$dbname];
            if ($keyword !== '') {
                $where .= " AND (TABLE_NAME LIKE ? OR TABLE_COMMENT LIKE ?)";
                $params[] = "%{$keyword}%";
                $params[] = "%{$keyword}%";
            }

            $countSql = "SELECT COUNT(*) AS total FROM information_schema.TABLES {$where}";
            $total = intval(Db::query($countSql, $params)[0]['total'] ?? 0);

            $orderField = preg_replace('/[^a-z_]/i', '', trim(input('_field_', 'data_size')));
            $orderType  = strtoupper(trim(input('_order_', 'desc')));
            $orderType  = in_array($orderType, ['ASC', 'DESC']) ? $orderType : 'DESC';
            $allowSort  = ['name', 'rows', 'data_size', 'index_size', 'data_free', 'engine', 'collation', 'comment', 'create_time', 'update_time'];
            if (!in_array($orderField, $allowSort)) {
                $orderField = 'data_size';
            }

            $listSql = "SELECT TABLE_NAME AS `name`, TABLE_ROWS AS `rows`,
                              DATA_LENGTH AS data_size, INDEX_LENGTH AS index_size,
                              DATA_FREE AS data_free, ENGINE AS `engine`,
                              TABLE_COLLATION AS collation, TABLE_COMMENT AS `comment`,
                              CREATE_TIME AS create_time, UPDATE_TIME AS update_time
                       FROM information_schema.TABLES
                       {$where}
                       ORDER BY `{$orderField}` {$orderType}
                       LIMIT {$limit} OFFSET {$offset}";
            $list = Db::query($listSql, $params);

            foreach ($list as &$row) {
                $row['rows'] = intval($row['rows'] ?? 0);
                $row['data_size'] = intval($row['data_size'] ?? 0);
                $row['index_size'] = intval($row['index_size'] ?? 0);
                $row['data_free'] = intval($row['data_free'] ?? 0);
            }
            unset($row);

            json(['code' => 0, 'msg' => '', 'count' => $total, 'data' => $list])->send();
            return;
        }

        // GET 请求：渲染视图
        $this->fetch();
    }

    /**
     * 表结构预览
     * @auth true
     */
    public function info(): void
    {
        $table = trim(input('table', ''));
        if (empty($table)) {
            $this->error('表名不能为空！');
        }
        $this->title = "表结构: {$table}";
        $this->table = $table;
        $this->columns = PluginBackupTable::getTableColumns($table);
        $this->indexes = PluginBackupTable::getTableIndexes($table);
        $this->fetch('info');
    }

    /**
     * 表数据预览
     * @auth true
     */
    public function data(): void
    {
        $table = trim(input('table', ''));
        if (empty($table)) {
            $this->error('表名不能为空！');
        }

        // AJAX 分页请求
        if ($this->request->isPost() || input('output') === 'layui.table') {
            $page   = max(1, intval(input('page', 1)));
            $limit  = max(1, min(100, intval(input('limit', 20))));
            $offset = ($page - 1) * $limit;

            $data   = PluginBackupTable::getTableData($table, $limit, $offset);
            $fields = PluginBackupTable::getTableFieldNames($table);
            $count  = PluginBackupTable::getTableCount($table);

            json(['code' => 0, 'msg' => '', 'count' => $count, 'data' => $data, 'fields' => $fields])->send();
            return;
        }

        // GET：渲染弹窗视图
        $this->table   = $table;
        $this->fields  = PluginBackupTable::getTableFieldNames($table);
        $this->fetch('data');
    }

    /**
     * 优化表（单表或全表）
     * @auth true
     */
    public function optimize(): void
    {
        $table = trim(input('table', ''));
        if ($table === '') {
            // 批量优化所有表
            $tables = PluginBackupTable::getAllTables();
            $names = array_column($tables, 'name');
            $msgs = [];
            foreach ($names as $t) {
                $msgs[] = PluginBackupTable::optimizeTable($t);
            }
            $msg = lang('批量优化完成，共处理 %d 张表', [count($names)]) . ' ' . implode('；', $msgs);
            sysoplog('数据表管理', $msg);
            $this->success($msg);
        }
        $result = PluginBackupTable::optimizeTable($table);
        sysoplog('数据表管理', "优化数据表 {$table}，结果：{$result}");
        $this->success($result);
    }

    /**
     * 分析表（单表）
     * @auth true
     */
    public function analyze(): void
    {
        $table = trim(input('table', ''));
        if (empty($table)) {
            $this->error('表名不能为空！');
        }
        $result = PluginBackupTable::analyzeTable($table);
        sysoplog('数据表管理', "分析数据表 {$table}，结果：{$result}");
        $this->success($result);
    }

    /**
     * 检查表（单表）
     * @auth true
     */
    public function check(): void
    {
        $table = trim(input('table', ''));
        if (empty($table)) {
            $this->error('表名不能为空！');
        }
        $result = PluginBackupTable::checkTable($table);
        sysoplog('数据表管理', "检查数据表 {$table}，结果：{$result}");
        $this->success($result);
    }

    /**
     * 修复表（单表）
     * @auth true
     */
    public function repair(): void
    {
        $table = trim(input('table', ''));
        if (empty($table)) {
            $this->error('表名不能为空！');
        }
        $result = PluginBackupTable::repairTable($table);
        sysoplog('数据表管理', "修复数据表 {$table}，结果：{$result}");
        $this->success($result);
    }
}
