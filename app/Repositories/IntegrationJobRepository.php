<?php

namespace App\Repositories;

use App\Models\IntegrationJob;

class IntegrationJobRepository
{
    public function createJob(string $provider, string $dateFrom, string $dateTo, array $payload): IntegrationJob
    {
        return IntegrationJob::create([
            'provider' => $provider,
            'status' => 'running',
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'payload' => $payload,
        ]);
    }

    public function updateStatus(IntegrationJob $job, string $status, ?string $error = null): bool
    {
        $data = ['status' => $status];
        if ($error !== null) {
            $data['error'] = substr($error, 0, 1000);
        }
        return $job->update($data);
    }

    public function getLatestJobByProvider(string $provider): ?IntegrationJob
    {
        return IntegrationJob::where('provider', $provider)
            ->latest()
            ->first();
    }
}