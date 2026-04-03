<?php
declare(strict_types=1);

namespace plugin\backup\model;

use think\admin\Model;

/**
 * 数据库备份记录模型
 * @property int $id
 * @property string $name 备份名称
 * @property string $file 备份文件名
 * @property int $size 备份文件大小(字节)
 * @property string $path 备份文件路径
 * @property int $tables 备份表数量
 * @property int $status 状态(0失败,1成功)
 * @property string $storage_type 存储类型(空=本地,alioss/qiniu等=对象存储)
 * @property string $storage_url 对象存储访问地址
 * @property string $create_at 创建时间
 * @property string $update_at 更新时间
 * @class PluginBackupRecord
 * @package plugin\backup\model
 */
class PluginBackupRecord extends Model
{
    /**
     * 获取备份状态标签
     */
    public static function getStatusLabels(): array
    {
        return [
            0 => ['label' => lang('备份失败'), 'class' => 'layui-bg-red'],
            1 => ['label' => lang('备份成功'), 'class' => 'layui-bg-green'],
        ];
    }

    /**
     * 格式化文件大小
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
}
