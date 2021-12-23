<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\Model\Admin;
use App\Admin\Model\ShopDeviceFault;
use Hyperf\Di\Annotation\Inject;
use Hyperf\DbConnection\Db;
use App\Admin\Model\ShopDevice;
use App\Admin\Model\ShopOrderExpress;
use App\Admin\Model\ShopOrderLog;
use App\Admin\Model\ShopRecycleOrder;
use App\Admin\Model\ShopRecycleOrderSub;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use HyperfExt\Jwt\Contracts\JwtFactoryInterface;
use HyperfExt\Jwt\Contracts\ManagerInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use function PHPUnit\Framework\throwException;


class RecycleOrderController extends AdminBaseController
{
    /**
     * 提供了对 JWT 编解码、刷新和失活的能力。
     *
     * @var \HyperfExt\Jwt\Contracts\ManagerInterface
     */
    protected $manager;
    /**
     * 提供了从请求解析 JWT 及对 JWT 进行一系列相关操作的能力。
     *
     * @var \HyperfExt\Jwt\Jwt
     */
    protected $jwt;

    /**
     * @Inject()
     * @var ValidatorFactoryInterface
     */
    protected $validationFactory;


    public function __construct(ManagerInterface $manager, JwtFactoryInterface $jwtFactory, ValidatorFactoryInterface $ValidatorFactoryInterface)
    {
        $this->manager = $manager;
        $this->jwt = $jwtFactory->make();
    }


    /***
     ** @api {get} admin/getOrderList 获取回收订单列表
     ** @apiName 获取回收订单列表
     ** @apiGroup 回收订单
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiParam {int} page          页码默认1      非必填
     ** @apiParam {int} pageSize      一页几条默认20 非必填
     ** @apiParam {str} start_date    开始时间      非必填
     ** @apiParam {str} end_date      结束时间      非必填
     ** @apiParam {str} express_no    快递单号      非必填
     ** @apiParam {str} member_str    用户查询条件   非必填
     ** @apiParam {str} merchant_str  商户查询条件   非必填
     ** @apiParam {str} order_sn      订单号        非必填
     ** @apiParam {str} status        状态         非必填
     ** @apiParamExample {str} status        状态         非必填
     * [0 => '待取货',1 => '待签收',2 => '待验收',3 => '待确认',4 => '寄回中',5 => '已完成',99 => '全部订单'];
     ** @apiSuccessExample {json} SuccessExample
    {
    "msg": "success",
    "code": 200,
    "data": {
    "lists": [
    {
    "id": 5,                                    ###订单ID
    "order_sn": "RYC2021091503390448456460",    ###订单号
    "member_id": 4998274,                       ###用户ID
    "merchant_name": "admin",                   ###商户名
    "member_name": "star?",                     ###用户名
    "member_real_name": "测试啦啦啦",            ###用户真实姓名
    "mobile": "18684900706",                    ###用户手机号
    "price": "74.50",                           ###预估价
    "recycle_price": "0.00",                    ###验收价
    "order_status": 0,                          ###订单状态
    "source": "h5",                             ###订单来源
    "created_at": "2021-09-15 03:39:04",        ###订单创建时间
    "express_name": null,                       ###快递公司名
    "express_number": null                      ###快递单号
    "order_status_name": "取消订单"              ###
    "express_no": "取消订单"                     ###订单取货 单号
    "express_number": "取消订单"              ###订单退回 单号
    }
    ],
    "totals": 4
    }
    }
     ***/
    public function orderList(RequestInterface $request)
    {
        $page       = $request->input('page', 1) - 1;
        $pageSize   = $request->input('pageSize', 20);
        $start_date = $request->input('start_date', '');
        $end_date   = $request->input('end_date', '');
        $expressNo  = $request->input('express_no', '');
        $memberStr  = $request->input('member_str', '');
        $merchantStr = $request->input('merchant_str', '');
        $orderSn    = $request->input('order_sn', '');
        $status     = $request->input('status', 99);

        $orderList = ShopRecycleOrder::leftJoin('shop_order_express', 'shop_recycle_order.id', '=', 'shop_order_express.order_id')
            ->leftJoin('shop_recycle_order_sub', 'shop_recycle_order.id', '=', 'shop_recycle_order_sub.order_id')
            ->select('shop_recycle_order.merchant_name','shop_recycle_order.express_no','shop_recycle_order.return_express_no','shop_recycle_order.id','shop_recycle_order.order_sn','shop_recycle_order.member_id','shop_recycle_order.member_name',
                'shop_recycle_order.member_real_name','shop_recycle_order.mobile','shop_recycle_order.price','shop_recycle_order.recycle_price',
                'shop_recycle_order.order_status','shop_recycle_order.source','shop_recycle_order.created_at','shop_recycle_order.order_status',
                'shop_order_express.express_name','shop_order_express.express_number')
        ;


        if ($start_date && $end_date) {
            $start_date .= ' 00:00:01';
            $start_date = strtotime($start_date);
            $end_date .= ' 23:59:59';
            $end_date = strtotime($end_date);
            $orderList->whereBetween('created_at', [$start_date, $end_date]);
        } elseif ($start_date) {
            $start_date .= ' 00:00:01';
            $start_date = strtotime($start_date);
            $orderList->where('created_at', '>=', $start_date);
        } elseif ($end_date) {
            $end_date .= ' 23:59:59';
            $end_date = strtotime($end_date);
            $orderList->where('created_at', '<=', $end_date);
        }
        if ($expressNo)      $orderList->where('shop_order_express.express_number', $expressNo);
        if ($orderSn)        $orderList->where('shop_recycle_order.order_sn', $orderSn);
        if ($status != 99)   $orderList->where('shop_recycle_order.order_status', $status);

        if ($memberStr) {
            $orderList->where('shop_recycle_order.mobile', $memberStr)
                ->orWhere('shop_recycle_order.member_real_name', $memberStr)
                ->orWhere('shop_recycle_order.member_id', $memberStr);
        }

        if ($merchantStr) $orderList->where('shop_recycle_order.merchant_name', $merchantStr);

        $total        = $orderList->count();
        $orderListRes = $orderList->offset($page * $pageSize)->limit($pageSize)->orderBy('shop_recycle_order.id', 'DESC')->get();

        $statusArr = [0 => '待取货',1 => '待签收',2 => '待验收',3 => '待确认',4 => '寄回中',5 => '已完成',6 => '取消订单',7 => '部分取消',8 => '申请退回',9 => '已退回'];
        foreach ($orderListRes as &$v) {
            $v['order_status_name'] = $statusArr[$v['order_status']] ?? '';
        }
        return jsonSuccess([
            'lists' => $orderListRes,
            'totals' => $total
        ]);
    }


    /***
     ** @api {get} admin/getOrderInfo 获取订单详细信息
     ** @apiName 获取订单详细信息
     ** @apiGroup 回收订单
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiParam {int} id          订单ID      必填
     *
     ** @apiSuccessExample {json} SuccessExample
    {
    "order_info": {   ###  订单信息
    "id": 7,    // 订单ID
    "order_sn": "RYC2021091701580455914435",    // 订单号
    "member_id": 4593633,    // 用户ID
    "member_real_name": "周成",    // 用户真实姓名
    "price": "180.00",    // 订单总价
    "recycle_price": "180.00",    // 结算价
    "total_goods_num": 1       // 订单总数
    "order_status": 0,    //   订单状态  0：待取货；1：待签收；2：待验收；3：待确认；4：寄回中；5：已完成 6：取消订单； 7：部分取消 8：申请退回
    "source",             // 订单来源   h5,ios,andriod,wx,miniprogram
    "order_type",        // 订单类型    [1:清洗，2:维修，3：回收，4：家政]
    'merchant_id',       // 商户id
    'merchant_logo',      // 商户logo
    'merchant_name',      // 商户名
    "created_at": "2021-09-17 01:58:04"
    'province',  收件人 - 省份
    'province_code',  收件人 - 省份
    'city',     收件人 - 城市
    'city_code',     收件人 - 城市
    'district', 收件人 - 区县
    'district_code', 收件人 - 区县
    'street',    收件人 - 街道
    'street_code',    收件人 - 街道
    'address',  收件人 - 详细地址
    'contact',  收件人 - 上门取货联系人
    'phone'      收件人 - 上门取货联系电话
    'express_no'      订单取货 单号
    'express_number'      订单退回 单号
    },
    "express": [],      ###  快递信息
     * // express_status -  [0 => '暂无结果', 3 => '在途', 4 => '揽件', 5 => '疑难', 6 => '6签收', 7 => '退签', 8 => '派件', 9 => '退回']
    "orderLogs": [      ###  订单操作日志
    {
    "type": 3,    type  -   [1 => '清洗', 2 => '维修', 3 => '回收', 4 => '家政']
    "created_at": "2021-10-14 06:37:41",
    "info": "用户：star?,手机号：18684900706，下单成功，订单类型：回收，订单id：15",
    "member_mobile": "18684900706",
    "member_real_name": "测试啦啦啦",
    "type_name": "回收"
    }
    ],
    "order_subs": [     ###  子订单信息
    {
    "id": 7,
    "goods_id": 1,     //  商品ID
    "goods_num": 1,     //  商品数量
    "goods_price": "200.00",     //  单价
    "device_id" : 20,            //   验机报价ID  可能为0
    "status": 0,    // status  -  ['待取货', '待签收', '待验收', '待确认', '寄回中', '已完成', , '已取消', '部分取消', '申请退回',
    "attribute":"[{\"id\":1,\"attribute_menu_id\":1,\"name\":\"\苹\果13\",\"desc\":\"\最\新\款\苹\果13\",\"sort\":0},{\"id\":5,\"attribute_menu_id\":2,\"name\":\"64G\",\"desc\":\"\",\"sort\":0},{\"id\":9,\"attribute_menu_id\":3,\"name\":\"\国\行\",\"desc\":\"\国\行\购\买\",\"sort\":0},{\"id\":12,\"attribute_menu_id\":4,\"name\":\"\在\保\",\"desc\":\"\在\保\描\述\",\"sort\":0}]",
    "status_name": "待取货"
    }
    ],
    "order_time_line": {
    "asc": [
    {
    "name": "提交订单",
    "status": 0,
    "at": "2021-10-15T02:32:08.000000Z"
    },
    {
    "name": "包裹签收",
    "status": 1
    },
    {
    "name": "包裹验收",
    "status": 2
    },
    {
    "name": "订单定价",
    "status": 3
    },
    {
    "name": "包裹寄回",
    "status": 4
    },
    {
    "name": "已完成",
    "status": 5
    },
    {
    "name": "已取消",
    "status": 6,
    "at": "2021-10-15T03:23:36.000000Z"
    }
    ],
    "desc": [
    {
    "name": "已取消",
    "at": "2021-10-15T03:23:36.000000Z",
    "status": 6
    },
    {
    "name": "提交订单",
    "at": "2021-10-15T02:32:08.000000Z",
    "status": 0
    }
    ]
    },
    "shopDevice": [         ###  验机报价 的 设备信息
    {
    "id": 103,     验机详情 或者修改 用这个id
    "number": "DEV20211022082741732",
    "brand_id": 1,
    "is_device": 1,   是否验机 1否 2是  默认1
    "goods_id": 1,
    "order_id": 204,
    "member_id": 4593633,
    "merchant_id": 1,
    "grade": 0,
    "order_sub_id": 203,
    "attribute_menu_id": 0,
    "attribute_ids":"[{\"id\":1,\"attribute_menu_id\":1,\"name\":\"\苹\果13\",\"desc\":\"\最\新\款\苹\果13\",\"sort\":0},{\"id\":5,\"attribute_menu_id\":2,\"name\":\"64G\",\"desc\":\"\",\"sort\":0},{\"id\":9,\"attribute_menu_id\":3,\"name\":\"\国\行\",\"desc\":\"\国\行\购\买\",\"sort\":0},{\"id\":12,\"attribute_menu_id\":4,\"name\":\"\在\保\",\"desc\":\"\在\保\描\述\",\"sort\":0}]",
    "price": "1000.00",
    "remark": null,
    "created_at": "2021-10-22 08:27:41",
    "updated_at": null,
    "deleted_at": null,
    "type": null,
    "status_name": null,
    "attribute": [
    {
    "id": 1,
    "attribute_menu_id": 1,
    "name": "苹果13",
    "desc": "最新款苹果13",
    "sort": 0
    },
    {
    "id": 5,
    "attribute_menu_id": 2,
    "name": "64G",
    "desc": "",
    "sort": 0
    },
    {
    "id": 9,
    "attribute_menu_id": 3,
    "name": "国行",
    "desc": "国行购买",
    "sort": 0
    },
    {
    "id": 12,
    "attribute_menu_id": 4,
    "name": "在保",
    "desc": "在保描述",
    "sort": 0
    }
    ],
    "at_name": "苹果1364G国行在保"
    }
    ]
    }
     ***/
    public function orderInfo(RequestInterface $request)
    {
        $rules = [
            'id' => 'required|integer'
        ];
        $messages = [
            'id.integer'    => 'id参数错误',
        ];

        $validator = $this->validationFactory->make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return jsonError($validator->errors()->first(),400);
        }

        $id = $request->input('id', 0);

        //  订单信息
        $orderInfo = ShopRecycleOrder::where('id', $id)
            ->select('id','express_no','return_express_no','express_number','order_sn','source','order_type','member_id','member_real_name','price','recycle_price','order_status','created_at','merchant_id','merchant_logo','merchant_name','address','type','province','province_code','city','city_code','district','district_code','street','street_code','address','contact','phone')
            ->first();

        if (!isset($orderInfo['id'])) return jsonError('参数错误，找不到数据',405);
        $date['order_info'] =$orderInfo;

        $orderTypeName = [1 => '清洗订单', 2 => '维修订单', 3 => '回收订单', 4 => '家政订单'];
        if ($orderInfo['order_type']) {
            $orderInfo['order_type_name'] = $orderTypeName[$orderInfo['order_type']];
        }

        //  快递信息
        $orderExpress = ShopOrderExpress::where('order_id', $orderInfo['id'])
            ->select('express_name','express_number','express_status')
            ->get();
        //0暂无结果，3在途，4 揽件，5 疑难，6签收，7退签，8 派件，9 退回
        $expressStatus = [0 => '暂无结果', 3 => '在途', 4 => '揽件', 5 => '疑难', 6 => '6签收', 7 => '退签', 8 => '派件', 9 => '退回'];
        foreach ($orderExpress as &$express) {
            $express['status_name'] = $expressStatus[$express['express_status']];
        }
        $date['express'] = $orderExpress;

        //  操作日志
        $orderLogs = ShopOrderLog::where('order_id', $orderInfo['id'])
            ->select('type','status','name','created_at','info','member_mobile','member_real_name')
            ->get()->toArray();
        $date['order_time_line'] = $this->_orderTimeLime($orderLogs);
        //1:清洗，2:维修，3：回收，4：家政
        $orderLogStatus = [1 => '清洗', 2 => '维修', 3 => '回收', 4 => '家政'];
        foreach ($orderLogs as &$orderLog) {
            $orderLog['type_name'] = $orderLogStatus[$orderLog['type']];
        }
        $date['order_logs'] = $orderLogs;

        //  子订单信息
        $orderInfoSub = ShopRecycleOrderSub::where('order_id', $orderInfo['id'])
            ->select('id','goods_id','goods_num','goods_price','status','attribute')
            ->get();
        $typeStatus = ['门店订单', '上门取货'];
        // 0：待取货；1：待签收；2：待验收；3：待确认；4：寄回中；5：已完成 -1：已完成
        $orderStatus = ['待取货', '待签收', '待验收', '待确认', '寄回中', '已完成', '已取消', '部分取消', '申请退回'];

        //  验机记录 设备信息
        $shopDevices = ShopDevice::where('order_id', $orderInfo['id'])
            ->get();
        $date['shopDevices'] = $shopDevices;
        // 商品属性集合
        $attributes = [];
        $totalGoodsNum = 0;
        foreach ($shopDevices as &$v) {
            $totalGoodsNum += 1;
            $v['type']                   = $typeStatus[$v['type']];
            $v['status_name']            = $orderStatus[$v['status']];
            $v['attribute']  = json_decode($v['attribute_ids'], true);
            foreach ($v['attribute'] as $at) {
                $v['at_name'] .= $at['name'];
            }
        }
        $date['shopDevices'] = $shopDevices;
        $date['order_info']['total_goods_num'] = $totalGoodsNum;

        return jsonSuccess($date);
    }


    /***
     ** @api {get} admin/getOrderStatus 获取订单所以状态
     ** @apiName 获取订单所以状态
     ** @apiGroup 回收订单
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     *
     ** @apiSuccessExample {json} SuccessExample
    {
    "msg": "success",
    "code": 200,
    "data": {
    "order_status": [
    {
    "type": 0,
    "name": "待取货"
    },
    {
    "type": 1,
    "name": "待签收"
    },
    {
    "type": 2,
    "name": "待验收"
    },
    {
    "type": 3,
    "name": "待确认"
    },
    {
    "type": 4,
    "name": "寄回中"
    },
    {
    "type": 5,
    "name": "已完成"
    },
    {
    "type": -1,
    "name": "取消订单"
    }
    ]
    }
    }
     ***/
    public function orderStatus(RequestInterface $request)
    {
        $orderStatus = [
            ['type' => 0, 'name' => '待取货'],
            ['type' => 1, 'name' => '待签收'],
            ['type' => 2, 'name' => '待验收'],
            ['type' => 3, 'name' => '待确认'],
            ['type' => 4, 'name' => '寄回中'],
            ['type' => 5, 'name' => '已完成'],
            ['type' => -1, 'name' => '取消订单'],
        ];
        return jsonSuccess($orderStatus);
    }


    /***
     ** @api {post} admin/fillExpressOfOrder 订单提交快递单号
     ** @apiName 订单提交快递单号
     ** @apiGroup 回收订单
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiParam {int} id          订单ID      必填
     ** @apiParam {string} express_no  快递单号      必填
     ** @apiSuccessExample {json} SuccessExample

     ***/
    public function fillExpressOfOrder(RequestInterface $request)
    {
        $rules = [
            'id'         => 'required|integer',
            'express_no' => 'required|max:20',
        ];
        $messages = [
            'id.integer'    => 'id参数错误',
            'express_no.required' => '快递单号不能为空',
            'express_no.max' => '快递单号参数错误',
        ];
        $validator = $this->validationFactory->make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return jsonError($validator->errors()->first(),400);
        }
        $express_no = $request->input('express_no', '');
        $id         = $request->input('id', 0);

        //  订单信息
        $orderInfo = ShopRecycleOrder::where('id', $id)
            ->select('id','order_sn','source','order_type','member_id','member_real_name','price','recycle_price','order_status','created_at','merchant_id','merchant_logo','merchant_name','address','type','province','province_code','city','city_code','district','district_code','street','street_code','address','contact','phone')
            ->first();

        if (!isset($orderInfo['id']))        return jsonError('参数错误，找不到数据',405);
        if ($orderInfo['order_status'] != 0) return jsonError('待取货状态才能填取货快递单号', 405);


        $date['order_status']   = 1;
        $name                   = '收件快递揽件';
        // 订单退回单号
        $date['express_no']     = $express_no;
        $date['updated_at']     = time();


        Db::beginTransaction();
        try {
            ShopRecycleOrder::where('id', $id)->update($date);
            // 添加订单操作日志
            ShopOrderLog::insert([
                'type'             => 3,
                'name'             => $name,
                'status'           => 1,
                'order_id'         => $id,
                'member_id'        => $orderInfo['member_id'],
                'member_name'      => $orderInfo['member_name'],
                'member_real_name' => $orderInfo['member_real_name'],
                'member_mobile'    => $orderInfo['mobile'],
                'admin_id'         => 0,
                'admin_name'       => '',
                'merchant_id'      => $orderInfo['merchant_id'],
                'merchant_account' => $orderInfo['merchant_account'],
                'info'             => '商户：'.$orderInfo['merchant_name'].',账号：'. $orderInfo['merchant_account'] . '，'. $name .'，订单类型：回收，订单id：'. $orderInfo['id'],
                'created_at'       => time()
            ]);

            Db::commit();
            return jsonSuccess('操作成功');
        } catch (\Exception $ex) {
            Db::rollBack();
            return jsonError($ex->getMessage(),500);
        }
    }
    

    /***
     ** @api {post} admin/expressInformation 修改收件人信息
     ** @apiName 修改收件人信息
     ** @apiGroup 回收订单
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiParam {int}    id             订单ID      必填
     ** @apiParam {int}    province       省份      必填
     ** @apiParam {int}    province_code  省份code      必填
     ** @apiParam {int}    city           城市      必填
     ** @apiParam {int}    city_code      城市      必填
     ** @apiParam {int}    address        详细地址      必填
     ** @apiParam {int}    contact        联系人      必填
     ** @apiParam {int}    phone          联系电话      必填

     ** @apiSuccessExample {json} SuccessExample

     ***/
    public function expressInformation(RequestInterface $request)
    {
        $rules = [
            'province'      => 'required|max:50',
            'province_code' => 'required|integer',
            'city'          => 'required|max:50',
            'city_code'     => 'required|integer',
            'address'       => 'required|max:255',
            'contact'       => 'required|max:20',
            'phone'         => 'required|max:20',
            'id'            => 'required|integer'
        ];
        $messages = [
            'province.required'      => '省份参数不能为空',
            'province.max'           => '省份参数错误',
            'province_code.required' => '省份code不能为空',
            'province_code.integer'  => '省份code参数错误',
            'city.required'          => '市参数不能为空',
            'city.max'               => '市参数错误',
            'city_code.required'     => '市code参数不能为空',
            'city_code.integer'      => '市code参数错误',
            'address.required'       => '详细地址不能为空',
            'address.max'            => '详细地址参数错误',
            'contact.required'       => '联系人不能为空',
            'contact.max'            => '联系人参数错误',
            'phone.required'         => '联系电话不能为空',
            'phone.max'              => '联系电话参数错误',
            'id.required'            => 'id参数不能为空',
            'id.integer'             => 'id参数错误',
        ];
        $validator = $this->validationFactory->make($request->all(), $rules, $messages);
        if ($validator->fails())
        {
            return jsonError($validator->errors()->first(),400);
        }
        $id = $request->input('id', 0);
        //  订单信息
        $orderInfo = ShopRecycleOrder::where('id', $id)
            ->select('id','order_status','member_id','member_name','member_real_name','mobile','merchant_id','merchant_account')
            ->first();
        if (!isset($orderInfo['id'])) return jsonError('参数错误，找不到数据',405);

//        if ($orderInfo['order_status'] != 2 ) {
//            return jsonError('只有在待签收状态下才能签收订单',405);
//        }

        $date['province']       = $request->input('province');
        $date['province_code']  = $request->input('province_code');
        $date['city']           = $request->input('city');
        $date['city_code']      = $request->input('city_code');
        $date['address']        = $request->input('address');
        $date['contact']        = $request->input('contact');
        $date['phone']          = $request->input('phone');
        $date['updated_at']     = time();

        Db::beginTransaction();
        try {
            ShopRecycleOrder::where('id', $id)->update($date);

            // 添加订单操作日志
            ShopOrderLog::insert([
                'type'             => 3,
                'name'             => '修改收件人信息',
                'status'           => $orderInfo['order_status'],
                'order_id'         => $id,
                'member_id'        => $orderInfo['member_id'],
                'member_name'      => $orderInfo['member_name'],
                'member_real_name' => $orderInfo['member_real_name'],
                'member_mobile'    => $orderInfo['mobile'],
                'admin_id'         => 0,
                'admin_name'       => '',
                'merchant_id'      => $orderInfo['merchant_id'],
                'merchant_account' => $orderInfo['merchant_account'],
                'info'             => '商户：'.$orderInfo['merchant_name'].',账号：'. $orderInfo['merchant_account'] . '，修改收件人信息，订单类型：回收，订单id：'. $orderInfo['id'],
                'created_at'       => time()
            ]);
            Db::commit();
            return jsonSuccess('操作成功');
        } catch (\Exception $ex) {
            Db::rollBack();
            return jsonError($ex->getMessage(),500);
        }

    }


    /***
     ** @api {post} admin/changeRemark 修改订单备注
     ** @apiName 修改订单备注
     ** @apiGroup 回收订单
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiParam {int}    id          订单ID      必填
     ** @apiParam {string} remark      订单备注     非必填 不填就是清空备注
     *
     ** @apiSuccess {json} Success

     ***/
    public function changeRemark(RequestInterface $request)
    {
        $info = $this->_checkId($request);

        if (!$info['status']) {
            return jsonError($info['msg'],400);
        }

        $id     = $request->input('id', 0);
        $remark = $request->input('remark', '');

        $orderInfo = ShopRecycleOrder::where('id', $id)
            ->select('id','order_status','member_id','member_name','member_real_name','mobile','merchant_id','merchant_account')
            ->first();
        if (!isset($orderInfo['id'])) return jsonError('参数错误，找不到数据',405);

        $date['remarks']    = $remark;
        $date['updated_at'] = time();

        $authUser = auth('admin')->user();
        Db::beginTransaction();
        try {
            ShopRecycleOrder::where('id', $id)->update($date);
            // 添加订单操作日志
            ShopOrderLog::insert([
                'type'             => 3,
                'name'             => '修改订单备注',
                'status'           => $orderInfo['order_status'],
                'order_id'         => $id,
                'member_id'        => $orderInfo['member_id'],
                'member_name'      => $orderInfo['member_name'],
                'member_real_name' => $orderInfo['member_real_name'],
                'member_mobile'    => $orderInfo['mobile'],
                'admin_id'         => 0,
                'admin_name'       => '',
                'merchant_id'      => $orderInfo['merchant_id'],
                'merchant_account' => $orderInfo['merchant_account'],
                'info'             => '总后台：'.$authUser['name']. '，修改订单备注，订单类型：回收，订单id：'. $orderInfo['id'],
                'created_at'       => time()
            ]);

            Db::commit();
            return jsonSuccess('操作成功');
        } catch (\Exception $ex) {
            Db::rollBack();
            return jsonError($ex->getMessage(),500);
        }
    }


    /***
     ** @api {get} admin/closeOrder 取消订单
     ** @apiName 取消订单
     ** @apiGroup 回收订单
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiParam {int} id          订单ID      必填
     *
     ** @apiSuccess {json} Success

     ***/
    public function close(RequestInterface $request)
    {
        $info = $this->_checkId($request);
        if (!$info['status']) {
            return jsonError($info['msg'],400);
        }
        $id = $request->input('id', 0);

        //  订单信息
        $orderInfo = ShopRecycleOrder::where('id', $id)
            ->first();
        if (!isset($orderInfo['id'])) return jsonError('参数错误，找不到数据',405);

//        if ($orderInfo['order_status'] != 0) return jsonError('只有在待取货状态下才能取消订单',405);

        $date['order_status'] = 6;
        $date['updated_at']   = time();

        $res = ShopRecycleOrder::where('id', $id)->update($date);

        $authUser = auth('admin')->user();
        // 添加订单操作日志
        ShopOrderLog::insert([
            'type'             => 3,
            'name'             => '取消订单',
            'status'           => 6,
            'order_id'         => $orderInfo['id'],
            'member_id'        => $orderInfo['member_id'],
            'member_name'      => $orderInfo['member_name'],
            'member_real_name' => $orderInfo['member_real_name'],
            'member_mobile'    => $orderInfo['mobile'],
            'admin_id'         => 0,
            'admin_name'       => '',
            'merchant_id'      => $orderInfo['merchant_id'],
            'merchant_account' => $orderInfo['merchant_account'],
            'info'             => '总后台：' . $authUser['name'] . '，'  . '，取消订单，订单类型：回收，订单id：'. $orderInfo['id'],
            'created_at'       => time()
        ]);
        if ($res) {
            return jsonSuccess('操作成功');
        } else {
            return jsonError('操作失败');
        }
    }


    /***
     ** @api {get} admin/signFor 签收订单
     ** @apiName 签收订单
     ** @apiGroup 回收订单
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiParam {int} id          订单ID      必填
     *
     ** @apiSuccess {json} Success

     ***/
    public function signFor(RequestInterface $request)
    {
        $info = $this->_checkId($request);
        if (!$info['status']) {
            return jsonError($info['msg'],400);
        }
        $id = $request->input('id', 0);

        //  订单信息
        $orderInfo = ShopRecycleOrder::where('id', $id)
            ->first();
        if (!isset($orderInfo['id'])) return jsonError('参数错误，找不到数据',405);

        if ($orderInfo['order_status'] != 1) return jsonError('只有在待签收状态下才能签收订单',405);

        $date['order_status'] = 2;
        $date['updated_at']   = time();

        $res = ShopRecycleOrder::where('id', $id)->update($date);

        $authUser = auth('admin')->user();
        // 添加订单操作日志
        ShopOrderLog::insert([
            'type'             => 3,
            'name'             => '快递签收',
            'status'           => 6,
            'order_id'         => $orderInfo['id'],
            'member_id'        => $orderInfo['member_id'],
            'member_name'      => $orderInfo['member_name'],
            'member_real_name' => $orderInfo['member_real_name'],
            'member_mobile'    => $orderInfo['mobile'],
            'admin_id'         => 0,
            'admin_name'       => '',
            'merchant_id'      => $orderInfo['merchant_id'],
            'merchant_account' => $orderInfo['merchant_account'],
            'info'             => '总后台：' . $authUser['name'] . '，'  . '，快递签收，订单类型：回收，订单id：'. $orderInfo['id'],
            'created_at'       => time()
        ]);
        if ($res) {
            return jsonSuccess('操作成功');
        } else {
            return jsonError('操作失败');
        }
    }


    /***
     ** @api {get} admin/getOrderRemark 获取订单备注
     ** @apiName 获取订单备注
     ** @apiGroup 回收订单
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiParam {int} id          订单ID      必填
     *
     ** @apiSuccess {json} Success

     ***/
    public function getRemark(RequestInterface $request)
    {
        $info = $this->_checkId($request);
        if (!$info['status']) {
            return jsonError($info['msg'],400);
        }

        $id = $request->input('id', 0);

        //  订单信息
        $orderInfo = ShopRecycleOrder::where('id', $id)
            ->select('id','remarks')
            ->first();
        if (!isset($orderInfo['id'])) return jsonError('参数错误，找不到数据',405);

        return jsonSuccess(['remarks' => $orderInfo['remarks']]);
    }


    /***
     ** @api {post} admin/shopDeviceGet 获取验机报价信息
     ** @apiName 获取验机报价信息
     ** @apiGroup 回收订单
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiParam {int}  device_id  验机报告id 必填

     ** @apiSuccessExample {json} SuccessExample
    {
    "msg": "success",
    "code": 200,
    "data": {
    "attribute": [  ###商品的属性
    {"name": "iphone13","desc": "iphone13 desc"},
    {"name": "iphone12","desc": "iphone12 desc"}
    ],
    "problemType": [  ###问题描述类型
    {"id": 0,"name": "屏幕问题"},
    {"id": 1,"name": "主板问题"},
    {"id": 2,"name": "电池问题"}
    ],
    "grade": [     ###  等级评价 下拉
    {"id": 0,"name": "全新"},
    {"id": 1,"name": "充花"},
    {"id": 2,"name": "大花"}
    ],
    "attribute_ids": [  ### 验机报告中的商品的属性  有可能为空
    {"id": 1,"attribute_menu_id": 1,"name": "苹果13","desc": "最新款苹果13","sort": 0},
    {"id": 5,"attribute_menu_id": 2,"name": "64G","desc": "","sort": 0},
    {"id": 9,"attribute_menu_id": 3,"name": "国行","desc": "国行购买","sort": 0},
    {"id": 12,"attribute_menu_id": 4,"name": "在保","desc": "在保描述","sort": 0}
    ],
    "final_price": "90.00",     ###预估价
    "number": "121212",  ###设备吗  ---没有添加验机报价时为空''
    "price": "119.32",   ###报价  ---没有添加验机报价时为空''
    "remark": "11111",   ###验机备注  ---没有添加验机报价时为空''
    "grade_int": 0,      ###等级评价  ---没有添加验机报价时为空''
    "shopDeviceFault": [   ###问题描述详细信息  ---没有添加验机报价时为空[]
    {"type": 1,"description": "描述","charges": "55.20","img": ["图片地址"],"type_name": "主板问题"},
    {"type": 2,"description": "描述2","charges": "55.50","img": ["666 "],"type_name": "电池问题"}
    ]
    }
    }
     ***/
    public function gitById(RequestInterface $request)
    {
        $rules = [
            'device_id'  => 'required|integer|min:1'
        ];
        $messages = [
            'device_id.required'  => '验机报告id不能为空',
            'device_id.integer'   => '验机报告id参数错误',
            'device_id.min'       => '验机报告id参数错误',

        ];
        $validator = $this->validationFactory->make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return jsonError($validator->errors()->first(),400);
        }
        $shopDeviceId = $request->input('device_id') ?? 0;   //  修改已有验机报价时传入

        // 验机详情
        $shopDevice = ShopDevice::where('id', $shopDeviceId)
            ->first();

        if ($shopDevice['order_id']) {
            // 订单详情
            $order = ShopRecycleOrder::where('id', $shopDevice['order_id'])->first();
            if (!$order) {
                return jsonError('参数错误，找不到数据',405);
            }
        }

        $orderStatusMark = ['待取货','待签收','待验收','待确认','寄回中','已完成', '已取消', '部分取消', '申请退回'];
        $shopDevice['orderStatus'] = $orderStatusMark[$order['order_status']];

        $problemType = config('merchant_config.problemType');
        $shopDevice['problemType'] = $problemType;
        $shopDevice['grade_int']   = $shopDevice['grade'];

        $grade = config('merchant_config.grade');
        $shopDevice['grade'] = $grade;
        if (!is_null($shopDevice['attribute_ids'])){
            $shopDevice['attribute_ids'] = json_decode($shopDevice['attribute_ids'], true);

            foreach ($shopDevice['attribute_ids'] as $att) {
                $shopDevice['at_name'] .= $att['name'];
            }
        }

        // 问题反馈
        $shopDeviceFault = ShopDeviceFault::where('device_id', $shopDevice['id'])->select('type','description','charges','img')->get()->toArray();

        foreach ($problemType as $item) {
            $problemArr[$item['id']] = $item['name'];
        }
        foreach ($shopDeviceFault as &$v) {
            $v['type_name'] = $problemArr[$v['type']];
        }
        $shopDevice['shopDeviceFault'] = $shopDeviceFault;

        return jsonSuccess($shopDevice,'操作成功');
    }


    /***
     ** @api {post} admin/mailOrder 订单全部退回
     ** @apiName 订单全部退回
     ** @apiGroup 回收订单
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiParam {int} id          订单ID      必填
     ** @apiParam {int} mail_name        退回收货人      必填
     ** @apiParam {int} mail_phone        退回收货人电话     必填
     ** @apiParam {int} mail_address      退回收货人地址      必填
     ** @apiParam {int} express_number   快递号      必填
     *
     ** @apiSuccess {json} Success

     ***/
    public function mailOrder(RequestInterface $request)
    {
        $rules = [
            'id'             => 'required|integer',
            'express_number' => 'required',
            'mail_name'      => 'required',
            'mail_phone'     => 'required',
            'mail_address'   => 'required',
        ];
        $messages = [
            'id.integer'              => 'id参数错误',
            'mail_name.required'      => '退回收货人不能为空',
            'mail_phone.required'     => '退回收货人电话不能为空',
            'mail_address.required'   => '退回收货人地址不能为空',
            'express_number.required' => '快递单号不能为空',
        ];
        $validator = $this->validationFactory->make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return ['status' => 0, 'msg' => $validator->errors()->first()];
        }

        $id             = $request->input('id', 0);
        $mail_name      = $request->input('mail_name', '');
        $mail_phone     = $request->input('mail_phone', '');
        $mail_address   = $request->input('mail_address', '');
        $express_number = $request->input('express_number', '');

        //  订单信息
        $orderInfo = ShopRecycleOrder::where('id', $id)
            ->first();
        if (!isset($orderInfo['id'])) return jsonError('参数错误，找不到数据',405);

        if ($orderInfo['order_status'] != 8) {
            return jsonError('只有在申请退回状态下才能全部退回',405);
        }

        $date['order_status']   = 4;
        $date['updated_at']     = time();
        $date['mail_name']      = $mail_name;
        $date['mail_phone']     = $mail_phone;
        $date['mail_address']   = $mail_address;
        $date['express_number'] = $express_number;
        $date['return_express_no'] = $express_number;

        $res = ShopRecycleOrder::where('id', $id)->update($date);

        // 添加订单操作日志
        ShopOrderLog::insert([
            'type'             => 3,
            'name'             => '订单退回中',
            'status'           => 4,
            'order_id'         => $orderInfo['id'],
            'member_id'        => $orderInfo['member_id'],
            'member_name'      => $orderInfo['member_name'],
            'member_real_name' => $orderInfo['member_real_name'],
            'member_mobile'    => $orderInfo['mobile'],
            'admin_id'         => 0,
            'admin_name'       => '',
            'merchant_id'      => $orderInfo['merchant_id'],
            'merchant_account' => $orderInfo['merchant_account'],
            'info'             => '商户：'.$orderInfo['merchant_name'].',账号：'. $orderInfo['merchant_account'] . '，订单退回中，订单类型：回收，订单id：'. $orderInfo['id'],
            'created_at'       => time()
        ]);

        if ($res) {
            return jsonSuccess('操作成功');
        } else {
            return jsonError('操作失败');
        }
    }


    private function _orderTimeLime($orderLogs)
    {
        $orderStatus = [
            ['name' => '提交订单', 'status' => 0],
            ['name' => '包裹签收', 'status' => 1],
            ['name' => '包裹验收', 'status' => 2],
            ['name' => '订单定价', 'status' => 3],
            ['name' => '包裹寄回', 'status' => 4],
            ['name' => '已完成', 'status' => 5],
            ['name' => '已取消', 'status' => 6],
        ];

        foreach ($orderLogs as $orderLog) {
            if (isset($orderStatus[$orderLog['status']])) {
                $orderStatus[$orderLog['status']]['at'] = $orderLog['created_at'];
            }
        }
        return $orderStatus;
    }

    private function _checkId($request)
    {
        $rules = [
            'id' => 'required|integer'
        ];
        $messages = [
            'id.integer'    => 'id参数错误',
        ];
        $validator = $this->validationFactory->make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return ['status' => 0, 'msg' => $validator->errors()->first()];
        }
        return ['status' => 1];
    }

}