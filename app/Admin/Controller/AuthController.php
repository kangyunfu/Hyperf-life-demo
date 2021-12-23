<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Common\RedisServer;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Di\Annotation\Inject;
use App\Admin\Model\Admin;
use App\Admin\Model\AdminLog;
use Hyperf\DbConnection\Db;
use Hyperf\Redis\Redis;
use HyperfExt\Jwt\Contracts\JwtFactoryInterface;
use HyperfExt\Jwt\Contracts\ManagerInterface;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use function PHPUnit\Framework\throwException;


class AuthController extends AdminBaseController
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
        $this->jwt = $jwtFactory->make();
    }

    /***
     ** @api {post} admin/login 登录
     ** @apiName 登录
     ** @apiGroup 管理员
     ** @apiParam {string} name 账号 必填
     ** @apiParam {string} password 密码登陆 必填
     ** @apiParam {string} code 账号 必填
     ** @apiSuccess {array}  token token
     ***/
    public function login(RequestInterface $request, ResponseInterface $response) {
        $rules = [
            'name' => 'required|max:50',
            'password' => 'required|min:6|max:20|alpha_num',
            'code' => 'required|size:4',
        ];
        $messages = [
            'name.required'    => '账号不能为空',
            'name.max'      => '账号不能超过20个字符',
            'password.required'   => '密码不能为空',
            'password.max'        => '密码不能超过20个字符',
            'password.min'        => '密码不能小于6个字符',
            'password.alpha_num'  => '密码必须是字母或数字',
            'code.required'    => '验证码不能为空',
            'code.size'      => '验证码不能超过4个字符',
        ];
        $attributes = [
            'name'  => '账号',
            'password'  => '密码',
            'code' => '验证码'
        ];

        $validator = $this->validationFactory->make($request->all(), $rules, $messages, $attributes);
        if ($validator->fails())
        {
           return jsonError($validator->errors()->first(), 400);
        }
        $name = $request->input('name');
        $password = $request->input('password');
        $code = $request->input('code');
        Db::beginTransaction();
        try{
            // 验证验证码
            if (!$this->redisServer->sIsMember('login_verification_code', $code))
            {
                throw new \Exception('验证码错误', 405);
            } else {
                $this->redisServer->sRem('login_verification_code', $code);
            }

            // 验证账号
            $admin = Admin::where('name', $name)->where('status', 1)->with('role')->first();
            if (empty($admin)) {
                throw new \Exception('用户不存在', 401);
            }
            // 验证密码
            $password = md5(md5($password).$admin->salt);
            //return $password .'======'. $admin->password;
            if ($password == $admin->password) {
                unset($admin['password']);
                unset($admin['salt']);
                $token = auth('admin')->login($admin);

                $admin->logins =  $admin->logins+1;
                $admin->last_time = time();
                $admin->last_ip = getClientIp();
                $admin->save();

                //存入redis 有效期 30 天, 60 * 60 * 24 * 30 = 2592000
                $key = 'token_admin_id_'.$admin->id;
                $this->redisServer->set($key, $token, 2592000);

               /* $data  = [
                    'token' => (string) $token,
                    'id'       => $admin->id,
                    'name'  => $admin->name,
                    'nickname' => $admin->nickname,
                    'real_name' => $admin->real_name,
                    'email' => $admin->email
                ];*/
                Db::commit();
                return jsonSuccess(['token' => $token],'登录成功');
            } else {
                throw new \Exception('密码错误', 402);
            }
        } catch(\Exception $ex){
            Db::rollBack();
            return jsonError($ex->getMessage(), $ex->getCode());
        }
    }

    /***
     ** @api {post} admin/logout 登出
     ** @apiName 登出
     ** @apiGroup 管理员
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiSuccess {array}  message 提示信息
     ***/
    public function logout(RequestInterface $request, ResponseInterface $response)
    {
        auth('admin')->logout();
        return jsonSuccess('登出成功');
    }

    /***
     ** @api {post} admin/userEdit 编辑管理员信息
     ** @apiName 编辑管理员信息
     ** @apiGroup 管理员
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiParam {int} id 被编辑的管理员ID 必填
     ** @apiParam {string} name 被编辑的管理员name  非必填
     ** @apiParam {string} password 被编辑的管理员密码 非必填
     ** @apiParam {string} real_name 被编辑的管理员真实姓名 非必填
     ** @apiParam {string} email 被编辑的管理员邮箱 非必填
     ** @apiParam {string} status 被编辑的管理员状态（1：开启；0：锁定） 非必填
     ** @apiSuccess {array}  id 被编辑的管理员ID
     ***/
    public function edit(RequestInterface $request, ResponseInterface $response)
    {
        $rules = [
            'id' => 'required|integer',
            'name' => 'max:20',
            'password' => 'min:6|max:20|alpha_num',
            'real_name' => 'max:20',
            'email' => 'email',
            'status' => 'in:0,1',
        ];
        $messages = [
            'id.required' => 'ID不能为空',
            'id.integer' => 'ID参数错误',
            'name.max'      => '账号不能超过20个字符',
            'password.max'        => '密码不能超过20个字符',
            'password.min'        => '密码不能小于6个字符',
            'password.alpha_num'  => '密码必须是字母或数字',
            'real_name.max'      => '真实姓名不能超过20个字符',
            'email.email' => '邮箱格式错误',
        ];
        $validator = $this->validationFactory->make($request->all(), $rules, $messages);
        if ($validator->fails())
        {
            return jsonError($validator->errors()->first(), 400);
        }
        $id = $request->input('id');
        $admin = Admin::find($id);
        if (empty($admin)) {
            return jsonError('用户不存在', 401);
        }
        $name = $request->input('name');
        $real_name = $request->input('real_name');
        $email = $request->input('email');
        $status = $request->input('status', 1);
        if ($name) {
            $data['name'] = $name;
        }
        if ($real_name) {
            $data['real_name'] = $real_name;
        }
        if ($email) {
            $data['email'] = $email;
        }
        $data['status'] = $status;
        $data['add_time'] = time();
        if ($request->has('password')) {
            $data['salt'] = getRandChar();
            $data['password'] =  md5(md5($request->input('password')) . $data['salt']);
        }
        $authUser = auth('admin')->user();
        if (false !== Admin::where('id', $id)->update($data)) {
            $info = $authUser['name'] .'编辑管理员，ID:'.$id;
            AdminLog::addData($info,3, getClientIp());
            return jsonSuccess(['id' => $id],'编辑成功');
        } else {
            return jsonError('编辑失败',500);
        }
    }


    /***
     ** @api {post} admin/user 添加管理员信息
     ** @apiName 添加管理员
     ** @apiGroup 管理员
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiParam {string} name 账号  必填
     ** @apiParam {string} password 密码 必填
     ** @apiParam {string} real_name 真实姓名 必填
     ** @apiParam {string} email 邮箱 必填
     ** @apiParam {string} status 管理员状态（1：开启；0：锁定） 非必填
     ** @apiSuccess {array}  id 添加成功的管理员ID
     ***/
    public function add(RequestInterface $request, ResponseInterface $response)
    {
        $rules = [
            'name' => 'required|max:20',
            'password' => 'required|min:6|max:20|alpha_num',
            'real_name' => 'required|max:20',
            'email' => 'email',
        ];
        $messages = [
            'name.required'    => '账号不能为空',
            'name.max'      => '账号不能超过20个字符',
            'password.required'   => '密码不能为空',
            'password.max'        => '密码不能超过20个字符',
            'password.min'        => '密码不能小于6个字符',
            'password.alpha_num'  => '密码必须是字母或数字',
            'real_name.required'    => '真实姓名不能为空',
            'real_name.max'      => '真实姓名不能超过20个字符',
            'email.email' => '邮箱格式错误',
        ];

        $validator = $this->validationFactory->make($request->all(), $rules, $messages);
        if ($validator->fails())
        {
            return jsonError($validator->errors()->first(),400);
        }
        $data['name'] = $request->input('name');
        $data['real_name'] = $request->input('real_name');
        $data['email'] = $request->input('email');
        $data['status'] = $request->input('status', 1);
        $data['salt'] = getRandChar();
        $data['password'] =  md5(md5($request->input('password')).$data['salt']);
        $data['add_time'] = time();

        if (Admin::where('name', $data['name'])->exists()){
            return jsonError('用户名已存在',401);
        }
        $authUser = auth('admin')->user();
        if (false !== $id = Admin::insertGetId($data)) {
            $info = $authUser['name'] .'添加管理员，ID:'.$id;
            AdminLog::addData($info,1, getClientIp());
            return jsonSuccess(['id' => $id],'添加成功');
        } else {
            return jsonError('添加失败',500);
        }
    }

    /***
     ** @api {get} admin/user 获取自己的用户信息
     ** @apiName 获取自己的用户信息
     ** @apiGroup 管理员
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiSuccess {array}  userinfo 管理员信息
     ***/
    public function myInfo(RequestInterface $request, ResponseInterface $response)
    {
        $id = auth('admin')->id();
        if (!$id) {
            return jsonError('token无效', 406);
        }
        $userinfo = Admin::where('id', $id)->select('id','role_id','member_id','name','email','real_name','nickname','tel','fax','last_time','last_ip','logins','status','is_admin','add_time','permission')->first();
        return jsonSuccess($userinfo);
    }

    /***
     ** @api {get} admin/userInfo 根据ID获取管理员信息
     ** @apiName 根据ID获取管理员信息
     ** @apiGroup 管理员
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiParam {int} id 用户ID  必填
     ** @apiSuccess {array}  userinfo 管理员信息
     ***/
    public function userInfo(RequestInterface $request, ResponseInterface $response)
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
            return jsonError('缺少参数', 405);
        }
        $userinfo = Admin::where('id', $id)->select('id','role_id','member_id','name','email','real_name','nickname','tel','fax','last_time','last_ip','logins','status','is_admin','add_time','permission')->first();

        return jsonSuccess($userinfo);
    }

    /***
     ** @api {get} admin/userList 获取管理员列表
     ** @apiName 获取管理员列表
     ** @apiGroup 管理员
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiParam {string} name 账号  非必填
     ** @apiParam {string} real_name 真实姓名  非必填
     ** @apiParam {string} sorting_field 排序字段 默认id 非必填
     ** @apiParam {string} sorting_method 排序方式 默认desc  非必填
     ** @apiParam {int} page 页码 默认1开始 非必填
     ** @apiParam {int} pageSize 页每页条目数 默认15 非必填
     ** @apiSuccess {array}  list 管理员列表
     ***/
    public function userList(RequestInterface $request, ResponseInterface $response)
    {
        $sorting_field = $request->input('sorting_field', 'id');
        $sorting_method = $request->input('sorting_method', 'desc');
        $page = intval($request->input('page', 1));
        $page = $page - 1;
        $pageSize = $request->input('pageSize', 15);
        $name = $request->input('name');
        $real_name = $request->input('real_name');
        $Admin = Admin::orderBy($sorting_field, $sorting_method);
        if (!empty($name)) {
            $Admin->where('name', 'like', "%{$name}%");
        }
        if (!empty($real_name)) {
            $Admin->where('real_name', 'like', "%{$real_name}%");
        }
        $totals = $Admin->count();
        $lists = $Admin->offset($page * $pageSize)->limit($pageSize)->get();
        return jsonSuccess([
            'lists' => $lists,
            'totals' => $totals
        ]);
    }

    /***
     ** @api {post} admin/modifyPassword 修改密码
     ** @apiName 修改密码
     ** @apiGroup 管理员
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiParam {string} old_password 原密码 必填
     ** @apiParam {string} password 新密码 必填
     ** @apiParam {string} confirm_password 确认密码 必填
     ** @apiSuccess {array}  message 提示信息
     ***/
    public function modifyPassword(RequestInterface $request, ResponseInterface $response)
    {
        $rules = [
            'password' => 'required|min:6|max:20|alpha_num',
            'confirm_password' => 'required|min:6|max:20|alpha_num',
            'old_password' => 'required|min:6|max:20|alpha_num',
        ];
        $messages = [
            'password.required'   => '密码不能为空',
            'password.max'        => '密码不能超过20个字符',
            'password.min'        => '密码不能小于6个字符',
            'password.alpha_num'  => '密码必须是字母或数字',

            'confirm_password.required'   => '确认密码不能为空',
            'confirm_password.max'        => '确认密码不能超过20个字符',
            'confirm_password.min'        => '确认密码不能小于6个字符',
            'confirm_password.alpha_num'  => '确认密码必须是字母或数字',

            'old_password.required'   => '原密码不能为空',
            'old_password.max'        => '原密码不能超过20个字符',
            'old_password.min'        => '原密码不能小于6个字符',
            'old_password.alpha_num'  => '原密码必须是字母或数字',
        ];

        $validator = $this->validationFactory->make($request->all(), $rules, $messages);
        if ($validator->fails())
        {
            return jsonError($validator->errors()->first(), 400);
        }

        $password = $request->input('password');
        $confirm_password = $request->input('confirm_password');
        $old_password = $request->input('old_password');

        // 比较新密码和确认密码
        if ($password !== $confirm_password) {
            return jsonError('两次密码不一致', 405);
        }

        // 比较原密码和新密码
        if ($password == $old_password) {
            return jsonError('新密码和原密码一致', 405);
        }

        $id = auth('admin')->id();
        if (!$id) {
            return jsonError('Token无效', 406);
        }
        $admin = Admin::find($id);

        // 验证原密码是否正确
        $old_password = md5(md5($old_password).$admin->salt);
        if ($old_password !== $admin->password) {
            return jsonError('原密码错误', 402);
        }

        // 更新密码
        $data['salt'] = getRandChar();
        $data['password'] =  md5(md5($password) . $data['salt']);
        $result = Admin::where('id', $id)->update($data);
        if (false !==$result) {
            $info = $admin['name'] . '修改密码，ID:' . $id;
            AdminLog::addData($info, 3, getClientIp());
            return jsonSuccess(['id' => $id], '修改成功');
        } else {
            return jsonError('修改失败',500);
        }
    }

    /***
     ** @api {get} admin/loginVerificationCode 获取登录验证码
     ** @apiName 获取登录验证码
     ** @apiGroup 管理员
     ** @apiSuccess {string} code 验证码
     ***/
    public function loginVerificationCode(RequestInterface $request, ResponseInterface $response)
    {
        $code = mt_rand(1000,9999);
        $key = 'login_verification_code';

        $this->redisServer->sAdd($key, $code);
        return jsonSuccess($code);
    }


}
