<?php
declare(strict_types=1);

namespace plugin\backup\controller\api\v1;

use hlw2326\mp\shared\controller\api\v1\Base;
use plugin\backup\model\PluginBackupRecord;
use plugin\backup\service\BackupService;

/**
 * 数据备份接口
 * @class Backup
 * @package plugin\backup\controller\api\v1
 */
class Backup extends Base
{
    /**
     * 获取备份记录列表
     * 需要登录
     * @return void
     */
    public function list(): void
    {
        if (empty($this->userId)) {
            $this->error('请先登录');
            return;
        }

        $page  = max(1, intval($this->request->get('page/d', 1)));
        $limit = min(50, max(1, intval($this->request->get('limit/d', 20))));

        $query = PluginBackupRecord::mk()->where('status', 1);
        $total = $query->count();
        $list  = $query->order('id desc')->page($page, $limit)->select()->toArray();

        foreach ($list as &$vo) {
            $vo['size_format'] = PluginBackupRecord::formatSize($vo['size']);
        }

        $this->success('获取成功', [
            'list'  => $list,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * 获取备份统计
     * 需要登录
     * @return void
     */
    public function stats(): void
    {
        if (empty($this->userId)) {
            $this->error('请先登录');
            return;
        }

        $total = PluginBackupRecord::mk()->count();
        $success = PluginBackupRecord::mk()->where('status', 1)->count();
        $failed = PluginBackupRecord::mk()->where('status', 0)->count();
        $totalSize = PluginBackupRecord::mk()->where('status', 1)->sum('size') ?? 0;

        $this->success('获取成功', [
            'total'       => $total,
            'success'     => $success,
            'failed'      => $failed,
            'total_size'  => $totalSize,
            'size_format' => PluginBackupRecord::formatSize($totalSize),
        ]);
    }

    /**
     * 获取可备份的表列表
     * 无需登录（后台管理员使用）
     * @return void
     */
    public function tables(): void
    {
        try {
            $service = BackupService::instance();
            $tables = $service->getTables();

            $list = [];
            foreach ($tables as $vo) {
                $list[] = [
                    'name'  => $vo['name'],
                    'rows'  => $vo['rows'],
                    'size'  => $vo['size'],
                    'size_format' => PluginBackupRecord::formatSize($vo['size']),
                ];
            }

            $this->success('获取成功', ['list' => $list]);
        } catch (\Throwable $e) {
            $this->error('获取失败：' . $e->getMessage());
        }
    }
}