<?php
/**
 * Create By PhpStorm
 * 作者 Bonjour<1051953562@qq.com>
 * 日期 2021/5/22
 * 时间 11:45
 */

namespace App\Common;

use EasyWeChat\Factory;
use Hyperf\Guzzle\CoroutineHandler;

class Wechat
{
    /**
     * 通过来源获取App实例
     * @param string $name
     * @return \EasyWeChat\MiniProgram\Application|\EasyWeChat\OfficialAccount\Application|false
     */
    public static function getAppFactory($name = '')
    {
        switch ($name) {
            case 'wx':
                $app = self::officialAccount();
                break;
            case 'miniprogram':
                $app = self::miniprogram();
                break;
            default:
                $app = false;
                break;
        }
        return $app;
    }

    /**
     * 通过来源获取支付实例
     * @param string $name
     * @return \EasyWeChat\Payment\Application|false
     */
    public function getPayFactory($name = '')
    {
        switch ($name) {
            case 'h5':
                $app = self::payByH5();
                break;
            case 'wx':
                $app = self::payByOfficialAccount();
                break;
            case 'miniprogram':
                $app = self::payByMiniprogram();
                break;
            case 'ios':
                $app = self::payByIos();
//                $app = $this->payByIos();
                break;
            case 'android':
                $app = self::payByAndroid();
                break;
            default:
                $app = false;
                break;
        }
        return $app;
    }


    // 公众号
    public static function officialAccount()
    {
        $config = [
            'app_id' => 'wx2fd062a34e83ab56',
            'secret' => '24f0be043beed337125d0142daaf8a4e',
        ];
        return Factory::officialAccount($config);
//        $app = Factory::miniProgram($config);
//        $app['guzzle_handler'] = CoroutineHandler::class;
//        return $app;
    }


    // 订阅号
    public static function subscribe()
    {
        $config = [
            'app_id' => 'wxad35e95396a84371',
            'secret' => 'f1eaf8aaaa77b131edf14a2a935ea1e0',
        ];
//        return Factory::officialAccount($config);
        $app = Factory::miniProgram($config);
        $app['guzzle_handler'] = CoroutineHandler::class;
        return $app;

    }

    // 小程序
    public static function miniprogram()
    {
        $config = [
            'app_id' => env('WECHAT_MINIP_APP_ID', 'wx899e26f0d5e313c0'),
            'secret' => env('WECHAT_MINIP_APP_SECRET', '323a92d872103cf61ca5f913df9a0227'),
        ];
//        return Factory::miniProgram($config);
        $app = Factory::miniProgram($config);
        $app['guzzle_handler'] = CoroutineHandler::class;
        return $app;
    }

    // h5微信支付
    public static function payByH5()
    {
        $config = [
            'app_id' => 'wx2fd062a34e83ab56',
            'mch_id' => '1529117901',
            'key' => env('WECHAT_PAY_KEY', '4dfae6a47b95d455229d2e62979e20e0'),
            'cert_path' => BASE_PATH . '/app/Common/keys/apiclient_cert.pem',
            'key_path' => BASE_PATH . '/app/Common/keys/apiclient_key.pem',
            'notify_url' => '回调地址'
        ];

        $app = Factory::payment($config);
        $app['guzzle_handler'] = CoroutineHandler::class;
        return $app;
    }

    // 微信公众号支付
    public static function payByOfficialAccount()
    {
        $config = [
            'app_id' => 'wx2fd062a34e83ab56',
            'mch_id' => env('WECHAT_PAY_MCH_ID', '1529117901'),
            'key' => env('WECHAT_PAY_KEY', '4dfae6a47b95d455229d2e62979e20e0'),
            'cert_path' => BASE_PATH . '/app/Common/keys/apiclient_cert.pem',
            'key_path' => BASE_PATH . '/app/Common/keys/apiclient_key.pem',
            'notify_url' => '回调地址'
        ];

//        return Factory::payment($config);
        $app = Factory::payment($config);
        $app['guzzle_handler'] = CoroutineHandler::class;
        return $app;
    }

    //微信小程序支付
    public static function payByMiniprogram()
    {
        $config = [
            'app_id' => 'wx899e26f0d5e313c0',
            'mch_id' => env('WECHAT_PAY_MCH_ID', '1529117901'),
            'key' => env('WECHAT_PAY_KEY', '4dfae6a47b95d455229d2e62979e20e0'),
            'cert_path' => BASE_PATH . '/app/Common/keys/apiclient_cert.pem',
            'key_path' => BASE_PATH . '/app/Common/keys/apiclient_key.pem',
            'notify_url' => '回调地址'
        ];

//        return Factory::payment($config);
        $app = Factory::payment($config);
        $app['guzzle_handler'] = CoroutineHandler::class;
        return $app;
    }

    // 安卓微信支付
    public static function payByAndroid()
    {
        $config = [
            'app_id' => env('WECHAT_PAY_APP_ID_ANDROID', 'wxf81b73ff95b94627'),
            'mch_id' => env('WECHAT_PAY_MCH_ID', '1529117901'),
            'key' => env('WECHAT_PAY_KEY', '4dfae6a47b95d455229d2e62979e20e0'),
            'cert_path' => BASE_PATH . '/app/Common/keys/apiclient_cert.pem',
            'key_path' => BASE_PATH . '/app/Common/keys/apiclient_key.pem',
            'notify_url' => '回调地址'
        ];
//        return Factory::payment($config);
        $app = Factory::payment($config);
        $app['guzzle_handler'] = CoroutineHandler::class;
        return $app;
    }

    // Ios微信支付
    public static function payByIos()
    {
        $config = [
            'app_id' => env('WECHAT_PAY_APP_ID_IOS', 'wx52efb1e3bc0d2f3b'),
            'mch_id' => env('WECHAT_PAY_MCH_ID', '1529117901'),
            'key' => env('WECHAT_PAY_KEY', '4dfae6a47b95d455229d2e62979e20e0'),
            'cert_path' => BASE_PATH . '/app/Common/keys/apiclient_cert.pem',
            'key_path' => BASE_PATH . '/app/Common/keys/apiclient_key.pem',
            'notify_url' => '回调地址'
        ];
        // return Factory::payment($config);
        $app = Factory::payment($config);
        $app['guzzle_handler'] = CoroutineHandler::class;
        return $app;

//        return $this->factory::payment($config);
    }

}