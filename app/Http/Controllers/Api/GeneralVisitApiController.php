<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PteGeneralVisit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeneralVisitApiController extends Controller
{
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
            ->paginate($perPage, ['*'], 'page', $page);

        $docs = $paginator->getCollection()->map(function (PteGeneralVisit $row): array {
            if (is_array($row->raw_data) && $row->raw_data !== []) {
                return $row->raw_data;
            }

            return [
                '_id' => $row->pte_visit_id,
                'dateOfService' => optional($row->date_of_service)?->format('Y-m-d'),
                'appointmentStatus' => $row->appointment_status,
                'patient' => [
                    '_id' => $row->pte_patient_id,
                    'fullName' => $row->patient_full_name,
                    'firstName' => $row->patient_first_name,
                    'lastName' => $row->patient_last_name,
                    'email' => $row->patient_email,
                    'patientCode' => $row->patient_code,
                    'totalAppointmentVisit' => $row->patient_total_appointment_visit,
                ],
                'provider' => [
                    '_id' => $row->provider_id,
                    'name' => $row->provider_name,
                ],
                'service' => [
                    '_id' => $row->service_id,
                    'name' => $row->service_name,
                ],
                'location' => [
                    '_id' => $row->location_id,
                    'name' => $row->location_name,
                ],
                'invoice' => [
                    'invoiceStatus' => $row->invoice_status,
                    'invoiceNo' => $row->invoice_number,
                    'currentResponsibility' => $row->current_responsibility,
                    'packageInvoiceNumber' => $row->package_invoice_number,
                    'packageInvoiceName' => $row->package_invoice_name,
                ],
                'claimCreatedInfo' => $row->claim_created_info,
                'charges' => (float) ($row->charges ?? 0),
                'payments' => (float) ($row->payments ?? 0),
                'units' => (float) ($row->units ?? 0),
                'createdBy' => $row->created_by,
                'reason' => $row->reason,
                'lastUpdateBy' => $row->last_update_by,
                'lastUpdateDate' => optional($row->last_update_date)?->toISOString(),
                'cancellationNotice' => $row->cancellation_notice,
                'treatmentNote' => [
                    '_id' => $row->treatment_note_id,
                    'treatmentNoteNo' => $row->treatment_note_number,
                ],
            ];
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
