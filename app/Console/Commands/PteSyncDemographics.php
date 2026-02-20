<?php

namespace App\Console\Commands;

use App\Models\PteDemographic;
use App\Services\PtePullHistoryLogger;
use App\Services\PtEverywhereService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PteSyncDemographics extends Command
{
    protected $signature = 'pte:sync-demographics
                            {--limit= : Limit number of records to fetch}
                            {--from= : Start date (Y-m-d)}
                            {--to= : End date (Y-m-d)}
                            {--chunk-days=90 : Number of days per API chunk}
                            {--triggered-by=manual : Trigger source (manual or scheduler)}';

    protected $description = 'Pull demographics report from PtEverywhere API and store locally';

    public function handle(PtEverywhereService $api, PtePullHistoryLogger $historyLogger): int
    {
        $this->info('Fetching demographics from PtEverywhere...');
        $history = null;

        try {
            [$from, $to] = $this->resolveDateRange();
            $limit = $this->parseLimitOption();
            $chunkDays = $this->parseChunkDays();
            $triggeredBy = (string) ($this->option('triggered-by') ?: 'manual');

            $history = $historyLogger->start($this->getName() ?? 'pte:sync-demographics', [
                'source_key' => 'demographics',
                'triggered_by' => $triggeredBy,
                'from_date' => $from,
                'to_date' => $to,
                'options' => [
                    'limit' => $limit,
                    'chunk_days' => $chunkDays,
                ],
            ]);

            [$fetched, $created, $updated, $chunksProcessed, $failedChunks] = $this->syncDemographicsByChunk(
                $api,
                $from,
                $to,
                $chunkDays,
                $limit
            );

            $this->info(
                "Done! Chunks: {$chunksProcessed}, Fetched: {$fetched}, Created: {$created}, Updated: {$updated}"
            );

            $status = $failedChunks > 0 ? 'partial' : 'success';
            if ($history !== null) {
                $historyLogger->finish($history, [
                    'fetched' => $fetched,
                    'created' => $created,
                    'updated' => $updated,
                    'failed_chunks' => $failedChunks,
                    'status' => $status,
                ]);
            }

            if ($failedChunks > 0) {
                $this->warn("Completed with {$failedChunks} failed chunk(s). Re-run the failed date range(s).");

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
        $from = $this->parseDateOption($this->option('from'), 'from') ?? Carbon::create(2024, 1, 1);
        $to = $this->parseDateOption($this->option('to'), 'to') ?? now();

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

    private function parseChunkDays(): int
    {
        $chunkDays = (int) $this->option('chunk-days');
        if ($chunkDays <= 0) {
            throw new \RuntimeException('The --chunk-days option must be greater than 0.');
        }

        return $chunkDays;
    }

    /**
     * Fetch and save demographics rows chunk-by-chunk.
     *
     * @return array{0:int,1:int,2:int,3:int,4:int}
     */
    private function syncDemographicsByChunk(
        PtEverywhereService $api,
        string $from,
        string $to,
        int $chunkDays,
        ?int $limit
    ): array {
        $fetched = 0;
        $created = 0;
        $updated = 0;
        $chunksProcessed = 0;
        $failedChunks = 0;
        $fromDate = Carbon::parse($from);
        $currentEnd = Carbon::parse($to);

        while ($currentEnd->gte($fromDate) && ($limit === null || $fetched < $limit)) {
            $chunkEnd = $currentEnd->copy();
            $chunkStart = $currentEnd->copy()->subDays($chunkDays - 1);
            if ($chunkStart->lt($fromDate)) {
                $chunkStart = $fromDate->copy();
            }

            $chunksProcessed++;
            $this->line("Chunk {$chunksProcessed}: {$chunkStart->toDateString()} to {$chunkEnd->toDateString()}");

            $page = 1;
            $totalPages = 1;
            $fetchBar = $this->output->createProgressBar();
            $fetchBar->setFormat(' %current% pages fetched | %message%');
            $fetchBar->setMessage("starting chunk {$chunksProcessed}");
            $fetchBar->start();

            try {
                do {
                    $response = $api->getDemographics([
                        'from' => $chunkStart->toDateString(),
                        'to' => $chunkEnd->toDateString(),
                        'page' => $page,
                        'size' => 100,
                        'sortType' => 'desc',
                    ]);

                    $docs = $response['docs'] ?? [];
                    if (! is_array($docs)) {
                        break;
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

                        $rowKey = $this->buildRowKey($row);
                        if ($rowKey === null) {
                            continue;
                        }

                        $dependent = is_array($row['dependentOf'] ?? null) ? $row['dependentOf'] : [];

                        $record = PteDemographic::updateOrCreate(
                            ['row_key' => $rowKey],
                            [
                                'pte_patient_id' => $this->toStringOrNull($row['patientId'] ?? null),
                                'first_name' => $this->toStringOrNull($row['firstName'] ?? null),
                                'last_name' => $this->toStringOrNull($row['lastName'] ?? null),
                                'email' => $this->toStringOrNull($row['email'] ?? null),
                                'date_of_birth' => $this->toStringOrNull($row['dateOfBirth'] ?? null),
                                'month_of_birth' => $this->toInteger($row['monthOfBirth'] ?? null),
                                'year_of_birth' => $this->toInteger($row['yearOfBirth'] ?? null),
                                'phone_number' => $this->toStringOrNull($row['phoneNumber'] ?? null),
                                'zip_code' => $this->toStringOrNull($row['zipCode'] ?? null),
                                'address' => $this->toStringOrNull($row['address'] ?? null),
                                'city' => $this->toStringOrNull($row['city'] ?? null),
                                'state' => $this->toStringOrNull($row['state'] ?? null),
                                'insurance_info' => $this->toStringOrNull($row['insuranceInfo'] ?? null),
                                'open_case_str' => $this->toStringOrNull($row['openCaseStr'] ?? null),
                                'close_case_str' => $this->toStringOrNull($row['closeCaseStr'] ?? null),
                                'dependent_of_id' => $this->toStringOrNull($dependent['_id'] ?? null),
                                'dependent_of_first_name' => $this->toStringOrNull(
                                    $dependent['firstName']
                                    ?? $dependent['FirstName']
                                    ?? null
                                ),
                                'dependent_of_last_name' => $this->toStringOrNull(
                                    $dependent['lastName']
                                    ?? $dependent['LastName']
                                    ?? $dependent['LastName ']
                                    ?? null
                                ),
                                'dependent_of_middle_name' => $this->toStringOrNull(
                                    $dependent['middleName']
                                    ?? $dependent['MiddleName']
                                    ?? null
                                ),
                                'dependent_of_email' => $this->toStringOrNull(
                                    $dependent['email']
                                    ?? $dependent['Email']
                                    ?? null
                                ),
                                'raw_data' => $row,
                            ]
                        );

                        $record->wasRecentlyCreated ? $created++ : $updated++;
                        $fetched++;
                    }

                    $totalPages = (int) ($response['totalPages'] ?? $response['total_pages'] ?? 1);
                    $totalPages = max($totalPages, 1);
                    $fetchBar->advance();
                    $fetchBar->setMessage(
                        "chunk {$chunksProcessed} page {$page}/{$totalPages}, got ".count($docs).", saved {$fetched}"
                    );
                    $page++;
                } while (
                    count($docs) > 0
                    && $page <= $totalPages
                    && ($limit === null || $fetched < $limit)
                );
            } catch (\Throwable $e) {
                $failedChunks++;
                $this->newLine();
                $this->error(
                    "Chunk {$chunksProcessed} failed at page {$page} "
                    ."({$chunkStart->toDateString()} to {$chunkEnd->toDateString()}): "
                    .$e->getMessage()
                );
            }

            $fetchBar->finish();
            $this->newLine();
            $currentEnd = $chunkStart->copy()->subDay();
        }

        return [$fetched, $created, $updated, $chunksProcessed, $failedChunks];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function buildRowKey(array $row): ?string
    {
        $patientId = trim((string) ($row['patientId'] ?? ''));
        if ($patientId !== '') {
            return hash('sha256', $patientId);
        }

        $parts = [
            trim((string) ($row['email'] ?? '')),
            trim((string) ($row['firstName'] ?? '')),
            trim((string) ($row['lastName'] ?? '')),
            trim((string) ($row['dateOfBirth'] ?? '')),
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

    private function toStringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $str = trim((string) $value);

        return $str === '' ? null : $str;
    }
}
