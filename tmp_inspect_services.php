<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$svc = $app->make(App\Services\PtEverywhereService::class);
$res = $svc->getServices(['page' => 1, 'size' => 5]);
$rows = [];
if (is_array($res) && isset($res['docs']) && is_array($res['docs'])) {
    $rows = $res['docs'];
} elseif (is_array($res) && isset($res[0]) && is_array($res[0])) {
    $rows = $res;
}
if (count($rows) === 0) {
    echo "no_rows\n";
    exit(0);
}
$first = $rows[0];
echo 'keys='.implode(',', array_keys($first))."\n";
echo 'sample='.json_encode($first, JSON_UNESCAPED_SLASHES)."\n";
