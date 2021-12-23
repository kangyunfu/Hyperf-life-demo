<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use function PHPUnit\Framework\throwException;


class CommonController extends AdminBaseController
{
    /**
     * @Inject()
     * @var ValidatorFactoryInterface
     */
    protected $validationFactory;

    /***
     ** @api {post} admin/upload 上传
     ** @apiName 上传
     ** @apiGroup 公共
     ** @apiHeader {string} token 已登录管理员的token(Header: token)  必填
     ** @apiParam {file} file 必填
     ** @apiSuccessExample {json} SuccessExample
     * {
    "msg": "success",
    "code": 200,
    "data": {
    "url": "https://img.xfb315.com/life_service/merchant/1cda659bf62ffd4ee183e3ac787eb54e.png"
    }
    }
     ***/
    public function upload(RequestInterface $request) {
        if (!$request->hasFile('file')) {
            return jsonError('请选择要上传的投诉图片');
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

        $file_path = '/life_service/admin/';
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
