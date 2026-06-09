<?php

namespace App\Services;;

use App\Models\Tenant;
use App\Repositories\TenantRepository;
use Illuminate\Support\Facades\DB;
use Exception;

class TenantService
{
    protected $tenantRepo;

    public function __construct(TenantRepository $tenantRepo)
    {
        $this->tenantRepo = $tenantRepo;
    }

    public function registerTenant(array $data): Tenant
    {
        try {
            $tenant = $this->tenantRepo->create($data); //create database不能在BEGIN ... COMMIT中

            DB::transaction(function () use ($tenant, $data) {
                $this->tenantRepo->createDomain($tenant, $data['domain']);
            });

            return $tenant;

        } catch (Exception $e) {
            //把刚刚生成的租户连带库一起删掉
            if (isset($tenant) && $tenant) {
                DB::purge('tenant'); //清理内存
                DB::disconnect('tenant'); //删除链接
                $tenant->delete();
            }

            throw $e;
        }
    }

    public function issueNewToken(string $tenantId, ?string $tokenName): array
    {
        $tenant = $this->tenantRepo->find($tenantId);
        $name = $tokenName ?? 'postman-sanctum-key';
        
        $token = $this->tenantRepo->clearAndCreateToken($tenant, $name);

        return [
            'tenant_id' => $tenant->id,
            'token' => $token,
        ];
    }
}