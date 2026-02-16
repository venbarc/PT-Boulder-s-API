<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PteGeneralVisit extends Model
{
    /**
     * Export/display columns from pte_general_visits.
     * raw_data is intentionally excluded.
     *
     * @var array<int, string>
     */
    public const EXPORT_COLUMNS = [
        'id',
        'row_key',
        'pte_visit_id',
        'pte_patient_id',
        'patient_full_name',
        'patient_first_name',
        'patient_last_name',
        'patient_email',
        'patient_code',
        'patient_total_appointment_visit',
        'date_of_service',
        'appointment_status',
        'provider_id',
        'provider_name',
        'service_id',
        'service_name',
        'location_id',
        'location_name',
        'invoice_status',
        'invoice_number',
        'current_responsibility',
        'package_invoice_number',
        'package_invoice_name',
        'claim_created_info',
        'created_by',
        'reason',
        'last_update_by',
        'last_update_date',
        'cancellation_notice',
        'treatment_note_id',
        'treatment_note_number',
        'units',
        'charges',
        'payments',
        'summary_total_appointments',
        'summary_total_charges',
        'summary_total_payments',
        'summary_total_units',
        'summary_total_patients',
        'summary_average_charges',
        'summary_average_payments',
        'summary_average_units',
        'created_at',
        'updated_at',
    ];

    protected $table = 'pte_general_visits';

    protected $guarded = ['id'];

    protected $casts = [
        'raw_data' => 'array',
        'date_of_service' => 'date',
        'last_update_date' => 'datetime',
        'patient_total_appointment_visit' => 'integer',
        'units' => 'decimal:2',
        'charges' => 'decimal:2',
        'payments' => 'decimal:2',
        'summary_total_appointments' => 'integer',
        'summary_total_charges' => 'decimal:2',
        'summary_total_payments' => 'decimal:2',
        'summary_total_units' => 'decimal:2',
        'summary_total_patients' => 'integer',
        'summary_average_charges' => 'decimal:2',
        'summary_average_payments' => 'decimal:2',
        'summary_average_units' => 'decimal:2',
    ];
}
