<?php

namespace App\Console\Commands;

use App\Models\PteDemographic;
use App\Services\PtEverywhereService;
use Illuminate\Console\Command;

class PteSyncDemographics extends Command
{
    protected $signature = 'pte:sync-demographics
                            {--limit= : Limit number of records to fetch}
                            {--from-year=2024 : Start year}
                            {--to-year= : End year (defaults to current year)}
                            {--chunk-years=1 : Number of years per API chunk}';

    protected $description = 'Pull demographics report from PtEverywhere API and store locally';

    public function handle(PtEverywhereService $api): int
    {
        $this->info('Fetching demographics from PtEverywhere...');

        try {
            [$fromYear, $toYear] = $this->resolveYearRange();
            $limit = $this->parseLimitOption();
            $chunkYears = $this->parseChunkYears();

            [$fetched, $created, $updated, $chunksProcessed, $failedChunks] = $this->syncDemographicsByChunk(
                $api,
                $fromYear,
                $toYear,
                $chunkYears,
                $limit
            );

            $this->info(
                "Done! Chunks: {$chunksProcessed}, Fetched: {$fetched}, Created: {$created}, Updated: {$updated}"
            );

            if ($failedChunks > 0) {
                $this->warn("Completed with {$failedChunks} failed chunk(s). Re-run the failed year range(s).");

                return self::FAILURE;
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @return array{0:int,1:int}
     */
    private function resolveYearRange(): array
    {
        $fromYear = $this->parseYearOption($this->option('from-year'), 'from-year') ?? 2024;
        $toYear = $this->parseYearOption($this->option('to-year'), 'to-year') ?? (int) now()->year;

        if ($fromYear > $toYear) {
            throw new \RuntimeException('The --from-year value must be less than or equal to --to-year.');
        }

        return [$fromYear, $toYear];
    }

    private function parseYearOption(mixed $value, string $name): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            throw new \RuntimeException("Invalid --{$name} value. Use a 4-digit year.");
        }

        $year = (int) $value;
        if ($year < 1900 || $year > 2100) {
            throw new \RuntimeException("Invalid --{$name} value. Use a year between 1900 and 2100.");
        }

        return $year;
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

    private function parseChunkYears(): int
    {
        $chunkYears = (int) $this->option('chunk-years');
        if ($chunkYears <= 0) {
            throw new \RuntimeException('The --chunk-years option must be greater than 0.');
        }

        return $chunkYears;
    }

    /**
     * Fetch and save demographics rows chunk-by-chunk.
     *
     * @return array{0:int,1:int,2:int,3:int,4:int}
     */
    private function syncDemographicsByChunk(
        PtEverywhereService $api,
        int $fromYear,
        int $toYear,
        int $chunkYears,
        ?int $limit
    ): array {
        $fetched = 0;
        $created = 0;
        $updated = 0;
        $chunksProcessed = 0;
        $failedChunks = 0;
        $year = $toYear;

        while ($year >= $fromYear && ($limit === null || $fetched < $limit)) {
            $chunkTo = $year;
            $chunkFrom = max($year - $chunkYears + 1, $fromYear);

            $chunksProcessed++;
            $this->line("Chunk {$chunksProcessed}: {$chunkFrom} to {$chunkTo}");

            $page = 1;
            $totalPages = 1;
            $fetchBar = $this->output->createProgressBar();
            $fetchBar->setFormat(' %current% pages fetched | %message%');
            $fetchBar->setMessage("starting chunk {$chunksProcessed}");
            $fetchBar->start();

            try {
                do {
                    $response = $api->getDemographics([
                        'fromYear' => $chunkFrom,
                        'toYear' => $chunkTo,
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
                                'pte_patient_id' => $this->toStringOrNull($row['_id'] ?? null),
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
                    ."({$chunkFrom} to {$chunkTo}): "
                    .$e->getMessage()
                );
            }

            $fetchBar->finish();
            $this->newLine();
            $year = $chunkFrom - 1;
        }

        return [$fetched, $created, $updated, $chunksProcessed, $failedChunks];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function buildRowKey(array $row): ?string
    {
        $patientId = trim((string) ($row['_id'] ?? ''));
        if ($patientId !== '') {
            return hash('sha256', $patientId);
        }

        $parts = [
            trim((string) ($row['email'] ?? '')),
            trim((string) ($row['firstName'] ?? '')),
            trim((string) ($row['lastName'] ?? '')),
            trim((string) ($row['dateOfBirth'] ?? '')),
            trim((string) ($row['zipCode'] ?? '')),
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
