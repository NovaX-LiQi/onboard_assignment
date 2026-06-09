<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTenantRequest;
use App\Services\TenantService;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TenantController extends Controller
{
    protected $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    public function store(StoreTenantRequest $request): JsonResponse
    {
        $tenant = $this->tenantService->registerTenant($request->validated());

        return response()->json([
            'message' => 'Tenant created',
            'tenant' => $tenant,
        ]);
    }

    public function issueToken(Request $request): JsonResponse
    {
        $request->validate([
            'tenant_id' => ['required', 'string', 'exists:tenants,id'],
            'token_name' => ['nullable', 'string'],
        ]);
        
        $result = $this->tenantService->issueNewToken(
            $request->tenant_id, 
            $request->token_name
        );

        return response()->json(array_merge([
            'message' => 'Token issued successfully',
        ], $result));
    }
}