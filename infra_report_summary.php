<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Report\InfraReportController;
use Illuminate\Http\Request;

$from = '2026-09-10';
$to = '2026-09-24';

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║       INFRA REPORT DATA FETCH - SEPTEMBER 10-24, 2026      ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

$controller = new InfraReportController();
$request = new Request();
$request->merge(['from' => $from, 'to' => $to]);

try {
    $response = $controller->data($request);
    $data = json_decode($response->getContent(), true);

    if (isset($data['error'])) {
        echo "❌ Error: {$data['error']}\n";
        exit(1);
    }

    echo "✅ Data fetched successfully!\n\n";
    echo str_repeat('─', 60) . "\n";
    
    // Network Report
    echo "\n📡 OPERASIONAL JARINGAN (Network Monitoring)\n";
    echo str_repeat('─', 60) . "\n";
    foreach ($data['network'] as $site) {
        echo "📍 {$site['location']}\n";
        echo "   Devices: {$site['qty']}\n";
        echo "   Uptime: {$site['uptime']}%\n";
        if (!empty($site['failed_list'])) {
            echo "   ⚠ Failed Devices:\n";
            foreach ($site['failed_list'] as $device) {
                echo "      - {$device['device']}: {$device['issue']}\n";
            }
        } else {
            echo "   ✅ All devices operational\n";
        }
        echo "\n";
    }
    
    // NVR Report
    echo "\n📹 OPERASIONAL NVR (Network Video Recorder)\n";
    echo str_repeat('─', 60) . "\n";
    foreach ($data['nvr'] as $site) {
        echo "📍 {$site['location']}\n";
        echo "   NVR Devices: {$site['qty']}\n";
        echo "   Uptime: {$site['uptime']}%\n";
        if (!empty($site['failed_list'])) {
            echo "   ⚠ Failed NVRs:\n";
            foreach ($site['failed_list'] as $device) {
                echo "      - {$device['device']}: {$device['issue']}\n";
            }
        } else {
            echo "   ✅ All NVRs operational\n";
        }
        echo "\n";
    }
    
    // CCTV Report
    echo "\n📷 OPERASIONAL CCTV\n";
    echo str_repeat('─', 60) . "\n";
    foreach ($data['cctv'] as $site) {
        echo "📍 {$site['location']}\n";
        echo "   CCTV Cameras: {$site['qty']}\n";
        echo "   Uptime: {$site['uptime']}%\n";
        if (!empty($site['failed_list'])) {
            echo "   ⚠ Failed Cameras:\n";
            foreach ($site['failed_list'] as $device) {
                echo "      - {$device['device']}: {$device['issue']}\n";
            }
        } else {
            echo "   ✅ All cameras operational\n";
        }
        echo "\n";
    }
    
    // Bandwidth Report
    echo "\n🌐 BANDWIDTH MONITORING\n";
    echo str_repeat('─', 60) . "\n";
    foreach ($data['bandwidth'] as $site) {
        echo "📍 {$site['location']}\n";
        if (!empty($site['providers'])) {
            foreach ($site['providers'] as $provider) {
                echo "   Provider: {$provider['provider']}\n";
                if (!empty($provider['circuits'])) {
                    foreach ($provider['circuits'] as $circuit) {
                        echo "      - {$circuit['circuit']}: {$circuit['avg_mbps']} Mbps (avg)\n";
                        if (!empty($circuit['remark'])) {
                            echo "        Note: {$circuit['remark']}\n";
                        }
                    }
                }
            }
        } else {
            echo "   ⚠ No bandwidth data available\n";
        }
        echo "\n";
    }
    
    // Server Report
    echo "\n🖥️  OPERASIONAL SERVER\n";
    echo str_repeat('─', 60) . "\n";
    foreach ($data['server'] as $site) {
        echo "📍 {$site['location']}\n";
        echo "   Servers: {$site['qty']}\n";
        echo "   Uptime: {$site['uptime']}%\n";
        if (!empty($site['failed_list'])) {
            echo "   ⚠ Failed Servers:\n";
            foreach ($site['failed_list'] as $device) {
                echo "      - {$device['device']}: {$device['issue']}\n";
            }
        } else {
            echo "   ✅ All servers operational\n";
        }
        echo "\n";
    }
    
    // Helpdesk Report
    echo "\n🎫 OPERASIONAL DUKUNGAN (Helpdesk)\n";
    echo str_repeat('─', 60) . "\n";
    foreach ($data['helpdesk'] as $site) {
        echo "📍 {$site['location']}\n";
        echo "   Total Cases: {$site['case']}\n";
        echo "   Closed: {$site['closed']}\n";
        echo "   Performance: {$site['performance']}%\n";
        if (!empty($site['pending_list'])) {
            echo "   📋 Ticket List:\n";
            foreach ($site['pending_list'] as $ticket) {
                $status = $ticket['status'] ?? 'Unknown';
                echo "      #{$ticket['id']} - {$ticket['case']} ({$ticket['date']})\n";
                echo "         Duration: {$ticket['duration']} | Status: {$status}\n";
                if (!empty($ticket['remark'])) {
                    echo "         Remark: {$ticket['remark']}\n";
                }
            }
        }
        echo "\n";
    }
    
    echo str_repeat('═', 60) . "\n";
    echo "📊 OVERALL SUMMARY\n";
    echo str_repeat('═', 60) . "\n";
    
    $totalNetwork = array_sum(array_column($data['network'], 'qty'));
    $avgNetworkUptime = array_sum(array_column($data['network'], 'uptime')) / count($data['network']);
    
    $totalNVR = array_sum(array_column($data['nvr'], 'qty'));
    $avgNVRUptime = array_sum(array_column($data['nvr'], 'uptime')) / count($data['nvr']);
    
    $totalCCTV = array_sum(array_column($data['cctv'], 'qty'));
    $avgCCTVUptime = array_sum(array_column($data['cctv'], 'uptime')) / count($data['cctv']);
    
    $totalServer = array_sum(array_column($data['server'], 'qty'));
    $avgServerUptime = array_sum(array_column($data['server'], 'uptime')) / count($data['server']);
    
    $totalCases = array_sum(array_column($data['helpdesk'], 'case'));
    $totalClosed = array_sum(array_column($data['helpdesk'], 'closed'));
    $avgPerformance = array_sum(array_column($data['helpdesk'], 'performance')) / count($data['helpdesk']);
    
    echo "Network Devices: {$totalNetwork} devices | Avg Uptime: " . round($avgNetworkUptime, 2) . "%\n";
    echo "NVR Devices: {$totalNVR} devices | Avg Uptime: " . round($avgNVRUptime, 2) . "%\n";
    echo "CCTV Cameras: {$totalCCTV} cameras | Avg Uptime: " . round($avgCCTVUptime, 2) . "%\n";
    echo "Servers: {$totalServer} servers | Avg Uptime: " . round($avgServerUptime, 2) . "%\n";
    echo "Helpdesk: {$totalCases} cases | {$totalClosed} closed | Performance: " . round($avgPerformance, 2) . "%\n";
    
    echo "\n✅ Data fetch completed successfully!\n";
    echo "Period: {$from} to {$to}\n";
    
} catch (\Throwable $e) {
    echo "❌ Exception: {$e->getMessage()}\n";
    echo "   File: {$e->getFile()}:{$e->getLine()}\n";
    exit(1);
}
