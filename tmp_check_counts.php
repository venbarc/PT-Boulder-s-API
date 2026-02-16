<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
echo 'patients='.App\Models\PtePatient::count().PHP_EOL;
echo 'appointments='.App\Models\PteAppointment::count().PHP_EOL;
echo 'invoices='.App\Models\PteInvoice::count().PHP_EOL;
