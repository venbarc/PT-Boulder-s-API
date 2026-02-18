<?php

namespace App\Console\Commands;

use App\Models\PteMasterUser;
use App\Services\PtEverywhereService;
use Illuminate\Console\Command;

class PteSyncMasterUsers extends Command
{
    protected $signature = 'pte:sync-master-users
                            {--limit= : Limit number of records to fetch}
                            {--page-size=100 : Number of rows per API page}';

    protected $description = 'Pull clinic user master data from PtEverywhere API and store locally';

    public function handle(PtEverywhereService $api): int
    {
        $this->info('Fetching master users from PtEverywhere...');

        try {
            $limit = $this->parseLimitOption();
            $pageSize = $this->parsePageSizeOption();
            [$fetched, $created, $updated] = $this->syncUsers($api, $pageSize, $limit);

            $this->info("Done! Fetched: {$fetched}, Created: {$created}, Updated: {$updated}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
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
    private function syncUsers(PtEverywhereService $api, int $pageSize, ?int $limit): array
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
            $response = $api->getClinicUsers([
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

                $record = PteMasterUser::updateOrCreate(
                    ['row_key' => $rowKey],
                    [
                        'pte_user_id' => $this->toStringOrNull($row['_id'] ?? $row['id'] ?? null),
                        'full_name' => $this->toStringOrNull($row['fullName'] ?? $row['name'] ?? null),
                        'first_name' => $this->toStringOrNull($row['firstName'] ?? null),
                        'last_name' => $this->toStringOrNull($row['lastName'] ?? null),
                        'email' => $this->toStringOrNull($row['email'] ?? null),
                        'phone' => $this->toStringOrNull($row['phoneNumber'] ?? $row['phone'] ?? null),
                        'role' => $this->toStringOrNull($row['role'] ?? $row['userRole'] ?? $row['type'] ?? null),
                        'title' => $this->toStringOrNull($row['title'] ?? $row['designation'] ?? null),
                        'status' => $this->toStringOrNull($row['status'] ?? null),
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
        $userId = trim((string) ($row['_id'] ?? $row['id'] ?? ''));
        if ($userId !== '') {
            return hash('sha256', $userId);
        }

        $parts = [
            trim((string) ($row['email'] ?? '')),
            trim((string) ($row['fullName'] ?? $row['name'] ?? '')),
            trim((string) ($row['role'] ?? $row['userRole'] ?? $row['type'] ?? '')),
        ];

        if (implode('', $parts) === '') {
            return null;
        }

        return hash('sha256', implode('|', $parts));
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
