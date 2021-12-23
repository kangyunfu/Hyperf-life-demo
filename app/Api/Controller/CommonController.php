<?php

declare(strict_types=1);

namespace App\Api\Controller;

use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use App\Common\Model\Area;
use App\Api\Model\ShopReceiveAddress;
use function PHPUnit\Framework\throwException;


class CommonController extends ApiBaseController
{
    /**
     * @Inject()
     * @var ValidatorFactoryInterface
     */
    protected $validationFactory;

    /***
     ** @api {get} api/common/area 获取地区
     ** @apiName 获取地区
     ** @apiGroup 公共
     ** @apiParam {int} pcode 父级code，默认为0 非必填
     ** @apiSuccessExample {json} SuccessExample
     * {
    "msg": "success",
    "code": 200,
    "data": [
    {
    "id": 1,
    "name": "北京市",
    "code": 11
    },
    {
    "id": 358,
    "name": "天津市",
    "code": 12
    },
    {
    "id": 676,
    "name": "河北省",
    "code": 13
    }]
     }
     **/

    public function area(RequestInterface $request)
    {
        $pcode = $request->input('pcode', 0);
        $list = Area::where('pcode', $pcode)->select('id', 'name', 'code')->get();
        return jsonSuccess($list);
    }

    /***
     ** @api {get} api/common/addressList 获取会员地址
     ** @apiName 获取会员地址
     ** @apiGroup 公共
     ** @apiHeader {string} sign sign值  必填
     ** @apiSuccessExample {json} SuccessExample
     * {
    "msg": "success",
    "code": 200,
    "data": [
    {
    "id": 412,
    "member_id": 5418136,
    "vote_member_id": 0,
    "province": "福建省",
    "province_code": "35",
    "city": "厦门市",
    "city_code": "3502",
    "district_code": "350205",
    "district": "海沧区",
    "street_code": "350205003",
    "street": "嵩屿街道",
    "address": "甚至导致的",
    "receive_username": "设置地址",
    "receive_mobile": "18588499342",
    "is_default": 0,
    "add_time": "2021-09-06 17:08:41",
    "deleted_at": null,
    "source": ""
    },
    {
    "id": 413,
    "member_id": 5418136,
    "vote_member_id": 0,
    "province": "广东省",
    "province_code": "44",
    "city": "深圳市",
    "city_code": "4403",
    "district_code": "440307",
    "district": "龙岗区",
    "street_code": "440307011",
    "street": "南澳街道",
    "address": "很熟社会回国后发货",
    "receive_username": "清洗地址",
    "receive_mobile": "18588499342",
    "is_default": 1,
    "add_time": "2021-09-06 17:17:29",
    "deleted_at": null,
    "source": ""
    },
    {
    "id": 414,
    "member_id": 5418136,
    "vote_member_id": 0,
    "province": "西藏自治区",
    "province_code": "54",
    "city": "昌都市",
    "city_code": "5403",
    "district_code": "540323",
    "district": "类乌齐县",
    "street_code": "540323200",
    "street": "甲桑卡乡",
    "address": "发一份一次一次杨超越",
    "receive_username": "积分商城",
    "receive_mobile": "18588499342",
    "is_default": 0,
    "add_time": "2021-09-06 17:24:12",
    "deleted_at": null,
    "source": ""
    },
    {
    "id": 416,
    "member_id": 5418136,
    "vote_member_id": 0,
    "province": "天津市",
    "province_code": "12",
    "city": "市辖区",
    "city_code": "1201",
    "district_code": "120101",
    "district": "和平区",
    "street_code": "120101001",
    "street": "劝业场街道",
    "address": "V型不会喜欢回电话",
    "receive_username": "设置地址",
    "receive_mobile": "18588499342",
    "is_default": 0,
    "add_time": "2021-09-06 17:40:59",
    "deleted_at": null,
    "source": ""
    },
    {
    "id": 417,
    "member_id": 5418136,
    "vote_member_id": 0,
    "province": "吉林省",
    "province_code": "22",
    "city": "吉林市",
    "city_code": "2202",
    "district_code": "220203",
    "district": "龙潭区",
    "street_code": "220203002",
    "street": "湘潭街道",
    "address": "关注下就是",
    "receive_username": "清洗二",
    "receive_mobile": "18588499342",
    "is_default": 0,
    "add_time": "2021-09-06 17:42:07",
    "deleted_at": null,
    "source": ""
    },
    {
    "id": 418,
    "member_id": 5418136,
    "vote_member_id": 0,
    "province": "山西省",
    "province_code": "14",
    "city": "大同市",
    "city_code": "1402",
    "district_code": "140213",
    "district": "平城区",
    "street_code": "140213002",
    "street": "北关街道",
    "address": "V型不会喜欢茹茹等会等会超级鸡车吃鸡吃鸡鸡超级飞",
    "receive_username": "积分二",
    "receive_mobile": "18588499342",
    "is_default": 0,
    "add_time": "2021-09-06 17:47:54",
    "deleted_at": null,
    "source": ""
    },
    {
    "id": 423,
    "member_id": 5418136,
    "vote_member_id": 0,
    "province": "青海省",
    "province_code": "63",
    "city": "海北藏族自治州",
    "city_code": "6322",
    "district_code": "632222",
    "district": "祁连县",
    "street_code": "632222102",
    "street": "默勒镇",
    "address": "擦地址我",
    "receive_username": "测试三",
    "receive_mobile": "18588499342",
    "is_default": 0,
    "add_time": "2021-09-08 18:56:24",
    "deleted_at": null,
    "source": ""
    }
    ]
    }
     **/
    public function addressList()
    {
        $member_id = auth('api')->id();
        $list = ShopReceiveAddress::where('member_id', $member_id)->get();
        return jsonSuccess($list);
    }



}
