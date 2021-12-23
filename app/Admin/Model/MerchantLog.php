<?php

declare (strict_types=1);
namespace App\Admin\Model;

class MerchantLog extends Model
{
    protected $table = 'merchant_log';
//    protected $dateFormat = 'U'; // 时间戳
    const UPDATED_AT = null; // 无更新时间
}