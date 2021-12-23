<?php
use Hyperf\HttpServer\Router\Router;

Router::get('index', '\App\Api\Controller\IndexController@index');

// 回收
Router::addGroup(
    'recycle/',
    function () {
        // 生成订单前
        Router::get('getCategory', '\App\Api\Controller\recycle\OrderController@getCategory');
        Router::get('getBrand', '\App\Api\Controller\recycle\OrderController@getBrand');
        Router::get('getMainAttribute', '\App\Api\Controller\recycle\OrderController@getMainAttribute');
        Router::get('getOthersAttribute', '\App\Api\Controller\recycle\OrderController@getOthersAttribute');
        Router::get('getGoodsInfo', '\App\Api\Controller\recycle\OrderController@getGoodsInfo');

        // 需要登陆
        Router::addGroup(
            '',
            function () {
                Router::post('createOrder', '\App\Api\Controller\recycle\OrderController@createOrder');
                Router::get('getOrderInfo', '\App\Api\Controller\recycle\OrderController@getOrderInfo');
                Router::get('cancelOrder', '\App\Api\Controller\recycle\OrderController@cancelOrder');
                Router::get('returnOrder', '\App\Api\Controller\recycle\OrderController@returnOrder');
//                Router::get('report', '\App\Api\Controller\recycle\OrderController@report');
                // 订单列表
                Router::get('getOrderLists', '\App\Api\Controller\recycle\OrderController@orderLists');
                //  确认出售
                Router::get('confirmOrder', '\App\Api\Controller\recycle\OrderController@confirmOrder');
                //  获取报告信息
                Router::get('shopDeviceGet', '\App\Api\Controller\recycle\OrderController@shopDeviceGet');

                // ===== 会员中心
                // 个人流水记录
                Router::get('getBillList', '\App\Api\Controller\recycle\MemberCenterController@getBillList');
                // 提现功能
//                Router::get('withdrawal', '\App\Api\Controller\recycle\MemberCenterController@withdrawal');
                Router::get('withdrawal', [\App\Api\Controller\recycle\MemberCenterController::class, 'withdrawal'],
                    ['middleware' => [\App\Api\Middleware\WechatAuthMiddleware::class]]);
                // 个人余额
                Router::get('balance', '\App\Api\Controller\recycle\MemberCenterController@balance');
            },
            [
                'middleware' => [
                    \App\Api\Middleware\CheckLoginMiddleware::class,
                ]
            ],
        );
    }
);

// 公共
Router::addGroup(
    'common/',
    function () {
        Router::get('area', '\App\Api\Controller\CommonController@area');

        // 需要登陆
        Router::addGroup(
            '',
            function () {
                Router::get('addressList', '\App\Api\Controller\CommonController@addressList');
            },
            [
                'middleware' => [
                    \App\Api\Middleware\CheckLoginMiddleware::class,
                ]
            ],
        );
    }
);


