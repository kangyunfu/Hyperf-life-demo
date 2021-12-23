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
use Hyperf\HttpServer\Router\Router;

Router::addRoute(['GET', 'POST', 'PUT', 'DELETE', 'HEAD'], '/', 'App\Common\Controller\IndexController@index');

Router::get('/favicon.ico', function () {
    return '';
});

/**
 * 后台路由
 */
Router::addGroup('/admin/', function () {
    // Router::get('index', '\App\Admin\Controller\IndexController@index');
        include ("routes/admin.php");
    },
    ['middleware' => [\App\Common\Middleware\CorsMiddleware::class]]
);

/**
 * 接口路由
 */
Router::addGroup('/api/', function () {
    // Router::get('index', '\App\Api\Controller\IndexController@index');
        include ("routes/api.php");
    },
     ['middleware' => [\App\Common\Middleware\CorsMiddleware::class]]
);

/**
 * 商户后台路由
 */
Router::addGroup('/merchant/', function () {
    // Router::get('index', '\App\Merchant\Controller\IndexController@index');
        include ("routes/merchant.php");
    },
    ['middleware' => [\App\Common\Middleware\CorsMiddleware::class]]
);

