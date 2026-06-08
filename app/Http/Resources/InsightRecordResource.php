<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InsightRecordResource extends JsonResource
{
    //控制和格式化广告数据模型的对外输格式
    //屏蔽数据库真实字段。即便某天数据库字段名从 `spend` 改成了 `total_cost`，也只需要改此文件的映射，前端拿到的结构完全不受影响，系统表现极度稳定。
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'level' => $this->level,
            'external_id' => $this->external_id,
            'date' => $this->date,
            'metrics' => [
                'impressions' => $this->impressions,
                'clicks' => $this->clicks,
                'spend' => $this->spend,
            ],
        ];
    }
}