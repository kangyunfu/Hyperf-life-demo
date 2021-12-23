<?php

use Upyun\Upyun;
use Upyun\Config;
if (!function_exists('auth')) {
    /**
     * Auth认证辅助方法
     * @param string|null $guard
     * @return \HyperfExt\Auth\Contracts\GuardInterface|\HyperfExt\Auth\Contracts\StatefulGuardInterface|\HyperfExt\Auth\Contracts\StatelessGuardInterface
     */
    function auth(string $guard = null)
    {
        if (is_null($guard)) $guard = config('auth.default.guard');
        return make(\HyperfExt\Auth\Contracts\AuthManagerInterface::class)->guard($guard);
    }
}

if (!function_exists('getRandChar')) {
    /**
     * 获取随机字符串
     *
     * @param int $len 获取长度
     * @return string
     */
    function getRandChar($len = 6)
    {
        $str = '';
        $rand = 'qwertyuiopasdfghjklzxcvbnm1234567890';
        for ($i = 0; $i < $len; $i++) {
            $str .= $rand[rand(0, strlen($rand) - 1)];
        }
        return $str;
    }
}

if (!function_exists('jsonSuccess')) {
    /**
     * 请求成功
     *
     * @param        $data
     * @param string $message
     *
     * @return array
     */
    function jsonSuccess($data = null, $message = '操作成功', $code = 200)
    {
        if (empty($data)) $data = '';
        //$code = $this->response->getStatusCode();
        return ['msg' => $message, 'code' => $code, 'data' => $data];
    }
}


if (!function_exists('jsonError')) {
    /**
     * 请求失败.
     *
     * @param string $message
     *
     * @return array
     */
    function jsonError($message = 'Request format error!', $code = 400)
    {
        //$code = $this->response->getStatusCode();
        return ['msg' => $message, 'code' => $code, 'data' => null];
    }
}


if (!function_exists('getClientIp')) {
    /**
     * 获取客户端ip地址
     * @return mixed
     */
    function getClientIp()
    {
        $res = make(Hyperf\HttpServer\Contract\RequestInterface::class)->getServerParams();
        if (isset($res['http_client_ip'])) {
            return $res['http_client_ip'];
        } elseif (isset($res['http_x_real_ip'])) {
            return $res['http_x_real_ip'];
        } elseif (isset($res['http_x_forwarded_for'])) {
            //部分CDN会获取多层代理IP，所以转成数组取第一个值
            $arr = explode(',', $res['http_x_forwarded_for']);
            return $arr[0];
        } else {
            return $res['remote_addr'];
        }
    }
}




if (!function_exists('uploadUpyun')) {
    /* 上传又拍云
    * @param $localFile
    * @param $upyunFile
    * @return string
    */
    function uploadUpyun($localFile, $upyunFile)
    {
        $serviceConfig = new Config(config('upyun_bucketname'), config('upyun_operator_name'),
            config('upyun_operator_pwd'));
        $client = new Upyun($serviceConfig);
        try {
            $fh = @fopen($localFile, 'rb');
            $rsp = @$client->write($upyunFile, $fh, true);   // 上传图片，自动创建目录
            @fclose($fh);
            $file = config('upyun_domain') . $upyunFile;
        } catch (Exception $e) {
            $file = trim($localFile, '.');
        }
        return $file;
    }
}


if (!function_exists('setMerchantKey')) {
    /**
     * 商户秘钥
     *
     * @param string $message
     *
     * @return string
     */
    function setMerchantKey($prefx = 'xfb', $salt = 'xfb123')
    {
       return md5($prefx.$salt.time());
    }
}


if (!function_exists('createOrderSn')) {
    /**
     * 创建订单号
     * @param string $prefix
     * @return string
     */
    function createOrderSn($prefix = 'XFB')
    {
        return $prefix . date('YmdHis') . explode('.', uniqid('', true))[1];
    }
}

