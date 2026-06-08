<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConnectIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // 权限交给 Controller 的 Gate 处理
    }

    public function rules(): array
    {
        return [
            'provider' => 'required|string|in:facebook',
            'credentials.access_token' => 'required|string',
            'credentials.ad_account_id' => 'required|string',
        ];
    }
}