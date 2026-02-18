<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PteDemographic extends Model
{
    /**
     * Export/display columns from pte_demographics.
     * raw_data is intentionally excluded.
     *
     * @var array<int, string>
     */
    public const EXPORT_COLUMNS = [
        'id',
        'row_key',
        'pte_patient_id',
        'first_name',
        'last_name',
        'email',
        'date_of_birth',
        'month_of_birth',
        'year_of_birth',
        'phone_number',
        'zip_code',
        'address',
        'city',
        'state',
        'insurance_info',
        'open_case_str',
        'close_case_str',
        'dependent_of_id',
        'dependent_of_first_name',
        'dependent_of_last_name',
        'dependent_of_middle_name',
        'dependent_of_email',
        'created_at',
        'updated_at',
    ];

    protected $table = 'pte_demographics';

    protected $guarded = ['id'];

    protected $casts = [
        'raw_data' => 'array',
        'month_of_birth' => 'integer',
        'year_of_birth' => 'integer',
    ];
}
