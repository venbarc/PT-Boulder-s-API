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
                            {--page-size=100 : Number of rows per API page}
                            {--chunk-days= : Deprecated for demographics (ignored)}
                            {--triggered-by=manual : Trigger source (manual or scheduler)}';

    protected $description = 'Pull demographics report from PtEverywhere API and store locally';

    public function handle(PtEverywhereService $api, PtePullHistoryLogger $historyLogger): int
    {
        $this->info('Fetching demographics from PtEverywhere...');
        $history = null;

        try {
            [$from, $to] = $this->resolveDateRange();
            $limit = $this->parseLimitOption();
            $pageSize = $this->parsePageSizeOption();
            $triggeredBy = (string) ($this->option('triggered-by') ?: 'manual');

            $history = $historyLogger->start($this->getName() ?? 'pte:sync-demographics', [
                'source_key' => 'demographics',
                'triggered_by' => $triggeredBy,
                'from_date' => $from,
                'to_date' => $to,
                'options' => [
                    'limit' => $limit,
                    'page_size' => $pageSize,
                ],
            ]);

            [$fetched, $created, $updated, $windowsProcessed, $failedWindows] = $this->syncDemographicsByWindow(
                $api,
                $from,
                $to,
                $pageSize,
                $limit
            );

            $this->info(
                "Done! Windows: {$windowsProcessed}, Fetched: {$fetched}, Created: {$created}, Updated: {$updated}"
            );

            $status = $failedWindows > 0 ? 'partial' : 'success';
            if ($history !== null) {
                $historyLogger->finish($history, [
                    'fetched' => $fetched,
                    'created' => $created,
                    'updated' => $updated,
                    'failed_chunks' => $failedWindows,
                    'status' => $status,
                ]);
            }

            if ($failedWindows > 0) {
                $this->warn("Completed with {$failedWindows} failed window(s). Re-run the failed date range(s).");

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
        $from = $this->parseDateOption($this->option('from'), 'from') ?? Carbon::create(1970, 1, 1);
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

    private function parsePageSizeOption(): int
    {
        $pageSize = (int) $this->option('page-size');
        if ($pageSize <= 0 || $pageSize > 500) {
            throw new \RuntimeException('The --page-size option must be between 1 and 500.');
        }

        return $pageSize;
    }

    /**
     * Fetch and save demographics rows by year-month windows.
     *
     * @return array{0:int,1:int,2:int,3:int,4:int}
     */
    private function syncDemographicsByWindow(
        PtEverywhereService $api,
        string $from,
        string $to,
        int $pageSize,
        ?int $limit
    ): array {
        $fetched = 0;
        $created = 0;
        $updated = 0;
        $windowsProcessed = 0;
        $failedWindows = 0;
        $windows = $this->buildYearMonthWindows($from, $to);

        foreach ($windows as $window) {
            if ($limit !== null && $fetched >= $limit) {
                break;
            }

            $windowsProcessed++;
            $windowLabel = "{$window['from_date']} to {$window['to_date']}";
            $this->line("Window {$windowsProcessed}: {$windowLabel}");

            $page = 1;
            $totalPages = 0;
            $lastPageDigest = null;
            $fetchBar = $this->output->createProgressBar();
            $fetchBar->setFormat(' %current% pages fetched | %message%');
            $fetchBar->setMessage("starting window {$windowsProcessed}");
            $fetchBar->start();

            try {
                do {
                    $response = $api->getDemographics([
                        'fromYear' => $window['from_year'],
                        'toYear' => $window['to_year'],
                        'listMonth' => $window['months'],
                        'page' => $page,
                        'size' => $pageSize,
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
                                'pte_patient_id' => $this->toStringOrNull($row['_id'] ?? $row['patientId'] ?? null),
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

                    $totalPages = $this->resolveTotalPages($response, $pageSize);
                    $totalPagesLabel = $totalPages > 0 ? (string) $totalPages : '?';
                    $fetchBar->advance();
                    $fetchBar->setMessage(
                        "window {$windowsProcessed} page {$page}/{$totalPagesLabel}, got "
                        .count($docs).", saved {$fetched}"
                    );

                    if ($limit !== null && $fetched >= $limit) {
                        break;
                    }

                    if ($totalPages === 0) {
                        $currentDigest = hash('sha256', json_encode($docs) ?: '');
                        if ($lastPageDigest !== null && $currentDigest === $lastPageDigest) {
                            $this->newLine();
                            $this->warn(
                                'Pagination metadata is missing and repeated page payload detected. Stopping safely.'
                            );

                            break;
                        }
                        $lastPageDigest = $currentDigest;
                    }

                    $page++;
                } while (
                    count($docs) > 0
                    && ($totalPages > 0 ? $page <= $totalPages : count($docs) >= $pageSize)
                    && ($limit === null || $fetched < $limit)
                );
            } catch (\Throwable $e) {
                $failedWindows++;
                $this->newLine();
                $this->error(
                    "Window {$windowsProcessed} failed at page {$page} "
                    ."({$windowLabel}): "
                    .$e->getMessage()
                );
            }

            $fetchBar->finish();
            $this->newLine();
        }

        return [$fetched, $created, $updated, $windowsProcessed, $failedWindows];
    }

    /**
     * @return array<int, array{
     *     from_year:int,
     *     to_year:int,
     *     months: array<int, int>,
     *     from_date: string,
     *     to_date: string
     * }>
     */
    private function buildYearMonthWindows(string $from, string $to): array
    {
        $fromDate = Carbon::parse($from);
        $toDate = Carbon::parse($to);
        $fromYear = (int) $fromDate->year;
        $toYear = (int) $toDate->year;
        $windows = [];

        for ($year = $toYear; $year >= $fromYear; $year--) {
            if ($year === $fromYear && $year === $toYear) {
                $months = range((int) $fromDate->month, (int) $toDate->month);
                $windowFromDate = $fromDate->toDateString();
                $windowToDate = $toDate->toDateString();
            } elseif ($year === $toYear) {
                $months = range(1, (int) $toDate->month);
                $windowFromDate = Carbon::create($year, 1, 1)->toDateString();
                $windowToDate = $toDate->toDateString();
            } elseif ($year === $fromYear) {
                $months = range((int) $fromDate->month, 12);
                $windowFromDate = $fromDate->toDateString();
                $windowToDate = Carbon::create($year, 12, 31)->toDateString();
            } else {
                $months = range(1, 12);
                $windowFromDate = Carbon::create($year, 1, 1)->toDateString();
                $windowToDate = Carbon::create($year, 12, 31)->toDateString();
            }

            $windows[] = [
                'from_year' => $year,
                'to_year' => $year,
                'months' => $months,
                'from_date' => $windowFromDate,
                'to_date' => $windowToDate,
            ];
        }

        return $windows;
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
        $patientId = trim((string) ($row['_id'] ?? $row['patientId'] ?? ''));
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
