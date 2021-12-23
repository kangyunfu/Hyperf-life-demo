<?php
declare(strict_types=1);

use App\Api\Model\MemberXFB;

return [
    'default' => [
        'guard' => 'api',    // 默认接口api守护
        'passwords' => 'users',
    ],
    'guards' => [
        'web' => [
            'driver' => \HyperfExt\Auth\Guards\SessionGuard::class,
            'provider' => 'users',
            'options' => [],
        ],
        // 接口api守护
        'api' => [
            'driver' => \HyperfExt\Auth\Guards\JwtGuard::class,
            'provider' => 'api',
            'options' => [],
        ],
        // 管理端admin守护
        'admin' => [
            'driver' => \HyperfExt\Auth\Guards\JwtGuard::class,
            'provider' => 'admin',
            'options' => [],
        ],
        // 商户merchant守护
        'merchant' => [
            'driver' => \HyperfExt\Auth\Guards\JwtGuard::class,
            'provider' => 'merchant',
            'options' => [],
        ],
    ],
    'providers' => [
        'api' => [
            'driver' => \HyperfExt\Auth\UserProviders\ModelUserProvider::class,
            'options' => [
                'model' => \App\Api\Model\MemberXFB::class,    // 用户模型
                'hash_driver' => 'bcrypt',
            ],
        ],

        'admin' => [
            'driver' => \HyperfExt\Auth\UserProviders\ModelUserProvider::class,
            'options' => [
                'model' => \App\Admin\Model\Admin::class,    // 管理员模型
                'hash_driver' => 'bcrypt',
            ],
        ],
        'merchant' => [
            'driver' => \HyperfExt\Auth\UserProviders\ModelUserProvider::class,
            'options' => [
                'model' => \App\Merchant\Model\Merchant::class,    // 商户模型
                'hash_driver' => 'bcrypt',
            ],
        ]
    ],
    'passwords' => [
        'users' => [
            'driver' => \HyperfExt\Auth\Passwords\DatabaseTokenRepository::class,
            'provider' => 'users',
            'options' => [
                'connection' => null,
                'table' => 'password_resets',
                'expire' => 86400,
                'throttle' => 60,
                'hash_driver' => null,
            ],
        ],
    ],
    'password_timeout' => 43200,
    'policies' => [
        //Model::class => Policy::class,
    ],
];