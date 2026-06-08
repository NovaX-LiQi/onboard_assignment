<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TenantController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'id' => ['required', 'string', 'unique:tenants,id'],
            'domain' => ['required', 'string', 'unique:domains,domain'],
        ]);

        $tenant = Tenant::create([
            'id' => $request->id,
        ]);

        $tenant->domains()->create([
            'domain' => $request->domain,
        ]);

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
        
        $tenant = Tenant::find($request->tenant_id);
        
        $tokenName = $request->token_name ?? 'postman-sanctum-key';

        $tenant->tokens()->delete();

        $tokenResult = $tenant->createToken($tokenName);

        return response()->json([
            'message' => 'Token issued successfully',
            'tenant_id' => $tenant->id,
            'token' => $tokenResult->plainTextToken,
        ]);
    }
}