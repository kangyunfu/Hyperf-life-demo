<?php

declare (strict_types=1);
namespace App\Api\Model;

/**
 */
class ShopAttributeMenu extends Model
{
    //protected $dateFormat = 'U';
    protected $table = 'shop_attribute_menu';

    /**
     * 关联属性
     * @return \Hyperf\Database\Model\Relations\hasMany
     */
    public function shopAttribute() {
        return $this->hasMany('App\Admin\Model\ShopAttribute', 'attribute_menu_id', 'id');
    }

    /**
     * 关联品牌
     * @return \Hyperf\Database\Model\Relations\hasMany
     */
    public function shopBrand() {
        return $this->belongsTo('App\Admin\Model\ShopBrand', 'brand_id', 'id');
    }

    /**
     * 关联分类
     * @return \Hyperf\Database\Model\Relations\hasMany
     */
    public function shopCategory() {
        return $this->belongsTo('App\Admin\Model\ShopCategory', 'category_id', 'id');
    }

}