<?php

declare (strict_types=1);
namespace App\Admin\Model;

use HyperfExt\Auth\Authenticatable;
use HyperfExt\Auth\Contracts\AuthenticatableInterface;
use HyperfExt\Jwt\Contracts\JwtSubjectInterface;

/**
 */
class AdminRoles extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'admin_roles';
}