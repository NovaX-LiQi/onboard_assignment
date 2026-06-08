<?php

namespace App\Repositories;

use App\Models\InsightRecord;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class InsightRecordRepository
{
    public function updateOrCreateRecord(array $attributes, array $values): InsightRecord
    {
        return InsightRecord::updateOrCreate($attributes, $values);
    }

    public function getPaginatedRecords(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        //使用 `when` 灵活的高阶函数进行动态条件拼接
        return InsightRecord::query()
            ->when($filters['provider'] ?? null, fn($q, $provider) => $q->where('provider', $provider))
            ->when($filters['from'] ?? null, fn($q, $from) => $q->whereDate('date', '>=', $from))
            ->when($filters['to'] ?? null, fn($q, $to) => $q->whereDate('date', '<=', $to))
            ->paginate($perPage);
    }
}