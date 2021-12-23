<?php
declare(strict_types=1);

namespace App\Merchant\Controller;

use App\Common\RedisServer;
use App\Common\SFServer;
use App\Merchant\Model\Merchant;
use App\Merchant\Model\ShopDevice;
use App\Merchant\Model\ShopOrderExpress;
use App\Merchant\Model\ShopOrderLog;
use App\Merchant\Model\ShopRecycleOrder;
use App\Merchant\Model\ShopRecycleOrderSub;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use HyperfExt\Jwt\Contracts\JwtFactoryInterface;
use HyperfExt\Jwt\Contracts\ManagerInterface;
use Hyperf\DbConnection\Db;


class XxxController extends MerchantBaseController
{

    /**
     * 注入 SF
     * @Inject
     * @var SFServer
     */
    private $SFServer;

    public function index(RequestInterface $request)
    {
        $orderInfo = ShopRecycleOrder::where('id', 12)
            ->select('id','order_sn','member_id','member_name','mobile','member_real_name','price','recycle_price','merchant_id','merchant_name','merchant_account','contact','phone','province','city','district','street','address')
            ->first();
        if (!$orderInfo['id']) return jsonError('找不到订单，操作失败', 405);

        $orderInfoSub = ShopRecycleOrderSub::where('order_id', $orderInfo['id'])
            ->select('goods_num', 'attribute')
            ->get();
        $merchant = Merchant::where('id', $orderInfo['merchant_id'])
            ->select('province','city','district','street','address','name','mobile')
            ->first();

        $name = '';
        $goodNum = 0;
        foreach ($orderInfoSub as &$v) {
            $goodNum += $v['goods_num'];
            if ($v['attribute']) {
                $att = json_decode($v['attribute'], true);
                foreach ($att as $at) {
                    $name .= $at['name'];
                }
            }
        }

        $data['orderInfo']    = $orderInfo;
        $data['orderInfoSub'] = $orderInfoSub;
        $data['merchant']     = $merchant;
        $data['name']         = $name;
        $data['goodNum']      = $goodNum;

        $a = $this->SFServer->createOrder($data, 0);
        $result = json_decode($a['apiResultData'], true);
        if (!$result['success']) {
            return jsonError($result['errorMsg']);
        }
        $waybillNo = '';

        Db::beginTransaction();
        try {
            foreach ($result['msgData']['waybillNoInfoList'] as $v) {
                if ($v['waybillNo']) {
                    ShopOrderExpress::insert([
                       'type'           => 3,
                       'order_id'       => $orderInfo['id'],
                       'express_name'   => '顺丰',
                       'express_number' => $v['waybillNo'],
                       'express_status' => 4,
                       'created_at'     => time(),
                       'updated_at'     => time(),
                    ]);
                }
                $waybillNo .= trim($v['waybillNo']);
            }

            // 添加订单操作日志
            ShopOrderLog::insert([
                'type' => 3,
                'name' => '顺丰开单',
                'order_id' => $orderInfo['id'],
                'member_id' => $orderInfo['member_id'],
                'member_name' => $orderInfo['member_name'],
                'member_real_name' => $orderInfo['member_real_name'],
                'member_mobile' => $orderInfo['mobile'],
                'admin_id' => 0,
                'admin_name' => '',
                'merchant_id' => $orderInfo['merchant_id'],
                'merchant_account' => $orderInfo['merchant_account'],
                'info' => '商户：'.$orderInfo['merchant_name'].',账号：'. $orderInfo['merchant_account'] . '，顺丰开单，订单类型：回收，订单id：'. $orderInfo['id'],
                'created_at' => time()
            ]);
            Db::commit();
            return jsonSuccess('顺丰开单，单号-' . $waybillNo);
        } catch (\Exception $ex) {
            Db::rollBack();
            return jsonError($ex->getMessage(),500);
        }
    }

}