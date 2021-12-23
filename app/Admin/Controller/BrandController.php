<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\Model\ShopCategory;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Di\Annotation\Inject;
use App\Admin\Model\ShopBrand;
use App\Admin\Model\AdminLog;
use Hyperf\DbConnection\Db;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use function PHPUnit\Framework\throwException;


class BrandController extends AdminBaseController
{
    /**
     * @Inject()
     * @var ValidatorFactoryInterface
     */
    protected $validationFactory;


    /***
     ** @api {post} admin/brand 添加品牌
     ** @apiName 添加品牌
     ** @apiGroup 品牌
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiParam {string} name 品牌名称 必填
     ** @apiParam {string} category_ids 多选以,隔开 必填
     ** @apiParam {int} sort 排序 非必填
     ** @apiParam {url} logo logo图片路径 必填
     ** @apiParam {int} status 是否显示 [0禁用；1启用]默认为1 非必填
     ** @apiSuccess {array}  id id
     ***/

    public function add(RequestInterface $request, ResponseInterface $response)
    {
        $rules = [
            'name' => 'required|max:20',
            'category_ids' => 'required',
            'status' => 'in:0,1',
            'sort' => 'min:0|max:999',
            'logo' => 'required|url',
        ];
        $messages = [
            'name.required'    => '品牌名称不能为空',
            'name.max'      => '品牌名称不能超过20个字符',
            'category_ids.required'   => '请至少选择一个分类',
            'status.in'        => '状态参数错误',
            'sort.max'    => '排序参数错误',
            'sort.min'    => '排序参数错误',
            'logo.required'      => '请上传品牌logo',
            'logo.url' => '品牌logo参数错误',
        ];

        $validator = $this->validationFactory->make($request->all(), $rules, $messages);
        if ($validator->fails())
        {
            return jsonError($validator->errors()->first(),400);
        }
        $category_id  = $request->input('category_ids', '');
        $category_id  = rtrim($category_id, ',');

        //  判断传入的数据是否是顶级目录  暂时只有几个 并且不会经常改变 所以不做负责判断
        $category_pid = [1, 2, 3, 4];
        if (in_array($category_id, $category_pid)) {
            //  当前判定顶级目录  查询所有子目录
            $child_category_ids = ShopCategory::where('pid', $category_id)
                ->whereNull('deleted_at')
                ->pluck('id')
                ->toArray();
            if (is_array($child_category_ids)) {
                $category_id = implode(',', $child_category_ids);
            }
        }

        $data['name']         = $request->input('name');
        $data['status']       = intval($request->input('pid', 1));
        $data['category_ids'] = $category_id;
        $data['sort']         = $request->input('sort', 0);
        $data['logo']         = $request->input('logo');
        $data['created_at'] = time();
        $data['updated_at'] = time();

        if (ShopBrand::where('name', $data['name'])
            ->where('status', 1)
            ->exists()
        ) {
            return jsonError('品牌已存在',405);
        }

        $authUser = auth('admin')->user();

        if (false !== $id = ShopBrand::insertGetId($data)) {
            $info = $authUser['name'] .'添加品牌，ID:'.$id;
            AdminLog::addData($info,1, getClientIp());
            return jsonSuccess(['id' => $id],'添加成功');
        } else {
            return jsonError('添加失败',500);
        }
    }

    /***
     ** @api {post} admin/brandEdit 编辑品牌
     ** @apiName 编辑品牌
     ** @apiGroup 品牌
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiParam {int} id 品牌id 必填
     ** @apiParam {string} name 品牌名称 必填
     ** @apiParam {string} category_ids 多选以,隔开 必填
     ** @apiParam {int} sort 排序 非必填
     ** @apiParam {url} logo logo图片路径 必填
     ** @apiParam {int} status 是否显示 [0禁用；1启用]默认为1 非必填
     ** @apiSuccess {array}  id id
     ***/
    public function edit(RequestInterface $request, ResponseInterface $response)
    {
        $rules = [
            'id' => 'required|integer',
            'name' => 'required|max:20',
            'category_ids' => 'required',
            'status' => 'in:0,1',
            'sort' => 'min:0|max:999',
            'logo' => 'required|url',
        ];
        $messages = [
            'id.required' => 'ID不能为空',
            'id.integer' => 'ID参数错误',
            'name.required'    => '品牌名称不能为空',
            'name.max'      => '品牌名称不能超过20个字符',
            'category_ids.required'   => '请至少选择一个分类',
            'status.in'        => '状态参数错误',
            'sort.max'    => '排序参数错误',
            'sort.min'    => '排序参数错误',
            'logo.required'      => '请上传品牌logo',
            'logo.url' => '品牌logo参数错误',
        ];

        $validator = $this->validationFactory->make($request->all(), $rules, $messages);
        if ($validator->fails())
        {
            return jsonError($validator->errors()->first(),400);
        }
        $id = intval($request->input('id', 0));
        if (!$id) return jsonError('id参数错误',405);

        $category_id  = $request->input('category_ids', '');
        $category_id  = rtrim($category_id, ',');

        //  判断传入的数据是否是顶级目录  暂时只有几个 并且不会经常改变 所以不做负责判断
        $category_pid = [1, 2, 3, 4];
        if (in_array($category_id, $category_pid)) {
            //  当前判定顶级目录  查询所有子目录
            $child_category_ids = ShopCategory::where('pid', $category_id)
                ->whereNull('deleted_at')
                ->pluck('id')
                ->toArray();
            if (is_array($child_category_ids)) {
                $category_id = implode(',', $child_category_ids);
            }
        }
        $data['category_ids'] = $category_id;
        $data['name'] = $request->input('name');
        $data['status'] = intval($request->input('pid', 1));
        $data['sort'] = $request->input('sort', 0);
        $data['logo'] = $request->input('logo');
        $data['updated_at'] = time();

        if (ShopBrand::where('name', $data['name'])
            ->where('status', 1)
            ->where('id','<>', $id)
            ->exists()
        ) {
            return jsonError('品牌已存在',405);
        }

        $res = ShopBrand::where('id', $id)->update($data);
        $authUser = auth('admin')->user();
        if ($res) {
            $info = $authUser['name'] .'编辑品牌，ID:'.$id;
            AdminLog::addData($info,3, getClientIp());
            return jsonSuccess(['id' => $id],'编辑成功');
        }
        return jsonError('编辑失败',500);
    }


    /***
     ** @api {get} admin/brand 品牌信息
     ** @apiName 品牌信息
     ** @apiGroup 品牌
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiParam {int} id 品牌id 必填
     ** @apiSuccess {array}  brandInfo
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
        $brand = ShopBrand::find($id);
        return jsonSuccess($brand);

    }


    /***
     ** @api {get} admin/brandList 品牌列表
     ** @apiName 品牌列表
     ** @apiGroup 品牌
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiParam {int} page 页码 默认1开始 非必填
     ** @apiParam {int} pageSize 页每页条目数 默认15 非必填
     ** @apiParam {int} category_id 分类id  非必填
     ** @apiParam {int} status 状态 非必填
     ** @apiParam {string} name 品牌名称 非必填
     ** @apiSuccess {array}  list 品牌列表
     ***/
    public function brandList(RequestInterface $request, ResponseInterface $response)
    {
        $page = $request->input('page', 1);
        $page = $page - 1;
        $pageSize = $request->input('pageSize', 15);
        $name = $request->input('name', '');
        $status = $request->input('status', '');
        $category_id = $request->input('category_id', 0);

        $brand = ShopBrand::orderBy('sort', 'asc')
            ->orderBy('id', 'desc');

        if ($name != '') {
            $brand->where('name', 'like', "%{$name}%");
        }

        if ($status != '') {
            $brand->where('status', $status);
        }

        if ($category_id) {
            $brand->whereRaw('FIND_IN_SET(?,category_ids)',[$category_id]);
        }
        $totals = $brand->count();
        $lists = $brand->offset($page * $pageSize)->limit($pageSize)->get();

        // 根据分类ID获取分类名称
        if ($lists) {
            foreach ($lists as $k => $v) {
                if ($v['category_ids']) {
                    $category_ids = explode(',', $v['category_ids']);
                    $category_name = ShopCategory::where('is_show', 1)
                        ->whereIn('id', $category_ids)
                        ->pluck('cat_name');
                    $category_name = $category_name ? $category_name->toArray() : [];
                    $lists[$k]['category_name'] = implode(',', $category_name);
                } else {
                    $lists[$k]['category_name'] = '';
                }
            }
        }

        return jsonSuccess([
            'lists' => $lists,
            'totals' => $totals
        ]);
    }

}
