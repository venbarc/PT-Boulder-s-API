<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PteService extends Model
{
    /**
     * Export/display columns from pte_services.
     * raw_data is intentionally excluded.
     *
     * @var array<int, string>
     */
    public const EXPORT_COLUMNS = [
        'id',
        'row_key',
        'pte_service_id',
        'service_name',
        'service_code',
        'cpt_code',
        'category',
        'duration_minutes',
        'price',
        'tax',
        'is_active',
        'created_at',
        'updated_at',
    ];

    protected $table = 'pte_services';

    protected $guarded = ['id'];

    protected $casts = [
        'raw_data' => 'array',
        'duration_minutes' => 'integer',
        'price' => 'decimal:2',
        'tax' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
