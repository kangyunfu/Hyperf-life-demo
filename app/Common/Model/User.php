<?php

declare (strict_types=1);
namespace App\Common\Model;

use HyperfExt\Auth\Authenticatable;
use HyperfExt\Auth\Contracts\AuthenticatableInterface;
use HyperfExt\Jwt\Contracts\JwtSubjectInterface;

/**
 */
class User extends Model implements AuthenticatableInterface ,JwtSubjectInterface
{
    use Authenticatable;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users';
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
            'guard' => 'api'    // 添加一个自定义载荷保存守护名称，方便后续判断
        ];
    }
}