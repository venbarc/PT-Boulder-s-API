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
                            'username' => ['type' => 'string', 'example' => 'joberto24'],
                            'password' => ['type' => 'string', 'example' => 'jobertpass24'],
                        ],
                    ],
                    'AuthTokenResponse' => [
                        'type' => 'object',
                        'properties' => [
                            'accessToken' => ['type' => 'string'],
                        ],
                    ],
                    'GeneralVisitResponse' => [
                        'type' => 'object',
                        'properties' => [
                            'summary' => [
                                'type' => 'object',
                                'properties' => [
                                    'totalAppointments' => ['type' => 'integer'],
                                    'totalCharges' => ['type' => 'number'],
                                    'totalPayments' => ['type' => 'number'],
                                    'totalUnits' => ['type' => 'number'],
                                    'totalPatients' => ['type' => 'integer'],
                                    'averageCharges' => ['type' => 'number'],
                                    'averagePayments' => ['type' => 'number'],
                                    'averageUnits' => ['type' => 'number'],
                                ],
                            ],
                            'docs' => [
                                'type' => 'array',
                                'items' => ['type' => 'object'],
                            ],
                            'paging' => [
                                'type' => 'object',
                                'properties' => [
                                    'page' => ['type' => 'integer'],
                                    'perPage' => ['type' => 'integer'],
                                    'total' => ['type' => 'integer'],
                                    'totalPages' => ['type' => 'integer'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
