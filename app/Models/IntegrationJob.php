<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class IntegrationJob extends Model
{
    protected $fillable = [
        'provider',
        'status',
        'date_from',
        'date_to',
        'payload',
        'error',
    ];

    protected $casts = [
        'payload' => 'array',
        'date_from' => 'date',
        'date_to' => 'date',
    ];
}