<?php

declare(strict_types=1);

namespace App\Api\Controller\recycle;


use App\Api\Model\MemberBill;
use App\Api\Model\MemberXFB;
use App\Api\Model\ShopBill;
use App\Api\Model\ShopDevice;
use App\Api\Model\ShopDeviceFault;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Di\Annotation\Inject;
use App\Api\Model\ShopAttributeMenu;
use App\Api\Model\ShopCategory;
use App\Api\Model\ShopBrand;
use App\Api\Model\Merchant;
use App\Api\Model\ShopGoods;
use App\Api\Model\Member;
use App\Api\Model\ShopAttribute;
use App\Api\Model\ShopOrderSnapshot;
use App\Api\Model\ShopRecycleOrder;
use App\Api\Model\ShopRecycleOrderSub;
use App\Common\Model\ShopOrderLog;
use Hyperf\DbConnection\Db;
use App\Api\Controller\ApiBaseController;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use function PHPUnit\Framework\throwException;
use function Symfony\Component\Translation\t;


class OrderController extends ApiBaseController
{
    /**
     * @Inject()
     * @var ValidatorFactoryInterface
     */
    protected $validationFactory;

    /***
     ** @api {get} api/recycle/getCategory 获取分类
     ** @apiName 获取分类
     ** @apiParam {int} service_id 服务id [1:清洗，2:维修，3：回收，4：家政]  非必填
     ** @apiGroup 订单
     ** @apiSuccess {array}  categoryList
     ***/
    public function getCategory(RequestInterface $request, ResponseInterface $response)
    {
        $rules = [
            'service_id' => 'integer|min:1',
        ];
        $messages = [
            'service_id.integer'    => '服务id参数错误',
            'service_id.min'    => '服务id参数错误',

        ];
        $validator = $this->validationFactory->make($request->all(), $rules, $messages);
        if ($validator->fails())
        {
            return jsonError($validator->errors()->first(),400);
        }
        $service_id = $request->input('service_id');
        // 根据商品找出所有的分类
        $lists = ShopGoods::leftJoin('shop_category', 'shop_category.id', '=', 'shop_goods.category_id')
            ->where('shop_goods.status', 1)
            ->where('shop_category.is_show', 1);
        if ($service_id) {
            $lists->where('shop_category.pid', $service_id);
        }
        $list = $lists->orderBy('shop_goods.sort', 'asc')
            ->select('shop_goods.category_id','shop_category.cat_name')
            ->groupBy('category_id')
            ->get();

        return jsonSuccess($list);
    }

    /***
     ** @api {get} api/recycle/getBrand 获取品牌
     ** @apiName 获取品牌
     ** @apiGroup 订单
     ** @apiParam {int} category_id 分类id  非必填
     ** @apiSuccess {array}  brandList
     ***/
    public function getBrand(RequestInterface $request, ResponseInterface $response)
    {
        // 根据分类找出相应的品牌
        $rules = [
            'category_id' => 'integer|min:1',
        ];
        $messages = [
            'category_id.integer'    => '分类id参数错误',
            'category_id.min'    => '分类id参数错误',

        ];
        $validator = $this->validationFactory->make($request->all(), $rules, $messages);
        if ($validator->fails())
        {
            return jsonError($validator->errors()->first(),400);
        }
        $category_id = $request->input('category_id');
        $lists = ShopGoods::leftJoin('shop_brand', 'shop_brand.id', '=', 'shop_goods.brand_id')
            ->where('shop_goods.status', 1)
            ->where('shop_brand.status', 1);
        if ($category_id) {
            $lists->where('shop_goods.category_id', $category_id);
        }

        $list = $lists->orderBy('shop_brand.sort', 'asc')
        ->select('shop_goods.brand_id','shop_brand.name','shop_brand.logo')
        ->groupBy('brand_id')
        ->get();

        return jsonSuccess($list);
    }

    /***
     ** @api {get} api/recycle/getMainAttribute 根据分类、品牌获取主属性
     ** @apiName 根据分类获、品牌获取主属性
     ** @apiGroup 订单
     ** @apiParam {int} brand_id 品牌id  必填
     ** @apiParam {int} category_id 分类id  必填
     ** @apiSuccess {array}  attributeList
     ***/
    public function getMainAttribute(RequestInterface $request, ResponseInterface $response)
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
        $id = $this->getMainAttributeMenuId($category_id, $brand_id);

        // 根据分类和品牌取商品的属性集合
        $goodsAttributes = ShopGoods::getGoodsAttribute($category_id, $brand_id);
        $attributeList = ShopAttribute::where('attribute_menu_id', $id)
            ->whereIn('id', $goodsAttributes)
            ->orderBy('sort', 'asc')
            ->select('id', 'attribute_menu_id', 'name', 'desc')
            ->get();
        return jsonSuccess($attributeList);
    }


    /***
     ** @api {get} api/recycle/getOthersAttribute 根据分类、品牌获取属性
     ** @apiName 根据分类获、品牌获取属性
     ** @apiGroup 订单
     ** @apiParam {int} brand_id 品牌id  必填
     ** @apiParam {int} category_id 分类id  必填
     ** @apiSuccess {array}  attributeList
     ***/
    public function getOthersAttribute(RequestInterface $request, ResponseInterface $response)
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

        // 根据分类和品牌取商品的属性集合
        $goodsAttributes = ShopGoods::getGoodsAttribute($category_id, $brand_id);
        $mainId = $this->getMainAttributeMenuId($category_id, $brand_id);
        $attributeList = ShopAttributeMenu::with([
            'shopAttribute' => function($query) use ($goodsAttributes) {
                return $query->whereIn('id', $goodsAttributes)
                    ->select('id','attribute_menu_id','name','desc');
            },
        ])
            ->where('status', 1)
            ->where('category_id', $category_id)
            ->where('brand_id', $brand_id)
            ->where('id', '<>', $mainId)
            ->orderBy('id', 'asc')
            ->select('id', 'name', 'select_type')
            ->get();
        return jsonSuccess($attributeList);
    }


    /***
     ** @api {get} api/recycle/getGoodsInfo 根据属性组合获取商品信息
     ** @apiName 根据属性组合获取商品信息
     ** @apiGroup 订单
     ** @apiParam {int} brand_id 品牌id  必填
     ** @apiParam {int} category_id 分类id  必填
     ** @apiParam {string} attribute_ids 属性组合以,隔开  必填
     ** @apiSuccess {array}  attributeList
     ***/
    public function getGoodsInfo(RequestInterface $request, ResponseInterface $response)
    {
        $rules = [
            'brand_id' => 'required|integer|min:1',
            'category_id' => 'required|integer|min:1',
            'attribute_ids' => 'required',
        ];
        $messages = [
            'brand_id.required' => '品牌id不能为空',
            'brand_id.integer'    => '品牌id参数错误',
            'brand_id.min'    => '品牌id参数错误',
            'category_id.required' => '分类id不能为空',
            'category_id.integer'    => '分类id参数错误',
            'category_id.min'    => '分类id参数错误',
            'attribute_ids.required' => '属性组合参数不能为空'

        ];
        $validator = $this->validationFactory->make($request->all(), $rules, $messages);
        if ($validator->fails())
        {
            return jsonError($validator->errors()->first(),400);
        }
        $brand_id = $request->input('brand_id');
        $category_id = $request->input('category_id');
        $attribute_ids = $this->stringToSort($request->input('attribute_ids'));
        $goodsInfo = ShopGoods::where('brand_id', $brand_id)
            ->where('category_id', $category_id)
            ->where('attribute_ids', $attribute_ids)
            ->where('status', 1)
            ->select('*','service_type as service_type_code')
            ->first();
        return jsonSuccess($goodsInfo);
    }


    /***
     ** @api {post} api/recycle/createOrder 生成订单
     ** @apiName 生成订单
     ** @apiGroup 订单
     ** @apiHeader {string} sign sign值  必填
     ** @apiParam {int} goods_id 商品id  必填
     ** @apiParam {int} goods_num 商品数量  必填
     ** @apiParam {int} type 回收方式(1:门店订单；2：上门取货)  必填
     ** @apiParam {string} contact 联系人  必填
     ** @apiParam {string} phone 联系电话  必填
     ** @apiParam {string} source 来源 ['android', 'ios', 'h5', 'wechat', 'miniprogram']  必填
     ** @apiParam {string} province 省份(如：广东省)  非必填
     ** @apiParam {int} province_code 省份code  非必填
     ** @apiParam {string} city 市(如：深圳市)  非必填
     ** @apiParam {int} city_code 市code  非必填
     ** @apiParam {string} district 区(如：深山区)  非必填
     ** @apiParam {int} district_code 区code  非必填
     ** @apiParam {string} street 街道(如：西丽街道)  非必填
     ** @apiParam {int} street_code 街道code  非必填
     ** @apiParam {string} address 详细地址（回收方式为上门，需填写收件地址）  非必填
     ** @apiParam {string} remark 订单备注  非必填
     ** @apiSuccess {int} order_id 订单id
     ***/
    public function createOrder(RequestInterface $request, ResponseInterface $response)
    {
        $rules = [
            'goods_id' => 'required|integer|min:1',
            'goods_num' => 'required|integer|min:1',
            'type' => 'in:1,2',
            'contact' => 'required|max:20',
            'phone' => 'required|max:20',
            'province' => 'max:50',
            'province_code' => 'integer',
            'city' => 'max:50',
            'city_code' => 'integer',
            'district' => 'max:50',
            'district_code' => 'integer',
            'street' => 'max:50',
            'street_code' => 'integer',
            'address' => 'max:255',
            'remark' => 'max:255'
        ];
        $messages = [
            'goods_id.required' => '商品id不能为空',
            'goods_id.integer'    => '商品id参数错误',
            'goods_id.min'    => '商品id参数错误',
            'goods_num.required' => '商品数量不能为空',
            'goods_num.integer'    => '商品数量参数错误',
            'goods_num.min'    => '商品数量参数错误',
            'type.in' => '回收方式参数错误',
            'contact.required' => '联系人不能为空',
            'contact.max' => '联系人参数错误',
            'phone.required' => '联系电话不能为空',
            'phone.max' => '联系电话参数错误',
            'province.required' => '省份参数不能为空',
            'province.max' => '省份参数错误',
            'province_code.required' => '省份code不能为空',
            'province_code.integer' => '省份code参数错误',
            'city.required' => '市参数不能为空',
            'city.max' => '市参数错误',
            'city_code.required' => '市code参数不能为空',
            'city_code.integer' => '市code参数错误',
            'district.required' => '区参数不能为空',
            'district.max' => '区参数错误',
            'district_code.required' => '区code参数不能为空',
            'district_code.integer' => '区code参数错误',
            'street.required' => '街道参数不能为空',
            'street.max' => '街道参数错误',
            'street_code.required' => '街道code参数不能为空',
            'street_code.integer' => '街道code参数错误',
            'address.required' => '详细地址不能为空',
            'address.max' => '详细地址参数错误',
            'remark.max' => '备注字符超过最大标准'
        ];
        $validator = $this->validationFactory->make($request->all(), $rules, $messages);
        if ($validator->fails())
        {
            return jsonError($validator->errors()->first(),400);
        }
        $source = $request->input('source');
        if (!in_array($source, ['android', 'ios', 'h5', 'wechat', 'miniprogram'])) {
            return jsonError('来源参数错误',405);
        }

        $goods_id = $request->input('goods_id');
        Db::beginTransaction();
        try {
            $goods_info = ShopGoods::find($goods_id);
            if (!$goods_info) {
                throw new \Exception('商品不存在', 405);
            }

            // 判断回收方式
            $type = $request->input('type');
            $service_type_arr = explode(',', $goods_info['service_type']);
            if ($service_type_arr && is_array($service_type_arr)) {
                if (in_array($type, $service_type_arr)) {
                    // 存在
                } else {
                    throw new \Exception('不支持当前回收方式', 405);
                }
            }

            // 判断服务范围
            if ($type == 1) { //上门取货
                $province_code = $request->input('province_code', '');
                $city_code = $request->input('city_code', '');
                $service_area_arr = explode(',', $goods_info['service_area']);
                if ($service_area_arr && is_array($service_area_arr)) {
                    if (in_array(0, $service_area_arr)) {
                        // 全国
                    } elseif (in_array($province_code, $service_area_arr)) {
                        // 省匹配
                    } elseif (in_array($city_code, $service_area_arr)) {
                        // 市匹配
                    } else {
                        throw new \Exception('超出服务范围', 405);
                    }
                } else {
                    throw new \Exception('服务范围未设置', 405);
                }
            }



            $brand = ShopBrand::find($goods_info['brand_id']);
            $category = ShopCategory::find($goods_info['category_id']);
            $attribute = ShopGoods::getShopAttribute($goods_info['attribute_ids']);
            $member_id = auth('api')->id();
            $member = MemberXFB::find($member_id);
            $merchant = Merchant::find($goods_info['merchant_id']);
            $goods_num = $request->input('goods_num');
            // 订单
            $data['order_sn'] = createOrderSn('RYC');
            $data['member_id'] = $member_id;
            $data['member_name'] = $member['name'];
            $data['member_real_name'] = $member['real_name'];
            $data['mobile'] = $member['mobile'];
            $data['price'] = $goods_info['final_price'] * $goods_num;
            $data['recycle_price'] = $data['price'];
            $data['remarks'] = $request->input('remarks', '');
            $data['source'] = $source;
            $data['merchant_id'] = $goods_info['merchant_id'];
            $data['merchant_name'] = $merchant['name'];
            $data['merchant_account'] = $merchant['account'];
            $data['merchant_logo'] = $merchant['logo'];
            $data['type'] = $request->input('type');
            $data['contact'] = $request->input('contact');
            $data['phone'] = $request->input('phone');
            $data['province'] = $request->input('province', 0);
            $data['province_code'] = $request->input('province_code', '');
            $data['city'] = $request->input('city', '');
            $data['city_code'] = $request->input('city_code', '');
            $data['district'] = $request->input('district', '');
            $data['district_code'] = $request->input('district_code', '');
            $data['street'] = $request->input('street', '');
            $data['street_code'] = $request->input('street_code', '');
            $data['address'] = $request->input('address', '');
            $data['order_status'] = 0;
            $data['created_at'] = time();
            $data['updated_at'] = time();
            $order_id = ShopRecycleOrder::insertGetId($data);

            // 子订单
            $sub['order_id'] = $order_id;
            $sub['goods_id'] = $goods_id;
            $sub['category_id'] = $goods_info['category_id'];
            $sub['brand_id'] = $goods_info['brand_id'];
            $sub['brand_name'] = $brand['name'];
            $sub['brand_logo'] = $brand['logo'];
            $sub['category_name'] = $category['cat_name'];
            $sub['attribute'] = json_encode($attribute);
            $sub['goods_num'] = $goods_num;
            $sub['goods_price'] = $goods_info['final_price'];
            $sub['created_at'] = time();
            $sub['updated_at'] = time();
            $order_sub_id = ShopRecycleOrderSub::insertGetId($sub);

            // =========sw  添加设备
            $shopDevices = [];
            $shopDevice = [];
            for($a = 0; $a < $goods_num; $a++) {
                $shopDevice['brand_id']      = $goods_info['brand_id'];
                $shopDevice['brand_name']    = $brand['name'];
                $shopDevice['number']        = createOrderSn('DEV');;
                $shopDevice['goods_id']      = $goods_id;
                $shopDevice['order_id']      = $order_id;
                $shopDevice['member_id']     = $member_id;
                $shopDevice['member_name']   = $member['name'];
                $shopDevice['merchant_id']   = $goods_info['merchant_id'];
                $shopDevice['merchant_name'] = $merchant['name'];
                $shopDevice['category_id']   = $goods_info['category_id'];
                $shopDevice['order_sub_id']  = $order_sub_id;
                $shopDevice['attribute_ids'] = json_encode($attribute);
                $shopDevice['price']         = $goods_info['final_price'];
                $shopDevice['recycle_price'] = $goods_info['final_price'];
                $shopDevice['created_at']    = time();
                $shopDevices[] = $shopDevice;

                $shopDevice = [];
            }
            if($shopDevices) ShopDevice::insert($shopDevices);
            // =========sw

            // 添加快照
            $snapshot_service_type = '';
            if ($goods_info['service_type'] == 0) {
                $snapshot_service_type = '到家';
            } elseif ($goods_info['service_type'] == 1) {
                $snapshot_service_type = '邮寄';
            } elseif ($goods_info['service_type'] == 2) {
                $snapshot_service_type = '到店';
            }
            $snapshot_service_area = ShopGoods::getShopServiceArea($goods_info['service_area']);

            $snapshot['order_id'] = $order_id;
            $snapshot['order_type'] = 1;
            $snapshot['order_sn'] = $data['order_sn'];
            $snapshot['goods_info'] = json_encode(
               [
                   'number' => $goods_info['number'],
                   'brand_name' => $brand['name'],
                   'brand_logo' => $brand['logo'],
                   'category_name' => $category['cat_name'],
                   'attribute' => $attribute,
                   'original_price' => $goods_info['original_price'],
                   'final_price' => $goods_info['final_price'],
                   'door_fee' => $goods_info['door_fee'],
                   'express_fee' => $goods_info['express_fee'],
                   'status' => $goods_info['status'] > 0 ? '上架' : '未上架',
                   'explain' => $goods_info['explain'],
                   'service_type' => $snapshot_service_type,
                   'service_area' => $snapshot_service_area,
                   'created_at' => $goods_info['created_at'],
                   'updated_at' => $goods_info['updated_at'],
                   'deleted_at' => $goods_info['deleted_at']
               ]
            );

            $snapshot['member_info'] = json_encode(
                [
                    'name' => $member['name'],
                    'password' => $member['password'],
                    'real_name' => $member['real_name'],
                    'age' => $member['age'],
                    'sex' => $member['sex'],
                    'avatar' => $member['avatar'],
                    'mobile' => $member['mobile'],
                    'email' => $member['email']
                ]
            );

            $snapshot['merchant_info'] = json_encode(
                [
                    'number' => $merchant['number'],
                    'name' => $merchant['name'],
                    'account' => $merchant['account'],
                    'password' => $merchant['password'],
                    'logo' => $merchant['logo'],
                    'public_key' => $merchant['public_key'],
                    'private_key' => $merchant['private_key'],
                    'mobile' => $merchant['mobile'],
                    'email' => $merchant['email'],
                    'white_ips' => $merchant['white_ips']
                ]
            );

            $snapshot['address_info'] = json_encode(
                [
                    'province' => $sub['province'],
                    'province_code' => $sub['province_code'],
                    'city' => $sub['city'],
                    'city_code' => $sub['city_code'],
                    'district' => $sub['district'],
                    'district_code' => $sub['district_code'],
                    'street' => $sub['street'],
                    'street_code' => $sub['street_code'],
                    'address' => $sub['address'],
                ]
            );
            $snapshot['created_at'] = time();
            ShopOrderSnapshot::insert($snapshot);

            // 添加订单操作日志
            ShopOrderLog::insert([
                'type' => 3,
                'name' => '下单成功',
                'order_id' => $order_id,
                'member_id' => $member_id,
                'member_name' => $member['name'],
                'member_real_name' => $member['real_name'],
                'member_mobile' => $member['mobile'],
                'admin_id' => 0,
                'admin_name' => '',
                'merchant_id' => $merchant['id'],
                'merchant_account' => $merchant['account'],
                'info' => '用户：'.$member['name'].',手机号：'. $member['mobile'] . '，下单成功，直接代签收，后面改回来。订单类型：回收，订单id：'.$order_id,
                'created_at' => time()
            ]);

            Db::commit();
            return jsonSuccess(['order_id' => $order_id],'生成订单成功');
        } catch (\Exception $ex) {
            Db::rollBack();
            return jsonError($ex->getMessage(),500);
        }
    }

    /***
     ** @api {get} api/recycle/getOrderInfo 订单详情
     ** @apiName 订单详情
     ** @apiGroup 订单
     ** @apiHeader {string} sign sign值  必填
     ** @apiParam {int} order_id 订单id  必填
     ** @apiSuccessExample {json} SuccessExample
     * {
    "id": 13,
    "order_sn": "RYC2021101307165460472039",  // 单号
    "member_id": 4593633,  // 单号
    "member_name": "star?",  // 用户名
    "member_real_name": "测试啦啦啦",  // 用户真实姓名
    "mobile": "18684900706",  //  用户手机号
    "price": "960.00",  //    订单总价
    "recycle_price": "960.00",  // 结算价
    "order_status": -1,  // 单号  订单状态  0：待取货；1：待签收；2：待验收；3：待确认；4：寄回中；5：已完成  6：取消订单； 7：部分取消 8：申请退回
    "remarks": "",  // 备注
    "source": "h5",  //   来源
    "is_comment": 0,  //   是否评论
    "created_at": "2021-10-13 07:16:54",
    "updated_at": "2021-10-13 08:54:38",
    "merchant_id": 1,    //   商户ID
    "merchant_name": "admin",  //   商户名
    "merchant_account": "admin",  //   商户账号
    "merchant_logo": "https://img.xfb315.com/life_service/admin/c29511bacbe5dd53c6caea30f7662697.png",  //  商户头像
    "type": 2,   //    0:门店订单；1：上门取货
    "contact": "噜噜噜",  // 上门取货联系人
    "phone": "18684900708",  // 上门取货联系电话
    "province": "0",  // 省份
    "province_code": "",  // 单号
    "city": "",  // 城市
    "city_code": "",  // 单号
    "district": "",  // 区县
    "district_code": "",  // 单号
    "street": "",  // 街道
    "street_code": "",  // 单号
    "address": "",   地址
    "express_no": "取消订单"                     ###订单取货 单号
    "return_express_no": "取消订单"              ###订单退回 单号
    "recycle_order_sub": [   //   子订单信息
    {
    "id": 12,
    "order_id": 13,      // 订单id
    "goods_id": 1,        // 商品id
    "category_id": 5,      // 分类id
    "brand_id": 1,      // 品牌id
    "category_name": "手机",      // 分类名称
    "brand_name": "苹果",      //  品牌名称
    "brand_logo": "https://img.xfb315.com/life_service/admin/ace710cd2865875f2a7451fa54344850.png",      // 品牌logo
    "attribute":"[{\"id\":1,\"attribute_menu_id\":1,\"name\":\"\苹\果13\",\"desc\":\"\最\新\款\苹\果13\",\"sort\":0},{\"id\":5,\"attribute_menu_id\":2,\"name\":\"64G\",\"desc\":\"\",\"sort\":0},{\"id\":9,\"attribute_menu_id\":3,\"name\":\"\国\行\",\"desc\":\"\国\行\购\买\",\"sort\":0},{\"id\":12,\"attribute_menu_id\":4,\"name\":\"\在\保\",\"desc\":\"\在\保\描\述\",\"sort\":0}]",
    "goods_num": 1,      // 数量
    "goods_price": "1000.00",      // 单价=商品结算价
    "status": 0,      // 子订单状态：   0：待取货；1：待签收；2：待验收；3：待确认；4：寄回中；5：已完成  6：取消订单； 7：部分取消 8：申请退回
    "created_at": "2021-10-13 07:16:54",
    "updated_at": "2021-10-13 07:16:54"
    }
    ],
    "shop_order_log": [       //  订单日志表
    {
    "id": 18,
    "type": 3,        //  订单日志表
    "name": "下单成功",       //   [1:清洗，2:维修，3：回收，4：家政]
    "order_id": 13,       //  订单日志表
    "member_id": 4998274,       //  用户ID
    "member_name": "star?",       //  用户名
    "member_real_name": "测试啦啦啦",       //  用户真实姓名
    "member_mobile": "18684900706",       //  电话
    "admin_id": 0,       //  管理员ID
    "admin_name": "",       //  管理员名称
    "merchant_id": 1,       //  商户ID
    "merchant_account": "admin",       //  商户账号
    "info": "用户：star?,手机号：18684900706，下单成功，订单类型：回收，订单id：13",    // 操作信息
    "created_at": "2021-10-13 07:16:54"
    }
    ],
    "shop_order_express": []    //  订单快递表
    }
     *
     ***/
    public function getOrderInfo(RequestInterface $request, ResponseInterface $response)
    {
        $rules = [
            'order_id' => 'required|integer|min:1',
        ];
        $messages = [
            'order_id.required' => '订单id不能为空',
            'order_id.integer'    => '订单id参数错误',
            'order_id.min'    => '订单id参数错误'
        ];
        $validator = $this->validationFactory->make($request->all(), $rules, $messages);
        if ($validator->fails())
        {
            return jsonError($validator->errors()->first(),400);
        }
        $order_id = $request->input('order_id');
        $member_id = auth('api')->id();

        $totalGoodsNum = 0;
        $memberInfo = ShopRecycleOrder::with([
            'recycleOrderSub',
            'ShopOrderLog' => function($query){
                $query->orderBy('id', 'desc');
            },
            'shopOrderExpress'
        ])
            ->where('id', $order_id)
            ->where('member_id', $member_id)
            ->first()->toArray();

        if ($memberInfo['recycle_order_sub']) {
            foreach ($memberInfo['recycle_order_sub'] as &$item) {
                $totalGoodsNum += $item['goods_num'];
                $item['attribute'] = json_decode($item['attribute'], true);
            }
        }

        $memberInfo['total_goods_num'] = $totalGoodsNum;

        return jsonSuccess($memberInfo);
    }

    /***
     ** @api {get} api/recycle/cancelOrder 取消订单
     ** @apiName 取消订单
     ** @apiGroup 订单
     ** @apiHeader {string} sign sign值  必填
     ** @apiParam {int} order_id 订单id  必填
     ** @apiSuccess {array}  order_id
     ***/
    public function cancelOrder(RequestInterface $request, ResponseInterface $response)
    {
        return $this->updateOrder($request, 'cancel', 6);
    }



//    public function report(RequestInterface $request, ResponseInterface $response)
//    {
//        $rules = [
//            'order_id' => 'required|integer|min:1',
//            'goods_id' => 'required|integer|min:1'
//        ];
//        $messages = [
//            'order_id.required' => '订单id不能为空',
//            'order_id.integer'    => '订单id参数错误',
//            'order_id.min'    => '订单id参数错误',
//            'goods_id.required' => '商品id不能为空',
//            'goods_id.integer'    => '商品id参数错误',
//            'goods_id.min'    => '商品id参数错误'
//        ];
//        $validator = $this->validationFactory->make($request->all(), $rules, $messages);
//        if ($validator->fails())
//        {
//            return jsonError($validator->errors()->first(),400);
//        }
//        $order_id = $request->input('order_id');
//        $goods_id = $request->input('goods_id');
//        $member_id = auth('api')->id();
//        $report = ShopDevice::with([
//            'shopDeviceFault',
//            'shopDeviceCheckItem'
//        ])
//            ->where('goods_id', $goods_id)
//            ->where('order_id', $order_id)
//            ->where('member_id', $member_id)
//            ->first();
//        return jsonSuccess($report);
//    }


    /***
     ** @api {get} api/recycle/returnOrder 申请退回
     ** @apiName 申请退回
     ** @apiGroup 订单
     ** @apiHeader {string} sign sign值  必填
     ** @apiParam {int} order_id 订单id  必填
     ** @apiSuccess {array}  order_id
     ***/
    public function returnOrder(RequestInterface $request, ResponseInterface $response)
    {
        return $this->updateOrder($request, 'return', 8);
    }

    /***
     ** @api {get} api/recycle/confirmOrder 确认出售 C端确认
     ** @apiName 确认出售
     ** @apiGroup 订单
     ** @apiHeader {string} sign sign值  必填
     ** @apiParam {int} order_id 订单id  必填
     ** @apiSuccess {array}  order_id
     ***/
    public function confirmOrder(RequestInterface $request, ResponseInterface $response)
    {
         return $this->updateOrder($request, 'confirm', 5);
    }



    /***
     ** @api {get} api/recycle/getOrderLists 订单列表
     ** @apiName 订单列表
     ** @apiGroup 订单
     ** @apiHeader {string} sign sign值  必填
     ** @apiParam {int} page     页   非必填
     ** @apiParam {int} pageSize 量   非必填
     ** @apiSuccessExample {json} SuccessExample
     *
     * {
        "id": 13,
        "order_sn": "RYC2021101307165460472039",
        "price": "960.00",
        "recycle_price": "960.00",
        "order_status": 6,
        "created_at": "2021-10-13 07:16:54",
        "recycle_order_sub": [
        {
            "id": 12,
            "order_id": 13,
            "goods_id": 1,
            "category_id": 5,
            "brand_id": 1,
            "category_name": "手机",
            "brand_name": "苹果",
            "brand_logo": "https://img.xfb315.com/life_service/admin/ace710cd2865875f2a7451fa54344850.png",
            "attribute":"[{\"id\":1,\"attribute_menu_id\":1,\"name\":\"\苹\果13\",\"desc\":\"\最\新\款\苹\果13\",\"sort\":0},{\"id\":5,\"attribute_menu_id\":2,\"name\":\"64G\",\"desc\":\"\",\"sort\":0},{\"id\":9,\"attribute_menu_id\":3,\"name\":\"\国\行\",\"desc\":\"\国\行\购\买\",\"sort\":0},{\"id\":12,\"attribute_menu_id\":4,\"name\":\"\在\保\",\"desc\":\"\在\保\描\述\",\"sort\":0}]",
            "goods_num": 1,
            "goods_price": "1000.00",
            "status": 0,
            "created_at": "2021-10-13 07:16:54",
            "updated_at": "2021-10-13 07:16:54",
            "at_name": "苹果1364G国行在保"
        }
        ]
    }
     ***/
    public function orderLists(RequestInterface $request)
    {
        $page       = $request->input('page', 1) - 1;
        $pageSize   = $request->input('pageSize', 10);
        $type       = $request->input('type', 3);

        if (!in_array($type, [0,1,2,3,4])) {
            return jsonError('订单参数错误',405);
        }

        $offset     = $page * $pageSize;
        $member_id = auth('api')->id();
        $orderLists = ShopRecycleOrder::where('member_id', $member_id)
            ->select('id','order_sn','price','recycle_price','order_status','created_at');
        if ($type) {
            $orderLists->where('order_type', $type);
        }
        $orderLists = $orderLists->with(['recycleOrderSub'])
            ->orderBy('id','desc')
            ->offset($offset)
            ->limit($pageSize)
            ->get();

        foreach ($orderLists as $orderList) {
            foreach ($orderList['recycleOrderSub'] as &$v) {
                foreach (json_decode($v['attribute'], true) as $att) {
                    $v['at_name'] .= $att['name'];
                }
                $v['attribute'] = json_decode($v['attribute'], true);
            }
        }
        return jsonSuccess($orderLists);
    }


    /***
     ** @api {get} api/recycle/shopDeviceGet 获取报告信息
     ** @apiName 获取报告信息
     ** @apiGroup 订单
     ** @apiHeader {string} sign sign值  必填
     ** @apiParam {int} order_id    订单id   必填
     ** @apiParam {int} goods_id     商品id   必填
     ** @apiSuccessExample {json} SuccessExample
     *
    {
        "msg": "操作成功",
        "code": 200,
        "data": {
        "price_total": 2000,  ### 总参考价
        "price": "119.32",  ### 报价
        "recycle_price": "119.32",  ### 成交价/验机报价
        "remark": "11111",  ###  备注
        "attribute_ids": [  ### 属性
            {
                "id": 1,
                "attribute_menu_id": 1,
                "name": "苹果13",
                "desc": "最新款苹果13",
                "sort": 0
            },
            {
                "id": 5,
                "attribute_menu_id": 2,
                "name": "64G",
                "desc": "",
                "sort": 0
            },
            {
                "id": 9,
                "attribute_menu_id": 3,
                "name": "国行",
                "desc": "国行购买",
                "sort": 0
            },
            {
                "id": 12,
                "attribute_menu_id": 4,
                "name": "在保",
                "desc": "在保描述",
                "sort": 0
            }
        ],
        "shopDeviceFault": [    ### 验机问题
            {
                "type": 1,
                "description": "描述",
                "charges": "55.20",
                "img": "图片地址",
                "type_name": "主板问题"
            },
            {
                "type": 11,
                "description": "描述2",
                "charges": "55.50",
                "img": "666 ",
                "type_name": null
            }
        ]
        }
    }
     ***/
    public function shopDeviceGet(RequestInterface $request)
    {
        $rules = [
            'order_id'  => 'required|integer|min:1',
            'goods_id'  => 'required|integer|min:1',
        ];
        $messages = [
            'order_id.required'  => '订单id不能为空',
            'order_id.integer'   => '订单id参数错误',
            'order_id.min'       => '订单id参数错误',
            'goods_id.required'  => '商品id不能为空',
            'goods_id.integer'   => '商品id参数错误',
            'goods_id.min'       => '商品id参数错误',

        ];
        $validator = $this->validationFactory->make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return jsonError($validator->errors()->first(),400);
        }

        $order_id     = $request->input('order_id') ?? 0;    //  为当前订单商品 添加验机报价时传入
        $goods_id     = $request->input('goods_id') ?? 0;    //  为当前订单商品 添加验机报价时传入

        // 订单详情
        $orderSub = ShopRecycleOrderSub::where('order_id', $order_id)
            ->select('goods_num','goods_price')
            ->where('goods_id', $goods_id)
            ->first();
        if (!$orderSub) {
            return jsonError('参数错误，找不到数据',405);
        }
        $date['price_total'] = $orderSub['goods_num'] * $orderSub['goods_price'];
        // 验机详情
        $shopDevice = ShopDevice::where('order_id', $order_id)
            ->select('remark','grade','price','id','attribute_ids','recycle_price')
            ->where('goods_id', $goods_id)
            ->first();

        $problemType = config('merchant_config.problemType');

        if (!$shopDevice) {
            return jsonError('参数错误，找不到数据',405);
        } else {
            $date['price']          = $shopDevice['price'];
            $date['recycle_price']  = $shopDevice['recycle_price'];
            $date['remark']         = $shopDevice['remark'];
            if (!is_null($shopDevice['attribute_ids'])){
                $date['attribute_ids'] = json_decode($shopDevice['attribute_ids'], true);
            }

            // 问题反馈
            $shopDeviceFault = ShopDeviceFault::where('device_id', $shopDevice['id'])->select('type','description','charges','img')->get()->toArray();

            foreach ($problemType as $item) {
                $problemArr[$item['id']] = $item['name'];
            }
            foreach ($shopDeviceFault as &$v) {
                $v['type_name'] = $problemArr[$v['type']];
            }
            $date['shopDeviceFault'] = $shopDeviceFault;
        }

        return jsonSuccess($date,'操作成功');
    }


    // 获取主属性分类ID
    private function getMainAttributeMenuId($category_id, $brand_id)
    {
        $id = 0;
        $ids = ShopAttributeMenu::where('status', 1)
            ->where('is_main', 1)
            ->where('category_id', $category_id)
            ->where('brand_id', $brand_id)
            ->orderBy('id', 'asc')
            ->pluck('id');

        if (!isset($ids[0])) {
            $ids = ShopAttributeMenu::where('status', 1)
                ->where('category_id', $category_id)
                ->where('brand_id', $brand_id)
                ->orderBy('id', 'asc')
                ->pluck('id');
        }
        if (isset($ids[0])) {
            $id = $ids[0];
        }
        return $id;
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


    // 更新订单状态并记录日志
    private function updateOrder(RequestInterface $request, $type = 'cancel', $status = 0)
    {
        $rules = [
            'order_id' => 'required|integer|min:1',
        ];
        $messages = [
            'order_id.required' => '订单id不能为空',
            'order_id.integer'    => '订单id参数错误',
            'order_id.min'    => '订单id参数错误'
        ];
        $validator = $this->validationFactory->make($request->all(), $rules, $messages);
        if ($validator->fails())
        {
            return jsonError($validator->errors()->first(),400);
        }
        $order_id = $request->input('order_id');
        $order = ShopRecycleOrder::find($order_id);
        $member = auth('api')->user();

        $member_id = auth('api')->id();

        if ($order->member_id != $member_id) return jsonError('参数错误，找不到数据',405);

        if (!$order) {
            return jsonError('订单不存在',405);
        }
        $name = '';
        if ($type == 'cancel') {
            if ($order['order_status'] != 0) {
                return jsonError('待取货状态才能取消', 405);
            }
            $name = '取消订单';
        }

        if ($type == 'return') {
            if ($order['order_status'] != 3) {
                return jsonError('待确认状态才能申请退回', 405);
            }
            $name = '申请退回';
        }

        if ($type == 'finish') {
            if ($order['order_status'] != 2) {
                return jsonError('待验收状态才能完成验收', 405);
            }
            $name = '完成验收';
        }

        if ($type == 'confirm') {
            if ($order['order_status'] != 3) {
                return jsonError('待确认状态才能确认出售', 405);
            }
            $name = '确认出售';
        }

        Db::beginTransaction();
        try {

            // TODO  确认出售  待完善
            if ($type == 'confirm') {
                // 更新用户钱包
                MemberXFB::where('id', $member['id'])->increment('money', $order['recycle_price']);

                // 更新商户钱包
                Merchant::where('id', $order['merchant_id'])->decrement('money', $order['recycle_price']);

                $merchant = Merchant::find($order['merchant_id']);
                $member   = MemberXFB::find($order['member_id']);
                // 添加账变记录
                ShopBill::insert([
                    'order_id' => $order['id'],
                    'order_sn' => $order['order_sn'],
                    'type' => 2,
                    'merchant_id' => $order['merchant_id'],
                    'merchant_name' => $order['merchant_name'],
                    'member_id' => $order['member_id'],
                    'member_mobile' => $order['mobile'],
                    'member_name' => $order['member_name'],
                    'member_real_name' => $order['member_real_name'],
                    'money' => $order['recycle_price'],
                    'balance' => $merchant['money'],
                    'created_at' => time(),
                ]);

                // 会员的收支记录
                    MemberBill::insert([
                        'order_id'         => $order['id'],
                        'order_sn'         => $order['order_sn'],
                        'type'             => 1,
                        'merchant_id'      => $order['merchant_id'],
                        'merchant_name'    => $order['merchant_name'],
                        'member_id'        => $order['member_id'],
                        'member_mobile'    => $order['mobile'],
                        'member_name'      => $order['member_name'],
                        'member_real_name' => $order['member_real_name'],
                        'money'            => $order['recycle_price'],
                        'balance'          => $member['money'],
                        'created_at'       => time(),
                ]);
                    //  TODO   更新商户的 总金额
            }

            // 更新订单状态
            ShopRecycleOrder::where('id', $order_id)->update([
                'order_status' => $status,
                'updated_at' => time()
            ]);
            ShopRecycleOrderSub::where('order_id', $order_id)->update([
                'status' => $status,
                'updated_at' => time()
            ]);

            // 添加订单操作日志
            ShopOrderLog::insert([
                'type' => 3,
                'name' => $name,
                'status' => $status,
                'order_id' => $order_id,
                'member_id' => $member['id'],
                'member_name' => $member['name'],
                'member_real_name' => $member['real_name'],
                'member_mobile' => $member['mobile'],
                'admin_id' => 0,
                'admin_name' => '',
                'merchant_id' => 0,
                'merchant_account' => '',
                'info' => $info = '用户：'.$member['name'].',手机号：'. $member['mobile'] . ','.$name.'成功，订单类型：回收，订单id：'.$order_id,
                'created_at' => time()
            ]);

            Db::commit();
            return jsonSuccess(['order_id' => $order_id],'操作成功');
        } catch (\Exception $ex) {
            Db::rollBack();
            return jsonError($ex->getMessage(),500);
        }

    }

}
