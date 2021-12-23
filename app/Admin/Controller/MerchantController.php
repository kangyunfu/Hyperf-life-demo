<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\Model\MerchantLog;
use App\Admin\Model\ShopCategory;
use App\Admin\Model\Merchant;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Di\Annotation\Inject;
use App\Admin\Model\ShopBrand;
use App\Admin\Model\AdminLog;
use Hyperf\DbConnection\Db;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use function PHPUnit\Framework\throwException;


class MerchantController extends AdminBaseController
{
    /**
     * @Inject()
     * @var ValidatorFactoryInterface
     */
    protected $validationFactory;


    /***
     ** @api {post} admin/merchant 添加商户
     ** @apiName 添加商户
     ** @apiGroup 商户管理
     ** @apiParam {string} token 已登录管理员的token(Header: token)  必填
     ** @apiParam {string} name 商户名称 必填
     ** @apiParam {int} sort 排序(0-999) 非必填
     ** @apiParam {url} logo logo图片路径 必填
     ** @apiParam {string} account 商户账号 必填
     ** @apiParam {string} password 商户密码 必填
     *
     ** @apiParam {string} province       省 必填
     ** @apiParam {string} province_code  省code 必填
     ** @apiParam {string} city           城市 必填
     ** @apiParam {string} city_code      城市code 必填
     ** @apiParam {string} district       县 必填
     ** @apiParam {string} district_code  县code 必填
     ** @apiParam {string} address        详细地址 必填
     ** @apiParam {string} express_contact 快递联系人 必填
     ** @apiParam {string} express_phone   快递联系人电话 必填
     *
     ** @apiParam {string} white_ips ip白名单(多个以,隔开) 必填
     ** @apiParam {int} status 是否显示 [0禁用；1启用]默认为1 非必填
     ** @apiSuccess {array}  id id
     ***/

    public function add(RequestInterface $request, ResponseInterface $response)
    {
        $rules = [
            'name'          => 'required|max:100',
            'sort'          => 'min:0|max:999',
            'logo'          => 'required|url',
            'account'       => 'required|max:50',
            'password'      => 'required|min:6|max:20|alpha_num',
            'status'        => 'in:0,1',
            'province'      => 'max:50',
            'province_code' => 'integer',
            'city'          => 'max:50',
            'city_code'     => 'integer',
            'district'      => 'max:50',
            'district_code' => 'integer',
            'address'       => 'max:255',
            'express_contact' => 'max:50',
            'express_phone' => 'max:50',
        ];
        $messages = [
            'name.required'            => '商户名称不能为空',
            'name.max'                 => '商户名称不能超过100个字符',
            'sort.max'                 => '排序参数错误',
            'sort.min'                 => '排序参数错误',
            'logo.required'            => '请上传品牌logo',
            'logo.url'                 => '品牌logo参数错误',
            'account.required'         => '商户账号不能为空',
            'account.max'              => '商户账号不能超过100个字符',
            'password.required'        => '密码不能为空',
            'password.max'             => '密码不能超过20个字符',
            'password.min'             => '密码不能小于6个字符',
            'password.alpha_num'       => '密码必须是字母或数字',
            'status.in'                => '状态参数错误',
            'province.required'        => '省份参数不能为空',
            'province.max'             => '省份参数错误',
            'province_code.required'   => '省份code不能为空',
            'province_code.integer'    => '省份code参数错误',
            'city.required'            => '市参数不能为空',
            'city.max'                 => '市参数错误',
            'city_code.required'       => '市code参数不能为空',
            'city_code.integer'        => '市code参数错误',
            'district.required'        => '区参数不能为空',
            'district.max'             => '区参数错误',
            'district_code.required'   => '区code参数不能为空',
            'district_code.integer'    => '区code参数错误',
            'address.required'         => '详细地址不能为空',
            'address.max'              => '详细地址参数错误',
            'express_contact.required' => '邮件联系人不能为空',
            'express_contact.max'      => '邮件联系人参数错误',
            'express_phone.required'   => '邮件联系人电话不能为空',
            'express_phone.max'        => '邮件联系人电话参数错误',
        ];

        $validator = $this->validationFactory->make($request->all(), $rules, $messages);
        if ($validator->fails())
        {
            return jsonError($validator->errors()->first(),400);
        }
        Db::beginTransaction();
        try {
            $data['name'] = $request->input('name');
            $data['account'] = $request->input('account');
            $data['status'] = intval($request->input('sattus', 1));
            $data['sort'] = intval($request->input('sort', 0));
            $data['logo'] = $request->input('logo');

            $data['province'] = $request->input('province');
            $data['province_code'] = $request->input('province_code');
            $data['city'] = $request->input('city');
            $data['city_code'] = $request->input('city_code');
            $data['district'] = $request->input('district');
            $data['district_code'] = $request->input('district_code');
            $data['street'] = $request->input('street');
            $data['street_code'] = $request->input('street_code');
            $data['address'] = $request->input('address');
            $data['express_contact'] = $request->input('express_contact');
            $data['express_phone'] = $request->input('express_phone');

            $data['salt'] = getRandChar(8);
            $data['password'] = md5(md5($request->input('password')) . $data['salt']);
            $data['number'] = 'M' . mt_rand(10000, 99999) . date('is');
            $data['public_key'] = setMerchantKey('public', $data['salt']);
            $data['private_key'] = setMerchantKey('private' . $data['salt']);
            $data['white_ips'] = $request->input('white_ips');
            $data['created_at'] = time();
            $data['updated_at'] = time();

            if (Merchant::where('account', $data['account'])
                ->where('status', 1)
                ->exists()
            ) {
                throw new \Exception('账号已存在', 405);
            }
            $id = Merchant::insertGetId($data);
            $authUser = auth('admin')->user();
            $info = $authUser['name'] . '添加商户，ID:' . $id;
            AdminLog::addData($info, 1, getClientIp());

            Db::commit();
            return jsonSuccess(['id' => $id],'添加成功');
        } catch (\Exception $ex) {
            Db::rollBack();
            return jsonError($ex->getMessage(),500);
        }
    }

    /***
     ** @api {post} admin/merchantEdit 编辑商户
     ** @apiName 编辑商户
     ** @apiGroup 商户管理
     ** @apiParam {string} token 已登录管理员的token(Header: token)  必填
     ** @apiParam {int} id 商户id 必填
     ** @apiParam {string} name 商户名称 必填
     ** @apiParam {int} sort 排序(0-999) 非必填
     ** @apiParam {url} logo logo图片路径 必填
     ** @apiParam {string} account 商户账号 必填
     ** @apiParam {string} province       省 必填
     ** @apiParam {string} province_code  省code 必填
     ** @apiParam {string} city           城市 必填
     ** @apiParam {string} city_code      城市code 必填
     ** @apiParam {string} district       县 必填
     ** @apiParam {string} district_code  县code 必填
     ** @apiParam {string} address        详细地址 必填
     ** @apiParam {string} express_contact 快递联系人 必填
     ** @apiParam {string} express_phone   快递联系人电话 必填
     ** @apiParam {string} password 商户密码 非必填
     ** @apiParam {string} white_ips ip白名单(多个以,隔开) 必填
     ** @apiParam {int} status 是否显示 [0禁用；1启用]默认为1 非必填
     ** @apiSuccess {array}  id id
     ***/
    public function edit(RequestInterface $request, ResponseInterface $response)
    {
        $rules = [
            'id' => 'required|integer',
            'name' => 'required|max:100',
            'sort' => 'min:0|max:999',
            'logo' => 'required|url',
            'account' => 'required|max:50',
            'password' => 'min:6|max:20|alpha_num',
            'status' => 'in:0,1',
            'province'      => 'max:50',
            'province_code' => 'integer',
            'city'          => 'max:50',
            'city_code'     => 'integer',
            'district'      => 'max:50',
            'district_code' => 'integer',
            'address'       => 'max:255',
            'express_contact' => 'max:50',
            'express_phone' => 'max:50',
        ];
        $messages = [
            'id.required' => 'ID不能为空',
            'id.integer' => 'ID参数错误',
            'name.required'    => '商户名称不能为空',
            'name.max'      => '商户名称不能超过100个字符',
            'sort.max'    => '排序参数错误',
            'sort.min'    => '排序参数错误',
            'logo.required'      => '请上传品牌logo',
            'logo.url' => '品牌logo参数错误',
            'account.required'   => '商户账号不能为空',
            'account.max'      => '商户账号不能超过100个字符',
            'password.max'        => '密码不能超过20个字符',
            'password.min'        => '密码不能小于6个字符',
            'password.alpha_num'  => '密码必须是字母或数字',
            'status.in'        => '状态参数错误',
            'province.required'        => '省份参数不能为空',
            'province.max'             => '省份参数错误',
            'province_code.required'   => '省份code不能为空',
            'province_code.integer'    => '省份code参数错误',
            'city.required'            => '市参数不能为空',
            'city.max'                 => '市参数错误',
            'city_code.required'       => '市code参数不能为空',
            'city_code.integer'        => '市code参数错误',
            'district.required'        => '区参数不能为空',
            'district.max'             => '区参数错误',
            'district_code.required'   => '区code参数不能为空',
            'district_code.integer'    => '区code参数错误',
            'address.required'         => '详细地址不能为空',
            'address.max'              => '详细地址参数错误',
            'express_contact.required' => '邮件联系人不能为空',
            'express_contact.max'      => '邮件联系人参数错误',
            'express_phone.required'   => '邮件联系人电话不能为空',
            'express_phone.max'        => '邮件联系人电话参数错误',
        ];

        $validator = $this->validationFactory->make($request->all(), $rules, $messages);
        if ($validator->fails())
        {
            return jsonError($validator->errors()->first(),400);
        }
        $id = intval($request->input('id', 0));
        if (!$id) return jsonError('id参数错误',405);

        Db::beginTransaction();
        try {
            $data['name'] = $request->input('name');
            $data['account'] = $request->input('account');
            $data['status'] = intval($request->input('sattus', 1));
            $data['sort'] = intval($request->input('sort', 0));
            $data['logo'] = $request->input('logo');
            $data['white_ips'] = $request->input('white_ips');
            $data['updated_at'] = time();
            $data['province'] = $request->input('province');
            $data['province_code'] = $request->input('province_code');
            $data['city'] = $request->input('city');
            $data['city_code'] = $request->input('city_code');
            $data['district'] = $request->input('district');
            $data['district_code'] = $request->input('district_code');
            $data['street'] = $request->input('street');
            $data['street_code'] = $request->input('street_code');
            $data['address'] = $request->input('address');
            $data['express_contact'] = $request->input('express_contact');
            $data['express_phone'] = $request->input('express_phone');

            $password = $request->input('password', '');
            if ($password) {
                $data['salt'] = getRandChar(8);
                $data['password'] = md5(md5($password) . $data['salt']);
            }

            if (Merchant::where('account', $data['account'])
                ->where('id','<>', $id)
                ->where('status', 1)
                ->exists()
            ) {
                throw new \Exception('账号已存在', 405);
            }
            Merchant::where('id', $id)->update($data);
            $authUser = auth('admin')->user();
            $info = $authUser['name'] . '编辑商户，ID:' . $id;
            AdminLog::addData($info, 1, getClientIp());

            Db::commit();
            return jsonSuccess(['id' => $id],'编辑成功');
        } catch (\Exception $ex) {
            Db::rollBack();
            return jsonError($ex->getMessage(),500);
        }
    }


    /***
     ** @api {get} admin/merchant 商户信息
     ** @apiName 商户信息
     ** @apiGroup 商户管理
     ** @apiParam {string} token 已登录管理员的token(Header: token)  必填
     ** @apiParam {int} id 商户id 必填
     ** @apiSuccess {array}  merchantInfo
     ***/
    public function get(RequestInterface $request, ResponseInterface $response)
    {
        $rules = [
            'id' => 'required|integer'
        ];
        $messages = [
            'id.integer'    => 'id参数错误',
        ];
        $validator = $this->validationFactory->make($request->all(), $rules, $messages);
        if ($validator->fails())
        {
            return jsonError($validator->errors()->first(),400);
        }
        $id = $request->input('id', 0);
        if (!$id) {
            return jsonError('参数错误',405);
        }
        $info = Merchant::where('id', $id)
            ->select('id','number','name','money','account','logo','public_key',
                'private_key','mobile','email', 'last_login_time','last_login_ip',
                'created_at','updated_at','login_times', 'status','sort','white_ips',
                'province','province_code','city','city_code','district','district_code','street','street_code',
                'address','express_contact','express_phone'
            )->first();

        return jsonSuccess($info);

    }


    /***
     ** @api {get} admin/merchantList 商户列表
     ** @apiName 商户列表
     ** @apiGroup 商户管理
     ** @apiParam {string} token 已登录管理员的token(Header: token)  必填
     ** @apiParam {int} page 页码 默认1开始 非必填
     ** @apiParam {int} pageSize 页每页条目数 默认15 非必填
     ** @apiParam {date} start_date 开始时间  非必填
     ** @apiParam {date} end_date 结束时间  非必填
     ** @apiParam {int} status 状态 非必填
     ** @apiParam {string} name 搜索关键字 非必填
     ** @apiSuccess {array}  list 商户列表
     ***/
    public function merchantList(RequestInterface $request, ResponseInterface $response)
    {
        $page = $request->input('page', 1);
        $page = $page - 1;
        $pageSize = $request->input('pageSize', 15);
        $name = $request->input('name', '');
        $status = $request->input('status', '');
        $start_date = $request->input('start_date', '');
        $end_date = $request->input('end_date', '');

        $merchant = Merchant::orderBy('sort', 'asc')
            ->orderBy('id', 'desc')
            ->select('id','number','name','money','account','logo','public_key',
                'private_key','mobile','email', 'last_login_time','last_login_ip',
                'created_at','updated_at','login_times', 'status','sort','white_ips'
            );
        if ($name != '') {
            $merchant->where('name', 'like', "%{$name}%");
            $merchant->orWhere('number', 'like', "%{$name}%");
            $merchant->orWhere('account', 'like', "%{$name}%");
        }

        if ($status != '') {
            $merchant->where('status', $status);
        }

        if ($start_date && $end_date) {
            $start_date .= ' 00:00:01';
            $start_date = strtotime($start_date);
            $end_date .= ' 23:59:59';
            $end_date = strtotime($end_date);
            $merchant->whereBetween('created_at', [$start_date, $end_date]);
        } elseif ($start_date) {
            $start_date .= ' 00:00:01';
            $start_date = strtotime($start_date);
            $merchant->where('created_at', '>=', $start_date);
        } elseif ($end_date) {
            $end_date .= ' 23:59:59';
            $end_date = strtotime($end_date);
            $merchant->where('created_at', '<=', $end_date);
        }

        $totals = $merchant->count();
        $lists = $merchant->offset($page * $pageSize)->limit($pageSize)->get();

        return jsonSuccess([
            'lists' => $lists,
            'totals' => $totals
        ]);
    }

    /***
     ** @api {post} admin/resetKey 重置商户秘钥
     ** @apiName 重置商户秘钥
     ** @apiGroup 商户管理
     ** @apiParam {string} token 已登录管理员的token(Header: token)  必填
     ** @apiParam {int} id 商户id 必填
     ** @apiParam {int} type 类型[0:公钥；1：私钥] 默认0 非必填
     ** @apiSuccess {array}  merchantInfo
     ***/
    public function resetKey(RequestInterface $request, ResponseInterface $response)
    {
        $rules = [
            'id' => 'required|integer',
            'type' => 'in:0,1',
        ];
        $messages = [
            'id.integer'    => 'id参数错误',
            'type.in'        => '类型参数错误',
        ];
        $validator = $this->validationFactory->make($request->all(), $rules, $messages);
        if ($validator->fails())
        {
            return jsonError($validator->errors()->first(),400);
        }
        $id = $request->input('id', 0);
        $type = $request->input('type', 0);
        if (!$id) {
            return jsonError('参数错误',405);
        }
        Db::beginTransaction();
        try {
            $authUser = auth('admin')->user();
            $info = Merchant::find($id);
            $merchant = Merchant::where('id', $id);
            if ($type) { //私钥
                $desc = $authUser['name'] . '重置私钥，ID:' . $id;
                $merchant->update([
                    'private_key' => setMerchantKey('private', $info['salt']),
                    'updated_at' => time()
                ]);
            } else { // 公钥
                $desc = $authUser['name'] . '重置公钥，ID:' . $id;
                $merchant->update([
                    'public_key' => setMerchantKey('public', $info['salt']),
                    'updated_at' => time()
                ]);
            }

            AdminLog::addData($desc, 3, getClientIp());
            Db::commit();
            return jsonSuccess(['id' => $id],'操作成功');
        } catch (\Exception $ex) {
            Db::rollBack();
            return jsonError($ex->getMessage(),500);
        }

    }


    /***
     ** @api {get} admin/getLog 操作日志
     ** @apiName 操作日志
     ** @apiGroup 商户管理
     ** @apiParam {string} token 已登录管理员的token(Header: token)  必填
     ** @apiParam {int} page 页码 默认1开始 非必填
     ** @apiParam {int} pageSize 页每页条目数 默认15 非必填
     ** @apiParam {string} merchant_name 页每页条目数 默认15 非必填
     ** @apiParam {date} start_date 开始时间  非必填
     ** @apiParam {date} end_date 结束时间  非必填
     ** @apiSuccessExample {json} SuccessExample
    {
    "msg": "success",
    "code": 200,
    "data": {
    "lists": [
    {
    "id": 1,
    "merchant_id": 1,
    "merchant_name": "",
    "type": 1,         1增加 2删除 3编辑
    "info": "admin添加商品，ID:1",
    "ip_address": "192.168.0.148",
    "created_at": "",
    "type_name": "增加"
    }
    ],
    "totals": 26
    }
    }
     ***/
    public function getLog(RequestInterface $request)
    {
        $page = $request->input('page', 1);
        $page = $page - 1;
        $pageSize = $request->input('pageSize', 15);
        $merchant_name = $request->input('merchant_name', '');
        $start_date = $request->input('start_date', '');
        $end_date = $request->input('end_date', '');

        $loges = MerchantLog::select('id','merchant_id','merchant_name','type','info','ip_address','created_at')
        ;

        if ($start_date && $end_date) {
            $start_date .= ' 00:00:01';
            $start_date = strtotime($start_date);
            $end_date .= ' 23:59:59';
            $end_date = strtotime($end_date);
            $loges->whereBetween('created_at', [$start_date, $end_date]);
        } elseif ($start_date) {
            $start_date .= ' 00:00:01';
            $start_date = strtotime($start_date);
            $loges->where('created_at', '>=', $start_date);
        } elseif ($end_date) {
            $end_date .= ' 23:59:59';
            $end_date = strtotime($end_date);
            $loges->where('created_at', '<=', $end_date);
        }
        if ($merchant_name != '') {
            $loges->where('merchant_name', $merchant_name);
        }
        $totals = $loges->count();
        $lists = $loges->offset($page * $pageSize)->limit($pageSize)->get();
        $typeName = [1 => '增加',2 => '删除',3 => '编辑',];
        foreach ($lists as &$list) {
            $list['type_name'] = $typeName[$list['type']];
        }
        return jsonSuccess([
            'lists' => $lists,
            'totals' => $totals
        ]);

    }
}
