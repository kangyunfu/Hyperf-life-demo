<?php

namespace App\Common;

use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;

class ValidateForm
{
    /**
     * @Inject()
     * @var ValidatorFactoryInterface
     */
    protected $validationFactory;

    public function check(RequestInterface $request, array $rules, array $messages = [])
    {
        $validator = $this->validationFactory->make($request->all(), $rules, $messages);
        if ($validator->fails()){
            // Handle exception
            $errorMessage = $validator->errors()->first();
            return jsonError($errorMessage);
        }
        return true;
        // Do something
    }
}
