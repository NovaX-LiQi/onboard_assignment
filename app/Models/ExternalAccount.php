<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ExternalAccount extends Model
{
    protected $fillable = [
        'provider',
        'access_token',
        'ad_account_id',
    ];

    protected $casts = [
        'access_token' => 'encrypted', 
    ];
}