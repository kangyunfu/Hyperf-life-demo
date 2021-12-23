<?php
declare (strict_types=1);
namespace App\Admin\Model;

use HyperfExt\Auth\Authenticatable;
use HyperfExt\Auth\Contracts\AuthenticatableInterface;
use HyperfExt\Jwt\Contracts\JwtSubjectInterface;

/**
 */
class Admin extends Model implements AuthenticatableInterface ,JwtSubjectInterface
{
    use Authenticatable;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'admin';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [];
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [];


    const UPDATED_AT = null;

    public function getJwtIdentifier()
    {
        return $this->getKey();
    }

    /**
     * JWT自定义载荷
     * @return array
     */
    public function getJwtCustomClaims(): array
    {
        return [
            'guard' => 'admin'    // 添加一个自定义载荷保存守护名称，方便后续判断
        ];
    }

    /**
     * 关联角色
     * @return \Hyperf\Database\Model\Relations\BelongsTo
     */
    public function role() {
        return $this->belongsTo('App\Admin\Model\AdminRoles', 'role_id', 'id');
    }


    public function getAddTimeAttribute()
    {
        return date('Y-m-d H:i:s', $this->attributes['add_time']);
    }

    public function getLastTimeAttribute()
    {
        return date('Y-m-d H:i:s', $this->attributes['last_time']);
    }

}