<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\QueryInsightRecordRequest;
use App\Http\Resources\InsightRecordResource;
use App\Repositories\InsightRecordRepository;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class InsightController extends Controller
{
    public function __construct(
        protected InsightRecordRepository $insightRepository
    ) {}

    public function index(QueryInsightRecordRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('manageSettings', [tenant()]);

        //通过 Repository 过滤并进行分页（Paginate），最后用 JsonResource 格式化后吐给前端
        //引入了 `per_page` 最大 100 的防御性限制，防止前端恶意传 `per_page=999999` 导致数据库内存溢出
        $records = $this->insightRepository->getPaginatedRecords(
            $request->validated(),
            $request->get('per_page', 15)
        );

        return InsightRecordResource::collection($records);
    }
}