<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use App\Http\Controllers\Report\InfraReportController;

class CheckInfraData extends Command
{
    protected $signature = 'check:infra {from=2026-09-04} {to=2026-09-10}';
    protected $description = 'Check infra report data endpoint';

    public function handle()
    {
        $from = $this->argument('from');
        $to = $this->argument('to');

        $this->info("Checking InfraReportController::data from $from to $to...");

        $ctrl = app(InfraReportController::class);
        $req = new Request(['from' => $from, 'to' => $to]);

        $res = $ctrl->data($req);
        $data = json_decode($res->getContent(), true);

        if (isset($data['error'])) {
            $this->error("ERROR: " . $data['error']);
            $this->error("File: " . ($data['file'] ?? '') . " Line: " . ($data['line'] ?? ''));
            return 1;
        }

        $this->info("SUCCESS!");
        $this->line("Network rows: " . count($data['network'] ?? []));
        $this->line("Bandwidth rows: " . count($data['bandwidth'] ?? []));
        $this->line("NVR rows: " . count($data['nvr'] ?? []));
        $this->line("CCTV rows: " . count($data['cctv'] ?? []));
        $this->line("Server rows: " . count($data['server'] ?? []));
        $this->line("Helpdesk rows: " . count($data['helpdesk'] ?? []));

        $this->line(json_encode($data, JSON_PRETTY_PRINT));
        return 0;
    }
}
