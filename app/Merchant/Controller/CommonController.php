<?php

declare(strict_types=1);
namespace App\Merchant\Controller;

use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Di\Annotation\Inject;

class CommonController extends MerchantBaseController
{
    /**
     * @Inject()
     * @var ValidatorFactoryInterface
     */
    protected $validationFactory;

    /***
     ** @api {post} merchant/upload 上传
     ** @apiName 上传
     ** @apiGroup 公共
     ** @apiHeader {string} token 已登录商户的token(Header: token)  必填
     ** @apiParam {file} file 必填
     ** @apiSuccess {array}  url
     ** @apiSuccessExample {json} SuccessExample
     * {
    "msg": "success",
    "code": 200,
    "data": {
    "url": "https://img.xfb315.com/life_service/merchant/39acfc6a5606ca4b45a072f7a412082d.png"
    }
    }
     ***/
    public function upload(RequestInterface $request) {
        if (!$request->hasFile('file')) {
            return jsonError('请选择要上传的商户logo');
        }
        $file = $request->file('file');
        if (!$file->isValid()) {
            return jsonError('上传图片失败');
        }

        // 由于 Swoole 上传文件的 tmp_name 并没有保持文件原名，所以这个方法已重写为获取原文件名的后缀名
        $extension = $request->file('file')->getExtension();
        $ext = ['JPG', 'JPEG', 'PNG', 'BMP'];
        if (!in_array(strtoupper($extension), $ext)) {
            return jsonError('上传图片类型不符合！');
        }

        // 判断大小
        $size = $file->getSize();
        if ($size > 5242880) {
            return jsonError('上传图片大小超出标准！');
        }

        //上传路径
        $file_path = '/life_service/merchant/';
        //文件名
        $filename = md5(time() . rand(1000, 9999)) . '.' . $extension;
        $temp_file_path = './runtime/temp/';
        if (!file_exists($temp_file_path)){
            mkdir ($temp_file_path,0777,true);
        }
        $file->moveTo($temp_file_path . $filename);
        // 通过 isMoved(): bool 方法判断方法是否已移动
        if ($file->isMoved()) {
            $result = uploadUpyun( $temp_file_path.$filename, $file_path . $filename);
            unlink($temp_file_path.$filename);
            return jsonSuccess(['url' => $result]);
        } else {
            return jsonError('上传失败！', 500);
        }
    }

}