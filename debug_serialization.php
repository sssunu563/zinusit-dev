<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$inspection = \App\Models\Inspection::find(5);

echo "Inspection #5 toArray():\n";
echo json_encode($inspection->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

echo "Checking signature_date specifically:\n";
echo "signature_date key exists: " . (isset($inspection->toArray()['signature_date']) ? 'YES' : 'NO') . "\n";
echo "signature_date value: " . var_export($inspection->toArray()['signature_date'] ?? 'NOT FOUND', true) . "\n";
