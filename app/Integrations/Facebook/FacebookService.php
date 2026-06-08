<?php

namespace App\Integrations\Facebook;

use App\Integrations\Facebook\Dto\FacebookInsightsRequestDTO;
use App\Repositories\InsightRecordRepository;

class FacebookService
{
    public function __construct(
        protected InsightRecordRepository $insightRepository
    ) {}

    public function syncPageInsights(array $responsePage, FacebookInsightsRequestDTO $dto): void
    {
        foreach ($responsePage['data'] ?? [] as $row) {
            $level = $dto->level;

            $externalId = match ($level) {
                'ad' => $row['ad_id'] ?? null,
                'adset' => $row['adset_id'] ?? null,
                'campaign' => $row['campaign_id'] ?? null,
                default => $row['account_id'] ?? null,
            };

            if (!$externalId) {
                $externalId = $row['ad_id'] ?? $row['adset_id'] ?? $row['campaign_id'] ?? $row['account_id'] ?? 'facebook_account';
            }

            $date = $row['date_start'] ?? now()->toDateString();

            $this->insightRepository->updateOrCreateRecord(
                [//唯一
                    'provider' => 'facebook',
                    'external_id' => $externalId,
                    'date' => $date,
                    'level' => $level,
                ],
                [
                    'impressions' => (int) ($row['impressions'] ?? 0),
                    'clicks' => (int) ($row['clicks'] ?? 0),
                    'spend' => (float) ($row['spend'] ?? 0),
                ]
            );
        }
    }
}