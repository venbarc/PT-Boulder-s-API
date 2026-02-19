<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PtePullHistory extends Model
{
    protected $table = 'pte_pull_histories';

    protected $guarded = ['id'];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'from_date' => 'date',
        'to_date' => 'date',
        'from_year' => 'integer',
        'to_year' => 'integer',
        'duration_seconds' => 'integer',
        'fetched_count' => 'integer',
        'created_count' => 'integer',
        'updated_count' => 'integer',
        'upserted_count' => 'integer',
        'failed_chunks' => 'integer',
        'options' => 'array',
    ];
}
