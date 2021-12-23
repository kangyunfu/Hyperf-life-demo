<?php

declare(strict_types=1);

namespace App\Common\Middleware;

use Hyperf\Utils\Context;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CorsMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
//        $response = Context::get(ResponseInterface::class);
//        $response = $response->withHeader('Access-Control-Allow-Origin', '*')
//            ->withHeader('Access-Control-Allow-Credentials', 'true')
//            ->withHeader('Access-Control-Request-Method','GET, POST, HEAD, PUT, DELETE, OPTIONS')
////            ->withHeader('Access-Control-Allow-Methods','GET, POST, HEAD, PUT, DELETE, OPTIONS')
//            ->withHeader('Cache-control', 'no-cache, private')
//            ->withHeader('Content-type', 'application/json')
////            ->withHeader('Access-Control-Max-Age', 1728000)
////            ->withHeader('Content-Length', 0)
//            ->withHeader('Access-Control-Allow-Headers', 'Content-type,token,X-CSRF-TOKEN,Authorization,sign,lang,type,source');
//
//        Context::set(ResponseInterface::class, $response);
//
//        if ($request->getMethod() == 'OPTIONS') {
//            return $response;
//        }
//
//        return $handler->handle($request);



        $response = Context::get(ResponseInterface::class);
        $response = $response->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            // Headers 可以根据实际情况进行改写。
            ->withHeader('Access-Control-Allow-Headers', 'Content-type,token,X-CSRF-TOKEN,Authorization,sign,lang,type,source');

        Context::set(ResponseInterface::class, $response);

        if ($request->getMethod() == 'OPTIONS') {
            return $response;
        }

        return $handler->handle($request);

    }
}
