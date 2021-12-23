<?php

declare (strict_types=1);
namespace App\Admin\Model;

use Hyperf\Database\Model\SoftDeletes;

/**
 */
class ShopCategory extends Model
{
    use SoftDeletes;

    // protected $dateFormat = 'U';
    protected $table = 'shop_category';
    protected $fillable = [];
    protected $casts = [];

    //获取分类
    public static function getParentCate($pid = 0)
    {
        return self::where('pid', $pid)->select('id', 'cat_name', 'pid')->get();
    }

    //获取下级分类的所有ID集合
    public static function getChildIds($pid = 0)
    {
        return self::where('pid', $pid)->pluck('id');
    }

    //获取下级分类的所有ID 和 name集合
    public static function getChilds($pid = 0)
    {
        return self::where('pid', $pid)->pluck('cat_name','id');
    }

}