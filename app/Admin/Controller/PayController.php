<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace App\Admin\Controller;

use App\Admin\Model\MemberBill;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Utils\ApplicationContext;
use App\Common\Wechat;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Di\Annotation\Inject;


class PayController extends AdminBaseController
{
    /**
     * 提供了对 JWT 编解码、刷新和失活的能力。
     *
     * @var \HyperfExt\Jwt\Contracts\ManagerInterface
     */
    protected $manager;
    /**
     * 提供了从请求解析 JWT 及对 JWT 进行一系列相关操作的能力。
     *
     * @var \HyperfExt\Jwt\Jwt
     */
    protected $jwt;

    /**
     * @Inject()
     * @var ValidatorFactoryInterface
     */
    protected $validationFactory;












    // 提现打款
    public function toBalance(RequestInterface $request, ResponseInterface $response)
    {
        try {
            $app = Wechat::payByOfficialAccount();
            $res = $app->transfer->toBalance([
                'partner_trade_no' => '1233455', // 商户订单号，需保持唯一性(只能是字母或者数字，不能包含有符号)
                'openid' => 'oxTWIuGaIt6gTKsQRLau2M0yL16E',
                'check_name' => 'FORCE_CHECK', // NO_CHECK：不校验真实姓名, FORCE_CHECK：强校验真实姓名
                're_user_name' => '王小帅', // 如果 check_name 设置为FORCE_CHECK，则必填用户真实姓名
                'amount' => 1000, // 企业付款金额，单位为分
                'desc' => '提现打款', // 企业付款操作说明信息。必填
            ]);
            if ($res['return_code'] == 'SUCCESS') {
                if ($res['result_code'] == 'SUCCESS') {
                    // 更新状态

                }
            } else {
                return jsonError($res['return_msg'], 405);
            }
        } catch (\Exception $exception) {
            return jsonError('打款失败' . $exception->getMessage(), 405);
        }

    }
}
