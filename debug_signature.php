<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$inspection = \App\Models\Inspection::find(5);

echo "Debug Inspection #5 signedAt calculation:\n";
echo "---\n";
echo "it_signature exists: " . (bool)$inspection->it_signature . "\n";
echo "signature_date value: " . var_export($inspection->signature_date, true) . "\n";

// Test condition dari Inspection/Show.vue
$signedAt = $inspection->it_signature ? $inspection->signature_date : null;
echo "signedAt result (from Show.vue): " . var_export($signedAt, true) . "\n";
echo "signedAt truthy check: " . (bool)$signedAt . "\n";
echo "signedAt === null: " . ($signedAt === null ? 'YES' : 'NO') . "\n";
echo "signedAt empty(): " . (empty($signedAt) ? 'YES' : 'NO') . "\n";

if ($signedAt) {
    echo "Formatted (should display): " . $signedAt->format('j M Y, H:i') . "\n";
} else {
    echo "Would display: Belum ditandatangani\n";
}
