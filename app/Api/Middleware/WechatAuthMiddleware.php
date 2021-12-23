<?php

declare(strict_types=1);

namespace App\Api\Middleware;

use App\Common\Wechat;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Hyperf\Utils\Context;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Hyperf\Redis\RedisFactory;
use App\Traits\ApiResponse;
use Hyperf\Utils\ApplicationContext;
use App\Api\Model\WechatMember;

class WechatAuthMiddleware implements MiddlewareInterface
{
    use ApiResponse;
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var HttpResponse
     */
    protected $response;

    public function __construct(ContainerInterface $container, HttpResponse $response, RequestInterface $request)
    {
        $this->container = $container;
        $this->response = $response;
        $this->request = $request;
    }

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Overtrue\Socialite\Exceptions\AuthorizeFailedException
     *
     * ###
    需要授权的时候直接重定向到下面这个地址:https://api2.test.xfb315.com/v6.5.1/wechatAuthorization?target_url=目标地址
    授权成功之后跳转到目标地址后面带有微信授权之后的code 例：https://shoplaw.xfb315.com/login?redirect=%2Flogin&code=091Y21Ga1sXB1C03slJa1dEZCx4Y21GW
    ####
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $member_id = auth('api')->id();

        $source = $this->request->getHeader('source')[0] ?? '';
        $sourceType = ['wechat' => 1,'miniprogram' => 2,'ios' => 3,'android' => 4,'h5' => 5,'pc' => 6,];
        $sourceS    = ["wechat", "miniprogram", "ios", "android", "h5", "pc"];

        if (!in_array($source, $sourceS)) {
            return $this->error(400, 'source参数错误');
        }

        // 判断是否有OPENID
        $wechat_member = WechatMember::where('member_id', $member_id)
            ->where('type' ,$sourceType[$source])
            ->first();

        if (isset($wechat_member['open_id'])) {
            $openid = $wechat_member['open_id'];
        } else {
            $openid = $this->request->input('wechat_openid', '');
        }

        if (!$wechat_member || !$wechat_member['open_id']) {
            Db::beginTransaction();
            try {
                //  区分小程序和公众号
                //  小程序公众号 传code 后台获取  APP自带
                if ($source == 'miniprogram' || $source == 'wechat') {
                    // 判断是否传递code参数
                    $code = $this->request->input('code', '');
                    $code = trim($code);
                    $app = Wechat::officialAccount();
                    if ($code) { // 有code,根据code获取open_id,插入数据到wechat_member表

                        $user = $app->oauth->userFromCode($code);

                        $openid = $user->getId();
                        if (!$openid) {
                            return $this->error(400, '微信授权参数错误');
                        }
                    } else {
                        return $this->error(400, '请先微信授权');
                    }
                } else {
                    if (!$openid) return $this->error(400, '请先微信授权获取必要参数');
                }
                WechatMember::insert([
                    'member_id' => $member_id,
                    'type'=> $sourceType[$source],
                    'open_id' => $openid,
                    // 'union_id' => '',
                    'created_at' => time()
                ]);
                Db::commit();
            } catch (\Exception $ex){
                Db::rollBack();
                return $this->error(400, $ex->getMessage());
            }
        }
        if (!$openid) return $this->error(400, '请先微信授权获取必要参数111');
        // openID 添加到入参 方便后面使用
        $request = $request->withAttribute('openid', $openid);
        $request = $request->withAttribute('pay_type', $sourceType[$source]);
        Context::set(ServerRequestInterface::class, $request);

        return $handler->handle($request);
    }
}
