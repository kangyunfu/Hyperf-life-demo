<?php

declare(strict_types=1);

namespace App\Api\Middleware;

use App\Api\Model\MemberXFB;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Hyperf\Redis\RedisFactory;
use App\Traits\ApiResponse;
use Hyperf\Utils\ApplicationContext;
use App\Api\Model\Member;

class CheckLoginMiddleware implements MiddlewareInterface
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

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $contextContainer = ApplicationContext::getContainer();
        $redis = $contextContainer->get(RedisFactory::class)->get('test');
        $sign = $request->getHeader('sign');
        if (!$sign || !isset($sign[0])) {
            return $this->error(406, '缺少sign参数');
        } else {
            $sign = $sign[0];
            // 验证sign是否正确
            $member_id = $redis->get('laravel:Token:' . $sign);
            if (!$member_id) {
                return $this->error(406,'sign参数错误');
            } else {
                $member = MemberXFB::find($member_id);
                if (empty($member)) {
                    return $this->error(406,'sign参数错误22');
                } else {
                    auth('api')->setUser($member);
                }
            }

        }
        return $handler->handle($request);
    }
}
