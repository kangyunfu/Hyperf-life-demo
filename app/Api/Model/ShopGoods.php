<?php

declare (strict_types=1);
namespace App\Api\Model;

/**
 */
class ShopGoods extends Model
{
    //protected $dateFormat = 'U';
    protected $table = 'shop_goods';

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

    /**
     * 关联规格
     * @return \Hyperf\Database\Model\Relations\hasMany
     */
    public function shopAttributeMenu() {
        return $this->belongsTo('App\Admin\Model\ShopAttributeMenu', 'attribute_menu_id', 'id');
    }

    /**
     * 获取属性
     * @return \Hyperf\Database\Model\Relations\hasMany
     */
    public static function getShopAttribute($attribute_ids) {
        return \App\Admin\Model\ShopAttribute::whereRaw('FIND_IN_SET(id,?)',[$attribute_ids])->select('id','attribute_menu_id','name', 'desc','sort')->get();
    }

    /**
     * 获取服务区域
     * @return \Hyperf\Database\Model\Relations\hasMany
     */
    public static function getShopServiceArea($service_area_codes) {
        return \App\Common\Model\Area::whereRaw('FIND_IN_SET(code,?)',[$service_area_codes])->select('name','code','pcode','level')->get();
    }

    public function getServiceTypeAttribute($value){
        return isset(config('merchant_config.serviceType')[$value]) ? config('merchant_config.serviceType')[$value]:$value;
    }

    /**
     * 根据分类和品牌取商品的属性集合
     * @return \Hyperf\Database\Model\Relations\hasMany
     */
    public static function getGoodsAttribute($category_id,$brand_id) {
        $attribute_ids = self::where('status', 1)
            ->where('deleted_at', null)
            ->where('category_id', $category_id)
            ->where('brand_id', $brand_id)
            ->pluck('attribute_ids');
        $str = '';
        $arr = [];
        if ($attribute_ids) {
            foreach ($attribute_ids as $attribute_id) {
                $str .= $attribute_id . ',';
            }
            $arr = $str ? explode(',', $str) : [];
        }
        return $arr;
    }


}