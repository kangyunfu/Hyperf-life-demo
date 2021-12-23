<?php

declare(strict_types=1);

namespace App\Api\Controller\recycle;

use App\Api\Controller\ApiBaseController;
use App\Api\Model\Member;
use App\Api\Model\MemberBill;
use App\Api\Model\MemberXFB;
use App\Api\Model\WechatMember;
use App\Common\Wechat;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;

class MemberCenterController extends ApiBaseController
{

    /**
     * @Inject()
     * @var ValidatorFactoryInterface
     */
    protected $validationFactory;


    /***
     ** @api {post} api/recycle/getBillList   获取用户流水
     ** @apiName 获取用户流水
     ** @apiGroup 会员中心
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
            "id": 1,                                    // 流水ID
            "order_id": 10,                             // ID 号
            "order_sn": "RYC2021091803534403396192",    // ID sn
            "type": 1,                                  // 1：收款 ，2：提现，3：退款
            "status": 1,                                // 提现状态   type=2时使用   1提现中  2提现完成
            "merchant_id": 1,                           // 商户ID
            "merchant_name": "admin",                   // 商户名
            "member_id": 5418136,                       // 用户ID
            "member_mobile": "18684900706",             // 用户手机号
            "member_name": "star?",                     // 用户名称
            "member_real_name": "测试啦啦啦",            //  用户真实姓名
            "category_id": 2,                           //
            "category_name": "",                        //
            "money": "598.00",                          // 账变金额
            "balance": "598.00",                        // 余额
            "created_at": "2021-09-18 03:53:44",        // 产生时间
            "updated_at": null,                         //
            "typeName": "收款"                           // 类型
            }
        ]
        }
    }
     ***/
    public function getBillList(RequestInterface $request)
    {
        $page     = $request->input('page', 1);
        $page     = $page - 1;
        $pageSize = $request->input('pageSize', 10);

        $member_id = auth('api')->id();
        if (!$member_id) {
            return jsonError('Token无效', 406);
        }

        $billLilts = MemberBill::where('member_id', $member_id)
            ->orderBy('created_at', 'desc')
            ->offset($page * $pageSize)->limit($pageSize)
            ->get();
        // 1：收款 ，2：体现，3：退款
        $billStatus = [1 => '收款',2 => '提现',3 => '退款'];
        foreach ($billLilts as &$billLilt) {
            $billLilt['typeName'] = $billStatus[$billLilt['type']];
        }
        return jsonSuccess($billLilts);
    }



    /***
     ** @api {get} api/recycle/withdrawal   用户申请提现
     ** @apiName 提现
     ** @apiGroup 会员中心
     ** @apiHeader {string} sign   (Header: sign)  必填
     ** @apiHeader {string} source  (Header: source)  必填-["wechat", "miniprogram", "ios", "android", "h5", "pc"]
     ** @apiParam {string} code      公众号小程序专用参数，用户没有授权时必传
     ** @apiParam {string} openid    APP专用参数，用户没有授权时必传
     ** @apiSuccessExample {json} SuccessExample
     * {
    {
    "msg": "success",
    "code": 200,
    "data": [
    ]
    }
    }
     ***/
    public function withdrawal(RequestInterface $request)
    {
        $member_id = auth('api')->id();

        if (!$member_id) return jsonError('Token无效', 406);

        $source = $this->request->getHeader('source')[0] ?? '';
        $sourceType = ['wechat' => 1,'miniprogram' => 2,'ios' => 3,'android' => 4,'h5' => 5,'pc' => 6,];
        //  检验当前用户是否有提现
        //  操作前提是 用户必须全部提现
        //  查用户提现记录中 是否有类型为 提现  状态为  提现中 的记录
        if (MemberBill::where('member_id', $member_id)->where('type', 2)->where('status', 1)->exists()) {
            return jsonError('不能重复提现', 406);
        }

        $member = MemberXFB::select('money','id','name','real_name','mobile')
            ->where('id', $member_id)
            ->first();
        if ($member['money'] <=0) return jsonError('没有可提现金额', 406);

        $payType = $sourceType[$source];
        $wechatMember = WechatMember::where('member_id', $member_id)
            ->where('type', $payType)
            ->first();
        if (!$wechatMember || !$wechatMember['open_id']) return jsonError('openid无效', 406);

        // 用户提现  提交成功  就把用户的余额 减掉
        // 失败或者退回 再加回来
        // 这样每次提现 只需要管余额够不够
        Db::beginTransaction();
        try {
            MemberBill::insert([
                'type'             => 2,
                'status'           => 1,
                'cash_at'          => time(),
                'open_id'          => $wechatMember['open_id'],
                'pay_type'         => $payType,
                'member_id'        => $member['id'],
                'member_mobile'    => $member['mobile'],
                'member_name'      => $member['name'],
                'member_real_name' => $member['real_name'],
                'money'            => $member['money'],
                'balance'          => 0,
                'created_at'       => time(),
            ]);
            MemberXFB::where('id',$member['id'])->update(['money' => 0,'update_time' => time()]);
            Db::commit();
            return jsonSuccess('');
        } catch (\Exception $ex) {
            Db::rollBack();
            return jsonError($ex->getMessage(),500);
        }



    }


    /***
     ** @api {get} api/recycle/balance   获取用户余额告知是否需要传openID
     ** @apiName 获取用户余额
     ** @apiGroup 会员中心
     ** @apiHeader {string} sign   (Header: sign)  必填
     ** @apiHeader {string} source  (Header: source)  必填-["wechat", "miniprogram", "ios", "android", "h5", "pc"]
     ** @apiSuccessExample {json} SuccessExample
     *{
        "msg": "success",
        "code": 200,
        "data": {
            "money": "0.00",
            "id": 4593633,
            "name": "消费包投消费包",
            "real_name": "周成",
            "mobile": "15999686187"
            "has_openid": 0   0 / 1  0没有openID  1有openID
        }
    }
     ***/
    public function balance(RequestInterface $request)
    {
        $source = $request->getHeader('source')[0] ?? '';
        $member_id = auth('api')->id();

        $sourceType = ['wechat' => 1,'miniprogram' => 2,'ios' => 3,'android' => 4,'h5' => 5,'pc' => 6,];
        $sourceS = ["wechat", "miniprogram", "ios", "android", "h5", "pc"];

        if (!in_array($source, $sourceS)) {
            return jsonError('source参数错误', 406);
        }

        if (!$member_id) {
            return jsonError('Token无效', 406);
        }

        $wechat_member = WechatMember::where('member_id', $member_id)
            ->where('type' ,$sourceType[$source])
            ->first();

        $member = MemberXFB::select('money','id','name','real_name','mobile')
            ->where('id', $member_id)
            ->first();
        $member['has_openid'] = isset($wechat_member['open_id']) ? 1 : 0;

        return jsonSuccess($member);
    }



}