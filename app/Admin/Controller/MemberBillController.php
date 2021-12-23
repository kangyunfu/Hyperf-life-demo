<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\Model\Member;
use App\Admin\Model\MemberBill;
use App\Admin\Model\MemberXFB;
use App\Admin\Model\Merchant;
use App\Admin\Model\ShopBill;
use App\Admin\Model\WechatMember;
use App\Common\Wechat;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;

class MemberBillController extends AdminBaseController
{
    /**
     * @Inject()
     * @var ValidatorFactoryInterface
     */
    protected $validationFactory;

    /**
     * @Inject()
     * @var Wechat
     */
    protected $wechat;


    /***
     ** @api {get} /admin/withdrawalList   提现列表
     ** @apiName admin 提现列表
     ** @apiGroup  提现管理
     ** @apiHeader {string} sign   (Header: sign)  必填
     ** @apiParam {int} page         页码   非必填
     ** @apiParam {int} pageSize     页几条  非必填
     ** @apiSuccessExample {json} SuccessExample
     * {
    {
    "msg": "success",
    "code": 200,
    "data": [
    {
    "id": 3,
    "order_id": null,
    "order_sn": null,
    "type": 2,      //  账变类型：1：收款 ，2：提现，3：退款，4：提现失败
    "status": 1,   // 提现状态type=2时使用   1提现中  2提现完成 3提现失败-微信打款时失败 4提现失败-手动驳回
    "cash_at": 1632729823,  // 提现发起时间
    "finish_at": 0,     // 提现完成时间
    "merchant_id": null,     //
    "merchant_name": null,     //
    "member_id": 5418136,     // 用户ID
    "member_mobile": "18588499342",     // 用户电话
    "member_name": "消费保",     // 用户名
    "member_real_name": "孙红兵",     // 用户真实姓名
    "category_id": 0,     //
    "category_name": "",     //
    "money": "1.00",     // 提现金额
    "balance": "98.00",     // 钱包余额
    "created_at": "2021-09-27 08:03:43",
    "updated_at": null,
    "statusName": "提现中"     // 状态名
    "partner_trade_no": "支付单号-我们自己的"     // 支付单号-我们自己的
    "payment_no": "支付流水-支付成功支付系统的 如微信"     // 支付流水-支付成功支付系统的 如微信
    "payment_time": "支付时间"     // 支付时间
    "pay_return_msg": "支付返回信息"     // 支付返回信息
    "admin_name": "提现中"     // 管理员name
    "admin_id": "提现中"     // 管理员id
    "remark": "提现中"     // 备注
    }
    ]
    }
     ***/
    public function withdrawalList(RequestInterface $request)
    {
        $page     = $request->input('page', 1);
        $page     = $page - 1;
        $pageSize = $request->input('pageSize', 10);

        $lists = MemberBill::where('type', 2)
            ->orderBy('created_at', 'desc');
        $total = $lists->count();
        $lists = $lists->offset($page * $pageSize)
            ->limit($pageSize)
            ->get();
        // 1提现中  2提现完成 3提现失败-微信打款时失败 4提现失败-手动驳回
        $statusArr = [1 => '提现中', 2 => '提现完成', 3 => '提现失败-微信打款时失败', 4 => '提现失败-手动驳回'];
        foreach ($lists as $list) {
            $list['statusName'] = $statusArr[$list['status']];
        }
        return jsonSuccess([
            'lists' => $lists,
            'totals' => $total
        ]);
    }



    /***
     ** @api {get} /admin/withdrawa   后台提现打款
     ** @apiName admin后台提现打款
     ** @apiGroup  提现管理
     ** @apiHeader {string} sign   (Header: sign)  必填
     ** @apiParam {int} bill_id         提现记录id   必填
     ** @apiSuccessExample {json} SuccessExample
     * {
    {
    "msg": "success",
    "code": 200,
    "data": "提现打款成功"
    }
     ***/
    public function withdrawa(RequestInterface $request)
    {
        $rules = [
            'bill_id' => 'required|integer|min:1'
        ];
        $messages = [
            'bill_id.required'   => '提现记录ID不能为空',
            'bill_id.integer'    => '提现提现记录ID参数错误',
            'bill_id.min'        => '提现提现记录ID错误',
        ];
        $validator = $this->validationFactory->make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return jsonError($validator->errors()->first(),400);
        }

        $billId = $request->input('bill_id', 0);
        $memberBill = MemberBill::where('id',$billId)->first();

        // 各种校验
        if (!$memberBill['id'])            return jsonError('参数错误，提现失败',405);
        if ($memberBill['type'] != 2)      return jsonError('该状态不能提现，提现失败',405);
        if ($memberBill['status'] =! 1)    return jsonError('该记录非提现中，提现失败',405);
        if (!isset($memberBill['open_id'])) return jsonError('必要参数缺失不能打款',405);

        //  校验 openID 是否记录 是否属于当前提现记录用户
        $wechatMember = WechatMember::where('open_id', $memberBill['open_id'])
            ->where('member_id', $memberBill['member_id'])
            ->where('type', $memberBill['pay_type'])
            ->first();
        if (!isset($wechatMember['open_id']))  return jsonError('数据校验失败，提现打款失败',405);

        // 校验openID的来源端  不同端对应不同实例
        $payType = [1 => "wx", 2 => "miniprogram", 3 => "ios", 4 => "android"];
        $sourceType = ['wechat' => 1,'miniprogram' => 2,'ios' => 3,'android' => 4,'h5' => 5,'pc' => 6,];
        if (!$payType[$wechatMember['type']]) return jsonError('数据校验失败，提现打款失败',405);

        $partnerTradeNo = 'P' .  date('YmdHis') . $memberBill['member_id'];
        $app = $this->wechat->getPayFactory($payType[$wechatMember['type']]);

        $result = $app->transfer->toBalance([
            'partner_trade_no' => $partnerTradeNo, //特别注意这里，参数跟用户支付给企业out_trade_no区分开来,这里可以使用随机字符串作为订单号，跟红包和支付一个概念。
            'openid'           => $wechatMember['open_id'], //收款人的openid
            'check_name'       => 'NO_CHECK',  //文档中有三种校验实名的方法 NO_CHECK不校验 OPTION_CHECK参数校验 FORCE_CHECK强制校验
            're_user_name'     => $memberBill['member_real_name'],     //OPTION_CHECK FORCE_CHECK 校验实名的时候必须提交
            'amount'           =>'100',  //单位为分
            'desc'             => '消费宝生活服务-钱包提现',
            //'spbill_create_ip' => '39.108.XXX.VVV',  //发起交易的服务器IP地址
        ]);
        $adminUser = auth('admin');

        Db::beginTransaction();
        try {

            if($result['result_code']=='SUCCESS') {
                //这里写支付成功相关逻辑，更新数据库订单状态为已付款，给用户推送到账模板消息，短信通知用户等
                MemberBill::where('id',$billId)->update([
                    'status'           => 2,
                    'finish_at'        => time(),
                    'updated_at'       => time(),
                    'admin_name'       => $adminUser['name'],
                    'admin_id'         => $adminUser['id'],
                    'partner_trade_no' => $partnerTradeNo,
                    'payment_no'       => $result['payment_no'] ?? '',
                    'payment_time'     => $result['payment_time'] ?? '',
                    'pay_return_msg'   => $result['return_msg'] ?? ''
                ]);
                $msg = '提现打款成功';
            } else {
                //支付失败相关回调处理
                MemberBill::where('id',$billId)->update([
                    'status'           => 3,
                    'updated_at'       => time(),
                    'partner_trade_no' => $partnerTradeNo,
                    'admin_name'       => $adminUser['name'],
                    'admin_id'         => $adminUser['id'],
                    'pay_return_msg'   => $result['return_msg'] ?? ''
                ]);
                $msg = $result['return_msg'] ?? '提现打款失败';

                // TODO  提现操作失败  把用户金额加回用户钱包  需重新提现
                // TODO  修改提现记录状态  改成提现失败
                // 更新用户钱包
                MemberXFB::where('id', $memberBill['member_id'])->increment('money', $memberBill['money']);
                // 更新用户收支记录
                MemberBill::insert([
                    'order_id'         => $memberBill['order_id'],
                    'order_sn'         => $memberBill['order_sn'],
                    'type'             => 3,
                    'order_type'       => 3,
                    'pay_type'         => $memberBill['pay_type'],
                    'open_id'          => $memberBill['open_id'],
                    'merchant_id'      => $memberBill['merchant_id'],
                    'merchant_name'    => $memberBill['merchant_name'],
                    'member_id'        => $memberBill['member_id'],
                    'member_mobile'    => $memberBill['mobile'],
                    'admin_name'       => $adminUser['name'],
                    'admin_id'         => $adminUser['id'],
                    'member_name'      => $memberBill['member_name'],
                    'member_real_name' => $memberBill['member_real_name'],
                    'money'            => $memberBill['money'],
                    'balance'          => $memberBill['money'],
                    'remark'           => '支付失败，资金退回',
                    'created_at'       => time(),
                ]);
            }
            Db::commit();
            return jsonSuccess('',$msg);
        } catch (\Exception $ex) {
            Db::rollBack();
            return jsonError($ex->getMessage(),500);
        }
    }



    /***
     ** @api {post} admin/getMemberBillList 获取用户流水列表
     ** @apiName 获取用户流水列表
     ** @apiGroup 财务
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiParam {int} page          页码默认1      非必填
     ** @apiParam {int} pageSize      一页几条默认20 非必填
     ** @apiParam {str} start_date    开始时间      非必填
     ** @apiParam {str} end_date      结束时间      非必填
     ** @apiParam {str} type          账变类型：1：收款 ，2：出款，3：退款      非必填
     ** @apiParam {str} order_sn      订单号        非必填
     ** @apiParam {str} member        用户信息 用户电话  用户真实姓名  用户id        非必填
     ** @apiParam {str} merchant      商户信息 商户id  商户名       非必填
     ** @apiParam {str} type_name      1：收款 ，2：提现，3：退款，       非必填
     ** @apiParam {str} order_type_name       [1:清洗，2:维修，3：回收，4：家政]       非必填

     ** @apiSuccessExample {json} SuccessExample

     ***/
    public function memberBillList(RequestInterface $request)
    {
        $page       = $request->input('page', 1) - 1;
        $pageSize   = $request->input('pageSize', 20);
        $start_date = $request->input('start_date', '');
        $end_date   = $request->input('end_date', '');
        $type       = $request->input('type', 1);      //账变类型：1：收款 ，2：出款，3：退款
        $orderSn    = $request->input('order_sn', '');
        $member     = $request->input('member', '');   // 用户电话  用户真实姓名  用户id
        $merchant   = $request->input('merchant', ''); // 商户id  商户名

        $lists = MemberBill::where('type', $type);

        if ($start_date && $end_date) {
            $start_date .= ' 00:00:01';
            $start_date = strtotime($start_date);
            $end_date .= ' 23:59:59';
            $end_date = strtotime($end_date);
            $lists->whereBetween('created_at', [$start_date, $end_date]);
        } elseif ($start_date) {
            $start_date .= ' 00:00:01';
            $start_date = strtotime($start_date);
            $lists->where('created_at', '>=', $start_date);
        } elseif ($end_date) {
            $end_date .= ' 23:59:59';
            $end_date = strtotime($end_date);
            $lists->where('created_at', '<=', $end_date);
        }

        if ($orderSn) {
            $lists->where('order_sn', $orderSn);
        }

        if ($member) {
            $lists->where('member_mobile', $member)
                ->orWhere('member_real_name', $member)
                ->orWhere('member_id', $member);
        }
        if ($merchant) {
            $lists->where('merchant_id', $merchant)
                ->orWhere('merchant_name', $merchant);
        }
        $offset = $page * $pageSize;
        $total    = $lists->count();
        $billList = $lists->offset($offset)->limit($pageSize)->orderBy('created_at', 'DESC')->get();

        //1：收款 ，2：出款，3：退款
        //1:清洗，2:维修，3：回收，4：家政
        $types      = [1 => '收款',2 => '出款',3 => '退款'];
        $orderTypes = [1 => '清洗',2 => '维修',3 => '回收',4 => '家政'];
        foreach ($billList as &$v) {
            $v['type_name']       = $types[$v['type']];
            $v['order_type_name'] = $orderTypes[$v['order_type']];
        }

        return jsonSuccess([
            'lists' => $billList,
            'totals' => $total
        ]);
    }


    /***
     ** @api {post} admin/getMerchantBillList 获取商户流水列表
     ** @apiName 获取商户流水列表
     ** @apiGroup 财务
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiParam {int} page          页码默认1      非必填
     ** @apiParam {int} pageSize      一页几条默认20 非必填
     ** @apiParam {str} start_date    开始时间      非必填
     ** @apiParam {str} end_date      结束时间      非必填
     ** @apiParam {str} type          账变类型：1：收款 ，2：出款，3：退款      非必填
     ** @apiParam {str} order_sn      订单号        非必填
     ** @apiParam {str} member        用户信息 用户电话  用户真实姓名  用户id        非必填
     ** @apiParam {str} merchant      商户信息 商户id  商户名       非必填

     ** @apiSuccessExample {json} SuccessExample
     * {
    "msg": "success",
    "code": 200,
    "data": {
    "lists": [
    {
    "id": 3,
    "order_id": 272,      ########   订单号
    "order_sn": "RYC2021110306195963932182",       ########   订单sn
    "type": 2,        ########   订单号  1：收款 ，2：出款，3：退款
    "merchant_id": 1,
    "merchant_name": "admin",########   商户名
    "member_id": 5418153,########   用户id
    "member_mobile": "13172490481",########   用户手机
    "member_name": "十年",########   用户名
    "member_real_name": "王者来不来",########   用户真实名
    "category_id": 0,########   分类id
    "category_name": "",########   分类名
    "service_id": 0,########   服务类型id
    "service_name": "",########   服务类型名
    "money": "860.00",########   账变金额
    "balance": "-1720.00",########   余额
    "created_at": "2021-11-03 06:24:52",
    "updated_at": null
    }
    ],
    "totals": 3
    }
    }

     ***/
    public function merchantBillList(RequestInterface $request)
    {
        $page       = $request->input('page', 1) - 1;
        $pageSize   = $request->input('pageSize', 20);
        $start_date = $request->input('start_date', '');
        $end_date   = $request->input('end_date', '');
        $type       = $request->input('type', 2);      //账变类型：1：收款 ，2：出款，3：退款
        $orderSn    = $request->input('order_sn', '');
        $member     = $request->input('member', '');   // 用户电话  用户真实姓名  用户id
        $merchant   = $request->input('merchant', ''); // 商户id  商户名

        $lists = ShopBill::where('type', $type);

        if ($start_date && $end_date) {
            $start_date .= ' 00:00:01';
            $start_date = strtotime($start_date);
            $end_date .= ' 23:59:59';
            $end_date = strtotime($end_date);
            $lists->whereBetween('created_at', [$start_date, $end_date]);
        } elseif ($start_date) {
            $start_date .= ' 00:00:01';
            $start_date = strtotime($start_date);
            $lists->where('created_at', '>=', $start_date);
        } elseif ($end_date) {
            $end_date .= ' 23:59:59';
            $end_date = strtotime($end_date);
            $lists->where('created_at', '<=', $end_date);
        }

        if ($orderSn) {
            $lists->where('order_sn', $orderSn);
        }

        if ($member) {
            $lists->where('member_mobile', $member)
                ->orWhere('member_real_name', $member)
                ->orWhere('member_id', $member);
        }
        if ($merchant) {
            $lists->where('merchant_id', $merchant)
                ->orWhere('merchant_name', $merchant);
        }
        $offset = $page * $pageSize;
        $total    = $lists->count();
        $billList = $lists->offset($offset)->limit($pageSize)->orderBy('created_at', 'DESC')->get();

        //1：收款 ，2：出款，3：退款
        //1:清洗，2:维修，3：回收，4：家政
        $types      = [1 => '收款',2 => '出款',3 => '退款'];
        $orderTypes = [1 => '清洗',2 => '维修',3 => '回收',4 => '家政'];
        foreach ($billList as &$v) {
            $v['type_name']       = $types[$v['type']];
            $v['order_type_name'] = $orderTypes[$v['order_type']];
        }

        return jsonSuccess([
            'lists' => $billList,
            'totals' => $total
        ]);


    }


    /***
     ** @api {post} /admin/billRemark   后台提现审核备注/提现审核驳回
     ** @apiName admin后台提现审核备注/提现审核驳回
     ** @apiGroup  提现管理
     ** @apiHeader {string} sign   (Header: sign)  必填
     ** @apiParam {int} bill_id         提现记录id   必填
     ** @apiParam {string} remark       备注和驳回备注   必填
     ** @apiParam {int} status       状态值 只有填了4这个值才会认定为驳回   选填
     ** @apiSuccessExample {json} SuccessExample
     * {
    {
    "msg": "操作成功",
    "code": 200,
    "data": ""
    }
     ***/
    public function billRemark(RequestInterface $request)
    {
        $rules = [
            'bill_id' => 'required|integer|min:1',
            'remark'  => 'required'
        ];
        $messages = [
            'bill_id.required'   => '提现记录ID不能为空',
            'bill_id.integer'    => '提现提现记录ID参数错误',
            'bill_id.min'        => '提现提现记录ID错误',
            'remark.required'    => '备注原因不能为空',
        ];
        $validator = $this->validationFactory->make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return jsonError($validator->errors()->first(),400);
        }

        $billId = $request->input('bill_id', 0);
        $remark = $request->input('remark', '');
        // 默认不传就是纯备注 传4表示手动驳回并且备注
        $status = $request->input('status', 0);

        $authUser = auth('admin')->user();

        $date['remark']     = $remark;
        $date['admin_name'] = $authUser['name'];
        $date['admin_id']   = $authUser['id'];
        $date['updated_at'] = time();

        $memberBill = MemberBill::where('id',$billId)->first();
        if ( $status == 4) {
            $date['status'] = 4;
            if ($memberBill['status'] != 1) {
                return jsonError('只有提现中才能操作驳回',405);
            }
        }
        if (!$memberBill['id'] || !$memberBill['member_id']) return jsonError('参数错误，提现失败',405);

        Db::beginTransaction();
        try {
            MemberBill::where('id',$billId)->update($date);
            if ($status == 4) {
                // 更新用户钱包
                MemberXFB::where('id', $memberBill['member_id'])->increment('money', $memberBill['money']);
                // 更新用户收支记录
                MemberBill::insert([
                    'order_id'         => $memberBill['order_id'],
                    'order_sn'         => $memberBill['order_sn'],
                    'type'             => 3,
                    'order_type'       => 3,
                    'admin_name'       => $authUser['name'],
                    'admin_id'         => $authUser['id'],
                    'pay_type'         => $memberBill['pay_type'],
                    'open_id'          => $memberBill['open_id'],
                    'merchant_id'      => $memberBill['merchant_id'],
                    'merchant_name'    => $memberBill['merchant_name'],
                    'member_id'        => $memberBill['member_id'],
                    'member_mobile'    => $memberBill['mobile'],
                    'member_name'      => $memberBill['member_name'],
                    'member_real_name' => $memberBill['member_real_name'],
                    'money'            => $memberBill['money'],
                    'balance'          => $memberBill['money'],
                    'remark'           => '提现驳回，资金退回',
                    'created_at'       => time(),
                ]);
            }
            Db::commit();
            return jsonSuccess();
        } catch (\Exception $ex) {
            Db::rollBack();
            return jsonError($ex->getMessage(),500);
        }

    }



}