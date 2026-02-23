<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class ApiDocsController extends Controller
{
    public function index()
    {
        return view('api-docs');
    }

    public function openApi(): JsonResponse
    {
        $webBaseUrl = rtrim((string) config('app.url'), '/');
        $apiBaseUrl = $webBaseUrl.'/api';

        return response()->json([
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'PT Boulder API',
                'version' => '1.0.0',
                'description' => 'Internal integration API for PT Boulder data.',
            ],
            'tags' => [
                [
                    'name' => 'Auth',
                    'description' => 'Token-based authentication.',
                ],
                [
                    'name' => 'Report',
                    'description' => 'Reporting endpoints backed by local synced PT Boulder tables.',
                ],
            ],
            'servers' => [
                [
                    'url' => 'https://pt-boulder.cfoutsourcing.com/api',
                    'description' => 'Production',
                ],
                [
                    'url' => $apiBaseUrl,
                    'description' => 'Current App URL',
                ],
            ],
            'paths' => [
                '/auth/token' => [
                    'post' => [
                        'tags' => ['Auth'],
                        'summary' => 'Issue access token',
                        'description' => 'Use integration credentials provided out-of-band by PT Boulder admins.',
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/AuthTokenRequest',
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Successful',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            '$ref' => '#/components/schemas/AuthTokenResponse',
                                        ],
                                    ],
                                ],
                            ],
                            '401' => [
                                'description' => 'Invalid credentials',
                            ],
                        ],
                    ],
                ],
                '/report/general-visit' => [
                    'get' => [
                        'tags' => ['Report'],
                        'summary' => 'General Visit report (from local synced DB)',
                        'description' => 'Returns rows from local table `pte_general_visits` in table-style fields.',
                        'security' => [
                            ['bearerAuth' => []],
                        ],
                        'parameters' => [
                            [
                                'name' => 'from',
                                'in' => 'query',
                                'required' => false,
                                'schema' => ['type' => 'string', 'format' => 'date'],
                            ],
                            [
                                'name' => 'to',
                                'in' => 'query',
                                'required' => false,
                                'schema' => ['type' => 'string', 'format' => 'date'],
                            ],
                            [
                                'name' => 'page',
                                'in' => 'query',
                                'required' => false,
                                'schema' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                            ],
                            [
                                'name' => 'per_page',
                                'in' => 'query',
                                'required' => false,
                                'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 500, 'default' => 100],
                            ],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Successful',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            '$ref' => '#/components/schemas/GeneralVisitResponse',
                                        ],
                                        'examples' => [
                                            'default' => [
                                                'summary' => 'General visit report payload',
                                                'value' => [
                                                    'summary' => [
                                                        'totalAppointments' => 1,
                                                        'totalCharges' => 120.0,
                                                        'totalPayments' => 80.0,
                                                        'totalUnits' => 1.0,
                                                        'totalPatients' => 1,
                                                        'averageCharges' => 120.0,
                                                        'averagePayments' => 80.0,
                                                        'averageUnits' => 1.0,
                                                    ],
                                                    'docs' => [
                                                        $this->generalVisitDocExample(),
                                                    ],
                                                    'paging' => [
                                                        'page' => 1,
                                                        'perPage' => 100,
                                                        'total' => 1,
                                                        'totalPages' => 1,
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            '401' => [
                                'description' => 'Unauthorized',
                            ],
                        ],
                    ],
                ],
            ],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'token',
                    ],
                ],
                'schemas' => [
                    'AuthTokenRequest' => [
                        'type' => 'object',
                        'required' => ['username', 'password'],
                        'properties' => [
                            'username' => [
                                'type' => 'string',
                                'description' => 'Integration username (provided by admin).',
                            ],
                            'password' => [
                                'type' => 'string',
                                'format' => 'password',
                                'description' => 'Integration password (provided by admin).',
                            ],
                        ],
                    ],
                    'AuthTokenResponse' => [
                        'type' => 'object',
                        'properties' => [
                            'accessToken' => ['type' => 'string'],
                        ],
                    ],
                    'GeneralVisitSummary' => [
                        'type' => 'object',
                        'properties' => [
                            'totalAppointments' => ['type' => 'integer', 'example' => 12],
                            'totalCharges' => ['type' => 'number', 'format' => 'float', 'example' => 3500.25],
                            'totalPayments' => ['type' => 'number', 'format' => 'float', 'example' => 2400.75],
                            'totalUnits' => ['type' => 'number', 'format' => 'float', 'example' => 34.0],
                            'totalPatients' => ['type' => 'integer', 'example' => 8],
                            'averageCharges' => ['type' => 'number', 'format' => 'float', 'example' => 291.69],
                            'averagePayments' => ['type' => 'number', 'format' => 'float', 'example' => 200.06],
                            'averageUnits' => ['type' => 'number', 'format' => 'float', 'example' => 2.83],
                        ],
                    ],
                    'GeneralVisitDoc' => [
                        'type' => 'object',
                        'properties' => $this->generalVisitDocProperties(),
                        'example' => $this->generalVisitDocExample(),
                    ],
                    'GeneralVisitPaging' => [
                        'type' => 'object',
                        'properties' => [
                            'page' => ['type' => 'integer', 'example' => 1],
                            'perPage' => ['type' => 'integer', 'example' => 100],
                            'total' => ['type' => 'integer', 'example' => 1023],
                            'totalPages' => ['type' => 'integer', 'example' => 11],
                        ],
                    ],
                    'GeneralVisitResponse' => [
                        'type' => 'object',
                        'properties' => [
                            'summary' => [
                                '$ref' => '#/components/schemas/GeneralVisitSummary',
                            ],
                            'docs' => [
                                'type' => 'array',
                                'items' => [
                                    '$ref' => '#/components/schemas/GeneralVisitDoc',
                                ],
                            ],
                            'paging' => [
                                '$ref' => '#/components/schemas/GeneralVisitPaging',
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function generalVisitDocProperties(): array
    {
        $properties = [
            'pte_visit_id' => ['type' => 'string', 'nullable' => true],
            'pte_patient_id' => ['type' => 'string', 'nullable' => true],
            'patient_full_name' => ['type' => 'string', 'nullable' => true],
            'patient_first_name' => ['type' => 'string', 'nullable' => true],
            'patient_last_name' => ['type' => 'string', 'nullable' => true],
            'patient_email' => ['type' => 'string', 'nullable' => true],
            'patient_code' => ['type' => 'string', 'nullable' => true],
            'patient_total_appointment_visit' => ['type' => 'integer', 'nullable' => true],
            'date_of_service' => ['type' => 'string', 'format' => 'date', 'nullable' => true],
            'appointment_status' => ['type' => 'string', 'nullable' => true],
            'provider_id' => ['type' => 'string', 'nullable' => true],
            'provider_name' => ['type' => 'string', 'nullable' => true],
            'service_id' => ['type' => 'string', 'nullable' => true],
            'service_name' => ['type' => 'string', 'nullable' => true],
            'location_id' => ['type' => 'string', 'nullable' => true],
            'location_name' => ['type' => 'string', 'nullable' => true],
            'invoice_status' => ['type' => 'string', 'nullable' => true],
            'invoice_number' => ['type' => 'string', 'nullable' => true],
            'current_responsibility' => ['type' => 'string', 'nullable' => true],
            'package_invoice_number' => ['type' => 'string', 'nullable' => true],
            'package_invoice_name' => ['type' => 'string', 'nullable' => true],
            'claim_created_info' => ['type' => 'string', 'nullable' => true],
            'created_by' => ['type' => 'string', 'nullable' => true],
            'reason' => ['type' => 'string', 'nullable' => true],
            'last_update_by' => ['type' => 'string', 'nullable' => true],
            'last_update_date' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
            'cancellation_notice' => ['type' => 'string', 'nullable' => true],
            'treatment_note_id' => ['type' => 'string', 'nullable' => true],
            'treatment_note_number' => ['type' => 'string', 'nullable' => true],
            'units' => ['type' => 'number', 'format' => 'float', 'nullable' => true],
            'charges' => ['type' => 'number', 'format' => 'float', 'nullable' => true],
            'payments' => ['type' => 'number', 'format' => 'float', 'nullable' => true],
            'summary_total_appointments' => ['type' => 'integer', 'nullable' => true],
            'summary_total_charges' => ['type' => 'number', 'format' => 'float', 'nullable' => true],
            'summary_total_payments' => ['type' => 'number', 'format' => 'float', 'nullable' => true],
            'summary_total_units' => ['type' => 'number', 'format' => 'float', 'nullable' => true],
            'summary_total_patients' => ['type' => 'integer', 'nullable' => true],
            'summary_average_charges' => ['type' => 'number', 'format' => 'float', 'nullable' => true],
            'summary_average_payments' => ['type' => 'number', 'format' => 'float', 'nullable' => true],
            'summary_average_units' => ['type' => 'number', 'format' => 'float', 'nullable' => true],
            'created_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
            'updated_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
        ];

        $example = $this->generalVisitDocExample();
        foreach ($properties as $field => $definition) {
            if (array_key_exists($field, $example) && $example[$field] !== null) {
                $properties[$field]['example'] = $example[$field];
            }
        }

        return $properties;
    }

    /**
     * @return array<string, mixed>
     */
    private function generalVisitDocExample(): array
    {
        return [
            'pte_visit_id' => 'visit:abc123',
            'pte_patient_id' => 'patient:xyz789',
            'patient_full_name' => 'Jane Doe',
            'patient_first_name' => 'Jane',
            'patient_last_name' => 'Doe',
            'patient_email' => 'jane@example.com',
            'patient_code' => 'PT-1001',
            'patient_total_appointment_visit' => 5,
            'date_of_service' => '2025-11-10',
            'appointment_status' => 'Completed',
            'provider_id' => 'provider:1',
            'provider_name' => 'Dr. Smith',
            'service_id' => 'service:1',
            'service_name' => 'Physical Therapy',
            'location_id' => 'location:1',
            'location_name' => 'Main Clinic',
            'invoice_status' => 'Open',
            'invoice_number' => 'INV-2025-0001',
            'current_responsibility' => 'Patient',
            'package_invoice_number' => null,
            'package_invoice_name' => null,
            'claim_created_info' => null,
            'created_by' => 'system',
            'reason' => null,
            'last_update_by' => 'system',
            'last_update_date' => '2025-11-10 12:00:00',
            'cancellation_notice' => null,
            'treatment_note_id' => null,
            'treatment_note_number' => null,
            'units' => 1.0,
            'charges' => 120.0,
            'payments' => 80.0,
            'summary_total_appointments' => 12,
            'summary_total_charges' => 3500.25,
            'summary_total_payments' => 2400.75,
            'summary_total_units' => 34.0,
            'summary_total_patients' => 8,
            'summary_average_charges' => 291.69,
            'summary_average_payments' => 200.06,
            'summary_average_units' => 2.83,
            'created_at' => '2025-11-10 12:00:00',
            'updated_at' => '2025-11-10 12:00:00',
        ];
    }
}
