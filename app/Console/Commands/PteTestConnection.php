<?php

namespace App\Console\Commands;

use App\Services\PtEverywhereService;
use Illuminate\Console\Command;

class PteTestConnection extends Command
{
    protected $signature = 'pte:test-connection';

    protected $description = 'Test the connection and authentication to PtEverywhere API';

    public function handle(PtEverywhereService $api): int
    {
        $this->info('Testing PtEverywhere API connection...');
        $this->newLine();

        $this->info('Base URL: '.config('pteverywhere.base_url'));
        $this->info('Username: '.config('pteverywhere.username'));
        $this->newLine();

        try {
            $this->info('Attempting authentication...');
            $token = $api->getToken();

            $this->info('Authentication successful!');
            $this->info('Token (first 20 chars): '.substr($token, 0, 20).'...');
            $this->newLine();

            // Verify the endpoint currently used for sync.
            $this->info('Testing data fetch (general visit)...');
            $today = now()->toDateString();
            $response = $api->getGeneralVisit([
                'from' => $today,
                'to' => $today,
                'size' => 1,
            ]);

            $docs = $response['docs'] ?? [];
            $this->info('General visit endpoint responded successfully.');
            if (is_array($docs)) {
                $this->info('Rows returned: '.count($docs));
                if (isset($docs[0]) && is_array($docs[0])) {
                    $this->info('First row keys: '.implode(', ', array_keys($docs[0])));
                }
            } else {
                $this->info('Response keys: '.implode(', ', array_keys($response)));
            }
            $this->newLine();

            $this->info('Connection test PASSED!');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Connection test FAILED: '.$e->getMessage());
            $this->newLine();
            $this->warn('Troubleshooting tips:');
            $this->warn('1. Check your PTE_USERNAME and PTE_PASSWORD in .env');
            $this->warn('2. Make sure the password has not expired (7-day window)');
            $this->warn('3. Check PTE_API_BASE_URL is correct');
            $this->warn('4. If you see SSL/certificate errors, set PTE_API_CA_BUNDLE to a local cacert.pem path');
            $this->warn('5. Review storage/logs/laravel.log for detailed errors');

            return self::FAILURE;
        }
    }
}
