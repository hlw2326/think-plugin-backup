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
            'comment' => '插件-数据备份记录',
            'id' => 'id',
        ]);
        $table->addColumn('name', 'string', ['limit' => 255, 'default' => '', 'comment' => '备份名称'])
              ->addColumn('file', 'string', ['limit' => 500, 'default' => '', 'comment' => '备份文件名'])
              ->addColumn('size', 'biginteger', ['default' => 0, 'signed' => false, 'comment' => '备份文件大小(字节)'])
              ->addColumn('path', 'string', ['limit' => 500, 'default' => '', 'comment' => '备份文件路径'])
              ->addColumn('tables', 'integer', ['limit' => 5, 'default' => 0, 'signed' => false, 'comment' => '备份表数量'])
              ->addColumn('status', 'integer', ['limit' => 1, 'default' => 1, 'signed' => false, 'comment' => '状态(0失败,1成功)'])
              ->addColumn('storage_type', 'string', ['limit' => 20, 'default' => '', 'comment' => '存储类型(空=本地,alioss/qiniu等=对象存储)'])
              ->addColumn('storage_url', 'string', ['limit' => 500, 'default' => '', 'comment' => '对象存储访问地址'])
              ->addColumn('create_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'comment' => '创建时间'])
              ->addColumn('update_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP', 'comment' => '更新时间'])
              ->addIndex('status')
              ->addIndex('storage_type')
              ->addIndex('create_at')
              ->save();
    }

    /**
     * 回滚时删除表
     */
    public function down(): void
    {
        $this->table('plugin_backup_record')->drop();
    }
}
