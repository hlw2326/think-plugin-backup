<?php

declare(strict_types=1);

namespace plugin\backup\controller;

use think\admin\Controller;
use think\admin\Storage;

/**
 * 数据管理配置
 * @class Config
 * @package plugin\backup\controller
 */
class Config extends Controller
{
    /**
     * 基础配置
     * @auth true
     * @menu true
     */
    public function index(): void
    {
        $this->title = '数据配置';
        // 获取存储类型（排除 local，local 表示仅本地存储不用于备份同步）
        $allTypes = Storage::types();
        unset($allTypes['local']);
        $this->storage_types = ['' => lang('本地存储（不同步）')] + $allTypes;

        if ($this->request->isPost()) {
            $post = $this->request->post();
            foreach ($post as $key => $value) {
                sysconf($key, $value);
            }
            $this->success('配置保存成功！');
        } else {
            $this->fetch();
        }
    }
}
