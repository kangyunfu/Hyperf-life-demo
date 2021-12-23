<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Di\Annotation\Inject;
use App\Admin\Model\ShopAttribute;
use App\Admin\Model\ShopCategory;
use App\Admin\Model\ShopBrand;
use App\Admin\Model\ShopAttributeMenu;
use App\Admin\Model\AdminLog;
use Hyperf\DbConnection\Db;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use function PHPUnit\Framework\throwException;


class AttributeController extends AdminBaseController
{
    /**
     * @Inject()
     * @var ValidatorFactoryInterface
     */
    protected $validationFactory;


    /***
     ** @api {post} admin/attribute 添加属性
     ** @apiName 添加属性
     ** @apiGroup 属性
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiParam {int} category_id 分类id 必填
     ** @apiParam {int} brand_id 品牌id 必填
     ** @apiParam {array} model_arr [{"name":"iphone13","desc":"iphone13 desc","sort":1},{"name":"iphone13","desc":"iphone13 desc","sort":2}] 属性集合 必填
     ** @apiParam {string} name 属性分类名称 必填
     ** @apiParam {int} status 是否显示 [0禁用；1启用]默认为1 非必填
     ** @apiParam {int} select_type 复选项 [0:单选；1：多选]默认为0 非必填
     ** @apiParam {int} is_main 是否作为客户端首页选项 [0:否；1：是]默认为0 非必填
     ** @apiSuccess {array}  id id
     ***/

    public function add(RequestInterface $request, ResponseInterface $response)
    {
        $rules = [
            'name' => 'required|max:20',
            'category_id' => 'required|integer',
            'brand_id' => 'required|integer',
            'model_arr' => 'required',
            'status' => 'in:0,1',
            'select_type' => 'in:0,1',
            'is_main' => 'in:0,1',
        ];
        $messages = [
            'name.required'    => '属性名称不能为空',
            'name.max'      => '属性名称不能超过20个字符',
            'category_id.required'   => '请选择分类',
            'category_id.integer'   => '分类参数错误',
            'brand_id.required'   => '请选择品牌',
            'brand_id.integer'   => '品牌参数错误',
            'model_arr.required' => '型号不能为空',
            'status.in'        => '状态参数错误',
            'select_type.in'        => '复选项参数错误',
            'is_main.in'        => '是否作为客户端首页选项参数错误',
        ];

        $validator = $this->validationFactory->make($request->all(), $rules, $messages);
        if ($validator->fails())
        {
            return jsonError($validator->errors()->first(),400);
        }

        $model_arr = $request->input('model_arr');
        if (empty($model_arr)) {
            return jsonError('属性集合不能为空',405);
        }
        Db::beginTransaction();
        try {
            $data['name'] = $request->input('name');
            $data['category_id'] = intval($request->input('category_id'));
            $data['brand_id'] = intval($request->input('brand_id'));
            $data['status'] = intval($request->input('status', 1));
            $data['select_type'] = intval($request->input('select_type', 0));
            $data['is_main'] = intval($request->input('is_main', 0));
            $data['created_at'] = time();
            $data['updated_at'] = time();

            if (ShopAttributeMenu::where('name', $data['name'])->where('status', 1)->exists()){
                throw new \Exception('属性分类名称已存在', 405);
            }

            // 添加属性分类
            $id = ShopAttributeMenu::insertGetId($data);

            // 添加属性
            foreach ($model_arr as $m) {
                $insert['attribute_menu_id'] = $id;
                if (!$m['name']) {
                    throw new \Exception('属性不能为空', 405);
                    break;
                }
                $insert['name'] = $m['name'];
                $insert['desc'] = $m['desc'];
                $update['sort'] = $m['sort'] ?? 0;
                $insert['created_at'] = time();
                $insert['updated_at'] = time();
                if (ShopAttribute::where('attribute_menu_id', $id)
                    ->where('name', $insert['name'])
                    ->exists()
                ){
                    continue;
                }
                ShopAttribute::insert($insert);
            }

            // 添加日志
            $authUser = auth('admin')->user();
            $info = $authUser['name'] .'添加属性，ID:'.$id;
            AdminLog::addData($info,1, getClientIp());
            Db::commit();
            return jsonSuccess(['id' => $id],'添加成功');
        } catch (\Exception $ex) {
            Db::rollBack();
            return jsonError($ex->getMessage(),500);
        }
    }

    /***
     ** @api {post} admin/attributeEdit 编辑属性
     ** @apiName 编辑属性
     ** @apiGroup 属性
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiParam {int} id 属性分类id 必填
     ** @apiParam {int} category_id 分类id 必填
     ** @apiParam {int} brand_id 品牌id 必填
     ** @apiParam {array} model_arr [{"name":"iphone13","desc":"iphone13 desc","sort":1},{"name":"iphone13","desc":"iphone13 desc","sort":2}] 属性集合 必填
     ** @apiParam {string} name 属性分类名称 必填
     ** @apiParam {int} status 是否显示 [0禁用；1启用]默认为1 非必填
     ** @apiParam {int} select_type 复选项 [0:单选；1：多选]默认为0 非必填
     ** @apiParam {int} is_main 是否作为客户端首页选项 [0:否；1：是]默认为0 非必填
     ** @apiSuccess {array}  id id
     ***/

    public function edit(RequestInterface $request, ResponseInterface $response)
    {
        $rules = [
            'id' => 'required|integer',
            'name' => 'required|max:20',
            'category_id' => 'required|integer',
            'brand_id' => 'required|integer',
            'model_arr' => 'required',
            'status' => 'in:0,1',
            'select_type' => 'in:0,1',
            'is_main' => 'in:0,1',
        ];
        $messages = [
            'id.required' => 'ID不能为空',
            'id.integer' => 'ID参数错误',
            'name.required'    => '属性名称不能为空',
            'name.max'      => '属性名称不能超过20个字符',
            'category_id.required'   => '请选择分类',
            'category_id.integer'   => '分类参数错误',
            'brand_id.required'   => '请选择品牌',
            'brand_id.integer'   => '品牌参数错误',
            'model_arr.required' => '型号不能为空',
            'status.in'        => '状态参数错误',
            'select_type.in'        => '复选项参数错误',
            'is_main.in'        => '是否作为客户端首页选项参数错误',
        ];

        $validator = $this->validationFactory->make($request->all(), $rules, $messages);
        if ($validator->fails())
        {
            return jsonError($validator->errors()->first(),400);
        }

        $model_arr = $request->input('model_arr');
        if (empty($model_arr)) {
            return jsonError('属性集合不能为空',400);
        }
        // $model_arr = json_decode($model_arr, true);
        $id = $request->input('id');
        Db::beginTransaction();
        try {
            $data['name'] = $request->input('name');
            $data['category_id'] = intval($request->input('category_id'));
            $data['brand_id'] = intval($request->input('brand_id'));
            $data['status'] = intval($request->input('status', 1));
            $data['select_type'] = intval($request->input('select_type', 0));
            $data['is_main'] = intval($request->input('is_main', 0));
            $data['updated_at'] = time();

            if (ShopAttributeMenu::where('name', $data['name'])
                ->where('status', 1)
                ->where('id', '<>', $id)
                ->exists()){
                throw new \Exception('属性分类名称已存在', 405);
            }

            // 编辑属性
            ShopAttributeMenu::where('id', $id)->update($data);

            foreach ($model_arr as $m) {
                if (!$m['name']) {
                    throw new \Exception('属性不能为空', 405);
                    break;
                }
                if ($m['id']) {
                    $exit = ShopAttribute::where('id', $m['id'])->exists();
                    if ($exit) {
                        ShopAttribute::where('id', $m['id'])->update([
                            'name' => $m['name'],
                            'desc' => $m['desc'],
                            'sort' => $m['sort'] ?? 0,
                            'updated_at' => time()
                        ]);
                    } else {
                        $update['attribute_menu_id'] = $id;
                        $update['name'] = $m['name'];
                        $update['desc'] = $m['desc'];
                        $update['sort'] = $m['sort'] ?? 0;
                        $update['created_at'] = time();
                        $update['updated_at'] = time();
                        ShopAttribute::insert($update);
                    }
                } else {
                    $update['attribute_menu_id'] = $id;
                    $update['name'] = $m['name'];
                    $update['desc'] = $m['desc'];
                    $update['sort'] = $m['sort'] ?? 0;
                    $update['created_at'] = time();
                    $update['updated_at'] = time();
                    ShopAttribute::insert($update);
                }
            }

            // 添加日志
            $authUser = auth('admin')->user();
            $info = $authUser['name'] .'编辑属性，ID:'.$id;
            AdminLog::addData($info,3, getClientIp());
            Db::commit();
            return jsonSuccess(['id' => $id],'编辑成功');
        } catch (\Exception $ex) {
            Db::rollBack();
            return jsonError($ex->getMessage(),500);
        }
    }


    /***
     ** @api {get} admin/attribute 获取属性信息
     ** @apiName 根据ID获取属性信息
     ** @apiGroup 属性
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiParam {int} id 属性id 必填
     ** @apiSuccess {array}  attributeInfo
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
        $attribute = ShopAttributeMenu::with([
            'shopAttribute' => function($query){
                return $query->orderBy('sort', 'asc')
                    ->select('id','attribute_menu_id','name','desc','sort');
            },
            'shopCategory' => function($query){
                return $query->select('id','cat_name');
            },
            'shopBrand' => function($query){
                return $query->select('id','name');
            },
            ])->where('id', $id)->first();
        return jsonSuccess($attribute);

    }


    /***
     ** @api {get} admin/attributeList 属性列表
     ** @apiName 属性列表
     ** @apiGroup 属性
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiParam {int} page 页码 默认1开始 非必填
     ** @apiParam {int} pageSize 页每页条目数 默认15 非必填
     ** @apiParam {int} service_id 服务id  非必填
     ** @apiParam {int} category_id 分类id  非必填
     ** @apiParam {string} name 品牌名称 非必填
     ** @apiSuccess {array}  list 属性列表
     ***/
    public function attributeList(RequestInterface $request, ResponseInterface $response)
    {
        $page = intval($request->input('page', 1));
        $page = $page - 1;
        $pageSize = intval($request->input('pageSize', 15));
        $name = $request->input('name', '');
        $service_id = $request->input('service_id', 0);
        $category_id = $request->input('category_id', 0);

        $shopAttribute = ShopAttributeMenu::with([
            'shopAttribute' => function($query){
                return $query->select('attribute_menu_id','name','desc');
            }
            ])
            ->leftJoin('shop_brand', 'shop_brand.id', '=', 'shop_attribute_menu.brand_id')
            ->leftJoin('shop_category', 'shop_category.id', '=', 'shop_attribute_menu.category_id')
            ->orderBy('id', 'desc')
            ->select(
                'shop_attribute_menu.*',
                'shop_brand.name as brand_name',
                'shop_category.cat_name',
                'shop_category.pid as cat_pid',
            )
            ->whereNull('shop_category.deleted_at')
        ;
        if ($name) {
            $shopAttribute->where('shop_brand.name','like', "%{$name}%");
        }
        if ($service_id) {
            $cids = ShopCategory::getChildIds($service_id);
            $shopAttribute->whereIn('category_id' , $cids);
        }
        if ($category_id) {
            $shopAttribute->where('shop_attribute_menu.category_id', $category_id);
        }
        $totals = $shopAttribute->count();
        $lists = $shopAttribute->offset($page * $pageSize)->limit($pageSize)->get();

        if ($lists) {
            foreach ($lists as $k => $v) {
                $lists[$k]['parent_cat_name'] = ShopCategory::where('id', $v['cat_pid'])->value('cat_name');
            }
        }
        return jsonSuccess([
            'lists' => $lists,
            'totals' => $totals
        ]);
    }


    /***
     ** @api {get} admin/getBrandByCategory 根据分类获取品牌
     ** @apiName 根据分类ID获取品牌
     ** @apiGroup 属性
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiParam {int} id 分类id 必填
     ** @apiSuccess {array}  brandList
     ***/
    public function getBrandByCategory(RequestInterface $request, ResponseInterface $response)
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
        $brandList = ShopBrand::where('status', 1)
            ->whereRaw('FIND_IN_SET(?,category_ids)',[$id])
            ->orderBy('sort', 'asc')
            ->orderBy('id', 'desc')
            ->select('id','name','logo','sort')
            ->get();
        return jsonSuccess($brandList);

    }

    /***
     ** @api {get} admin/getBrand 根据服务类型-获取品牌
     ** @apiName 根据服务类型-获取品牌
     ** @apiGroup 属性
     ** @apiHeader {string} token 已登录商户的token值  必填
     ** @apiParam {int} category_id 分类id 必填
     ** @apiSuccess {array}  brandList
     ***/
    public function getBrand(RequestInterface $request, ResponseInterface $response)
    {
        $rules = [
            'category_id' => 'required|integer'
        ];
        $messages = [
            'category_id.required' => '分类id参数不能为空',
            'category_id.integer'    => '分类id参数错误',
        ];
        $validator = $this->validationFactory->make($request->all(), $rules, $messages);
        if ($validator->fails())
        {
            return jsonError($validator->errors()->first(),400);
        }
        $category_id = $request->input('category_id', 0);
        if (!$category_id) {
            return jsonError('参数错误',400);
        }

        $brandList = ShopBrand::where('status', 1)
            ->whereRaw('FIND_IN_SET(?,category_ids)',$category_id)
            ->orderBy('sort', 'asc')
            ->orderBy('id', 'desc')
            ->select('id','name','logo','sort')
            ->get();

        return jsonSuccess($brandList);

    }

}
