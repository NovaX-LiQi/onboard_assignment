<?php

namespace Tests;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

abstract class TenantTestCase extends TestCase
{
    use DatabaseMigrations;

    protected Tenant $tenant;
    protected string $tenantToken;
    protected string $domain = 'test.localhost';

    protected function setUp(): void
    {
        parent::setUp();

        //【安全防线】为了防止上次测试意外中断导致残留，进来先强行清理一次
        $this->cleanupTenantSystem();

        //创建测试租户和域名
        $this->tenant = Tenant::create(['id' => 'test']);
        $this->tenant->domains()->create(['domain' => $this->domain]);

        //为该租户生成测试用的 Sanctum Token
        $tokenResult = $this->tenant->createToken('test-token');
        $this->tenantToken = $tokenResult->plainTextToken;
    }

    protected function tearDown(): void
    {
        //测试结束，安全清理
        $this->cleanupTenantSystem();

        parent::tearDown();
    }

    /**
     * 强行切断租户数据库连接并清理数据的核心逻辑
     */
    protected function cleanupTenantSystem(): void
    {
        //让 Tenancy 扩展包退出租户上下文，回到中央库连接
        if (function_exists('tenancy') && tenancy()->initialized) {
            tenancy()->end();
        }

        //核心修复：强行关闭租户的 DB 连接，释放 PostgreSQL 进程锁
        //请确保这里的 'tenant' 对应你 config/database.php 里的租户连接名称
        DB::purge('tenant');
        DB::disconnect('tenant');

        //查出test tenant并将其根除（会连带触发 DROP DATABASE）
        $tenant = Tenant::find('test');
        if ($tenant) {
            try {
                $tenant->delete();
            } catch (\Exception $e) {
                //如果 Postgres 依然因为某些极度顽固的死锁报 Object in use，
                //我们通过中央库发指令，强行踢掉所有正在连接该测试库的 Postgres 进程
                $dbName = $tenant->database()->getName();
                
                DB::connection('pgsql')->statement("
                    SELECT pg_terminate_backend(pg_stat_activity.pid)
                    FROM pg_stat_activity
                    WHERE pg_stat_activity.datname = ?
                      AND pid <> pg_backend_pid();
                ", [$dbName]);

                //进程踢掉后，再次尝试删除
                $tenant->delete();
            }
        }
    }
}