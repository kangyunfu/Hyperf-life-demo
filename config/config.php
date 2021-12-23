<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
use Hyperf\Contract\StdoutLoggerInterface;
use Psr\Log\LogLevel;
use Hyperf\HttpServer\Contract\RequestInterface;

return [
    'app_name' => env('APP_NAME', 'xfb'),
    'app_env' => env('APP_ENV', 'dev'),
    'scan_cacheable' => env('SCAN_CACHEABLE', false),
    StdoutLoggerInterface::class => [
        'log_level' => [
            LogLevel::ALERT,
            LogLevel::CRITICAL,
//            LogLevel::DEBUG,
            LogLevel::EMERGENCY,
            LogLevel::ERROR,
            LogLevel::INFO,
            LogLevel::NOTICE,
            LogLevel::WARNING,
        ],
    ],
    'admin_config' => include ("app/Admin/Config/config.php"),
    'api_config'   => include ("app/Api/Config/config.php"),
    'merchant_config'   => include ("app/Merchant/Config/config.php"),

    //又拍云配置
    'upyun_bucketname' => "xiaofeibao",
    'upyun_operator_name' => "xiaofeibao",
    'upyun_operator_pwd' =>"aBxXeXq8D5lEfXAkxfjtmWNBu",
    'upyun_domain' => "https://img.xfb315.com",
];
