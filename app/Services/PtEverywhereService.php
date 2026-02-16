<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PtEverywhereService
{
    protected string $baseUrl;

    protected string $username;

    protected string $password;

    protected int $tokenTtl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('pteverywhere.base_url'), '/');
        $this->username = config('pteverywhere.username');
        $this->password = config('pteverywhere.password');
        $this->tokenTtl = config('pteverywhere.token_ttl', 55);
    }

    /**
     * Build a base HTTP client with TLS options from config.
     */
    protected function apiRequest(): PendingRequest
    {
        $request = Http::timeout(30);
        $sslVerify = (bool) config('pteverywhere.ssl_verify', true);
        $caBundle = config('pteverywhere.ca_bundle');
        $proxy = config('pteverywhere.proxy', '');

        $options = [];
        if ($proxy !== null) {
            $options['proxy'] = $proxy;
        }

        if (is_string($caBundle) && trim($caBundle) !== '') {
            $options['verify'] = trim($caBundle);

            return $request->withOptions($options);
        }

        $options['verify'] = $sslVerify;

        // On Windows, prefer the native trust store when no CA bundle is provided.
        if (
            $sslVerify
            && PHP_OS_FAMILY === 'Windows'
            && defined('CURLOPT_SSL_OPTIONS')
            && defined('CURLSSLOPT_NATIVE_CA')
        ) {
            $options['curl'] = [
                CURLOPT_SSL_OPTIONS => CURLSSLOPT_NATIVE_CA,
            ];
        }

        return $request->withOptions($options);
    }

    /**
     * Authenticate and retrieve a bearer token from the PtEverywhere API.
     * The token is cached to avoid re-authenticating on every request.
     */
    public function getToken(): string
    {
        return Cache::remember('pte_access_token', $this->tokenTtl * 60, function () {
            Log::info('PtEverywhere: Authenticating...');
            try {
                // Swagger contract: POST /auth/token with JSON { email, password }.
                $response = $this->apiRequest()->post("{$this->baseUrl}/auth/token", [
                    'email' => $this->username,
                    'password' => $this->password,
                ]);
            } catch (ConnectionException $e) {
                Log::error('PtEverywhere: Connection failed during authentication', [
                    'message' => $e->getMessage(),
                ]);
                throw new \RuntimeException(
                    'PtEverywhere connection failed while authenticating. '
                    .'Set PTE_API_CA_BUNDLE to a valid CA bundle file (cacert.pem) or fix your local CA trust store. '
                    .'Original error: '.$e->getMessage(),
                    0,
                    $e
                );
            }

            if (! $response->successful()) {
                Log::error('PtEverywhere: Authentication failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \RuntimeException(
                    "PtEverywhere authentication failed: {$response->status()} - {$response->body()}"
                );
            }

            $data = $response->json();
            $token = $this->extractToken($data);

            if (! $token) {
                $upstreamError = is_array($data) ? ($data['error'] ?? $data['message'] ?? null) : null;
                Log::error('PtEverywhere: No token in auth response', ['data' => $data]);

                if (is_string($upstreamError) && $upstreamError !== '') {
                    throw new \RuntimeException("PtEverywhere auth returned no token: {$upstreamError}");
                }

                throw new \RuntimeException('PtEverywhere: Could not extract token from auth response');
            }

            Log::info('PtEverywhere: Authenticated successfully');

            return $token;
        });
    }

    /**
     * Extract a bearer token from known auth response shapes.
     */
    protected function extractToken(?array $data): ?string
    {
        if (! is_array($data)) {
            return null;
        }

        return $data['access_token']
            ?? $data['accessToken']
            ?? $data['token']
            ?? $data['id_token']
            ?? $data['data']['token']
            ?? $data['data']['access_token']
            ?? $data['data']['accessToken']
            ?? null;
    }

    /**
     * Clear the cached authentication token (e.g., on 401 errors).
     */
    public function clearToken(): void
    {
        Cache::forget('pte_access_token');
    }

    /**
     * Make an authenticated GET request to the PtEverywhere API.
     */
    public function get(string $endpoint, array $query = []): array
    {
        return $this->request('GET', $endpoint, ['query' => $query]);
    }

    /**
     * Make an authenticated POST request to the PtEverywhere API.
     */
    public function post(string $endpoint, array $data = []): array
    {
        return $this->request('POST', $endpoint, ['json' => $data]);
    }

    /**
     * Make an authenticated request with automatic token refresh on 401.
     */
    protected function request(string $method, string $endpoint, array $options = []): array
    {
        $url = "{$this->baseUrl}/".ltrim($endpoint, '/');
        $token = $this->getToken();
        try {
            $response = $this->apiRequest()
                ->withHeaders(['Authorization' => $token])
                ->send($method, $url, $options);

            // If unauthorized, clear token cache and retry once
            if ($response->status() === 401) {
                Log::warning('PtEverywhere: Token expired, re-authenticating...');
                $this->clearToken();
                $token = $this->getToken();

                $response = $this->apiRequest()
                    ->withHeaders(['Authorization' => $token])
                    ->send($method, $url, $options);
            }
        } catch (ConnectionException $e) {
            Log::error("PtEverywhere connection error: {$method} {$endpoint}", [
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException(
                'PtEverywhere connection failed. If you are on Windows, verify CA certificates or set PTE_API_CA_BUNDLE. '
                .'Original error: '.$e->getMessage(),
                0,
                $e
            );
        }

        if (! $response->successful()) {
            Log::error("PtEverywhere API error: {$method} {$endpoint}", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException(
                "PtEverywhere API error ({$response->status()}): {$response->body()}"
            );
        }

        return $response->json() ?? [];
    }

    /**
     * Fetch all patients/clients.
     */
    public function getPatients(array $params = []): array
    {
        return $this->get('/masterdata/patients', $params);
    }

    /**
     * Fetch a single patient by ID.
     */
    public function getPatient(int|string $id): array
    {
        return $this->get("/masterdata/patients/{$id}");
    }

    /**
     * Fetch appointment report rows (general visit report).
     */
    public function getAppointments(array $params = []): array
    {
        $from = $params['from'] ?? $params['start_date'] ?? now()->subDays(30)->toDateString();
        $to = $params['to'] ?? $params['end_date'] ?? now()->toDateString();
        $page = (int) ($params['page'] ?? 1);
        $size = (int) ($params['size'] ?? $params['per_page'] ?? 100);

        return $this->post('/report/general-visit', [
            'dateOfService' => [
                'startDate' => $from,
                'endDate' => $to,
            ],
            'paging' => [
                'page' => $page,
                'pageSize' => $size,
            ],
        ]);
    }

    /**
     * Fetch a single appointment by ID.
     */
    public function getAppointment(int|string $id): array
    {
        throw new \RuntimeException(
            'Single appointment endpoint is not available in PtEverywhere v2 OpenAPI. '
            .'Use getAppointments() report filters and find by _id in results.'
        );
    }

    /**
     * Fetch invoice-related rows from general visit report.
     */
    public function getInvoices(array $params = []): array
    {
        $from = $params['from'] ?? $params['start_date'] ?? now()->subDays(30)->toDateString();
        $to = $params['to'] ?? $params['end_date'] ?? now()->toDateString();
        $page = (int) ($params['page'] ?? 1);
        $size = (int) ($params['size'] ?? $params['per_page'] ?? 100);

        return $this->post('/report/general-visit', [
            'dateOfService' => [
                'startDate' => $from,
                'endDate' => $to,
            ],
            'paging' => [
                'page' => $page,
                'pageSize' => $size,
            ],
        ]);
    }

    /**
     * Fetch general visit report rows.
     */
    public function getGeneralVisit(array $params = []): array
    {
        $from = $params['from'] ?? $params['start_date'] ?? now()->subDays(30)->toDateString();
        $to = $params['to'] ?? $params['end_date'] ?? now()->toDateString();
        $page = (int) ($params['page'] ?? 1);
        $size = (int) ($params['size'] ?? $params['per_page'] ?? 100);

        return $this->post('/report/general-visit', [
            'dateOfService' => [
                'startDate' => $from,
                'endDate' => $to,
            ],
            'paging' => [
                'page' => $page,
                'pageSize' => $size,
            ],
        ]);
    }

    /**
     * Fetch provider revenue report rows (includes CPT in payload).
     */
    public function getProviderRevenue(array $params = []): array
    {
        $from = $params['from'] ?? $params['start_date'] ?? now()->subDays(30)->toDateString();
        $to = $params['to'] ?? $params['end_date'] ?? now()->toDateString();
        $page = (int) ($params['page'] ?? 1);
        $size = (int) ($params['size'] ?? $params['per_page'] ?? 100);

        return $this->post('/report/provider-revenue', [
            'startDate' => $from,
            'endDate' => $to,
            'locations' => $params['locations'] ?? [],
            'providers' => $params['providers'] ?? [],
            'services' => $params['services'] ?? [],
            'packagesMemberships' => $params['packagesMemberships'] ?? [],
            'inventories' => $params['inventories'] ?? [],
            'searchStr' => $params['searchStr'] ?? '',
            'sorting' => [
                'sortBy' => $params['sortBy'] ?? '',
                'sortType' => $params['sortType'] ?? 'asc',
            ],
            'paging' => [
                'page' => $page,
                'pageSize' => $size,
            ],
        ]);
    }

    /**
     * Fetch a single invoice by ID.
     */
    public function getInvoice(int|string $id): array
    {
        throw new \RuntimeException(
            'Single invoice endpoint is not available in PtEverywhere v2 OpenAPI. '
            .'Use getInvoices() report filters and match invoice.invoiceNo in results.'
        );
    }

    /**
     * Fetch all services.
     */
    public function getServices(array $params = []): array
    {
        return $this->get('/masterdata/services', $params);
    }

    /**
     * Fetch all locations.
     */
    public function getLocations(array $params = []): array
    {
        return $this->get('/masterdata/locations', $params);
    }

    /**
     * Generic paginated fetcher -- pulls all pages for an endpoint.
     */
    public function getAllPaginated(string $endpoint, array $params = [], string $dataKey = 'data'): array
    {
        $allItems = [];
        $page = 1;
        $params['size'] = $params['size'] ?? ($params['per_page'] ?? 100);

        do {
            $requestParams = $params;
            $requestParams['page'] = $page;
            $response = $this->get($endpoint, $requestParams);

            $items = $response['docs'] ?? $response[$dataKey] ?? $response['items'] ?? $response;

            if (! is_array($items)) {
                break;
            }

            $allItems = array_merge($allItems, $items);

            $lastPage = $response['last_page']
                ?? $response['meta']['last_page']
                ?? $response['total_pages']
                ?? $response['totalPages']
                ?? null;
            $currentPage = $response['current_page']
                ?? $response['meta']['current_page']
                ?? $response['page']
                ?? $response['pageIndex']
                ?? $page;
            $hasPaginationMeta = $lastPage !== null;
            $hasMore = $hasPaginationMeta
                ? (int) $currentPage < (int) $lastPage
                : false;

            $page++;
        } while ($hasMore && count($items) > 0);

        return $allItems;
    }
}
