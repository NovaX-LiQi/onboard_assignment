<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncInsightsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_from' => 'required|date|date_format:Y-m-d',
            'date_to' => 'required|date|date_format:Y-m-d|after_or_equal:date_from', //确保结束日期必须大于或等于开始日期
            'level' => 'nullable|string|in:ad,adset,campaign,account',
        ];
    }
}