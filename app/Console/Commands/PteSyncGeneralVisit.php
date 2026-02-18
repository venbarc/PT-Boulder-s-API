<?php

namespace App\Console\Commands;

use App\Models\PteGeneralVisit;
use App\Services\PtEverywhereService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PteSyncGeneralVisit extends Command
{
    protected $signature = 'pte:sync-general-visit
                            {--limit= : Limit number of records to fetch}
                            {--from= : Start date (Y-m-d)}
                            {--to= : End date (Y-m-d)}
                            {--chunk-days=90 : Number of days per API chunk}';

    protected $description = 'Pull general visit report from PtEverywhere API and store locally';

    public function handle(PtEverywhereService $api): int
    {
        $this->info('Fetching general visit data from PtEverywhere...');

        try {
            [$from, $to] = $this->resolveDateRange();
            $limit = $this->parseLimitOption();
            $chunkDays = $this->parseChunkDays();

            [$fetched, $created, $updated, $chunksProcessed, $failedChunks] = $this->syncGeneralVisitByChunk(
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
     * Fetch and save general-visit rows chunk-by-chunk.
     *
     * @return array{0:int,1:int,2:int,3:int,4:int}
     */
    private function syncGeneralVisitByChunk(
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
                    $response = $api->getGeneralVisit([
                        'from' => $chunkStart->toDateString(),
                        'to' => $chunkEnd->toDateString(),
                        'page' => $page,
                        'size' => 100,
                    ]);

                    $docs = $response['docs'] ?? [];
                    if (! is_array($docs)) {
                        break;
                    }
                    $summary = is_array($response['summary'] ?? null) ? $response['summary'] : [];

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

                        $patient = is_array($row['patient'] ?? null) ? $row['patient'] : [];
                        $service = is_array($row['service'] ?? null) ? $row['service'] : [];
                        $provider = is_array($row['provider'] ?? null) ? $row['provider'] : [];
                        $location = is_array($row['location'] ?? null) ? $row['location'] : [];
                        $invoice = is_array($row['invoice'] ?? null) ? $row['invoice'] : [];
                        $treatmentNote = is_array($row['treatmentNote'] ?? null) ? $row['treatmentNote'] : [];

                        $record = PteGeneralVisit::updateOrCreate(
                            ['row_key' => $rowKey],
                            [
                                'pte_visit_id' => $this->toStringOrNull($row['_id'] ?? null),
                                'pte_patient_id' => $this->toStringOrNull(
                                    $patient['_id'] ?? $row['patientId'] ?? $row['clientId'] ?? null
                                ),
                                'patient_full_name' => $this->toStringOrNull($patient['fullName'] ?? null),
                                'patient_first_name' => $this->toStringOrNull($patient['firstName'] ?? null),
                                'patient_last_name' => $this->toStringOrNull($patient['lastName'] ?? null),
                                'patient_email' => $this->toStringOrNull($patient['email'] ?? null),
                                'patient_code' => $this->toStringOrNull($patient['patientCode'] ?? null),
                                'patient_total_appointment_visit' => $this->toInteger(
                                    $patient['totalAppointmentVisit'] ?? null
                                ),
                                'service_name' => $this->toStringOrNull(
                                    $service['name'] ?? $row['serviceName'] ?? null
                                ),
                                'service_id' => $this->toStringOrNull($service['_id'] ?? null),
                                'provider_name' => $this->toStringOrNull(
                                    $provider['name'] ?? $row['providerName'] ?? null
                                ),
                                'provider_id' => $this->toStringOrNull($provider['_id'] ?? null),
                                'location_name' => $this->toStringOrNull(
                                    $location['name'] ?? $row['locationName'] ?? null
                                ),
                                'location_id' => $this->toStringOrNull($location['_id'] ?? null),
                                'date_of_service' => $row['dateOfService'] ?? null,
                                'appointment_status' => $this->toStringOrNull(
                                    $row['appointmentStatus'] ?? $row['status'] ?? null
                                ),
                                'units' => $this->toDecimal($row['units'] ?? null),
                                'charges' => $this->toDecimal($row['charges'] ?? null),
                                'payments' => $this->toDecimal($row['payments'] ?? null),
                                'invoice_number' => $this->toStringOrNull(
                                    $invoice['invoiceNo'] ?? $invoice['packageInvoiceNumber'] ?? null
                                ),
                                'invoice_status' => $this->toStringOrNull(
                                    $invoice['invoiceStatus'] ?? $row['invoiceStatus'] ?? null
                                ),
                                'current_responsibility' => $this->toStringOrNull(
                                    $invoice['currentResponsibility'] ?? null
                                ),
                                'package_invoice_number' => $this->toStringOrNull(
                                    $invoice['packageInvoiceNumber'] ?? null
                                ),
                                'package_invoice_name' => $this->toStringOrNull(
                                    $invoice['packageInvoiceName'] ?? null
                                ),
                                'claim_created_info' => $this->toStringOrNull($row['claimCreatedInfo'] ?? null),
                                'created_by' => $this->toStringOrNull($row['createdBy'] ?? null),
                                'reason' => $this->toStringOrNull($row['reason'] ?? null),
                                'last_update_by' => $this->toStringOrNull($row['lastUpdateBy'] ?? null),
                                'last_update_date' => $row['lastUpdateDate'] ?? null,
                                'cancellation_notice' => $this->toStringOrNull($row['cancellationNotice'] ?? null),
                                'treatment_note_id' => $this->toStringOrNull($treatmentNote['_id'] ?? null),
                                'treatment_note_number' => $this->toStringOrNull(
                                    $treatmentNote['treatmentNoteNo'] ?? null
                                ),
                                'summary_total_appointments' => $this->toInteger(
                                    $summary['totalAppointments'] ?? null
                                ),
                                'summary_total_charges' => $this->toDecimal($summary['totalCharges'] ?? null),
                                'summary_total_payments' => $this->toDecimal($summary['totalPayments'] ?? null),
                                'summary_total_units' => $this->toDecimal($summary['totalUnits'] ?? null),
                                'summary_total_patients' => $this->toInteger($summary['totalPatients'] ?? null),
                                'summary_average_charges' => $this->toDecimal(
                                    $summary['averageCharges'] ?? null
                                ),
                                'summary_average_payments' => $this->toDecimal(
                                    $summary['averagePayments'] ?? null
                                ),
                                'summary_average_units' => $this->toDecimal($summary['averageUnits'] ?? null),
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
        $visitId = trim((string) ($row['_id'] ?? ''));
        if ($visitId !== '') {
            return hash('sha256', $visitId);
        }

        $patient = is_array($row['patient'] ?? null) ? $row['patient'] : [];
        $service = is_array($row['service'] ?? null) ? $row['service'] : [];

        $parts = [
            trim((string) ($patient['_id'] ?? $row['patientId'] ?? '')),
            trim((string) ($row['dateOfService'] ?? '')),
            trim((string) ($row['startDateTime'] ?? '')),
            trim((string) ($service['_id'] ?? $service['name'] ?? $row['serviceName'] ?? '')),
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
