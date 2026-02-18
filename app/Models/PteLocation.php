<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PteLocation extends Model
{
    /**
     * Export/display columns from pte_locations.
     * raw_data is intentionally excluded.
     *
     * @var array<int, string>
     */
    public const EXPORT_COLUMNS = [
        'id',
        'row_key',
        'pte_location_id',
        'location_name',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'zip_code',
        'timezone',
        'is_active',
        'created_at',
        'updated_at',
    ];

    protected $table = 'pte_locations';

    protected $guarded = ['id'];

    protected $casts = [
        'raw_data' => 'array',
        'is_active' => 'boolean',
    ];
}
