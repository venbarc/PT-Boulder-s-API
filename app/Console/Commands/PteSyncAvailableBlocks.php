<?php

namespace App\Console\Commands;

use App\Models\PteAvailableBlock;
use App\Models\PteTherapist;
use App\Services\PtePullHistoryLogger;
use App\Services\PtEverywhereService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PteSyncAvailableBlocks extends Command
{
    protected $signature = 'pte:sync-available-blocks
                            {--from= : Start date (Y-m-d)}
                            {--to= : End date (Y-m-d)}
                            {--therapist= : Therapist ID (single or comma-separated list)}
                            {--all-therapists=1 : Pull for all therapist IDs found in pte_therapists (1 or 0)}
                            {--limit= : Limit number of records to fetch}
                            {--triggered-by=manual : Trigger source (manual or scheduler)}';

    protected $description = 'Pull available appointment blocks from PtEverywhere API and store locally';

    public function handle(PtEverywhereService $api, PtePullHistoryLogger $historyLogger): int
    {
        $this->info('Fetching available appointment blocks from PtEverywhere...');
        $history = null;

        try {
            [$from, $to] = $this->resolveDateRange();
            $limit = $this->parseLimitOption();
            $triggeredBy = (string) ($this->option('triggered-by') ?: 'manual');
            $therapistIds = $this->resolveTherapistIds();

            if ($therapistIds === []) {
                throw new \RuntimeException(
                    'No therapist IDs resolved. Run php artisan pte:sync-therapists first or pass --therapist=<id>.'
                );
            }

            $history = $historyLogger->start($this->getName() ?? 'pte:sync-available-blocks', [
                'source_key' => 'available_blocks',
                'triggered_by' => $triggeredBy,
                'from_date' => $from,
                'to_date' => $to,
                'options' => [
                    'limit' => $limit,
                    'therapist_count' => count($therapistIds),
                ],
            ]);

            [$fetched, $created, $updated, $processedTherapists, $failedTherapists] = $this->syncAvailableBlocks(
                $api,
                $from,
                $to,
                $therapistIds,
                $limit
            );

            $this->info(
                "Done! Therapists: {$processedTherapists}, Fetched: {$fetched}, "
                ."Created: {$created}, Updated: {$updated}"
            );

            $status = $failedTherapists > 0 ? 'partial' : 'success';
            if ($history !== null) {
                $historyLogger->finish($history, [
                    'fetched' => $fetched,
                    'created' => $created,
                    'updated' => $updated,
                    'failed_chunks' => $failedTherapists,
                    'status' => $status,
                ]);
            }

            if ($failedTherapists > 0) {
                $this->warn("Completed with {$failedTherapists} failed therapist request(s).");

                return self::FAILURE;
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

    /**
     * @return array{0:string,1:string}
     */
    private function resolveDateRange(): array
    {
        $from = $this->parseDateOption($this->option('from'), 'from') ?? now();
        $to = $this->parseDateOption($this->option('to'), 'to') ?? now()->addDays(30);

        if ($from->gt($to)) {
            throw new \RuntimeException('The --from date must be before or equal to --to.');
        }

        return [$from->toDateString(), $to->toDateString()];
    }

    private function parseDateOption(?string $value, string $name): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $value);
        } catch (\Throwable) {
            throw new \RuntimeException("Invalid --{$name} date format. Use YYYY-MM-DD.");
        }

        if ($date->format('Y-m-d') !== $value) {
            throw new \RuntimeException("Invalid --{$name} date format. Use YYYY-MM-DD.");
        }

        return $date;
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

    /**
     * @return array<int, string>
     */
    private function resolveTherapistIds(): array
    {
        $therapistOption = trim((string) ($this->option('therapist') ?? ''));
        if ($therapistOption !== '') {
            $ids = array_filter(array_map('trim', explode(',', $therapistOption)));

            return array_values(array_unique($ids));
        }

        $allTherapists = (string) ($this->option('all-therapists') ?? '1') !== '0';
        if (! $allTherapists) {
            return [];
        }

        return PteTherapist::query()
            ->whereNotNull('pte_therapist_id')
            ->where('pte_therapist_id', '<>', '')
            ->pluck('pte_therapist_id')
            ->map(static fn ($id): string => trim((string) $id))
            ->filter(static fn (string $id): bool => $id !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $therapistIds
     * @return array{0:int,1:int,2:int,3:int,4:int}
     */
    private function syncAvailableBlocks(
        PtEverywhereService $api,
        string $from,
        string $to,
        array $therapistIds,
        ?int $limit
    ): array {
        $fetched = 0;
        $created = 0;
        $updated = 0;
        $processedTherapists = 0;
        $failedTherapists = 0;

        foreach ($therapistIds as $therapistId) {
            if ($limit !== null && $fetched >= $limit) {
                break;
            }

            $processedTherapists++;
            $this->line("Therapist {$processedTherapists}/".count($therapistIds).": {$therapistId}");

            try {
                $response = $api->getAvailableBlocks([
                    'startDate' => $from,
                    'endDate' => $to,
                    'therapist' => $therapistId,
                ]);
            } catch (\Throwable $e) {
                $failedTherapists++;
                $this->error("Therapist {$therapistId} failed: ".$e->getMessage());

                continue;
            }

            $docs = $this->extractDocs($response);
            if ($docs === []) {
                continue;
            }

            if ($limit !== null) {
                $remaining = $limit - $fetched;
                if ($remaining <= 0) {
                    break;
                }
                $docs = array_slice($docs, 0, $remaining);
            }

            foreach ($docs as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $location = is_array($row['location'] ?? null) ? $row['location'] : [];
                $service = is_array($row['service'] ?? null) ? $row['service'] : [];
                $rowKey = $this->buildRowKey($therapistId, $row, $location, $service);
                if ($rowKey === null) {
                    continue;
                }

                $record = PteAvailableBlock::updateOrCreate(
                    ['row_key' => $rowKey],
                    [
                        'pte_therapist_id' => $therapistId,
                        'start_datetime' => $this->parseDateTimeToSql($row['startDateTime'] ?? null),
                        'end_datetime' => $this->parseDateTimeToSql($row['endDateTime'] ?? null),
                        'location_id' => $this->toStringOrNull($location['_id'] ?? null),
                        'location_name' => $this->toStringOrNull($location['name'] ?? null),
                        'service_id' => $this->toStringOrNull($service['_id'] ?? null),
                        'service_name' => $this->toStringOrNull($service['name'] ?? null),
                        'request_start_date' => $from,
                        'request_end_date' => $to,
                        'raw_data' => $row,
                    ]
                );

                $record->wasRecentlyCreated ? $created++ : $updated++;
                $fetched++;
            }
        }

        return [$fetched, $created, $updated, $processedTherapists, $failedTherapists];
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
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $location
     * @param  array<string, mixed>  $service
     */
    private function buildRowKey(string $therapistId, array $row, array $location, array $service): ?string
    {
        $parts = [
            trim($therapistId),
            trim((string) ($row['startDateTime'] ?? '')),
            trim((string) ($row['endDateTime'] ?? '')),
            trim((string) ($location['_id'] ?? $location['name'] ?? '')),
            trim((string) ($service['_id'] ?? $service['name'] ?? '')),
        ];

        if (implode('', $parts) === '') {
            return null;
        }

        return hash('sha256', implode('|', $parts));
    }

    private function parseDateTimeToSql(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
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
