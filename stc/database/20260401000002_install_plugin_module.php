<?php

declare(strict_types=1);

use think\admin\extend\PhinxExtend;
use think\migration\Migrator;

@set_time_limit(0);
@ini_set('memory_limit', '-1');

class InstallPluginModule extends Migrator
{
    /**
     * 初始化数据模块（配置 + 菜单，挂到"系统配置"下）
     */
    public function up(): void
    {
        
        // 1. 写入默认备份路径配置（仅在不存在时写入，避免覆盖用户已修改的值）
        if (sysconf('data.backup_path') === null) {
            sysconf('data.backup_path', runtime_path() . 'database');
        }

        // // 2. 查找"系统配置"菜单的 ID（顶级菜单，pid = 0）
        // $rows = $this->query("SELECT id FROM system_menu WHERE pid=0 AND type=1 AND name='系统管理' LIMIT 1");
        // if (empty($rows)) {
        //     throw new \Exception('未找到"系统配置"顶级菜单，请确保 admin 插件已正确安装');
        // }
        // $pid = (int) $rows[0]['id'];

        // // 3. 挂到"系统配置"下
        // $this->insert('system_menu', [
        //     'pid' => $pid,
        //     'type' => 1,
        //     'name' => '备份管理',
        //     'icon' => 'layui-icon layui-icon-export',
        //     'url' => 'think-sql-data/backup/index',
        //     'node' => 'think-sql-data/backup/index',
        //     'params' => '',
        //     'sort' => 200,
        //     'status' => 1,
        // ]);

        // $this->insert('system_menu', [
        //     'pid' => $pid,
        //     'type' => 1,
        //     'name' => '数据配置',
        //     'icon' => 'layui-icon layui-icon-set',
        //     'url' => 'think-sql-data/config/index',
        //     'node' => 'think-sql-data/config/index',
        //     'params' => '',
        //     'sort' => 210,
        //     'status' => 1,
        // ]);

        // $this->insert('system_menu', [
        //     'pid' => $pid,
        //     'type' => 1,
        //     'name' => '数据表管理',
        //     'icon' => 'layui-icon layui-icon-table',
        //     'url' => 'think-sql-data/table/index',
        //     'node' => 'think-sql-data/table/index',
        //     'params' => '',
        //     'sort' => 220,
        //     'status' => 1,
        // ]);
    }

    /**
     * 回滚
     */
    public function down(): void
    {
        $this->execute("DELETE FROM `system_config` WHERE `type`='data' AND `name`='backup_path'");
    }
}
