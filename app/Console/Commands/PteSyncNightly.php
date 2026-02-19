<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PteSyncNightly extends Command
{
    protected $signature = 'pte:sync-nightly
                            {--from=2024-01-01 : Start date (Y-m-d) for report pulls}
                            {--include-masterdata=1 : Include masterdata commands (1 or 0)}';

    protected $description = 'Run scheduled nightly PtEverywhere pulls (report + optional masterdata)';

    public function handle(): int
    {
        $timezone = config('app.timezone', 'America/Los_Angeles');
        $from = $this->parseFromOption();
        $to = Carbon::now($timezone)->toDateString();
        $includeMasterdata = (string) $this->option('include-masterdata') !== '0';

        $this->info("Nightly sync window: {$from} to {$to} ({$timezone})");

        $jobs = [
            [
                'name' => 'pte:sync-provider-revenue',
                'options' => ['--from' => $from, '--to' => $to, '--triggered-by' => 'scheduler'],
            ],
            [
                'name' => 'pte:sync-general-visit',
                'options' => ['--from' => $from, '--to' => $to, '--triggered-by' => 'scheduler'],
            ],
            [
                'name' => 'pte:sync-patient-report',
                'options' => ['--from' => $from, '--to' => $to, '--triggered-by' => 'scheduler'],
            ],
        ];

        if ($includeMasterdata) {
            $jobs = array_merge($jobs, [
                ['name' => 'pte:sync-therapists', 'options' => ['--triggered-by' => 'scheduler']],
                ['name' => 'pte:sync-locations', 'options' => ['--triggered-by' => 'scheduler']],
                ['name' => 'pte:sync-services', 'options' => ['--triggered-by' => 'scheduler']],
                ['name' => 'pte:sync-master-patients', 'options' => ['--triggered-by' => 'scheduler']],
                ['name' => 'pte:sync-master-users', 'options' => ['--triggered-by' => 'scheduler']],
            ]);
        }

        $failed = false;

        foreach ($jobs as $job) {
            $this->newLine();
            $this->line("Running: {$job['name']}");
            $exitCode = $this->call($job['name'], $job['options']);

            if ($exitCode !== self::SUCCESS) {
                $failed = true;
                $this->error("{$job['name']} failed.");
            }
        }

        $this->newLine();
        if ($failed) {
            $this->error('Nightly sync finished with failures. Review pull history/logs.');

            return self::FAILURE;
        }

        $this->info('Nightly sync completed successfully.');

        return self::SUCCESS;
    }

    private function parseFromOption(): string
    {
        $from = (string) $this->option('from');

        try {
            $date = Carbon::createFromFormat('Y-m-d', $from);
        } catch (\Throwable) {
            throw new \RuntimeException('Invalid --from date format. Use YYYY-MM-DD.');
        }

        if ($date->format('Y-m-d') !== $from) {
            throw new \RuntimeException('Invalid --from date format. Use YYYY-MM-DD.');
        }

        return $from;
    }
}
