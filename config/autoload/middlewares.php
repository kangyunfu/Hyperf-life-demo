<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
return [
    // 下面的 http 字符串对应 config/autoload/server.php 内每个 server 的 name 属性对应的值，意味着对应的中间件配置仅应用在该 Server 中
    'http' => [
        // 数组内配置您的全局中间件，顺序根据该数组的顺序
        \Hyperf\Validation\Middleware\ValidationMiddleware::class,
        // 这里隐藏了其它中间件
        \App\Common\Middleware\CorsMiddleware::class
    ],
];
