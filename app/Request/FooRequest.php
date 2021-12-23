<?php

declare(strict_types=1);

namespace App\Request;

use Hyperf\Validation\Request\FormRequest;

class FooRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 验证规则
     */
    public function rules(): array
    {
        return [
            'foo' => 'required',
            'bar' => 'required',
        ];
    }

    /**
     * 获取已定义验证规则的错误消息.
     */
    public function messages(): array
    {
        return [
            'foo.required' => 'foo is required',
            'bar.required' => 'bar is required',
        ];
    }

    /**
     * 获取验证错误的自定义属性
     */
    public function attributes(): array
    {
        return [
            'foo' => 'foo of request',
            'bar' => 'bar of required',
        ];
    }
}
