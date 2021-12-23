<?php

declare(strict_types=1);

namespace App\Admin\Middleware;

use App\Traits\ApiResponse;
use Hyperf\Di\Annotation\Inject;
use HyperfExt\Jwt\Exceptions\TokenExpiredException;
use HyperfExt\Jwt\JwtFactory;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use App\Common\RedisServer;

class JwtMiddleware implements MiddlewareInterface
{
    use ApiResponse;
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * 注入 JwtFactory
     * @Inject
     * @var JwtFactory
     */
    private $jwtFactory;

    /**
     * 注入 Redis
     * @Inject
     * @var RedisServer
     */
    private $redisServer;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $jwt = $this->jwtFactory->make();

        // 判断token是否传递
        $requestToken = $request->getHeader('token');
        if (!$requestToken || !isset($requestToken[0])) {
            return $this->error(406, '缺少token参数');
        }

        // 判断token格式
        $parts = explode('.', $requestToken[0]);
        if (count($parts) !== 3) {
            return $this->error(406, 'token参数错误');
        }

        // 设置token
        $jwt->setToken($requestToken[0]);


        try {
            $jwt->checkOrFail();
        } catch (\Exception $exception) {
            if (! $exception instanceof TokenExpiredException) {
                return $this->error(406, $exception->getMessage());
            }
            //  尝试自动刷新 token
            try {
                $token = $jwt->getToken();

                // 刷新token
                $new_token = $jwt->getManager()->refresh($token);

                // 解析token载荷信息
                $payload = $jwt->getManager()->decode($token, false, true);

                // 旧token加入黑名单
                $jwt->getManager()->getBlacklist()->add($payload);

                // 一次性登录，保证此次请求畅通
                auth($payload->get('guard') ?? config('auth.default.guard'))
                    ->onceUsingId($payload->get('sub'));

                return $handler->handle($request)->withHeader('authorization', 'bearer ' . $new_token);
            } catch (\Exception $exception) {
                //    Token 验证失败
                return $this->setHttpCode(406)->error(406,$exception->getMessage());
            }
        }

        // 判断redis token
        $admin_id = auth('admin')->id();
        $token = $jwt->getToken();
        $key = 'token_admin_id_'.$admin_id;
        $redisToken = $this->redisServer->get($key);
        if ($token != $redisToken) {
            return $this->error(406, 'Token已过期');
        }

        return $handler->handle($request);
    }
}