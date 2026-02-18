<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PtePatientReport extends Model
{
    /**
     * Export/display columns from pte_patient_reports.
     * raw_data is intentionally excluded.
     *
     * @var array<int, string>
     */
    public const EXPORT_COLUMNS = [
        'id',
        'row_key',
        'pte_patient_id',
        'patient_name',
        'email',
        'phone',
        'registration_date_str',
        'registration_by',
        'first_login_date_str',
        'last_login_date_str',
        'total_last_logins',
        'first_appointment_id',
        'first_appointment_start_date',
        'last_appointment_id',
        'last_appointment_start_date',
        'next_appointment_id',
        'next_appointment_start_date',
        'last_therapist_id',
        'last_therapist_name',
        'last_therapist_email',
        'package_membership',
        'first_appointment_location',
        'first_seen_by',
        'last_seen_by',
        'referred_by_name',
        'payers_name',
        'total_revenue',
        'total_collected',
        'status',
        'dependent_of_id',
        'dependent_of_first_name',
        'dependent_of_last_name',
        'dependent_of_middle_name',
        'dependent_of_email',
        'total_appointment_visit',
        'total_session_completed',
        'created_at',
        'updated_at',
    ];

    protected $table = 'pte_patient_reports';

    protected $guarded = ['id'];

    protected $casts = [
        'raw_data' => 'array',
        'package_membership' => 'array',
        'total_last_logins' => 'integer',
        'total_revenue' => 'decimal:2',
        'total_collected' => 'decimal:2',
        'total_appointment_visit' => 'integer',
        'total_session_completed' => 'integer',
    ];
}
