<?php

namespace App\Console\Commands;

use App\Models\PteService;
use App\Services\PtePullHistoryLogger;
use App\Services\PtEverywhereService;
use Illuminate\Console\Command;

class PteSyncServices extends Command
{
    protected $signature = 'pte:sync-services
                            {--limit= : Limit number of records to fetch}
                            {--page-size=100 : Number of rows per API page}
                            {--triggered-by=manual : Trigger source (manual or scheduler)}';

    protected $description = 'Pull service master data from PtEverywhere API and store locally';

    public function handle(PtEverywhereService $api, PtePullHistoryLogger $historyLogger): int
    {
        $this->info('Fetching services from PtEverywhere...');
        $history = null;

        try {
            $limit = $this->parseLimitOption();
            $pageSize = $this->parsePageSizeOption();
            $triggeredBy = (string) ($this->option('triggered-by') ?: 'manual');

            $history = $historyLogger->start($this->getName() ?? 'pte:sync-services', [
                'source_key' => 'services',
                'triggered_by' => $triggeredBy,
                'options' => [
                    'limit' => $limit,
                    'page_size' => $pageSize,
                ],
            ]);

            [$fetched, $created, $updated] = $this->syncServices($api, $pageSize, $limit);

            $this->info("Done! Fetched: {$fetched}, Created: {$created}, Updated: {$updated}");
            if ($history !== null) {
                $historyLogger->finish($history, [
                    'fetched' => $fetched,
                    'created' => $created,
                    'updated' => $updated,
                    'status' => 'success',
                ]);
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            if ($history !== null) {
                $historyLogger->finish($history, ['status' => 'failed'], $e);
            }
            $this->error('Failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function parseLimitOption(): ?int
    {
        $limit = $this->option('limit');
        if ($limit === null || $limit === '') {
            return null;
        }

        $parsed = (int) $limit;
        if ($parsed <= 0) {
            throw new \RuntimeException('The --limit option must be greater than 0.');
        }

        return $parsed;
    }

    private function parsePageSizeOption(): int
    {
        $pageSize = (int) $this->option('page-size');
        if ($pageSize <= 0 || $pageSize > 500) {
            throw new \RuntimeException('The --page-size option must be between 1 and 500.');
        }

        return $pageSize;
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private function syncServices(PtEverywhereService $api, int $pageSize, ?int $limit): array
    {
        $fetched = 0;
        $created = 0;
        $updated = 0;
        $page = 1;
        $totalPages = 0;
        $lastPageDigest = null;

        $fetchBar = $this->output->createProgressBar();
        $fetchBar->setFormat(' %current% pages fetched | %message%');
        $fetchBar->setMessage('starting page 1');
        $fetchBar->start();

        do {
            $response = $api->getServices([
                'page' => $page,
                'size' => $pageSize,
            ]);

            $docs = $this->extractDocs($response);
            if ($limit !== null) {
                $remaining = $limit - $fetched;
                if ($remaining <= 0) {
                    break;
                }
                $docs = array_slice($docs, 0, $remaining);
            }

            if (count($docs) === 0) {
                break;
            }

            foreach ($docs as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $rowKey = $this->buildRowKey($row);
                if ($rowKey === null) {
                    continue;
                }

                $record = PteService::updateOrCreate(
                    ['row_key' => $rowKey],
                    [
                        'pte_service_id' => $this->toStringOrNull($row['_id'] ?? $row['id'] ?? null),
                        'service_name' => $this->toStringOrNull($row['name'] ?? $row['serviceName'] ?? null),
                        'service_code' => $this->toStringOrNull($row['serviceCode'] ?? $row['code'] ?? null),
                        'cpt_code' => $this->toStringOrNull(
                            $row['cpt'] ?? $row['cptCode'] ?? $row['procedureCode'] ?? null
                        ),
                        'category' => $this->toStringOrNull(
                            $row['category'] ?? $row['serviceCategory'] ?? $row['groupName'] ?? null
                        ),
                        'duration_minutes' => $this->toInteger(
                            $row['duration'] ?? $row['durationMinutes'] ?? $row['minutes'] ?? null
                        ),
                        'price' => $this->toDecimal(
                            $row['price'] ?? $row['rate'] ?? $row['charge'] ?? $row['cost'] ?? null
                        ),
                        'tax' => $this->toDecimal($row['tax'] ?? $row['taxAmount'] ?? null),
                        'is_active' => $this->toBooleanOrNull(
                            $row['isActive'] ?? $row['active'] ?? $row['status'] ?? null
                        ),
                        'raw_data' => $row,
                    ]
                );

                $record->wasRecentlyCreated ? $created++ : $updated++;
                $fetched++;
            }

            $totalPages = $this->resolveTotalPages($response, $pageSize);
            $totalPagesLabel = $totalPages > 0 ? (string) $totalPages : '?';

            $fetchBar->advance();
            $fetchBar->setMessage("page {$page}/{$totalPagesLabel}, got ".count($docs).", saved {$fetched}");

            if ($limit !== null && $fetched >= $limit) {
                break;
            }

            if ($totalPages === 0) {
                $currentDigest = hash('sha256', json_encode($docs) ?: '');
                if ($lastPageDigest !== null && $currentDigest === $lastPageDigest) {
                    $this->newLine();
                    $this->warn('Pagination metadata is missing and repeated page payload detected. Stopping safely.');

                    break;
                }
                $lastPageDigest = $currentDigest;
            }

            $page++;
            $hasMore = $totalPages > 0
                ? $page <= $totalPages
                : count($docs) >= $pageSize;
        } while ($hasMore);

        $fetchBar->finish();
        $this->newLine();

        return [$fetched, $created, $updated];
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<int, mixed>
     */
    private function extractDocs(array $response): array
    {
        if (array_is_list($response)) {
            return $response;
        }

        $docs = $response['docs'] ?? $response['data'] ?? $response['items'] ?? [];

        return is_array($docs) ? $docs : [];
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function resolveTotalPages(array $response, int $pageSize): int
    {
        $fromTotalPages = $response['totalPages']
            ?? $response['total_pages']
            ?? $response['meta']['totalPages']
            ?? $response['meta']['last_page']
            ?? null;

        if (is_numeric($fromTotalPages) && (int) $fromTotalPages > 0) {
            return (int) $fromTotalPages;
        }

        $fromTotal = $response['totalDocs']
            ?? $response['total_docs']
            ?? $response['total']
            ?? $response['meta']['total']
            ?? null;

        if (is_numeric($fromTotal) && (int) $fromTotal > 0 && $pageSize > 0) {
            return (int) ceil(((int) $fromTotal) / $pageSize);
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function buildRowKey(array $row): ?string
    {
        $serviceId = trim((string) ($row['_id'] ?? $row['id'] ?? ''));
        if ($serviceId !== '') {
            return hash('sha256', $serviceId);
        }

        $parts = [
            trim((string) ($row['name'] ?? $row['serviceName'] ?? '')),
            trim((string) ($row['serviceCode'] ?? $row['code'] ?? '')),
            trim((string) ($row['cpt'] ?? $row['cptCode'] ?? $row['procedureCode'] ?? '')),
        ];

        if (implode('', $parts) === '') {
            return null;
        }

        return hash('sha256', implode('|', $parts));
    }

    private function toInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_string($value)) {
            $normalized = preg_replace('/[^0-9\-]/', '', $value);
            if ($normalized !== null && $normalized !== '' && is_numeric($normalized)) {
                return (int) $normalized;
            }
        }

        return null;
    }

    private function toDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $normalized = preg_replace('/[^0-9.\-]/', '', $value);
            if ($normalized !== null && $normalized !== '' && is_numeric($normalized)) {
                return (float) $normalized;
            }
        }

        return null;
    }

    private function toBooleanOrNull(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['true', '1', 'yes', 'active', 'enabled'], true)) {
                return true;
            }

            if (in_array($normalized, ['false', '0', 'no', 'inactive', 'disabled'], true)) {
                return false;
            }
        }

        return null;
    }

    private function toStringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $str = trim((string) $value);

        return $str === '' ? null : $str;
    }
}
