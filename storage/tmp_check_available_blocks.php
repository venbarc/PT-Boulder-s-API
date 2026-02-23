<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$rows = App\Models\PteAvailableBlock::query()
    ->orderByDesc('id')
    ->limit(8)
    ->get(['id','start_datetime','end_datetime','location_id','location_name','service_id','service_name','raw_data']);
echo json_encode($rows->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
