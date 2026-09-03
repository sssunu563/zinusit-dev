<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupTemporaryDirectories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cleanup:temp-directories {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up temporary files in storage directories that are older than 30 days';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $tempDirs = [
            'app/inspection-temp',
            'app/peminjaman-temp',
            'app/stb-temp',
        ];

        $totalDeleted = 0;
        $daysOld = 30;
        $cutoffTime = now()->subDays($daysOld)->timestamp;

        foreach ($tempDirs as $tempDir) {
            $path = storage_path($tempDir);

            if (!is_dir($path)) {
                continue;
            }

            $this->info("Processing directory: {$tempDir}");

            $files = scandir($path);
            $deleted = 0;

            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                $filePath = "{$path}/{$file}";
                $fileTime = filemtime($filePath);

                // Delete if older than 30 days
                if ($fileTime && $fileTime < $cutoffTime) {
                    if ($dryRun) {
                        $this->line("  [DRY RUN] Would delete: {$file}");
                    } else {
                        if (is_file($filePath)) {
                            unlink($filePath);
                            $this->line("  Deleted: {$file}");
                        }
                    }
                    $deleted++;
                }
            }

            $totalDeleted += $deleted;
            $this->info("  Total deleted from {$tempDir}: {$deleted}\n");
        }

        if ($dryRun) {
            $this->warn("DRY RUN MODE - No files were actually deleted");
        }

        $this->info("Cleanup complete! Total files deleted: {$totalDeleted}");

        return self::SUCCESS;
    }
}
