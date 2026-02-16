<?php

namespace App\Console\Commands;

use App\Models\PteProviderRevenue;
use App\Services\PtEverywhereService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PteSyncProviderRevenue extends Command
{
    protected $signature = 'pte:sync-provider-revenue
                            {--limit= : Limit number of records to fetch}
                            {--from= : Start date (Y-m-d)}
                            {--to= : End date (Y-m-d)}
                            {--chunk-days=90 : Number of days per API chunk}';

    protected $description = 'Pull provider revenue report from PtEverywhere API and store locally';

    public function handle(PtEverywhereService $api): int
    {
        $this->info('Fetching provider revenue from PtEverywhere...');

        try {
            [$from, $to] = $this->resolveDateRange();
            $limit = $this->parseLimitOption();
            $chunkDays = $this->parseChunkDays();

            [$fetched, $created, $updated, $chunksProcessed, $failedChunks] = $this->syncProviderRevenueByChunk(
                $api,
                $from,
                $to,
                $chunkDays,
                $limit
            );

            $this->info(
                "Done! Chunks: {$chunksProcessed}, Fetched: {$fetched}, Created: {$created}, Updated: {$updated}"
            );

            if ($failedChunks > 0) {
                $this->warn("Completed with {$failedChunks} failed chunk(s). Re-run the failed date range(s).");

                return self::FAILURE;
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
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
     * Fetch and save provider-revenue rows chunk-by-chunk.
     *
     * @return array{0:int,1:int,2:int,3:int,4:int}
     */
    private function syncProviderRevenueByChunk(
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
        $start = Carbon::parse($from);
        $end = Carbon::parse($to);

        while ($start->lte($end) && ($limit === null || $fetched < $limit)) {
            $chunkStart = $start->copy();
            $chunkEnd = $start->copy()->addDays($chunkDays - 1);
            if ($chunkEnd->gt($end)) {
                $chunkEnd = $end->copy();
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
                    $response = $api->getProviderRevenue([
                        'from' => $chunkStart->toDateString(),
                        'to' => $chunkEnd->toDateString(),
                        'page' => $page,
                        'size' => 100,
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

                        $record = PteProviderRevenue::updateOrCreate(
                            ['row_key' => $rowKey],
                            [
                                'pte_patient_id' => $this->toStringOrNull($row['patientId'] ?? null),
                                'patient_name' => $this->toStringOrNull($row['patientName'] ?? null),
                                'therapist_name' => $this->toStringOrNull($row['therapistName'] ?? null),
                                'location_name' => $this->toStringOrNull($row['location'] ?? null),
                                'patient_email' => $this->toStringOrNull($row['patientEmail'] ?? null),
                                'date_of_service' => $row['dateOfService'] ?? null,
                                'revenue' => $this->toDecimal($row['revenue'] ?? null),
                                'adjustment' => $this->toDecimal($row['adjustment'] ?? null),
                                'collected' => $this->toDecimal($row['collected'] ?? null),
                                'due_amount' => $this->toDecimal($row['dueAmount'] ?? null),
                                'cpt' => $this->toStringOrNull($row['cpt'] ?? null),
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
            $start = $chunkEnd->copy()->addDay();
        }

        return [$fetched, $created, $updated, $chunksProcessed, $failedChunks];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function buildRowKey(array $row): ?string
    {
        $parts = [
            trim((string) ($row['patientId'] ?? '')),
            trim((string) ($row['dateOfService'] ?? '')),
            trim((string) ($row['cpt'] ?? '')),
            trim((string) ($row['therapistName'] ?? '')),
            trim((string) ($row['location'] ?? '')),
        ];

        if (implode('', $parts) === '') {
            return null;
        }

        return hash('sha256', implode('|', $parts));
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

    private function toStringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $str = trim((string) $value);

        return $str === '' ? null : $str;
    }
}
