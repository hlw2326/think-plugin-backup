<?php
declare(strict_types=1);

namespace plugin\backup\controller\api\v1;

use hlw2326\mp\shared\service\UserResolverService;
use think\admin\Controller;

/**
 * API 基础控制器
 * @class Base
 * @package plugin\backup\controller\api\v1
 */
abstract class Base extends Controller
{
    protected string $appid = '';
    protected ?string $userId = null;

    public function __construct()
    {
        parent::__construct();

        $this->appid = $this->request->header('appid', $this->request->get('appid', $this->request->post('appid', '')));
        $this->userId = UserResolverService::getUserId($this->appid);
    }
}
