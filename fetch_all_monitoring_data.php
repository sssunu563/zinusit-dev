<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\BandwidthController;
use App\Http\Controllers\CctvController;
use App\Http\Controllers\Cctv\CctvOperationController;
use App\Http\Controllers\Network\UptimeController;
use App\Http\Controllers\Server\ServerOperationController;

// Authenticate as admin user
$user = \App\Models\User::find(1); // Admin user
if (!$user) {
    echo "❌ User not found\n";
    exit(1);
}
auth()->login($user);

$startDate = '2026-09-10';
$endDate = '2026-09-24';

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║     FETCH ALL MONITORING DATA: SEPT 10-24, 2026            ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

$start = new DateTime($startDate);
$end = new DateTime($endDate);
$interval = new DateInterval('P1D');
$dateRange = new DatePeriod($start, $interval, $end->modify('+1 day'));

$results = [
    'bandwidth' => ['success' => 0, 'skipped' => 0, 'failed' => 0],
    'cctv' => ['success' => 0, 'skipped' => 0, 'failed' => 0],
    'cctv_operation' => ['success' => 0, 'skipped' => 0, 'failed' => 0],
    'network' => ['success' => 0, 'skipped' => 0, 'failed' => 0],
    'server' => ['success' => 0, 'skipped' => 0, 'failed' => 0],
];

foreach ($dateRange as $date) {
    $dateStr = $date->format('Y-m-d');
    echo "\n📅 Processing: {$dateStr}\n";
    echo str_repeat('─', 60) . "\n";
    
    // 1. Fetch Bandwidth Data
    echo "🌐 Bandwidth... ";
    try {
        $controller = app(BandwidthController::class);
        $request = new Request(['date' => $dateStr]);
        $response = $controller->fetch($request);
        $data = json_decode($response->getContent(), true);
        
        if ($data['success'] ?? false) {
            echo "✅ " . ($data['message'] ?? 'Success') . "\n";
            $results['bandwidth']['success']++;
        } else {
            $msg = $data['message'] ?? 'No message';
            if (str_contains(strtolower($msg), 'already') || str_contains(strtolower($msg), 'exist')) {
                echo "⏭️  Already exists\n";
                $results['bandwidth']['skipped']++;
            } else {
                echo "⚠️  {$msg}\n";
                $results['bandwidth']['failed']++;
            }
        }
    } catch (\Throwable $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        $results['bandwidth']['failed']++;
    }
    
    // 2. Fetch CCTV Data (raw)
    echo "📷 CCTV (Raw)... ";
    try {
        $controller = app(CctvController::class);
        $request = new Request(['date' => $dateStr]);
        $response = $controller->fetch($request);
        $data = json_decode($response->getContent(), true);
        
        if ($data['success'] ?? false) {
            echo "✅ " . ($data['message'] ?? 'Success') . "\n";
            $results['cctv']['success']++;
        } else {
            $msg = $data['message'] ?? 'No message';
            if (str_contains(strtolower($msg), 'already') || str_contains(strtolower($msg), 'exist')) {
                echo "⏭️  Already exists\n";
                $results['cctv']['skipped']++;
            } else {
                echo "⚠️  {$msg}\n";
                $results['cctv']['failed']++;
            }
        }
    } catch (\Throwable $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        $results['cctv']['failed']++;
    }
    
    // 3. Fetch CCTV Operation (NVR monitoring)
    echo "📹 CCTV Operation (NVR)... ";
    try {
        $controller = app(CctvOperationController::class);
        $request = new Request(['date' => $dateStr]);
        $response = $controller->fetch($request);
        $data = json_decode($response->getContent(), true);
        
        if ($data['success'] ?? false) {
            echo "✅ " . ($data['message'] ?? 'Success') . "\n";
            $results['cctv_operation']['success']++;
        } else {
            $msg = $data['message'] ?? 'No message';
            if (str_contains(strtolower($msg), 'already') || str_contains(strtolower($msg), 'exist')) {
                echo "⏭️  Already exists\n";
                $results['cctv_operation']['skipped']++;
            } else {
                echo "⚠️  {$msg}\n";
                $results['cctv_operation']['failed']++;
            }
        }
    } catch (\Throwable $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        $results['cctv_operation']['failed']++;
    }
    
    // 4. Fetch Network/Uptime Data
    echo "📡 Network (Uptime)... ";
    try {
        $controller = app(UptimeController::class);
        $request = new Request(['date' => $dateStr]);
        $response = $controller->fetch($request);
        $data = json_decode($response->getContent(), true);
        
        if ($data['success'] ?? false) {
            echo "✅ " . ($data['message'] ?? 'Success') . "\n";
            $results['network']['success']++;
        } else {
            $msg = $data['message'] ?? 'No message';
            if (str_contains(strtolower($msg), 'already') || str_contains(strtolower($msg), 'exist')) {
                echo "⏭️  Already exists\n";
                $results['network']['skipped']++;
            } else {
                echo "⚠️  {$msg}\n";
                $results['network']['failed']++;
            }
        }
    } catch (\Throwable $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        $results['network']['failed']++;
    }
    
    // 5. Fetch Server Data
    echo "🖥️  Server... ";
    try {
        $controller = app(ServerOperationController::class);
        $request = new Request(['date' => $dateStr]);
        $response = $controller->fetch($request);
        $data = json_decode($response->getContent(), true);
        
        if ($data['success'] ?? false) {
            echo "✅ " . ($data['message'] ?? 'Success') . "\n";
            $results['server']['success']++;
        } else {
            $msg = $data['message'] ?? 'No message';
            if (str_contains(strtolower($msg), 'already') || str_contains(strtolower($msg), 'exist')) {
                echo "⏭️  Already exists\n";
                $results['server']['skipped']++;
            } else {
                echo "⚠️  {$msg}\n";
                $results['server']['failed']++;
            }
        }
    } catch (\Throwable $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        $results['server']['failed']++;
    }
}

echo "\n" . str_repeat('═', 60) . "\n";
echo "📊 FINAL SUMMARY\n";
echo str_repeat('═', 60) . "\n\n";

foreach ($results as $type => $stats) {
    $total = $stats['success'] + $stats['skipped'] + $stats['failed'];
    echo ucfirst(str_replace('_', ' ', $type)) . ":\n";
    echo "  ✅ Success: {$stats['success']}/{$total}\n";
    echo "  ⏭️  Skipped: {$stats['skipped']}/{$total}\n";
    echo "  ❌ Failed:  {$stats['failed']}/{$total}\n\n";
}

echo "✅ All fetch operations completed!\n";
