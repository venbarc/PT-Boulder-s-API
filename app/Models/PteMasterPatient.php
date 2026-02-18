<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PteMasterPatient extends Model
{
    /**
     * Export/display columns from pte_master_patients.
     * raw_data is intentionally excluded.
     *
     * @var array<int, string>
     */
    public const EXPORT_COLUMNS = [
        'id',
        'row_key',
        'pte_patient_id',
        'patient_code',
        'full_name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'date_of_birth',
        'gender',
        'address',
        'city',
        'state',
        'zip_code',
        'status',
        'is_active',
        'created_at',
        'updated_at',
    ];

    protected $table = 'pte_master_patients';

    protected $guarded = ['id'];

    protected $casts = [
        'raw_data' => 'array',
        'is_active' => 'boolean',
    ];
}
