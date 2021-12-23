<?php

declare (strict_types=1);
namespace App\Merchant\Model;

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
        return $this->hasMany('App\Admin\Model\ShopAttributeMenu', 'id', 'attribute_menu_id');
    }

    /**
     * 获取属性
     * @return \Hyperf\Database\Model\Relations\hasMany
     */
    public static function getShopAttribute($attribute_ids) {
        return \App\Admin\Model\ShopAttribute::whereRaw('FIND_IN_SET(id,?)',[$attribute_ids])->select('id','attribute_menu_id', 'name', 'desc', 'sort')->get();
    }

    /**
     * 获取服务区域
     * @return \Hyperf\Database\Model\Relations\hasMany
     */
    public static function getShopServiceArea($service_area_codes) {
        return \App\Common\Model\Area::whereRaw('FIND_IN_SET(code,?)',[$service_area_codes])->select('name','code','pcode','level')->get();
    }

    public function getServiceTypeAttribute($value){
        $arr = explode(',', $value);
        $rArr = [];
        foreach ($arr as $v) {
            $rArr[] = isset(config('merchant_config.serviceType')[$v]) ? config('merchant_config.serviceType')[$v] : $v;
        }
        return $rArr;
//        return isset(config('merchant_config.serviceType')[$value]) ? config('merchant_config.serviceType')[$value]:$value;
    }



}