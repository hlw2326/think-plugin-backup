<?php
declare(strict_types=1);

namespace plugin\backup\controller;

use plugin\backup\model\PluginBackupRecord;
use plugin\backup\service\BackupService;
use think\admin\Controller;
use think\admin\helper\QueryHelper;
use think\admin\Storage;

/**
 * 数据库备份管理
 * @class Backup
 * @package plugin\backup\controller
 */
class Backup extends Controller
{
    /**
     * 备份列表
     * @auth true
     * @menu true
     */
    public function index(): void
    {
        PluginBackupRecord::mQuery()->layTable(function () {
            $this->title = '备份列表';
            $this->status_labels = PluginBackupRecord::getStatusLabels();
            $this->storage_types = Storage::types();
        }, function (QueryHelper $query) {
            $query->like('name,file');
            $query->equal('status');
            $query->dateBetween('create_at');
            $query->order('id desc');
        });
    }

    /**
     * 执行备份
     * @auth true
     */
    public function add(): void
    {
        $this->_applyFormToken();
        $this->title = '添加备份';
        PluginBackupRecord::mForm('form');
    }

    /**
     * 表单数据处理
     */
    protected function _form_filter(array &$data): void
    {
        if ($this->request->isPost()) {
            $name = trim($data['name'] ?? '');
            if (empty($name)) {
                $name = PluginBackupRecord::mk()->max('id', true) + 1;
                $name = '手动备份_' . date('Ymd') . '_' . str_pad((string) $name, 4, '0', STR_PAD_LEFT);
            }
            $service = BackupService::instance();
            $result = $service->backup($name);
            if ($result[0] === 1) {
                sysoplog('数据库备份', "执行数据库备份成功，文件: {$result[2]['file']}");
                $this->success('备份成功！');
            } else {
                sysoplog('数据库备份', "执行数据库备份失败: {$result[1]}");
                $this->error($result[1]);
            }
        }
    }

    /**
     * 执行数据库还原
     * @auth true
     */
    public function restore(): void
    {
        $data = $this->_vali([
            'id.require' => '备份ID不能为空！',
        ]);
        $record = PluginBackupRecord::mk()->find((int) $data['id']);
        if (empty($record)) {
            $this->error('备份记录不存在！');
        }
        if ($record['status'] != 1) {
            $this->error('该备份文件无效，无法还原');
        }
        $filepath = $record['path'];
        if (!\file_exists($filepath)) {
            $this->error('备份文件不存在！');
        }
        $sizeMB = \round($record['size'] / 1048576, 2);
        if ($sizeMB > 20) {
            $this->error("备份文件 {$sizeMB} MB 超过 20MB 限制，建议通过命令行或 phpMyAdmin 进行还原");
        }
        $service = BackupService::instance();
        $result = $service->restore((int) $data['id']);
        if ($result[0] === 1) {
            sysoplog('数据库还原', "数据库还原成功，备份ID: {$data['id']}");
            $this->success($result[1]);
        } else {
            sysoplog('数据库还原', "数据库还原失败: {$result[1]}");
            $this->error($result[1]);
        }
    }

    /**
     * 下载备份文件
     * @auth true
     */
    public function download(): void
    {
        $id = (int) input('id', 0);
        if ($id <= 0) {
            $this->error('备份ID不能为空！');
        }
        $record = PluginBackupRecord::mk()->find($id);
        if (empty($record)) {
            $this->error('备份记录不存在！');
        }
        if ($record['status'] != 1 || !\file_exists($record['path'])) {
            $this->error('备份文件不存在！');
        }
        sysoplog('数据库备份', "下载备份文件: {$record['file']}");
        $filepath = $record['path'];
        $filename = $record['file'];
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: no-cache');
        readfile($filepath);
        exit;
    }

    /**
     * 删除备份
     * @auth true
     */
    public function remove(): void
    {
        $data = $this->_vali([
            'id.require' => '备份ID不能为空！',
        ]);
        $service = BackupService::instance();
        $result = $service->delete((int) $data['id']);
        if ($result[0] === 1) {
            sysoplog('数据库备份', "删除备份记录，ID: {$data['id']}");
        }
        if ($result[0] === 1) {
            $this->success($result[1]);
        } else {
            $this->error($result[1]);
        }
    }

    /**
     * 清理孤立文件
     * @auth true
     */
    public function clean(): void
    {
        $service = BackupService::instance();
        $count = $service->cleanOrphanFiles();
        sysoplog('数据库备份', "清理孤立备份文件，删除 {$count} 个文件");
        $this->success("清理完成，共删除 {$count} 个孤立文件！");
    }
}
