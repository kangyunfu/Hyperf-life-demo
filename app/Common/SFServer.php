<?php

namespace App\Common;

use Hyperf\Utils\ApplicationContext;

class SFServer
{

    private $partnerID = "SWT72fKkJ";//此处替换为您在丰桥平台获取的顾客编码
    private $checkword = "vplUhaMSn35P05byuPmKRRZYEjMO2LJS";//此处替换为您在丰桥平台获取的校验码
    private $mark = [
        "EXP_RECE_CREATE_ORDER",        //  下订单
        "EXP_RECE_SEARCH_ORDER_RESP",   //  订单结果查询
        "EXP_RECE_UPDATE_ORDER",        //  订单确认取消
    ];

    public function createOrder($info = [], $code = 0)
    {
//        $file = config('common_config.EXP_RECE_CREATE_ORDER')['file'];

//        $msgData = file_get_contents($file);//读取文件内容

        // 没有数据 顺丰操作失败
        if (!$info) return false;

        $msgData = $this->makeDate($info, $code);//return $msgData;
        $msgData = json_encode($msgData, true);//return $msgData;
        $requestID = $this->create_uuid();

        //获取时间戳
        $timestamp = time();

        //通过MD5和BASE64生成数字签名
        $msgDigest = base64_encode(md5((urlencode($msgData . $timestamp . $this->checkword)), TRUE));

        //发送参数
        $post_data = array(
            'partnerID' => $this->partnerID,
            'requestID' => $requestID,
            'serviceCode' => $this->mark[0],
            'timestamp' => $timestamp,
            'msgDigest' => $msgDigest,
            'msgData' => $msgData
        );

        //沙箱环境的地址
        $CALL_URL_BOX = "http://sfapi-sbox.sf-express.com/std/service";
        //生产环境的地址
        $CALL_URL_PROD = "https://sfapi.sf-express.com/std/service";

        $resultCont = $this->send_post($CALL_URL_BOX, $post_data); //沙盒环境
//        return $resultCont;
        return json_decode($resultCont, true);
        // print_r(); //提示重复下单请修改json文件内对应orderid参数
    }


    //获取UUID
    function create_uuid()
    {
        $chars = md5(uniqid(mt_rand(), true));
        $uuid = substr($chars, 0, 8) . '-'
            . substr($chars, 8, 4) . '-'
            . substr($chars, 12, 4) . '-'
            . substr($chars, 16, 4) . '-'
            . substr($chars, 20, 12);
        return $uuid;
    }


    //POST
    function send_post($url, $post_data)
    {

        $postdata = http_build_query($post_data);
        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => 'Content-type:application/x-www-form-urlencoded;charset=utf-8',
                'content' => $postdata,
                'timeout' => 15 * 60 // 超时时间（单位:s）
            )
        );
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);

        return $result;
    }

    function makeDate($info, $code)
    {
        /// 下单
        $b = [
            "cargoDetails" => [
                "amount" => $info['orderInfo']['price'] ?? 0,
                "count" =>  $info['goodNum'] ?? 0,
                "name" => $info['name'] ?? '',
            ],
            "contactInfoList" => [
                [
                    "address" => $info['orderInfo']['address'],
                    "city" => $info['orderInfo']['city'],
                    "company" => $info['orderInfo']['contact'],
                    "contact" => $info['orderInfo']['contact'],
                    "contactType" => 1,  //寄件方信息
                    "county" => $info['orderInfo']['district'],
                    "tel" => $info['orderInfo']['phone'],
                    "mobile" => $info['orderInfo']['phone'],
                    "province" => $info['orderInfo']['province']
                ],
                [
                    "address" => $info['merchant']['address'],
                    "city" => $info['merchant']['city'],
                    "contact" => $info['merchant']['name'],
                    "county" => $info['merchant']['district'],
                    "contactType" => 2,     //到件方信息
                    "tel" => $info['merchant']['mobile'],
                    "mobile" => $info['merchant']['mobile'],
                    "province" => $info['merchant']['province']
                ]
            ],
            "isOneselfPickup" => 0, //  快件自取  1：客户同意快件自取  0：客户不同意快件自取
            "language" => "zh-CN",
            "orderId" => $info['orderInfo']['order_sn'],
            "parcelQty" => 1,       //  包裹数，一个包裹对应一个运单号
            "payMethod" => 1,   //付款方式  1:寄方付    2:收方付    3:第三方付
            "totalWeight" => 6
        ];
        $d = [
            "cargoDetails" => [
                "amount" => 0,
                "count" =>  0,
                "name" => 666,
            ],
            "contactInfoList" => [
                [
                    "address" => 111,
                    "city" => 111,
                    "company" => 111,
                    "contact" => 111,
                    "contactType" => 1,  //寄件方信息
                    "county" => 111,
                    "tel" => 111,

                    "province" => 111
                ],
                [
                    "address" => 111,
                    "city" => 111,
                    "contact" => 111,
                    "county" => 111,
                    "contactType" => 2,     //到件方信息
                    "tel" => 111,

                    "province" => 111
                ]
            ],
            "isOneselfPickup" => 0, //  快件自取  1：客户同意快件自取  0：客户不同意快件自取
            "language" => "zh-CN",
            "orderId" => 111111,
            "parcelQty" => 1,       //  包裹数，一个包裹对应一个运单号
            "payMethod" => 1,   //付款方式  1:寄方付    2:收方付    3:第三方付
            "totalWeight" => 6
        ];


        /// 取消订单
        $c = [
            "dealType"=> 2,  //   1:确认  2:取消
            "orderId"=> $info['orderInfo']['order_sn']
        ];
        return $b;
    }
}