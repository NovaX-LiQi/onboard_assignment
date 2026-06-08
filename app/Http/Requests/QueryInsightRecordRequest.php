<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QueryInsightRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider' => 'nullable|string',
            'from' => 'nullable|date|date_format:Y-m-d',
            'to' => 'nullable|date|date_format:Y-m-d',
            'per_page' => 'nullable|integer|min:1|max:100', // 限制最大单页 100 条
        ];
    }
}