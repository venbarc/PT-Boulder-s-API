<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PteAvailableBlock extends Model
{
    /**
     * Export/display columns from pte_available_blocks.
     * raw_data is intentionally excluded.
     *
     * @var array<int, string>
     */
    public const EXPORT_COLUMNS = [
        'id',
        'row_key',
        'pte_therapist_id',
        'start_datetime',
        'end_datetime',
        'location_id',
        'location_name',
        'service_id',
        'service_name',
        'request_start_date',
        'request_end_date',
        'created_at',
        'updated_at',
    ];

    protected $table = 'pte_available_blocks';

    protected $guarded = ['id'];

    protected $casts = [
        'raw_data' => 'array',
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
        'request_start_date' => 'date',
        'request_end_date' => 'date',
    ];
}
