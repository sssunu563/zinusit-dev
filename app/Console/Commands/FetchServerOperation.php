<?php

namespace App\Console\Commands;

use App\Services\ServerMonitorService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FetchServerOperation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:server-monitor-fetch {date? : Date to fetch (Y-m-d)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch server operation data (CPU, RAM, Disk, Temperature) from PRTG';

    /**
     * Execute the console command.
     */
    public function handle(ServerMonitorService $service)
    {
        $dateStr = $this->argument('date') ?? now()->subDay()->toDateString();
        $date = Carbon::parse($dateStr);

        $this->info("Fetching server operation data for {$date->toDateString()}...");

        $results = $service->fetchAll($date, null, false);

        $this->info("Resources: " . $results['resources']['ok'] . " OK, " . $results['resources']['fail'] . " failed.");
        $this->info("Temperature: " . $results['temperature']['ok'] . " OK, " . $results['temperature']['fail'] . " failed.");

        return 0;
    }
}
