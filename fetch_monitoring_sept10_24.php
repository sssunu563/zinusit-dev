<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\BandwidthController;
use App\Http\Controllers\Cctv\CctvOperationController;
use App\Http\Controllers\Network\UptimeController;
use App\Http\Controllers\Server\ServerOperationController;

// Authenticate as admin user
$user = \App\Models\User::find(1);
if (!$user) {
    echo "❌ User not found\n";
    exit(1);
}
auth()->login($user);

$startDate = '2026-09-10';
$endDate = '2026-09-24';
$logFile = 'fetch_log_' . date('Y-m-d_His') . '.txt';

function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $line = "[{$timestamp}] {$message}\n";
    file_put_contents($logFile, $line, FILE_APPEND);
    echo $message . "\n";
}

writeLog("╔════════════════════════════════════════════════════════════╗");
writeLog("║     FETCH ALL MONITORING DATA: SEPT 10-24, 2026            ║");
writeLog("╚════════════════════════════════════════════════════════════╝");
writeLog("");

$start = new DateTime($startDate);
$end = new DateTime($endDate);
$interval = new DateInterval('P1D');
$dateRange = new DatePeriod($start, $interval, $end->modify('+1 day'));

$results = [
    'bandwidth' => ['success' => 0, 'skipped' => 0, 'failed' => 0],
    'cctv_operation' => ['success' => 0, 'skipped' => 0, 'failed' => 0],
    'network' => ['success' => 0, 'skipped' => 0, 'failed' => 0],
    'server' => ['success' => 0, 'skipped' => 0, 'failed' => 0],
];

foreach ($dateRange as $date) {
    $dateStr = $date->format('Y-m-d');
    writeLog("");
    writeLog("📅 Processing: {$dateStr}");
    writeLog(str_repeat('─', 60));
    
    // 1. Fetch Bandwidth Data
    try {
        $controller = app(BandwidthController::class);
        $request = new Request(['date' => $dateStr]);
        $response = $controller->fetch($request);
        $data = json_decode($response->getContent(), true);
        
        if ($data['success'] ?? false) {
            writeLog("🌐 Bandwidth: ✅ " . ($data['message'] ?? 'Success'));
            $results['bandwidth']['success']++;
        } else {
            $msg = $data['message'] ?? 'No message';
            if (str_contains(strtolower($msg), 'already') || str_contains(strtolower($msg), 'exist')) {
                writeLog("🌐 Bandwidth: ⏭️  Already exists");
                $results['bandwidth']['skipped']++;
            } else {
                writeLog("🌐 Bandwidth: ⚠️  {$msg}");
                $results['bandwidth']['failed']++;
            }
        }
    } catch (\Throwable $e) {
        writeLog("🌐 Bandwidth: ❌ Error: " . $e->getMessage());
        $results['bandwidth']['failed']++;
    }
    
    // 2. Fetch CCTV Operation (NVR monitoring)
    try {
        $controller = app(CctvOperationController::class);
        $request = new Request(['date' => $dateStr]);
        $response = $controller->fetch($request);
        $data = json_decode($response->getContent(), true);
        
        if ($data['success'] ?? false) {
            writeLog("📹 CCTV/NVR: ✅ " . ($data['message'] ?? 'Success'));
            $results['cctv_operation']['success']++;
        } else {
            $msg = $data['message'] ?? 'No message';
            if (str_contains(strtolower($msg), 'already') || str_contains(strtolower($msg), 'exist')) {
                writeLog("📹 CCTV/NVR: ⏭️  Already exists");
                $results['cctv_operation']['skipped']++;
            } else {
                writeLog("📹 CCTV/NVR: ⚠️  {$msg}");
                $results['cctv_operation']['failed']++;
            }
        }
    } catch (\Throwable $e) {
        writeLog("📹 CCTV/NVR: ❌ Error: " . $e->getMessage());
        $results['cctv_operation']['failed']++;
    }
    
    // 3. Fetch Network/Uptime Data
    try {
        $controller = app(UptimeController::class);
        $request = new Request(['date' => $dateStr]);
        $response = $controller->fetch($request);
        $data = json_decode($response->getContent(), true);
        
        if ($data['success'] ?? false) {
            writeLog("📡 Network: ✅ " . ($data['message'] ?? 'Success'));
            $results['network']['success']++;
        } else {
            $msg = $data['message'] ?? 'No message';
            if (str_contains(strtolower($msg), 'already') || str_contains(strtolower($msg), 'exist')) {
                writeLog("📡 Network: ⏭️  Already exists");
                $results['network']['skipped']++;
            } else {
                writeLog("📡 Network: ⚠️  {$msg}");
                $results['network']['failed']++;
            }
        }
    } catch (\Throwable $e) {
        writeLog("📡 Network: ❌ Error: " . $e->getMessage());
        $results['network']['failed']++;
    }
    
    // 4. Fetch Server Data
    try {
        $controller = app(ServerOperationController::class);
        $request = new Request(['date' => $dateStr]);
        $response = $controller->fetch($request);
        $data = json_decode($response->getContent(), true);
        
        if ($data['success'] ?? false) {
            writeLog("🖥️  Server: ✅ " . ($data['message'] ?? 'Success'));
            $results['server']['success']++;
        } else {
            $msg = $data['message'] ?? 'No message';
            if (str_contains(strtolower($msg), 'already') || str_contains(strtolower($msg), 'exist')) {
                writeLog("🖥️  Server: ⏭️  Already exists");
                $results['server']['skipped']++;
            } else {
                writeLog("🖥️  Server: ⚠️  {$msg}");
                $results['server']['failed']++;
            }
        }
    } catch (\Throwable $e) {
        writeLog("🖥️  Server: ❌ Error: " . $e->getMessage());
        $results['server']['failed']++;
    }
}

writeLog("");
writeLog(str_repeat('═', 60));
writeLog("📊 FINAL SUMMARY");
writeLog(str_repeat('═', 60));
writeLog("");

foreach ($results as $type => $stats) {
    $total = $stats['success'] + $stats['skipped'] + $stats['failed'];
    writeLog(ucfirst(str_replace('_', ' ', $type)) . ":");
    writeLog("  ✅ Success: {$stats['success']}/{$total}");
    writeLog("  ⏭️  Skipped: {$stats['skipped']}/{$total}");
    writeLog("  ❌ Failed:  {$stats['failed']}/{$total}");
    writeLog("");
}

writeLog("✅ All fetch operations completed!");
writeLog("Log saved to: {$logFile}");
