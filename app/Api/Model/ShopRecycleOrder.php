<?php

declare (strict_types=1);
namespace App\Api\Model;


/**
 */
class ShopRecycleOrder extends Model
{
    //protected $dateFormat = 'U';
    protected $table = 'shop_recycle_order';

    /**
     * 关联子订单
     * @return \Hyperf\Database\Model\Relations\hasMany
     */
    public function recycleOrderSub() {
        return $this->hasMany('App\Api\Model\ShopRecycleOrderSub', 'order_id', 'id');
    }

    /**
     * 关联订单日志
     * @return \Hyperf\Database\Model\Relations\hasMany
     */
    public function shopOrderLog() {
        return $this->hasMany('App\Common\Model\ShopOrderLog', 'order_id', 'id');
    }

    /**
     * 关联订单物流信息
     * @return \Hyperf\Database\Model\Relations\hasMany
     */
    public function shopOrderExpress() {
        return $this->hasMany('App\Api\Model\ShopOrderExpress', 'order_id', 'id');
    }


    /**
     * 关联订设备信息、验机纪录
     * @return \Hyperf\Database\Model\Relations\hasMany
     */
    public function shopDevice() {
        return $this->hasMany('App\Api\Model\ShopDevice', 'order_id', 'id');
    }




}