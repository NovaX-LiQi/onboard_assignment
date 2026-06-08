<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\IntegrationJobRepository;
use App\Models\IntegrationJob;
use Illuminate\Http\Request;

class IntegrationStatusController extends Controller
{
    public function __construct(
        protected IntegrationJobRepository $jobRepository
    ) {}

    public function index(Request $request): ?IntegrationJob
    {
        //获取该渠道最新一次异步同步任务的运行状态（running, success, failed）
        $provider = $request->query('provider', 'facebook');
        
        return $this->jobRepository->getLatestJobByProvider($provider);
    }
}