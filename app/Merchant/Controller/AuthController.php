<?php

declare(strict_types=1);

namespace App\Merchant\Controller;

use App\Common\RedisServer;
use App\Merchant\Model\Merchant;
use App\Merchant\Model\MerchantLog;
use Cassandra\Time;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use HyperfExt\Jwt\Contracts\JwtFactoryInterface;
use HyperfExt\Jwt\Contracts\ManagerInterface;
use Hyperf\DbConnection\Db;
use function PHPUnit\Framework\throwException;

class AuthController extends MerchantBaseController
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

    public function __construct(ManagerInterface $manager, JwtFactoryInterface $jwtFactory, ValidatorFactoryInterface $ValidatorFactoryInterface) {
        $this->manager = $manager;
        $this->jwt     = $jwtFactory->make();
    }

    /***
     ** @api {post} merchant/login 登录
     ** @apiName 登录
     ** @apiGroup 商户会员
     ** @apiParam {string} account 账号 必填
     ** @apiParam {string} password 密码登陆 必填
     ** @apiParam {string} code 账号 必填
     ** @apiSuccessExample {json} SuccessExample
    {
    "msg": "登录成功",
    "code": 200,
    "data": {
    "token": "this is token"
    }
    }
     **/
    public function login(RequestInterface $request)
    {
        $rules = [
            'account'  => 'required|max:50',
            'password' => 'required|min:6|max:20|alpha_num',
            'code'     => 'required|size:4',
        ];
        $messages = [
            'account.required'   => '账号不能为空',
            'account.max'        => '账号不能超过20个字符',
            'password.required'  => '密码不能为空',
            'password.max'       => '密码不能超过20个字符',
            'password.min'       => '密码不能小于6个字符',
            'password.alpha_num' => '密码必须是字母或数字',
            'code.required'      => '验证码不能为空',
            'code.size'          => '验证码不能超过4个字符',
        ];
        $attributes = [
            'account'   => '账号',
            'password'  => '密码',
            'code'      => '验证码'
        ];

        $validator = $this->validationFactory->make($request->all(), $rules, $messages, $attributes);
        if ($validator->fails()) {
            return jsonError($validator->errors()->first(), 400);
        }

        $account  = $request->input('account');
        $password = $request->input('password');
        $code     = $request->input('code');

        try{
            // 验证验证码
            if (!$this->redisServer->sIsMember('login_verification_code', $code)) {
                throw new \Exception('验证码错误', 405);
            } else {
                $this->redisServer->sRem('login_verification_code', $code);
            }

            // 验证账号
            $merchant = Merchant::where('account', $account)->where('status', 1)->first();
            if (empty($merchant)) {
                throw new \Exception('用户不存在', 401);
            }
            // 验证密码
            $password = md5(md5($password) . $merchant->salt);
            if ($password == $merchant->password) {
                $token = auth('merchant')->login($merchant);
                Merchant::where('account', $account)->update([
                    'login_times'     => $merchant->login_times + 1 ,
                    'last_login_time' => time() ,
                    'updated_at'      => time() ,
                    'last_login_ip'   => getClientIp() ,
                ]);

                //存入redis 有效期 30 天, 60 * 60 * 24 * 30 = 2592000
                $key = 'token_merchant_id_' . $merchant->id;
                $this->redisServer->set($key, $token, 2592000);

                return jsonSuccess(['token' => $token],'登录成功');
            } else {
                throw new \Exception('密码错误', 402);
            }
        } catch(\Exception $ex){
            return jsonError($ex->getMessage(), $ex->getCode());
        }

    }


    /***
     ** @api {post} merchant/logout 登出
     ** @apiName 登出
     ** @apiGroup 商户会员
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiSuccessExample {json} SuccessExample
     * {
    "msg": "success",
    "code": 200,
    "data": "登出成功"
    }
     ***/
    public function logout(RequestInterface $request)
    {
        auth('merchant')->logout();
        return jsonSuccess('登出成功');
    }

    /***
     ** @api {put} merchant/modifyPassword 修改密码
     ** @apiName 修改密码
     ** @apiGroup 商户会员
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiParam {string} old_password 原密码 必填
     ** @apiParam {string} password 新密码 必填
     ** @apiParam {string} confirm_password 确认密码 必填
     ** @apiSuccessExample {json} SuccessExample
     * {
    "msg": "修改成功",
    "code": 200,
    "data": {
    "id": 1
    }
    }
     ***/
    public function modifyPassword(RequestInterface $request)
    {
        $rules = [
            'password'         => 'required|min:6|max:20|alpha_num',
            'confirm_password' => 'required|min:6|max:20|alpha_num',
            'old_password'     => 'required|min:6|max:20|alpha_num',
        ];
        $messages = [
            'password.required'   => '密码不能为空',
            'password.max'        => '密码不能超过20个字符',
            'password.min'        => '密码不能小于6个字符',
            'password.alpha_num'  => '密码必须是字母或数字',

            'confirm_password.required'  => '确认密码不能为空',
            'confirm_password.max'       => '确认密码不能超过20个字符',
            'confirm_password.min'       => '确认密码不能小于6个字符',
            'confirm_password.alpha_num' => '确认密码必须是字母或数字',

            'old_password.required'  => '原密码不能为空',
            'old_password.max'       => '原密码不能超过20个字符',
            'old_password.min'       => '原密码不能小于6个字符',
            'old_password.alpha_num' => '原密码必须是字母或数字',
        ];

        $validator = $this->validationFactory->make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return jsonError($validator->errors()->first(), 400);
        }

        $password         = $request->input('password');
        $confirm_password = $request->input('confirm_password');
        $old_password     = $request->input('old_password');

        // 比较新密码和确认密码
        if ($password !== $confirm_password) {
            return jsonError('两次密码不一致', 400);
        }

        // 比较原密码和新密码
        if ($password == $old_password) {
            return jsonError('新密码和原密码一致', 400);
        }

        $id = auth('merchant')->id();
        if (!$id) {
            return jsonError('Token无效', 406);
        }

        $merchant = Merchant::find($id);
        if (empty($merchant)) {
            return jsonError('商户不存在', 401);
        }

        // 验证原密码是否正确
        $old_password = md5(md5($old_password) . $merchant->salt);
        if ($old_password !== $merchant->password) {
            return jsonError('原密码错误', 402);
        }

        // 更新密码
        $data['salt']       = getRandChar();
        $data['updated_at'] = \time();
        $data['password']   =  md5(md5($password) . $data['salt']);
        $result = Merchant::where('id', $id)->update($data);
        if (false !== $result) {
            $info = $merchant['name'] . '修改密码，ID:' . $id;
            MerchantLog::addData($info, 3, getClientIp(),$merchant['name']);
            return jsonSuccess(['id' => $id], '修改成功');
        } else {
            return jsonError('修改失败',500);
        }
    }

    /***
     ** @api {post} merchant/changeLogo 修改商户信息
     ** @apiName 修改商户信息
     ** @apiGroup 商户会员
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiParam {string} logo           商户logo   必填
     ** @apiParam {string} province       省份       必填
     ** @apiParam {string} province_code  省份IDcode 必填
     ** @apiParam {string} city           城市       必填
     ** @apiParam {string} city_code      城市IDcode 必填
     ** @apiParam {string} district       区县       必填
     ** @apiParam {string} district_code  区县IDcode 必填
     ** @apiParam {string} street         街道       必填
     ** @apiParam {string} street_code    街道IDcode 必填
     ** @apiParam {string} address        详细地址    必填

     ** @apiSuccessExample {json} SuccessExample
     * {
    "msg": "修改成功",
    "code": 200,
    "data": {
    "id": 1
    }
    }
     ***/
    public function changeLogo(RequestInterface $request)
    {
        $rules = [
            'logo'          => 'required|url',
            'province'      => 'max:50',
            'province_code' => 'integer',
            'city'          => 'max:50',
            'city_code'     => 'integer',
            'district'      => 'max:50',
            'district_code' => 'integer',
            'street'        => 'max:50',
            'street_code'   => 'integer',
            'address'       => 'max:255',
        ];
        $messages = [
            'logo.required'          => '请上传商户logo',
            'logo.url'               => '商户logo参数错误',
            'province.required'      => '省份参数不能为空',
            'province.max'           => '省份参数错误',
            'province_code.required' => '省份code不能为空',
            'province_code.integer'  => '省份code参数错误',
            'city.required'          => '市参数不能为空',
            'city.max'               => '市参数错误',
            'city_code.required'     => '市code参数不能为空',
            'city_code.integer'      => '市code参数错误',
            'district.required'      => '区参数不能为空',
            'district.max'           => '区参数错误',
            'district_code.required' => '区code参数不能为空',
            'district_code.integer'  => '区code参数错误',
            'street.required'        => '街道参数不能为空',
            'street.max'             => '街道参数错误',
            'street_code.required'   => '街道code参数不能为空',
            'street_code.integer'    => '街道code参数错误',
            'address.required'       => '详细地址不能为空',
            'address.max'            => '详细地址参数错误',
        ];

        $validator = $this->validationFactory->make($request->all(), $rules, $messages);
        if ($validator->fails())
        {
            return jsonError($validator->errors()->first(),400);
        }

        $id = auth('merchant')->id();
        if (!$id) {
            return jsonError('Token无效', 406);
        }

        $merchant = Merchant::find($id);
        if (empty($merchant)) {
            return jsonError('商户不存在', 401);
        }

        $data['logo']          = $request->input('logo', '');
        $data['province']      = $request->input('province', 0);
        $data['province_code'] = $request->input('province_code', '');
        $data['city']          = $request->input('city', '');
        $data['city_code']     = $request->input('city_code', '');
        $data['district']      = $request->input('district', '');
        $data['district_code'] = $request->input('district_code', '');
        $data['street']        = $request->input('street', '');
        $data['street_code']   = $request->input('street_code', '');
        $data['address']       = $request->input('address', '');
        $data['updated_at']    = \time();

        if ($merchant['logo'] == $data['logo'] ) {
            return jsonError('重复提交', 405);
        }

        $result   = Merchant::where('id', $id)->update($data);
        if (false !== $result) {
            $info = $merchant['name'] . '修改信息，ID:' . $id;
            MerchantLog::addData($info, 3, getClientIp(), $merchant['name']);
            return jsonSuccess(['id' => $id], '修改成功');
        } else {
            return jsonError('修改失败',500);
        }
    }

    /***
     ** @api {get} merchant/getMerchant 根据token 获取用户信息
     ** @apiName 根据token 获取用户信息
     ** @apiGroup 商户会员
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiSuccessExample {json} SuccessExample
     * {
    "msg": "操作成功",
    "code": 200,
    "data": {
    "number": "M428634028",
    "name": "商户123",
    "money": "0.00",
    "account": "mtest123",
    "logo": "https://img.xfb315.com/life_service/merchant/8ec6e325014558a870cd95e4788cdda.png",
    "mobile": null,
    "email": null
    }
    }
     ***/
    public function getInfoByToken(RequestInterface $request)
    {
        $id = auth('merchant')->id();
        if (!$id) {
            return jsonError('Token无效', 406);
        }

        $merchant = Merchant::where('id', $id)->select('number','name','money','account','logo','mobile','email')
            ->first();
        if (empty($merchant)) {
            return jsonError('商户不存在', 401);
        }

        return jsonSuccess($merchant, '操作成功');
    }





}