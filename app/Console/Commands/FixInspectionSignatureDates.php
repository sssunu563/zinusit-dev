<?php

namespace App\Console\Commands;

use App\Models\Inspection;
use Illuminate\Console\Command;

class FixInspectionSignatureDates extends Command
{
    protected $signature = 'inspection:fix-signature-dates';
    protected $description = 'Fix signature_date for old inspections that only have date without time';

    public function handle()
    {
        $this->info('Fixing inspection signature dates...');

        // Get all inspections that have signatures but signature_date might be null or date-only
        $inspections = Inspection::where(function ($query) {
            $query->whereNotNull('it_signature')
                  ->orWhereNotNull('checked_signature')
                  ->orWhereNotNull('user_signature')
                  ->orWhereNotNull('leader_signature');
        })->get();

        $this->info("Found {$inspections->count()} inspections with signatures.");

        $fixed = 0;
        $skipped = 0;

        foreach ($inspections as $inspection) {
            // If signature_date is null, set it to created_at or current time
            if (is_null($inspection->signature_date)) {
                $inspection->signature_date = $inspection->created_at ?? now();
                $inspection->save();
                $fixed++;
                $this->line("Fixed Inspection #{$inspection->id} - Set signature_date to {$inspection->signature_date}");
            } 
            // If signature_date exists but has no time component (00:00:00)
            elseif ($inspection->signature_date->format('H:i:s') === '00:00:00') {
                // Set time to 12:00:00
                $inspection->signature_date = $inspection->signature_date->setTime(12, 0, 0);
                $inspection->save();
                $fixed++;
                $this->line("Fixed Inspection #{$inspection->id} - Added time 12:00:00");
            } else {
                $skipped++;
            }
        }

        $this->newLine();
        $this->info("✓ Fixed: {$fixed}");
        $this->info("○ Skipped (already has time): {$skipped}");
        $this->info('Done!');

        return 0;
    }
}
