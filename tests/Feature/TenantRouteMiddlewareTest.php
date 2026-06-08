<?php

use App\Models\Tenant;

/** @var \Tests\TenantTestCase $this */ // 这行注释是魔法，让 IDE 完美识别属性

test('it can create a tenant and issue a token via central api', function () {
    $randomId = 'newtenant' . rand(10, 99);
    
    // 测试中央 API：创建租户
    $this->postJson('/api/tenants', [
        'id' => $randomId,
        'domain' => "{$randomId}.localhost",
    ])->assertStatus(200)->assertJsonStructure(['message', 'tenant']);

    $this->assertDatabaseHas('tenants', ['id' => $randomId], 'pgsql');

    // 测试中央 API：生成 Token
    $this->postJson('/api/tenants/token', [
        'tenant_id' => $randomId,
        'token_name' => 'postman-key',
    ])->assertStatus(200)->assertJsonStructure(['message', 'token']);
    
    Tenant::find($randomId)?->delete();
});

test('it allows access via domain tenancy middleware', function () {
    // 此时 $this->tenantToken 和 $this->domain 自动高亮，不会报 Undefined
    $this->withHeaders([
        'Authorization' => "Bearer {$this->tenantToken}",
    ])->getJson("http://{$this->domain}/api/insights")
      ->assertStatus(200)
      ->assertJsonStructure(['data', 'links', 'meta']);
});

test('it allows access via x tenant header middleware', function () {
    $this->withHeaders([
        'Authorization' => "Bearer {$this->tenantToken}",
        'X-Tenant' => $this->tenant->id,
    ])->getJson('/api/v1/app/insights')
      ->assertStatus(200);
});

test('it blocks access if token is missing or invalid', function () {
    $this->getJson("http://{$this->domain}/api/insights")->assertStatus(401);

    $this->withHeaders([
        'Authorization' => 'Bearer wrong_token',
    ])->getJson("http://{$this->domain}/api/insights")->assertStatus(401);
});