<?php
use Hyperf\HttpServer\Router\Router;

// test
Router::addRoute(
    ['GET', 'POST', 'PUT', 'DELETE', 'HEAD'],
    'index',
    '\App\Admin\Controller\IndexController@index',
);
// 登录
Router::post('login', '\App\Admin\Controller\AuthController@login');
// 获取登录验证码
Router::get('loginVerificationCode', '\App\Admin\Controller\AuthController@loginVerificationCode');

// 需要登陆的组
Router::addGroup(
    '',
    function () {
        // 会员相关
        Router::post('logout', '\App\Admin\Controller\AuthController@logout');
        Router::post('userEdit', '\App\Admin\Controller\AuthController@edit');
        Router::post('user', '\App\Admin\Controller\AuthController@add');
        Router::get('user', '\App\Admin\Controller\AuthController@myInfo');
        Router::get('userInfo', '\App\Admin\Controller\AuthController@userInfo');
        Router::get('userList', '\App\Admin\Controller\AuthController@userList');
        Router::post('modifyPassword', '\App\Admin\Controller\AuthController@modifyPassword');

        // 分类
        Router::post('category', '\App\Admin\Controller\CategoryController@add');
        Router::post('categoryEdit', '\App\Admin\Controller\CategoryController@edit');
        Router::get('category', '\App\Admin\Controller\CategoryController@get');
        Router::get('categoryDel', '\App\Admin\Controller\CategoryController@delete');
        Router::get('categoryList', '\App\Admin\Controller\CategoryController@categoryList');
        Router::get('getCategory', '\App\Admin\Controller\CategoryController@getCategory');
        Router::get('getChilds', '\App\Admin\Controller\CategoryController@getChilds');

        // 品牌
        Router::post('brand', '\App\Admin\Controller\BrandController@add');
        Router::post('brandEdit', '\App\Admin\Controller\BrandController@edit');
        Router::get('brand', '\App\Admin\Controller\BrandController@get');
        Router::get('brandList', '\App\Admin\Controller\BrandController@brandList');

        // 图片上传
        Router::post('upload', '\App\Admin\Controller\CommonController@upload');

        // 属性
        Router::post('attribute', '\App\Admin\Controller\AttributeController@add');
        Router::post('attributeEdit', '\App\Admin\Controller\AttributeController@edit');
        Router::get('attribute', '\App\Admin\Controller\AttributeController@get');
        Router::get('attributeList', '\App\Admin\Controller\AttributeController@attributeList');
        Router::get('getBrandByCategory', '\App\Admin\Controller\AttributeController@getBrandByCategory');
        //  根据服务类型-获取品牌
        Router::get('getBrand', '\App\Admin\Controller\AttributeController@getBrand');

        // 商户管理
        Router::post('merchant', '\App\Admin\Controller\MerchantController@add');
        Router::post('merchantEdit', '\App\Admin\Controller\MerchantController@edit');
        Router::get('merchant', '\App\Admin\Controller\MerchantController@get');
        Router::get('merchantList', '\App\Admin\Controller\MerchantController@merchantList');
        Router::post('resetKey', '\App\Admin\Controller\MerchantController@resetKey');
        // 获取操作日志
        Router::get('getLog', '\App\Admin\Controller\MerchantController@getLog');

        // 提现
        // 提现列表
        Router::get('withdrawalList', '\App\Admin\Controller\MemberBillController@withdrawalList');
        // 提现
        Router::post('withdrawa', '\App\Admin\Controller\MemberBillController@withdrawa');
        // 获取用户流水列表
        Router::post('getMemberBillList', '\App\Admin\Controller\MemberBillController@memberBillList');
        // 获取商户流水列表
        Router::post('getMerchantBillList', '\App\Admin\Controller\MemberBillController@merchantBillList');
        // 提现审核备注 提现审核驳回
        Router::post('billRemark', '\App\Admin\Controller\MemberBillController@billRemark');


        // ==========
        //  订单列表
        Router::get('getOrderList', '\App\Admin\Controller\RecycleOrderController@orderList');
        //  订单详情
        Router::get('getOrderInfo', '\App\Admin\Controller\RecycleOrderController@orderInfo');
        //  获取 订单所以状态
        Router::get('getOrderStatus', '\App\Admin\Controller\RecycleOrderController@orderStatus');
        //  订单提交快递单号
        Router::post('fillExpressOfOrder', '\App\Admin\Controller\RecycleOrderController@fillExpressOfOrder');
//        Router::post('fillExpress', '\App\Admin\Controller\RecycleOrderController@fillExpress');
        Router::post('express', '\App\Admin\Controller\RecycleOrderController@express');
        //  修改收件人信息
        Router::post('expressInformation', '\App\Admin\Controller\RecycleOrderController@expressInformation');
        //  修改订单备注
        Router::post('changeRemark', '\App\Admin\Controller\RecycleOrderController@changeRemark');
        //  取消订单
        Router::get('closeOrder', '\App\Admin\Controller\RecycleOrderController@close');
        //  确认签收
        Router::get('signFor', '\App\Admin\Controller\RecycleOrderController@signFor');
        //  获取订单备注
        Router::get('getOrderRemark', '\App\Admin\Controller\RecycleOrderController@getRemark');
        // 获取当前回收订单 的报价信息
        Router::post('shopDeviceGet', '\App\Admin\Controller\RecycleOrderController@gitById');
        //  订单全部退回
        Router::post('mailOrder', '\App\Admin\Controller\RecycleOrderController@mailOrder');


    },
    [
        'middleware' => [
            \App\Admin\Middleware\JwtMiddleware::class,
        ]
    ]
);

