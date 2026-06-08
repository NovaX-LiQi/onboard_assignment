<?php

namespace App\Repositories;

use App\Models\ExternalAccount;

class ExternalAccountRepository
{
    public function updateOrCreateToken(string $provider, string $accessToken, string $adAccountId): ExternalAccount
    {
        return ExternalAccount::updateOrCreate(
            ['provider' => $provider],
            [
                'access_token' => $accessToken,
                'ad_account_id' => $adAccountId,
            ]
        );
    }

    public function findByProvider(string $provider): ?ExternalAccount
    {
        return ExternalAccount::where('provider', $provider)->first();
    }
}