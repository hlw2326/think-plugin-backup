<?php

declare(strict_types=1);

namespace plugin\backup;

use think\admin\Plugin;

/**
 * 插件服务注册
 * @class Service
 * @package plugin\backup
 */
class Service extends Plugin
{
    /**
     * 定义插件名称
     * @var string
     */
    protected $appName = '数据备份';

    /**
     * 定义安装包名
     * @var string
     */
    protected $package = 'hlw2326/think-plugin-backup';

    /**
     * 注册模块菜单（菜单由 stc 迁移脚本写入 system_menu，此处用于插件中心显示）
     */
    public static function menu(): array
    {
        $code = app(static::class)->appCode;
        return [
            [
                'name' => '数据配置',
                'icon' => 'layui-icon layui-icon-set',
                'node' => "{$code}/config/index",
            ],
            [
                'name' => '备份管理',
                'icon' => 'layui-icon layui-icon-export',
                'node' => "{$code}/backup/index",
            ],
            [
                'name' => '数据管理',
                'icon' => 'layui-icon layui-icon-table',
                'node' => "{$code}/table/index",
            ],
        ];
    }
}
