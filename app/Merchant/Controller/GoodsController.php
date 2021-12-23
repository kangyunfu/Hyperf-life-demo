<?php

declare(strict_types=1);

namespace App\Merchant\Controller;

use App\Merchant\Model\ShopCategory;
use App\Common\RedisServer;
use App\Merchant\Model\ShopGoods;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Di\Annotation\Inject;
use App\Merchant\Model\ShopAttributeMenu;
use App\Merchant\Model\ShopBrand;
use App\Common\Model\Area;
use App\Merchant\Model\MerchantLog;
use Hyperf\DbConnection\Db;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use function PHPUnit\Framework\throwException;


class GoodsController extends MerchantBaseController
{
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
    /***
     ** @api {get} merchant/getCategory 获取子分类
     ** @apiName 根据父级ID获取所有子分类的列表
     ** @apiGroup 商品管理
     ** @apiHeader {string} token 已登录商户的token值  必填
     ** @apiParam {int} pid 分类父级id 默认为0 必填
     ** @apiSuccess {array}  categoryList
     ***/
    public function getCategory(RequestInterface $request, ResponseInterface $response)
    {
        $pid = $request->input('pid', 0);
        $list = ShopCategory::getParentCate($pid);
        return jsonSuccess($list, '获取成功');
    }

    /***
     ** @api {get} merchant/getBrandByCategory 根据分类获取品牌
     ** @apiName 根据分类ID获取品牌
     ** @apiGroup 商品管理
     ** @apiHeader {string} token 已登录商户的token值  必填
     ** @apiParam {int} category_id 分类id 必填
     ** @apiSuccess {array}  brandList
     ***/
    public function getBrandByCategory(RequestInterface $request, ResponseInterface $response)
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
        // ======================================21.11.9  TODO 先不删除原代码  等确认查询条件之后在定  原作者写得代码和原型不符
        $brandList = ShopCategory::where('pid', $category_id)
            ->whereNull('deleted_at')
            ->select('id','cat_name as name')
            ->get();
        // ======================================21.11.9
        // 如果这个分类有下级，匹配所有的下级
//        $child_category_ids = ShopCategory::where('pid', $category_id)
//            ->whereNull('deleted_at')
//            ->pluck('id');
//        $child_category_ids = $child_category_ids ? $child_category_ids->toArray() : [];
//        array_push($child_category_ids, $category_id);
//
//        $brandList = ShopBrand::where('status', 1)
//            ->whereRaw('FIND_IN_SET(?,category_ids)',$child_category_ids)
//            ->orderBy('sort', 'asc')
//            ->orderBy('id', 'desc')
//            ->select('id','name','logo','sort')
//            ->get();

        return jsonSuccess($brandList);

    }

    /***
     ** @api {get} merchant/getBrand 服务设置-新增-获取品牌
     ** @apiName 服务设置-新增-获取品牌
     ** @apiGroup 商品管理
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
        // ======================================21.11.9  TODO 先不删除原代码  等确认查询条件之后在定  原作者写得代码和原型不符
//        $brandList = ShopCategory::where('pid', $category_id)
//            ->whereNull('deleted_at')
//            ->select('id','cat_name as name')
//            ->get();
        // ======================================21.11.9
        // 如果这个分类有下级，匹配所有的下级
        $child_category_ids = ShopCategory::where('pid', $category_id)
            ->whereNull('deleted_at')
            ->pluck('id');
        $child_category_ids = $child_category_ids ? $child_category_ids->toArray() : [];
        array_push($child_category_ids, $category_id);

        $brandList = ShopBrand::where('status', 1)
            ->whereRaw('FIND_IN_SET(?,category_ids)',$child_category_ids)
            ->orderBy('sort', 'asc')
            ->orderBy('id', 'desc')
            ->select('id','name','logo','sort')
            ->get();

        return jsonSuccess($brandList);

    }


    /***
     ** @api {get} merchant/getAttribute 根据分类、品牌获取属性
     ** @apiName 根据分类获、品牌获取属性
     ** @apiGroup 商品管理
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiParam {int} brand_id 品牌id  必填
     ** @apiParam {int} category_id 分类id  必填
     ** @apiSuccessExample {json} SuccessExample
     * {
    "msg": "success",
    "code": 200,
    "data": [
    {
    "id": 7,
    "category_id": 10,
    "brand_id": 3,
    "name": "型号4去",
    "status": 1,
    "select_type": 0,
    "is_main": 0,
    "created_at": "2021-09-09 07:37:58",
    "updated_at": "2021-09-09 08:44:07",
    "shop_attribute": [
    {
    "id": 20,
    "attribute_menu_id": 7,
    "name": "iphone12",
    "desc": "iphone12 desc123"
    }
    ]
    }
    ]
    }
     ***/
    public function getAttribute(RequestInterface $request, ResponseInterface $response)
    {
        $rules = [
            'brand_id' => 'required|integer|min:1',
            'category_id' => 'required|integer|min:1',
        ];
        $messages = [
            'brand_id.required' => '品牌id不能为空',
            'brand_id.integer'    => '品牌id参数错误',
            'brand_id.min'    => '品牌id参数错误',
            'category_id.required' => '分类id不能为空',
            'category_id.integer'    => '分类id参数错误',
            'category_id.min'    => '分类id参数错误',

        ];
        $validator = $this->validationFactory->make($request->all(), $rules, $messages);
        if ($validator->fails())
        {
            return jsonError($validator->errors()->first(),400);
        }
        $brand_id = $request->input('brand_id');
        $category_id = $request->input('category_id');

        $attributeList = ShopAttributeMenu::with([
            'shopAttribute' => function($query){
                return $query->select('id','attribute_menu_id','name','desc');
            },
        ])
            ->where('status', 1)
            ->where('category_id', $category_id)
            ->where('brand_id', $brand_id)
            ->orderBy('id', 'asc')
            ->get();
        return jsonSuccess($attributeList);
    }

    /***
     ** @api {get} merchant/getServiceType 获取服务方式（到家、邮寄、到店）
     ** @apiName 获取服务方式（到家、邮寄、到店）
     ** @apiGroup 商品管理
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiSuccessExample {json} SuccessExample
     * {
    "msg": "success",
    "code": 200,
    "data": [
    "到家",
    "邮寄",
    "到店"
    ]
    }
     ***/
    public function getServiceType(RequestInterface $request, ResponseInterface $response)
    {
        return jsonSuccess(config('merchant_config.serviceType'));
    }

    /***
     ** @api {get} merchant/getArea 获取服务区域
     ** @apiName 获取服务区域
     ** @apiGroup 商品管理
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiParam {int} level 等级(0 省份 1 城市 2 区域) 非必填
     ** @apiParam {int} code 区域编码 非必填
     ** @apiParam {int} pcode 区域上级code 非必填
     ** @apiSuccessExample {json} SuccessExample
     * {
    "msg": "success",
    "code": 200,
    "data": [
    {
    "id": 1,
    "code": 11,             区域编码
    "name": "北京市",          名称
    "pcode": 0,             上级code
    "level_0_code": 0,      省份编码
    "level_0_name": null,   省份名称
    "level_1_code": 0,      城市编码
    "level_1_name": null,   城市名称
    "level_2_code": 0,      地区编码
    "level_2_name": null,   地区名称
    "level": 0              0 省份 1 城市 2 区域
    }
    ]
    }
     ***/
    public function getArea(RequestInterface $request, ResponseInterface $response)
    {
        $list = $this->redisServer->get('lifeService:merchant_service_area');
        if ($list) {
            $list = json_decode($list, true);
        } else{
            $list = Area::where('level', 0)
                ->select('code', 'name')
                ->get();
            if ($list) {
                foreach ($list as $k => $v) {
                    $list[$k]['children'] = Area::where('level', 1)
                        ->where('pcode', $v['code'])
                        ->select('code', 'name')
                        ->get();
                }
            }
            $this->redisServer->set('lifeService:merchant_service_area', json_encode($list));
        }
        return jsonSuccess($list);
    }


    /***
     ** @api {post} merchant/goods 添加商品
     ** @apiName 添加商品
     ** @apiGroup 商品管理
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiParam {int} category_id 分类id 必填
     ** @apiParam {int} brand_id 品牌id 必填
     ** @apiParam {string} attribute_ids 属性集合（以,隔开） 必填
     ** @apiParam {string} service_type 服务方式(以,隔开) 必填
     ** @apiParam {string} service_area 服务区域集合 必填
     ** @apiParam {decimal} original_price 原价 非必填
     ** @apiParam {decimal} final_price 结算价 必填
     ** @apiParam {decimal} door_fee 上门费 非必填
     ** @apiParam {decimal} express_fee 快递费 非必填
     ** @apiParam {int} status 0未上架 1上架 默认0 非必填
     ** @apiParam {string} explain 解释说明 非必填
     ** @apiSuccessExample {json} SuccessExample
     * {"msg": "添加成功","code": 200,"data": {"id": 11}}
     ***/

    public function add(RequestInterface $request, ResponseInterface $response)
    {
        $rules = [
            'category_id' => 'required|integer',
            'brand_id' => 'required|integer',
            'attribute_ids' => 'required',
            'service_area' => 'required',
            'service_type' => 'required',
            'original_price' => 'max:10',
            'final_price' => 'required|max:10',
            'door_fee' => 'max:10',
            'express_fee' => 'max:10',
            'status' => 'in:0,1',
            'explain' => 'max:800'
        ];
        $messages = [
            'category_id.required' => '分类id参数不能为空',
            'category_id.integer' => '分类id参数错误',
            'brand_id.required' => '品牌id参数不能为空',
            'brand_id.integer' => '品牌id参数错误',
            'attribute_ids.required' => '属性不能为空',
            'service_area.required' => '服务范围不能为空',
            'service_type.required' => '服务方式不能为空',
            'original_price.max' => '原价设置超出范围',
            'final_price.required' => '结算价不能为空',
            'final_price.max' => '结算价设置超出范围',
            'door_fee.max' => '上门费设置超出范围',
            'express_fee.max' => '快递费设置超出范围',
            'status.in' => '状态设置参数错误',
            'explain.max' => '解释说明参数超过最大限度'
        ];

        $validator = $this->validationFactory->make($request->all(), $rules, $messages);
        if ($validator->fails())
        {
            return jsonError($validator->errors()->first(),400);
        }
        Db::beginTransaction();
        try {
            $data['number'] = 'G'. mt_rand(10000, 99999) . date('is');
            $data['merchant_id'] = auth('merchant')->id();
            $data['category_id'] = $request->input('category_id');
            $data['brand_id'] = $request->input('brand_id');
            $data['attribute_ids'] = $this->stringToSort($request->input('attribute_ids'));
            $data['service_area'] = $request->input('service_area');
            $data['service_type'] = $request->input('service_type');
            $data['original_price'] = $request->input('original_price', 0);
            $data['final_price'] = $request->input('final_price', 0);
            $data['door_fee'] = $request->input('door_fee',0);
            $data['express_fee'] = $request->input('express_fee', 0);
            $data['status'] = intval($request->input('status', 0));
            $data['explain'] = $request->input('explain', '');
            $data['created_at'] = time();
            $data['updated_at'] = time();

            if ($data['final_price'] <= 0) {
                throw new \Exception('结算价须大于0', 405);
            }

            // 上门费必须小于结算价
            if ($data['final_price'] <= ($data['door_fee'] + $data['express_fee'])) {
                throw new \Exception('上门费和快递费必须小于结算价', 405);
            }

            // 判断商品是存在
            if (ShopGoods::where('status', 1)
                ->where('merchant_id',  $data['merchant_id'])
                ->where('category_id', $data['category_id'])
                ->where('brand_id', $data['brand_id'])
                ->where('attribute_ids', $data['attribute_ids'])
                ->exists()
            ) {
                throw new \Exception('对应商品已存在', 405);
            }

            $id = ShopGoods::insertGetId($data);
            $authUser = auth('merchant')->user();
            $info = $authUser['name'] .'添加商品，ID:'.$id;
            MerchantLog::addData($info,1, getClientIp(), $authUser['name']);
            Db::commit();
            return jsonSuccess(['id' => $id],'添加成功');
        } catch (\Exception $ex) {
            Db::rollBack();
            return jsonError($ex->getMessage(),500);
        }
    }

    /***
     ** @api {put} merchant/goods 编辑商品
     ** @apiName 编辑商品
     ** @apiGroup 商品管理
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiParam {int} id 商品id 必填
     ** @apiParam {int} category_id 分类id 必填
     ** @apiParam {int} brand_id 品牌id 必填
     ** @apiParam {string} attribute_ids 属性集合（以,隔开） 必填
     ** @apiParam {string} service_type 服务方式(以,隔开) 必填
     ** @apiParam {string} service_area 服务区域集合 必填
     ** @apiParam {decimal} original_price 原价 非必填
     ** @apiParam {decimal} final_price 结算价 必填
     ** @apiParam {decimal} door_fee 上门费 非必填
     ** @apiParam {decimal} express_fee 快递费 非必填
     ** @apiParam {int} status 0未上架 1上架 默认0 非必填
     ** @apiParam {string} explain 解释说明 非必填
     ** @apiSuccessExample {json} SuccessExample
     ** {"msg": "编辑成功","code": 200,"data": {"id": 11}}
     ***/

    public function edit(RequestInterface $request, ResponseInterface $response)
    {
        $rules = [
            'id' => 'required|integer',
            'category_id' => 'required|integer',
            'brand_id' => 'required|integer',
            'attribute_ids' => 'required',
            'service_area' => 'required',
            'service_type' => 'required',
            'original_price' => 'max:10',
            'final_price' => 'required|max:10',
            'door_fee' => 'max:10',
            'express_fee' => 'max:10',
            'status' => 'in:0,1',
            'explain' => 'max:800'
        ];
        $messages = [
            'category_id.required' => '分类id参数不能为空',
            'category_id.integer' => '分类id参数错误',
            'brand_id.required' => '品牌id参数不能为空',
            'brand_id.integer' => '品牌id参数错误',
            'attribute_ids.required' => '属性不能为空',
            'service_area.required' => '服务范围不能为空',
            'service_type.required' => '服务方式不能为空',
            'original_price.max' => '原价设置超出范围',
            'final_price.required' => '结算价不能为空',
            'final_price.max' => '结算价设置超出范围',
            'door_fee.max' => '上门费设置超出范围',
            'express_fee.max' => '快递费设置超出范围',
            'status.in' => '状态设置参数错误',
            'explain.max' => '解释说明参数超过最大限度'
        ];

        $validator = $this->validationFactory->make($request->all(), $rules, $messages);
        if ($validator->fails())
        {
            return jsonError($validator->errors()->first(),400);
        }
        $id = $request->input('id');
        $merchant_id = auth('merchant')->id();
        Db::beginTransaction();
        try {
            $data['category_id'] = $request->input('category_id');
            $data['brand_id'] = $request->input('brand_id');
            $data['attribute_ids'] = $this->stringToSort($request->input('attribute_ids'));
            $data['service_area'] = $request->input('service_area');
            $data['service_type'] = $request->input('service_type');
            $data['original_price'] = $request->input('original_price',0);
            $data['final_price'] = $request->input('final_price', 0);
            $data['door_fee'] = $request->input('door_fee',0);
            $data['express_fee'] = $request->input('express_fee',0);
            $data['status'] = intval($request->input('status', 0));
            $data['explain'] = $request->input('explain', '');
            $data['updated_at'] = time();

            if ($data['final_price'] <= 0) {
                throw new \Exception('结算价须大于0', 405);
            }

            // 上门费必须小于结算价
            if ($data['final_price'] <= ($data['door_fee'] + $data['express_fee'])) {
                throw new \Exception('上门费和快递费必须小于结算价', 405);
            }

            // 判断商品是存在
            if (ShopGoods::where('status', 1)
                ->where('merchant_id',  $data['merchant_id'])
                ->where('id', '<>', $id)
                ->where('category_id', $data['category_id'])
                ->where('brand_id', $data['brand_id'])
                ->where('attribute_ids', $data['attribute_ids'])
                ->exists()
            ) {
                throw new \Exception('对应商品已存在', 405);
            }

            $id = ShopGoods::where('id', $id)->update($data);
            $authUser = auth('merchant')->user();
            $info = $authUser['name'] .'编辑商品，ID:'.$id;
            MerchantLog::addData($info,3, getClientIp(),$authUser['name']);
            Db::commit();
            return jsonSuccess(['id' => $id],'编辑成功');
        } catch (\Exception $ex) {
            Db::rollBack();
            return jsonError($ex->getMessage(),500);
        }
    }


    /***
     ** @api {get} merchant/goods 商品信息
     ** @apiName 商品信息
     ** @apiGroup 商品管理
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiParam {int} id 商品id 必填
     ** @apiSuccessExample {json} SuccessExample
     * {
    "msg": "success",
    "code": 200,
    "data": {
    "id": 17,
    "number": "G345754228",             产品编号
    "merchant_id": 2,                   商户ID
    "brand_id": 1,                      品牌id
    "category_id": 8,                   分类id
    "attribute_menu_id": 3,             规格id
    "attribute_ids": "1,3",             属性id集合，以，隔开
    "original_price": "100.00",         原价
    "final_price": "90.00",             结算价
    "door_fee": "10.00",                上门费
    "express_fee": "5.50",              快递费
    "status": 1,                        是否上架 1是 0否
    "explain": null,                    说明
    "sort": 0,                          排序,越小越前
    "service_type": "邮寄",               0 => '到家',1 => '邮寄',2 => '到店'
    "service_area": "4403,11,31",         服务范围（area id，以，隔开）
    "created_at": "2021-09-11 07:42:28",
    "updated_at": "2021-09-11 08:10:19",
    "deleted_at": null,
    "attribute": [
        {"name": "iphone13","desc": "iphone13 desc"},
        {"name": "iphone12","desc": "iphone12 desc"}],
    "area": [
        {"name": "北京市","code": 11,"pcode": 0,"level": 0},
        {"name": "深圳市","code": 4403,"pcode": 44,"level": 1}],
    "shop_attribute_menu": {"id": 3,"name": "型号2","select_type": 0},
    "shop_category": {"id": 8,"cat_name": "1222"},
    "shop_brand": {"id": 1,"name": "苹果1"}
    }
    }
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
            return jsonError('参数错误',400);
        }
        $goods = ShopGoods::with(
            [
                'shopCategory' => function($query){
                    return $query->select('id','cat_name');
                },
                'shopBrand' => function($query){
                    return $query->select('id','name');
                },
            ]
        )->where('id', $id)->first();
        $goods['attribute'] = ShopGoods::getShopAttribute($goods['attribute_ids']);
        $goods['area'] = ShopGoods::getShopServiceArea($goods['service_area']);
        return jsonSuccess($goods);

    }


    /***
     ** @api {get} merchant/goodsList 商品列表
     ** @apiName 商品列表
     ** @apiGroup 商品管理
     ** @apiHeader {string} token 已登录token(Header: token)  必填
     ** @apiParam {int} page 页码 默认1开始 非必填
     ** @apiParam {int} pageSize 页每页条目数 默认15 非必填
     ** @apiParam {int} service_id 服务id  非必填
     ** @apiParam {int} category_id 分类id  非必填
     ** @apiParam {string} name 品牌名称 非必填
     ** @apiParam {int} service_type 服务方式 非必填
     ** @apiSuccessExample {json} SuccessExample
     * {"msg": "success",
        "code": 200,
        "data": {
            "lists": [{
                "id": 27,
                "number": "G825503026",         ##产品编号
                "merchant_id": 5,               ##商户ID
                "brand_id": 5,                  ##品牌id
                "category_id": 7,               ##分类id
                "attribute_menu_id": 0,         ##规格id
                "attribute_ids": "30",          ##属性id集合，以，隔开
                "original_price": "10.00",      ##原价
                "final_price": "11.00",         ##结算价
                "door_fee": "1.00",             ##上门费
                "express_fee": "1.00",          ##快递费
                "status": 1,                    ##是否上架 1是 0否
                "explain": "123",               ##说明
                "sort": 0,                      ##排序,越小越前
                "service_type": "0,2,1",        ##0 => '到家',1 => '邮寄',2 => '到店'（多个以，隔开）
                "service_area": "11,12,15",     ##服务范围（area id，以，隔开）
                "created_at": "2021-09-15 02:30:26",
                "updated_at": "2021-09-15 02:48:19",
                "deleted_at": null,
                "brand_name": "锤子",
                "cat_name": "1111",             ##当前分类名称
                "cat_pid": 3,                   ##当前父级分类ID
                "attribute": [{"id": 30,"attribute_menu_id": 18,"name": "2","desc": "2","sort": 0}], ##属性集合
                "parent_cat_name": "回收",        ####当前父级分类名称
                "area": [{"name": "北京市","code": 11,"pcode": 0,"level": 0}]      ##地区
            }],
            "totals": 13
        }
    }
     ***/
    public function goodsList(RequestInterface $request, ResponseInterface $response)
    {
        $page = $request->input('page', 1);
        $page = $page - 1;
        $pageSize = $request->input('pageSize', 15);
        $name = $request->input('name', '');
        $service_id = $request->input('service_id', 0);
        $category_id = $request->input('category_id', 0);
        $service_type = $request->input('service_type', '');

        $shopGoods = ShopGoods::leftJoin('shop_brand', 'shop_brand.id', '=', 'shop_goods.brand_id')
            ->leftJoin('shop_category', 'shop_category.id', '=', 'shop_goods.category_id')
            ->orderBy('id', 'desc')
            ->select(
                'shop_goods.*',
                'shop_brand.name as brand_name',
                'shop_category.cat_name',
                'shop_category.pid as cat_pid',
            )
            ->whereNull('shop_category.deleted_at');
        if ($name) {
            $shopGoods->where('shop_brand.name','like', "%{$name}%");
        }
        if ($service_id) {
            $cids = ShopCategory::getChildIds($service_id);
            $shopGoods->whereIn('category_id' , $cids);
        }
        if ($category_id) {
            $shopGoods->where('category_id', $category_id);
        }
        if ($service_type != '') {
            $shopGoods->where('service_type', $service_type);
        }

        $totals = $shopGoods->count();
        $lists  = $shopGoods->offset($page * $pageSize)->limit($pageSize)->get();
        if ($lists) {
            foreach ($lists as $k => $v) {
                $lists[$k]['attribute'] = ShopGoods::getShopAttribute($v['attribute_ids']);
                $lists[$k]['parent_cat_name'] = ShopCategory::where('id', $v['cat_pid'])->value('cat_name');
                $lists[$k]['area'] = ShopGoods::getShopServiceArea($v['service_area']);
            }
        }
        return jsonSuccess([
            'lists' => $lists,
            'totals' => $totals
        ]);
    }


    // 字符串排序
    private function stringToSort($string = '')
    {
        if ($string) {
            $arr = explode(',', $string);
            if ($arr && is_array($arr)) {
                sort($arr);
                $string = implode(',', $arr);
            }
        }
        return $string;
    }

}
