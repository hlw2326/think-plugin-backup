<?php
declare(strict_types=1);

namespace plugin\backup\model;

use think\admin\Model;

/**
 * 数据库表模型（虚拟模型，不对应实际数据库表）
 * @class PluginBackupTable
 * @package plugin\backup\model
 */
class PluginBackupTable extends Model
{
    /**
     * 获取数据库中所有表的信息
     */
    public static function getAllTables(): array
    {
        $db = config('database.connections.mysql');
        $dbname = $db['database'] ?? '';

        $list = \think\facade\Db::query(
            "SELECT TABLE_NAME, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH, DATA_FREE, ENGINE, TABLE_COLLATION, TABLE_COMMENT, CREATE_TIME, UPDATE_TIME
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ?
             ORDER BY TABLE_ROWS DESC, DATA_LENGTH DESC",
            [$dbname]
        );

        $result = [];
        foreach ($list as $row) {
            $result[] = [
                'name'       => $row['TABLE_NAME'],
                'rows'       => intval($row['TABLE_ROWS'] ?? 0),
                'data_size'  => intval($row['DATA_LENGTH'] ?? 0),
                'index_size' => intval($row['INDEX_LENGTH'] ?? 0),
                'data_free'  => intval($row['DATA_FREE'] ?? 0),
                'engine'     => $row['ENGINE'] ?? 'InnoDB',
                'collation'  => $row['TABLE_COLLATION'] ?? '',
                'comment'    => $row['TABLE_COMMENT'] ?? '',
                'create_time'=> $row['CREATE_TIME'] ?? '',
                'update_time'=> $row['UPDATE_TIME'] ?? '',
            ];
        }
        return $result;
    }

    /**
     * 获取单个表的字段信息
     */
    public static function getTableColumns(string $table): array
    {
        $db = config('database.connections.mysql');
        $dbname = $db['database'] ?? '';

        $cols = \think\facade\Db::query(
            "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_COMMENT, COLUMN_KEY, EXTRA
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
             ORDER BY ORDINAL_POSITION",
            [$dbname, $table]
        );

        $result = [];
        foreach ($cols as $col) {
            $result[] = [
                'name'    => $col['COLUMN_NAME'],
                'type'    => $col['COLUMN_TYPE'],
                'null'    => $col['IS_NULLABLE'],
                'default' => $col['COLUMN_DEFAULT'],
                'comment' => $col['COLUMN_COMMENT'],
                'key'     => $col['COLUMN_KEY'],
                'extra'   => $col['EXTRA'],
            ];
        }
        return $result;
    }

    /**
     * 获取单个表的索引信息
     */
    public static function getTableIndexes(string $table): array
    {
        $db = config('database.connections.mysql');
        $dbname = $db['database'] ?? '';

        return \think\facade\Db::query(
            "SELECT INDEX_NAME, NON_UNIQUE, COLUMN_NAME, SEQ_IN_INDEX, INDEX_TYPE
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
             ORDER BY INDEX_NAME, SEQ_IN_INDEX",
            [$dbname, $table]
        );
    }

    /**
     * 获取表的总记录数
     */
    public static function getTableCount(string $table): int
    {
        $table = trim($table, '`');
        $table = "`{$table}`";
        try {
            $result = \think\facade\Db::query("SELECT COUNT(*) AS cnt FROM {$table}");
            return intval($result[0]['cnt'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * 获取表的前 N 条数据
     */
    public static function getTableData(string $table, int $limit = 20, int $offset = 0): array
    {
        $table = trim($table, '`');
        $table = "`{$table}`";
        try {
            $rows = \think\facade\Db::query(
                "SELECT * FROM {$table} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}"
            );
            return $rows ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * 获取表字段名
     */
    public static function getTableFieldNames(string $table): array
    {
        $db = config('database.connections.mysql');
        $dbname = $db['database'] ?? '';

        $cols = \think\facade\Db::query(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
             ORDER BY ORDINAL_POSITION",
            [$dbname, $table]
        );

        return array_column($cols, 'COLUMN_NAME');
    }

    /**
     * 格式化字节大小
     */
    public static function formatSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        } elseif ($bytes < 1048576) {
            return round($bytes / 1024, 2) . ' KB';
        } elseif ($bytes < 1073741824) {
            return round($bytes / 1048576, 2) . ' MB';
        } else {
            return round($bytes / 1073741824, 2) . ' GB';
        }
    }

    /**
     * 优化表（ANALYZE + OPTIMIZE）
     */
    public static function optimizeTable(string $table): string
    {
        $table = trim($table, '`');
        $table = "`{$table}`";
        try {
            \think\facade\Db::execute("OPTIMIZE TABLE {$table}");
            return '优化成功';
        } catch (\Throwable $e) {
            return '优化失败: ' . $e->getMessage();
        }
    }

    /**
     * 分析表
     */
    public static function analyzeTable(string $table): string
    {
        $table = trim($table, '`');
        $table = "`{$table}`";
        try {
            \think\facade\Db::execute("ANALYZE TABLE {$table}");
            return '分析成功';
        } catch (\Throwable $e) {
            return '分析失败: ' . $e->getMessage();
        }
    }

    /**
     * 检查表
     */
    public static function checkTable(string $table): string
    {
        $table = trim($table, '`');
        $table = "`{$table}`";
        try {
            $result = \think\facade\Db::query("CHECK TABLE {$table}");
            if (!empty($result[0])) {
                return $result[0]['Msg_text'] ?? '检查完成';
            }
            return '检查完成';
        } catch (\Throwable $e) {
            return '检查失败: ' . $e->getMessage();
        }
    }

    /**
     * 修复表
     */
    public static function repairTable(string $table): string
    {
        $table = trim($table, '`');
        $table = "`{$table}`";
        try {
            $result = \think\facade\Db::query("REPAIR TABLE {$table}");
            if (!empty($result[0])) {
                return $result[0]['Msg_text'] ?? '修复完成';
            }
            return '修复完成';
        } catch (\Throwable $e) {
            return '修复失败: ' . $e->getMessage();
        }
    }
}
