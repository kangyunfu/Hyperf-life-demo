<?php

declare (strict_types=1);
namespace App\Api\Model;


class ShopDevice extends Model
{

    protected $table = 'shop_device';

    /**
     * 关联检测项目
     * @return \Hyperf\Database\Model\Relations\hasMany
     */
    public function shopDeviceCheckItem() {
        return $this->hasMany('App\Api\Model\ShopDeviceCheckItem', 'device_id', 'id');
    }

    /**
     * 关联异常项目
     * @return \Hyperf\Database\Model\Relations\hasMany
     */
    public function shopDeviceFault() {
        return $this->hasMany('App\Api\Model\ShopDeviceFault', 'device_id', 'id');
    }

}