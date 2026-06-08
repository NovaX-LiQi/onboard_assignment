<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConnectIntegrationRequest;
use App\Http\Requests\SyncInsightsRequest;
use App\Integrations\Facebook\Dto\FacebookInsightsRequestDTO;
use App\Integrations\Facebook\FacebookClient;
use App\Repositories\ExternalAccountRepository;
use App\Jobs\SyncFacebookInsightsJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Gate;

class IntegrationController extends Controller
{
    public function __construct(
        protected FacebookClient $facebookClient,
        protected ExternalAccountRepository $accountRepository
    ) {}

    public function connect(ConnectIntegrationRequest $request): JsonResponse
    {
        //校验当前登录主体是否有权修改该租户的系统设置（防止跨租户越权）
        Gate::authorize('manageSettings', [tenant()]);

        $validated = $request->validated();
        $accessToken = $validated['credentials']['access_token'];
        $adAccountId = $validated['credentials']['ad_account_id'];

        //验证有没有“ads_read”权限
        $hasPermission = $this->facebookClient->validateAdsReadPermission($accessToken);

        if (!$hasPermission) {
            throw ValidationException::withMessages([
                'credentials.access_token' => ['The provided token is missing required "ads_read" permission.'],
            ]);
        }

        $this->accountRepository->updateOrCreateToken(
            $validated['provider'],
            $accessToken,
            $adAccountId
        );

        return response()->json(['message' => 'connected']);
    }

    public function sync(SyncInsightsRequest $request): JsonResponse
    {
        Gate::authorize('manageSettings', [tenant()]);

        $tenantId = tenant('id');
        //把请求参数规范化为 DTO 对象
        $dto = FacebookInsightsRequestDTO::fromArray($request->validated());

        SyncFacebookInsightsJob::dispatch($tenantId, $dto->toArray());

        return response()->json(['message' => 'sync queued'], 202);
    }
}