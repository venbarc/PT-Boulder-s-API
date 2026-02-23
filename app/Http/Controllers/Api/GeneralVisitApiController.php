<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PteGeneralVisit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeneralVisitApiController extends Controller
{
    /**
     * @var array<int, string>
     */
    private const DOC_COLUMNS = [
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

    /**
     * @var array<int, string>
     */
    private const INT_COLUMNS = [
        'patient_total_appointment_visit',
        'summary_total_appointments',
        'summary_total_patients',
    ];

    /**
     * @var array<int, string>
     */
    private const FLOAT_COLUMNS = [
        'units',
        'charges',
        'payments',
        'summary_total_charges',
        'summary_total_payments',
        'summary_total_units',
        'summary_average_charges',
        'summary_average_payments',
        'summary_average_units',
    ];

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $from = $validated['from'] ?? null;
        $to = $validated['to'] ?? null;
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 100);

        $baseQuery = PteGeneralVisit::query();
        if ($from !== null) {
            $baseQuery->whereDate('date_of_service', '>=', $from);
        }
        if ($to !== null) {
            $baseQuery->whereDate('date_of_service', '<=', $to);
        }

        $summaryQuery = clone $baseQuery;
        $totalAppointments = (int) $summaryQuery->count();
        $totalCharges = (float) ((clone $baseQuery)->sum('charges') ?: 0);
        $totalPayments = (float) ((clone $baseQuery)->sum('payments') ?: 0);
        $totalUnits = (float) ((clone $baseQuery)->sum('units') ?: 0);
        $totalPatients = (int) ((clone $baseQuery)->distinct('pte_patient_id')->count('pte_patient_id'));

        $averageCharges = $totalAppointments > 0 ? round($totalCharges / $totalAppointments, 2) : 0.0;
        $averagePayments = $totalAppointments > 0 ? round($totalPayments / $totalAppointments, 2) : 0.0;
        $averageUnits = $totalAppointments > 0 ? round($totalUnits / $totalAppointments, 2) : 0.0;

        $paginator = (clone $baseQuery)
            ->orderByDesc('date_of_service')
            ->orderByDesc('id')
            ->paginate($perPage, self::DOC_COLUMNS, 'page', $page);

        $docs = $paginator->getCollection()->map(function (PteGeneralVisit $row): array {
            $line = [];
            foreach (self::DOC_COLUMNS as $column) {
                $value = $row->{$column} ?? null;
                if ($value instanceof \DateTimeInterface) {
                    $line[$column] = $column === 'date_of_service'
                        ? $value->format('Y-m-d')
                        : $value->format('Y-m-d H:i:s');

                    continue;
                }

                if ($value === null || $value === '') {
                    $line[$column] = null;

                    continue;
                }

                if (in_array($column, self::INT_COLUMNS, true)) {
                    $line[$column] = (int) $value;

                    continue;
                }

                if (in_array($column, self::FLOAT_COLUMNS, true)) {
                    $line[$column] = round((float) $value, 2);

                    continue;
                }

                $line[$column] = (string) $value;
            }

            return $line;
        })->values();

        return response()->json([
            'summary' => [
                'totalAppointments' => $totalAppointments,
                'totalCharges' => round($totalCharges, 2),
                'totalPayments' => round($totalPayments, 2),
                'totalUnits' => round($totalUnits, 2),
                'totalPatients' => $totalPatients,
                'averageCharges' => $averageCharges,
                'averagePayments' => $averagePayments,
                'averageUnits' => $averageUnits,
            ],
            'docs' => $docs,
            'paging' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'totalPages' => $paginator->lastPage(),
            ],
        ]);
    }
}
