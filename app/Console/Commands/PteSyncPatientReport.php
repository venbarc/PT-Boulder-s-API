<?php

namespace App\Console\Commands;

use App\Models\PtePatientReport;
use App\Services\PtEverywhereService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PteSyncPatientReport extends Command
{
    protected $signature = 'pte:sync-patient-report
                            {--limit= : Limit number of records to fetch}
                            {--from= : Start date (Y-m-d)}
                            {--to= : End date (Y-m-d)}
                            {--chunk-days=90 : Number of days per API chunk}';

    protected $description = 'Pull patient report from PtEverywhere API and store locally';

    public function handle(PtEverywhereService $api): int
    {
        $this->info('Fetching patient report from PtEverywhere...');

        try {
            [$from, $to] = $this->resolveDateRange();
            $limit = $this->parseLimitOption();
            $chunkDays = $this->parseChunkDays();

            [$fetched, $created, $updated, $chunksProcessed, $failedChunks] = $this->syncPatientReportByChunk(
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
     * Fetch and save patient report rows chunk-by-chunk.
     *
     * @return array{0:int,1:int,2:int,3:int,4:int}
     */
    private function syncPatientReportByChunk(
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
                    $response = $api->getPatientReport([
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

                        $firstAppointment = is_array($row['firstAppointment'] ?? null) ? $row['firstAppointment'] : [];
                        $lastAppointment = is_array($row['lastAppointment'] ?? null) ? $row['lastAppointment'] : [];
                        $nextAppointment = is_array($row['nextAppointment'] ?? null) ? $row['nextAppointment'] : [];
                        $lastTherapist = is_array($row['lastTherapist'] ?? null) ? $row['lastTherapist'] : [];
                        $dependent = is_array($row['dependentOf'] ?? null) ? $row['dependentOf'] : [];

                        $record = PtePatientReport::updateOrCreate(
                            ['row_key' => $rowKey],
                            [
                                'pte_patient_id' => $this->toStringOrNull($row['patientId'] ?? null),
                                'patient_name' => $this->toStringOrNull($row['patientName'] ?? null),
                                'email' => $this->toStringOrNull($row['email'] ?? null),
                                'phone' => $this->toStringOrNull($row['phone'] ?? null),
                                'registration_date_str' => $this->toStringOrNull($row['registrationDateStr'] ?? null),
                                'registration_by' => $this->toStringOrNull($row['registrationBy'] ?? null),
                                'first_login_date_str' => $this->toStringOrNull($row['firstLoginDateStr'] ?? null),
                                'last_login_date_str' => $this->toStringOrNull($row['lastLoginDateStr'] ?? null),
                                'total_last_logins' => $this->toInteger($row['totalLastLogins'] ?? null),
                                'first_appointment_id' => $this->toStringOrNull($firstAppointment['_id'] ?? null),
                                'first_appointment_start_date' => $this->toStringOrNull(
                                    $firstAppointment['startDate'] ?? null
                                ),
                                'last_appointment_id' => $this->toStringOrNull($lastAppointment['_id'] ?? null),
                                'last_appointment_start_date' => $this->toStringOrNull(
                                    $lastAppointment['startDate'] ?? null
                                ),
                                'next_appointment_id' => $this->toStringOrNull($nextAppointment['_id'] ?? null),
                                'next_appointment_start_date' => $this->toStringOrNull(
                                    $nextAppointment['startDate'] ?? null
                                ),
                                'last_therapist_id' => $this->toStringOrNull($lastTherapist['_id'] ?? null),
                                'last_therapist_name' => $this->toStringOrNull($lastTherapist['name'] ?? null),
                                'last_therapist_email' => $this->toStringOrNull($lastTherapist['email'] ?? null),
                                'package_membership' => is_array($row['packageMembership'] ?? null)
                                    ? $row['packageMembership']
                                    : null,
                                'first_appointment_location' => $this->toStringOrNull(
                                    $row['firstAppointmentLocation'] ?? null
                                ),
                                'first_seen_by' => $this->toStringOrNull($row['firstSeenBy'] ?? null),
                                'last_seen_by' => $this->toStringOrNull($row['lastSeenBy'] ?? null),
                                'referred_by_name' => $this->toStringOrNull($row['referredByName'] ?? null),
                                'payers_name' => $this->toStringOrNull($row['payersName'] ?? null),
                                'total_revenue' => $this->toDecimal($row['totalRevenue'] ?? null),
                                'total_collected' => $this->toDecimal($row['totalCollected'] ?? null),
                                'status' => $this->toStringOrNull($row['status'] ?? null),
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
                                'total_appointment_visit' => $this->toInteger($row['totalAppointmentVisit'] ?? null),
                                'total_session_completed' => $this->toInteger($row['totalSessionCompleted'] ?? null),
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
        $patientId = trim((string) ($row['patientId'] ?? ''));
        if ($patientId !== '') {
            return hash('sha256', $patientId);
        }

        $parts = [
            trim((string) ($row['email'] ?? '')),
            trim((string) ($row['patientName'] ?? '')),
            trim((string) ($row['registrationDateStr'] ?? '')),
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

    private function toStringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $str = trim((string) $value);

        return $str === '' ? null : $str;
    }
}
