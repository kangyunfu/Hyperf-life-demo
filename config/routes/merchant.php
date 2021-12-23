<?php
use Hyperf\HttpServer\Router\Router;

Router::get('index', '\App\Merchant\Controller\IndexController@index');

// 登录
Router::addRoute(
    ['POST'],
    'login',
    '\App\Merchant\Controller\AuthController@login',
);

Router::get('xxx','\App\Merchant\Controller\XxxController@index');

// 需要登陆的组
Router::addGroup(
    '',
    function () {
        //==== 会员相关
        //登出
        Router::post('logout', '\App\Merchant\Controller\AuthController@logout');
        //修改密码
        Router::put('modifyPassword', '\App\Merchant\Controller\AuthController@modifyPassword');
        //修改logo
        Router::post('changeLogo', '\App\Merchant\Controller\AuthController@changeLogo');
        //修改logo
        Router::get('getMerchant', '\App\Merchant\Controller\AuthController@getInfoByToken');
        
        //上传图片
        Router::post('upload', '\App\Merchant\Controller\CommonController@upload');

        //=== 验机报价
        // 新增/修改
        Router::post('shopDeviceAdd', '\App\Merchant\Controller\ShopDeviceController@add');
        // 获取当前回收订单 的报价信息
        Router::post('shopDeviceGet', '\App\Merchant\Controller\ShopDeviceController@gitById');
        // 查看验机 机器详情
        Router::post('shopDeviceInfo', '\App\Merchant\Controller\ShopDeviceController@gitInfo');

        //=== 回收订单
        //  订单列表
        Router::get('getOrderList', '\App\Merchant\Controller\RecycleOrderController@orderList');
        //  订单详情
        Router::get('getOrderInfo', '\App\Merchant\Controller\RecycleOrderController@orderInfo');
        //  取消订单
        Router::get('closeOrder', '\App\Merchant\Controller\RecycleOrderController@close');
        //  完成验收
        Router::get('finishOrder', '\App\Merchant\Controller\RecycleOrderController@finishOrder');
        //  获取订单备注
        Router::get('getOrderRemark', '\App\Merchant\Controller\RecycleOrderController@getRemark');
        //  修改订单备注
        Router::post('changeRemark', '\App\Merchant\Controller\RecycleOrderController@changeRemark');
        //  获取 订单所以状态
        Router::get('getOrderStatus', '\App\Merchant\Controller\RecycleOrderController@orderStatus');
        //  确认签收
        Router::get('signFor', '\App\Merchant\Controller\RecycleOrderController@signFor');
        //  修改收件人信息
        Router::post('expressInformation', '\App\Merchant\Controller\RecycleOrderController@expressInformation');
        //  订单全部退回
        Router::post('mailOrder', '\App\Merchant\Controller\RecycleOrderController@mailOrder');


        // 商品管理
        Router::post('goods', '\App\Merchant\Controller\GoodsController@add');
        Router::put('goods', '\App\Merchant\Controller\GoodsController@edit');
        Router::post('goodsEdit', '\App\Merchant\Controller\GoodsController@edit');
        Router::get('goods', '\App\Merchant\Controller\GoodsController@get');
        Router::get('goodsList', '\App\Merchant\Controller\GoodsController@goodsList');
        Router::get('getAttribute', '\App\Merchant\Controller\GoodsController@getAttribute');
        Router::get('getServiceType', '\App\Merchant\Controller\GoodsController@getServiceType');
        Router::get('getArea', '\App\Merchant\Controller\GoodsController@getArea');
        Router::get('getCategory', '\App\Merchant\Controller\GoodsController@getCategory');
        Router::get('getBrandByCategory', '\App\Merchant\Controller\GoodsController@getBrandByCategory');
        //  服务设置 - 新增 -获取品牌
        Router::get('getBrand', '\App\Merchant\Controller\GoodsController@getBrand');

    },
    [
        'middleware' => [
            \App\Merchant\Middleware\JwtMiddleware::class,
        ]
    ]
);