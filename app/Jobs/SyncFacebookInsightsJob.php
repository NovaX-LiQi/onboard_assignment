<?php

namespace App\Jobs;

use App\Integrations\Facebook\Dto\FacebookInsightsRequestDTO;
use App\Integrations\Facebook\FacebookClient;
use App\Integrations\Facebook\FacebookService;
use App\Repositories\ExternalAccountRepository;
use App\Repositories\IntegrationJobRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncFacebookInsightsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    //失败后最多重试 3 次，每次间隔 60 秒。
    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public string $tenantId,
        public array $dtoData
    ) {}

    public function handle(
        FacebookClient $client, 
        FacebookService $service,
        ExternalAccountRepository $accountRepository,
        IntegrationJobRepository $jobRepository
    ): void {
        tenancy()->initialize($this->tenantId);

        $dto = FacebookInsightsRequestDTO::fromArray($this->dtoData);
        
        //提取账号转交给 Repository 
        //该租户是否有绑定
        $account = $accountRepository->findByProvider($dto->provider);
        if (!$account) {
            Log::error("External account settings missing for provider: {$dto->provider}");
            tenancy()->end();
            return;
        }

        //建立job log
        $jobRecord = $jobRepository->createJob(
            $dto->provider, 
            $dto->dateFrom, 
            $dto->dateTo, 
            $dto->toArray()
        );

        try {
            $pages = $client->getInsights($account->access_token, $account->ad_account_id, $dto);

            foreach ($pages as $pageData) {
                $service->syncPageInsights($pageData, $dto);
            }

            $jobRepository->updateStatus($jobRecord, 'success');

            Log::info('Facebook insights sync completed successfully.', [
                'tenant_id' => $this->tenantId,
                'level' => $dto->level,
                'date_from' => $dto->dateFrom
            ]);

        } catch (\Throwable $e) {
            $jobRepository->updateStatus($jobRecord, 'failed', $e->getMessage());

            Log::error('Facebook insights sync job failed.', [
                'tenant_id' => $this->tenantId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        } finally {
            tenancy()->end();
        }
    }
}