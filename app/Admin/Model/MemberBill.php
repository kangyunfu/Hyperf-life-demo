<?php

declare (strict_types=1);
namespace App\Admin\Model;


class MemberBill extends Model
{
    protected $table = 'member_bill';


    public function getCashAtAttribute()
    {
        if (is_numeric($this->attributes['cash_at'])) {
            return date('Y-m-d H:i:s', $this->attributes['cash_at']);
        } else {
            return  '';
        }
    }

    public function getPaymentTimeAttribute()
    {
        if (is_numeric($this->attributes['payment_time'])) {
            return date('Y-m-d H:i:s', $this->attributes['payment_time']);
        } else {
            return '';
        }
    }

}