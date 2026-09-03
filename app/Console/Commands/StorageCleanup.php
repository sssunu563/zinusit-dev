<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\Stb;
use App\Models\Inspection;

class StorageCleanup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'storage:cleanup {--dry-run : Only list files to be deleted}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup orphaned files in storage (signatures, photos, completed PDFs)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting storage cleanup...');

        $directories = [
            'stb-photos' => [Stb::class, 'photo'],
            'inspection-photos' => [Inspection::class, 'photo'],
            'completed-stbs' => [Stb::class, 'completed_pdf_path'],
        ];

        $this->warn('Note: "signatures" directory is skipped because signatures are now stored as encrypted data in the DB.');

        foreach ($directories as $dir => $config) {
            $this->cleanupDirectory($dir, $config[0], $config[1]);
        }

        $this->info('Cleanup process finished.');
    }

    /**
     * Cleanup a specific directory based on model usage.
     */
    protected function cleanupDirectory($directory, $modelClass, $columns)
    {
        if (!Storage::disk('public')->exists($directory)) {
            $this->line("Directory <comment>$directory</comment> does not exist. Skipping.");
            return;
        }

        $this->info("Scanning directory: <comment>$directory</comment>");
        $files = Storage::disk('public')->files($directory);
        $totalFiles = count($files);
        $deletedCount = 0;

        foreach ($files as $file) {
            // Check if file path is used in DB
            // Note: Since we use path relative to public disk, we compare directly
            $query = $modelClass::query();
            
            if (is_array($columns)) {
                $query->where(function($q) use ($columns, $file) {
                    foreach ($columns as $col) {
                        $q->orWhere($col, $file);
                    }
                });
            } else {
                $query->where($columns, $file);
            }

            if (!$query->exists()) {
                if ($this->option('dry-run')) {
                    $this->line("  [DRY-RUN] Orphaned: $file");
                } else {
                    Storage::disk('public')->delete($file);
                    $this->line("  [DELETED] Orphaned: $file");
                }
                $deletedCount++;
            }
        }

        $status = $this->option('dry-run') ? "identified" : "removed";
        $this->info("Summary for $directory: $deletedCount/$totalFiles orphaned files $status.");
        $this->line('');
    }
}
