<?php

declare (strict_types=1);
namespace App\Api\Model;

/**
 */
class ShopRecycleOrderSub extends Model
{
    //protected $dateFormat = 'U';
    protected $table = 'shop_recycle_order_sub';

    /**
     * 关联规格
     * @return \Hyperf\Database\Model\Relations\hasMany
     */
    public function shopAttributeMenu() {
        return $this->belongsTo('App\Admin\Model\ShopAttributeMenu', 'attribute_menu_id', 'id');
    }


}