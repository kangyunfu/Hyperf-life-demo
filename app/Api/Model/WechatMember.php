<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Api\Model;

/**
 * @property int $id
 * @property string $mobile
 * @property string $realname
 */
class WechatMember extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'wechat_member';

    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'xfb';

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
}
