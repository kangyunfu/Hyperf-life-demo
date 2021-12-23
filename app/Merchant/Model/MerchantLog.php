<?php

declare (strict_types=1);
namespace App\Merchant\Model;


class MerchantLog extends Model
{
    protected $table = 'merchant_log';
    protected $dateFormat = 'U'; // 时间戳
    const UPDATED_AT = null; // 无更新时间
    public static function addData($info = '', $type = 1, $ip = '', $name = '')
    {
        $data = [
            'merchant_id' => auth('merchant')->id(),
            'merchant_name' => $name,
            'type'        => $type,
            'info'        => $info,
            'ip_address'  => $ip,
            'created_at'  => time()
        ];
        return self::insert($data);
    }

}