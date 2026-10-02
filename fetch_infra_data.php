<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Report\InfraReportController;
use Illuminate\Http\Request;

$from = '2026-09-10';
$to = '2026-09-24';

echo "Fetching Infra Report data from {$from} to {$to}\n";
echo str_repeat('=', 60) . "\n\n";

$controller = new InfraReportController();
$request = new Request();
$request->merge(['from' => $from, 'to' => $to]);

try {
    $response = $controller->data($request);
    $data = json_decode($response->getContent(), true);

    if (isset($data['error'])) {
        echo "❌ Error: {$data['error']}\n";
        if (isset($data['file'])) {
            echo "   File: {$data['file']}:{$data['line']}\n";
        }
    } else {
        echo "✅ Data fetched successfully!\n\n";
        
        echo "📊 Summary:\n";
        echo "  - Network devices: " . count($data['network'] ?? []) . "\n";
        echo "  - NVR devices: " . count($data['nvr'] ?? []) . "\n";
        echo "  - CCTV devices: " . count($data['cctv'] ?? []) . "\n";
        echo "  - Bandwidth records: " . count($data['bandwidth'] ?? []) . "\n";
        echo "  - Server devices: " . count($data['server'] ?? []) . "\n";
        echo "  - Helpdesk tickets: " . count($data['helpdesk'] ?? []) . "\n";
        
        echo "\n" . str_repeat('=', 60) . "\n";
        
        // Show detailed structure
        echo "\n📋 Data Structure:\n";
        if (!empty($data['network'])) {
            echo "\nNetwork Device Structure:\n";
            echo json_encode(array_slice($data['network'], 0, 1), JSON_PRETTY_PRINT) . "\n";
        }
        
        if (!empty($data['bandwidth'])) {
            echo "\nBandwidth Record Structure:\n";
            echo json_encode(array_slice($data['bandwidth'], 0, 1), JSON_PRETTY_PRINT) . "\n";
        }
        
        if (!empty($data['server'])) {
            echo "\nServer Device Structure:\n";
            echo json_encode(array_slice($data['server'], 0, 1), JSON_PRETTY_PRINT) . "\n";
        }
        
        if (!empty($data['helpdesk'])) {
            echo "\nHelpdesk Ticket Structure:\n";
            echo json_encode(array_slice($data['helpdesk'], 0, 1), JSON_PRETTY_PRINT) . "\n";
        }
    }
    
} catch (\Throwable $e) {
    echo "❌ Exception: {$e->getMessage()}\n";
    echo "   File: {$e->getFile()}:{$e->getLine()}\n";
}

echo "\n✅ Fetch completed!\n";
