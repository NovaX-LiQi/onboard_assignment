<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class SanctumToken extends SanctumPersonalAccessToken
{
    protected $connection = 'pgsql'; 

    protected $table = 'personal_access_tokens';
}