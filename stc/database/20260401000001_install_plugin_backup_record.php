<?php

declare(strict_types=1);

use think\admin\extend\PhinxExtend;
use think\migration\Migrator;

@set_time_limit(0);
@ini_set('memory_limit', '-1');

class InstallPluginBackupRecord extends Migrator
{
    /**
     * 创建数据库备份表
     */
    public function up(): void
    {
        $table = $this->table('plugin_backup_record', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_general_ci',
            'comment' => '插件-数据备份记录',
        ]);

        PhinxExtend::upgrade($table, [
            [
                'name',
                'string',
                ['limit' => 255, 'default' => '', 'null' => true, 'comment' => '备份名称']
            ],
            [
                'file',
                'string',
                ['limit' => 500, 'default' => '', 'null' => true, 'comment' => '备份文件名']
            ],
            [
                'size',
                'biginteger',
                ['limit' => 20, 'default' => 0, 'null' => true, 'comment' => '备份文件大小(字节)']
            ],
            [
                'path',
                'string',
                ['limit' => 500, 'default' => '', 'null' => true, 'comment' => '备份文件路径']
            ],
            [
                'tables',
                'integer',
                ['limit' => 5, 'default' => 0, 'null' => true, 'comment' => '备份表数量']
            ],
            [
                'status',
                'integer',
                ['limit' => 1, 'default' => 1, 'null' => true, 'comment' => '状态(0失败,1成功)']
            ],
            [
                'storage_type',
                'string',
                ['limit' => 20, 'default' => '', 'null' => true, 'comment' => '存储类型(空=本地,alioss/qiniu等=对象存储)']
            ],
            [
                'storage_url',
                'string',
                ['limit' => 500, 'default' => '', 'null' => true, 'comment' => '对象存储访问地址']
            ],
            [
                'create_at',
                'datetime',
                ['default' => null, 'null' => true, 'comment' => '创建时间']
            ],
            [
                'update_at',
                'datetime',
                ['default' => null, 'null' => true, 'comment' => '更新时间']
            ],
        ], [
            'status',
            'storage_type',
            'create_at',
        ]);
    }

    /**
     * 回滚时删除表
     */
    public function down(): void
    {
        $this->table('plugin_backup_record')->drop();
    }
}
