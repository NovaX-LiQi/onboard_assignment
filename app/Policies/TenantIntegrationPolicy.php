<?php

namespace App\Policies;

use App\Models\Tenant;

class TenantIntegrationPolicy
{
    //校验操作权限。
    //拿当前登录授权的租户主体主键（`$loggedInTenantId`）和当前他企图操作的目标租户主键（`$currentTargetTenantId`）作比对。
    //物理级防越权。确保 Tenant A 哪怕拿到了有效的 Token，也绝对不可以通过改参数去动 Tenant B 的配置。
    public function manageSettings(Tenant $user, Tenant $targetTenant): bool
    {
        $loggedInTenantId = $user->getTenantKey();
        $currentTargetTenantId = $targetTenant->getTenantKey();

        return $loggedInTenantId === $currentTargetTenantId;
    }
}