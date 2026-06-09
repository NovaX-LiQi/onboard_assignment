<?php

namespace App\Repositories;

use App\Models\Tenant;

class TenantRepository
{
    public function find(string $id): ?Tenant
    {
        return Tenant::find($id);
    }

    public function create(array $data): Tenant
    {
        return Tenant::create([
            'id' => $data['id'],
        ]);
    }

    public function createDomain(Tenant $tenant, string $domain)
    {
        return $tenant->domains()->create([
            'domain' => $domain,
        ]);
    }

    public function clearAndCreateToken(Tenant $tenant, string $tokenName): string
    {
        $tenant->tokens()->delete();
        return $tenant->createToken($tokenName)->plainTextToken;
    }
}