<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$inspection = \App\Models\Inspection::find(5);

echo "Inspection #5 Data:\n";
echo "---\n";
echo "ID: " . $inspection->id . "\n";
echo "Report ID: " . $inspection->report_id . "\n";
echo "IT Signature: " . (bool)$inspection->it_signature . "\n";
echo "Signature Date: " . ($inspection->signature_date ? $inspection->signature_date->format('Y-m-d H:i:s') : 'NULL') . "\n";
echo "Signature Date (raw): " . var_export($inspection->signature_date, true) . "\n";
echo "\n";

// Check all inspections
echo "\nAll Inspections Signature Dates:\n";
echo "---\n";
$inspections = \App\Models\Inspection::whereNotNull('it_signature')->get();
foreach ($inspections as $i) {
    echo "ID: {$i->id} | Report: {$i->report_id} | Sig Date: " . ($i->signature_date ? $i->signature_date->format('Y-m-d H:i:s') : 'NULL') . "\n";
}
