<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class InsightRecord extends Model
{
    protected $fillable = [
        'provider',
        'level',
        'external_id',
        'date',
        'impressions',
        'clicks',
        'spend',
    ];
}