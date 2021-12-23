<?php

declare(strict_types=1);

namespace App\Merchant\Controller;

use App\Common\RedisServer;
use App\Merchant\Model\ShopDevice;
use App\Merchant\Model\ShopDeviceFault;
use App\Merchant\Model\ShopGoods;
use App\Merchant\Model\ShopOrderSnapshot;
use App\Merchant\Model\ShopRecycleOrder;
use App\Merchant\Model\ShopRecycleOrderSub;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use HyperfExt\Jwt\Contracts\JwtFactoryInterface;
use HyperfExt\Jwt\Contracts\ManagerInterface;
use Hyperf\Di\Annotation\Inject;

use App\Merchant\Model\Merchant;
use App\Merchant\Model\MerchantLog;
use Cassandra\Time;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\DbConnection\Db;
use phpDocumentor\Reflection\Types\True_;
use function PHPUnit\Framework\throwException;


class ShopDeviceController extends MerchantBaseController
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

    /**
     * 注入 Redis
     * @Inject
     * @var RedisServer
     */
    private $redisServer;

    public function __construct(ManagerInterface $manager, JwtFactoryInterface $jwtFactory, ValidatorFactoryInterface $ValidatorFactoryInterface)
    {
        $this->manager = $manager;
        $this->jwt = $jwtFactory->make();
    }


    /***
     ** @api {post} merchant/shopDeviceAdd 新增、修改提交 验机报价
     ** @apiName 添加 、修改提交 验机报价
     ** @apiGroup 回收订单
     ** @apiHeader {string} token 已登录token(Header: token)  必填

     ** @apiParam {int} device_id     验机设备信息ID【修改】 必填
     ** @apiParam {string} device_fault 问题描述 必填
     ** @apiParam {string} remark 验机师备注 必填
     ** @apiParam {int} grade 评价等级 必填
     ** @apiParam {decimal} recycle_price 报价 必填
     *
     ** @apiParam {json} device_fault 问题描述 必填
     ** @apiParamExample {json} device_fault 问题描述举例
     * [{"type":1,"description":"描述","img":"图片地址","charges":55.2},{"type":11,"description":"描述2","img":"666 ","charges":55.5}]
     *
     ** @apiParam {json} attribute_ids 商品属性 必填
     ** @apiParamExample {json} attribute_ids 商品属性举例
     * [{"id":1,"attribute_menu_id":1,"name":"\u82f9\u679c13","desc":"\u6700\u65b0\u6b3e\u82f9\u679c13","sort":0},{"id":5,"attribute_menu_id":2,"name":"64G","desc":"","sort":0},{"id":9,"attribute_menu_id":3,"name":"\u56fd\u884c","desc":"\u56fd\u884c\u8d2d\u4e70","sort":0},{"id":12,"attribute_menu_id":4,"name":"\u5728\u4fdd","desc":"\u5728\u4fdd\u63cf\u8ff0","sort":0}]

     ** @apiSuccessExample {json} SuccessExample
      {"msg": "添加成功","code": 200,"data": {"id": 11}}
     ***/
    public function add(RequestInterface $request)
    {
        $mark = $this->_makeDate($request);
        if ($mark['status']) {
            return jsonError($mark['msg'], $mark['status']);
        }
        $deviceFaultArr = $mark['msg'];

        $shopDevice['id']         = $request->input('device_id');
        $shopDevice['recycle_price']     = $request->input('recycle_price');
        $shopDevice['grade']             = $request->input('grade');
        $shopDevice['attribute_ids']     = json_encode($request->input('attribute_ids'));
        $shopDevice['remark']            = trim($request->input('remark'));

        $shopDeviceId             = $request->input('device_id') ?? 0;//验机设备ID  对应唯一设备 无新增 有修改

        $shopDeviceRes = ShopDevice::where('id',$shopDeviceId)
            ->first();

        $orderInfo = ShopRecycleOrder::where('id', $shopDeviceRes['order_id'])->first();
        if (is_null($orderInfo['id'])) {
            return jsonError('参数错误，找不到数据',405);
        }

        Db::beginTransaction();
        try {
            //  如果是修改  先去掉 上次的报价差
//            if ($shopDeviceRes['is_device'] == 2) {
//                $priceDiff = $orderInfo['recycle_price'] + ($shopDeviceRes['price'] - $shopDevice['recycle_price']);
//                ShopRecycleOrder::where('id', $orderInfo['id'])->update(['updated_at' => \time(), 'recycle_price' => $priceDiff]);
//            }

            // 修改
            $shopDevice['updated_at'] = \time();
            $shopDevice['is_device']  = 2 ;
            ShopDevice::where('id', $shopDeviceId)->update($shopDevice);
            // 问题描述 先删除 后新增
            @ShopDeviceFault::where('device_id', $shopDeviceId)->delete();
            $msg = '验机成功';

            // 修改订单总价  订单原成交价 - （报价信息中的报价 - 提交信息中的报价）
            // $orderInfo['recycle_price'] - （$shopDeviceRes['price'] - $shopDevice['recycle_price'] ）
            $priceDiff = $orderInfo['recycle_price'] - ($shopDeviceRes['price'] - $shopDevice['recycle_price']);
            ShopRecycleOrder::where('id', $orderInfo['id'])->update(['updated_at' => \time(), 'recycle_price' => $shopDevice['recycle_price']]);

            foreach ($deviceFaultArr as &$v) {
                $v['device_id'] = $shopDeviceId;
                $v['created_at'] = \time();
            }
            ShopDeviceFault::insert($deviceFaultArr);
            Db::commit();
            return jsonSuccess(['id' => $shopDeviceId], $msg);
        } catch (\Exception $ex) {
            Db::rollBack();
            return jsonError($ex->getMessage(), 500);
        }

    }


    /***
     ** @api {post} merchant/shopDeviceGet 获取验机报价信息
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


    private function _makeDate($request)
    {
        $rules = [
            'device_id'     => 'required|integer|min:1',
            'grade'         => 'required|integer',
            'recycle_price' => 'required|max:10',
            'remark'        => 'required',
//            'device_fault' => 'required',
            'attribute_ids' => 'required',
        ];
        $messages = [
            'device_id.required' => '验机报告id参数不能为空',
            'device_id.integer'  => '验机报告id参数错误',
            'device_id.min'      => '验机报告id参数错误',
            'grade.required'    => '评价等级参数不能为空',
            'grade.integer'     => '评价等级参数错误',
            'recycle_price.required' => '结算报价不能为空',
            'recycle_price.max'      => '报算价设置超出范围',
            'remark.required'   => '验机师备注不能为空',
//            'device_fault.required' => '问题描述不能为空',
            'attribute_ids.required' => '商品属性不能为空',

        ];
//        $rulesDeviceFault = [
//            '*.type'        => 'required',
//            '*.description' => 'required',
//            '*.img'         => 'required',
//            '*.charges'     => 'required',
//        ];
//        $messagesDeviceFault = [
//            '*.type.required'        => '问题描述类型不能为空',
//            '*.description.required' => '问题描述不能为空',
//            '*.img.required'         => '问题描述图片不能为空',
//            '*.charges.required'     => '问题描述图片不能为空',
//        ];

        $validator = $this->validationFactory->make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return ['status' => 400, 'msg' => $validator->errors()->first()];
        }

//        $deviceFaults = json_decode($request->input('device_fault'), true);
        if (is_array($request->input('device_fault'))) {
            $deviceFaults = $request->input('device_fault');
        } else {
            $deviceFaults = json_decode($request->input('device_fault'), true);
        }
        if (!is_array($deviceFaults)) return ['status' => 405, 'msg' => '问题描述数据类型错误'];
        
//        $validator = $this->validationFactory->make($deviceFaults, $rulesDeviceFault, $messagesDeviceFault);
//        if ($validator->fails()) {
//            return ['status' => 400, 'msg' => $validator->errors()->first()];
//        }
        $deviceFaultArr = [];
        $emptyS = 0;
        if (!empty($deviceFaults)) {
            $sumCharges = 0;
            foreach ($deviceFaults as $k => $v) {
                if (empty($v['description']) || empty($v['img']) || empty($v['charges'])) {
                    $emptyS = 1;
                    break;
                }
                $arr['type']        = $v['type'] ?? 0;
                $arr['description'] = $v['description'];
                $arr['img']         = $v['img'];
                $arr['charges']     = $v['charges'];
                $sumCharges        += $v['charges'];
                $deviceFaultArr[]   = $arr;
            }
        }
        // 验证当前数组中的任何一个参数都不能为空
        if ($emptyS == 1) {
            return ['status' => 405, 'msg' => '验机报价中数据不能为空'];
        }

        // 扣费总和 不能大于 报价
        if ($request->input('recycle_price') <= $sumCharges) {
            // TODO  这里要抛错
            return ['status' => 405, 'msg' => '扣费总和不能大于回收报价'];
        }
        return ['status' => 0, 'msg' => $deviceFaultArr];
    }


}