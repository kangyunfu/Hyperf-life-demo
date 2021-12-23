<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\Model\ShopOrderLog;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Di\Annotation\Inject;
use App\Admin\Model\ShopCategory;
use App\Admin\Model\AdminLog;
use Hyperf\DbConnection\Db;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use function PHPUnit\Framework\throwException;


class CategoryController extends AdminBaseController
{
    /**
     * @Inject()
     * @var ValidatorFactoryInterface
     */
    protected $validationFactory;

    /***
     ** @api {post} admin/category 添加分类
     ** @apiName 添加分类
     ** @apiGroup 分类
     ** @apiHeader {string} token 已登录管理员的token(Header: token)  必填
     ** @apiParam {string} name 分类名称 必填
     ** @apiParam {int} level 分类级别[1一级；2二级；3三级] 必填
     ** @apiParam {int} pid 分类父级 必填
     ** @apiParam {int} is_show 是否显示 [0不显示；1显示]默认为1 非必填
     ** @apiSuccess {array}  id id
     ***/

    public function add(RequestInterface $request, ResponseInterface $response)
    {
        $rules = [
            'name' => 'required|max:20',
            'level' => 'required|in:1,2,3',
            'pid' => 'required|integer',
            'is_show' => 'in:0,1',
        ];
        $messages = [
            'name.required'    => '账号不能为空',
            'name.max'      => '账号不能超过20个字符',
            'level.required'   => '分类等级不能为空',
            'level.in'        => '分类等级参数错误',
            'pid.required'    => '请选择产品类型',
            'pid.integer'      => '产品类型参数错误',
            'is_show.in' => '是否显示参数错误',
        ];

        $validator = $this->validationFactory->make($request->all(), $rules, $messages);
        if ($validator->fails())
        {
            return jsonError($validator->errors()->first(),400);
        }
        $data['cat_name'] = $request->input('name');
        $data['level'] = intval($request->input('level'));
        $data['pid'] = intval($request->input('pid'));
        $data['is_show'] = $request->input('is_show', 1);
        $data['created_at'] = time();
        $data['updated_at'] = time();

        // 出重
        if (ShopCategory::where('cat_name', $request->input('name'))
            ->where('pid', $request->input('pid'))
            ->exists()) {
            return jsonError('不能重复添加',405);
        }

        $authUser = auth('admin')->user();
        if (false !== $id = ShopCategory::insertGetId($data)) {
            $info = $authUser['name'] .'添加分类，ID:'.$id;
            AdminLog::addData($info,1, getClientIp());
            return jsonSuccess(['id' => $id],'添加成功');
        } else {
            return jsonError('添加失败',500);
        }
    }


    /***
     ** @api {get} admin/categoryDel 删除分类
     ** @apiName 删除分类
     ** @apiGroup 分类
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiParam {int} id 分类id 必填
     ** @apiSuccess {array}  message 提示信息
     ***/
    public function delete(RequestInterface $request, ResponseInterface $response)
    {
       $id = intval($request->input('id'));
        if (!$id) {
            return jsonError('参数错误',400);
        }
        $res = ShopCategory::destroy($id);
        $authUser = auth('admin')->user();
        if ($res) {
            $info = $authUser['name'] .'删除分类，ID:'.$id;
            AdminLog::addData($info,2, getClientIp());
            return jsonSuccess(['id' => $id],'删除成功');
        }
        return jsonError('删除失败',500);
    }


    /***
     ** @api {post} admin/categoryEdit 编辑分类
     ** @apiName 编辑分类
     ** @apiGroup 分类
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiParam {int} id 分类id 必填
     ** @apiParam {string} name 分类名称 必填
     ** @apiParam {int} is_show 是否显示 [0不显示；1显示]默认为1 非必填
     ** @apiSuccess {array}  message 提示信息
     ***/
    public function edit(RequestInterface $request, ResponseInterface $response)
    {
        $rules = [
            'name' => 'required|max:20',
            'id' => 'required|alpha_num',
            'is_show' => 'in:0,1',
        ];
        $messages = [
            'name.required'    => '账号不能为空',
            'name.max'      => '账号不能超过20个字符',
            'id.required'    => 'ID参数不能为空',
            'id.alpha_num'      => 'ID参数错误',
            'is_show.in' => '是否显示参数错误',
        ];

        $validator = $this->validationFactory->make($request->all(), $rules, $messages);
        if ($validator->fails())
        {
            return jsonError($validator->errors()->first(),400);
        }
        $data['cat_name'] = $request->input('name');
        $data['is_show'] = $request->input('is_show', 1);
        $data['updated_at'] = time();
        $id = intval($request->input('id'));
        if (!$id) return jsonError('id参数错误',405);
        $res = ShopCategory::where('id', $id)->update($data);
        $authUser = auth('admin')->user();
        if ($res) {
            $info = $authUser['name'] .'编辑分类，ID:'.$id;
            AdminLog::addData($info,3, getClientIp());
            return jsonSuccess(['id' => $id],'编辑成功');
        }
        return jsonError('编辑失败',500);
    }


    /***
     ** @api {get} admin/category 获取分类信息
     ** @apiName 根据分类ID获取分类信息
     ** @apiGroup 分类
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiParam {int} id 分类id 必填
     ** @apiSuccess {array}  category
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
        $category = ShopCategory::find($id);
        return jsonSuccess($category);

    }


    /***
     ** @api {get} admin/categoryList 分类列表
     ** @apiName 分类列表
     ** @apiGroup 分类
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiParam {int} page 页码 默认1开始 非必填
     ** @apiParam {int} pageSize 页每页条目数 默认15 非必填
     ** @apiSuccess {array}  list 分类列表
     ***/
    public function categoryList(RequestInterface $request, ResponseInterface $response)
    {
        $page = $request->input('page', 1);
        $page = $page - 1;
        $pageSize = $request->input('pageSize', 15);

        $lists = ShopCategory::orderBy('id', 'asc')
//            ->offset($page * $pageSize)
//            ->limit($pageSize)
            ->get();
        $totals = ShopCategory::count();
        return jsonSuccess([
            'lists' => $lists,
            'totals' => $totals
        ]);
    }

    /***
     ** @api {get} admin/getCategory 获取子分类
     ** @apiName 根据父级ID获取所有子分类的列表
     ** @apiGroup 分类
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiParam {int} pid 分类父级id 默认为0 必填
     ** @apiSuccess {array}  category
     ***/
    public function getCategory(RequestInterface $request, ResponseInterface $response)
    {
        $pid = $request->input('pid', 0);
        $list = ShopCategory::getParentCate($pid);
        return jsonSuccess($list, '获取成功');
    }

    /***
     ** @api {get} admin/getChilds 获取子分类的id和分类名称
     ** @apiName 根据父级ID获取子分类的id和分类名称（键值对）
     ** @apiGroup 分类
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiParam {int} pid 分类父级id 默认为0 必填
     ** @apiSuccess {array}  category
     ***/
    public function getChilds(RequestInterface $request, ResponseInterface $response)
    {
        $pid = $request->input('pid', 0);
        $list = ShopCategory::getChilds($pid);
        return jsonSuccess($list, '获取成功');
    }








}
