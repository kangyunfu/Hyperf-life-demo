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
namespace App\Common\Controller;
use App\Common\AbstractController;

class IndexController extends AbstractController
{
    public function index()
    {
        $method = $this->request->getMethod();
        return [
            'method' => $method,
            'message' => "Hello，i am test!.",
        ];
    }
}
