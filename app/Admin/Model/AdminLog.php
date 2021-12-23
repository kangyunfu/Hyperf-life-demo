<?php

declare (strict_types=1);
namespace App\Admin\Model;

/**
 */
class AdminLog extends Model
{
    protected $table = 'admin_log';
    protected $dateFormat = 'U'; // 时间戳
    const UPDATED_AT = null; // 无更新时间
    public static function addData($info = '', $type = 1, $ip = '')
    {
        $data = [
            'admin_id' => auth('admin')->id(),
            'type' => $type,
            'info' => $info,
            'ip_address' => $ip,
            'created_at' => time()
        ];
        return self::insert($data);
    }

}