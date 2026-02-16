<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PteProviderRevenue extends Model
{
    /**
     * Export/display columns from pte_provider_revenues.
     * raw_data is intentionally excluded.
     *
     * @var array<int, string>
     */
    public const EXPORT_COLUMNS = [
        'id',
        'row_key',
        'pte_patient_id',
        'patient_name',
        'therapist_name',
        'location_name',
        'patient_email',
        'date_of_service',
        'revenue',
        'adjustment',
        'collected',
        'due_amount',
        'cpt',
        'created_at',
        'updated_at',
    ];

    protected $table = 'pte_provider_revenues';

    protected $guarded = ['id'];

    protected $casts = [
        'raw_data' => 'array',
        'date_of_service' => 'date',
        'revenue' => 'decimal:2',
        'adjustment' => 'decimal:2',
        'collected' => 'decimal:2',
        'due_amount' => 'decimal:2',
    ];
}
