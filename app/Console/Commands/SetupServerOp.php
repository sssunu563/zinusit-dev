<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SetupServerOp extends Command
{
    protected $signature = 'setup:server-op';
    protected $description = 'Create Server Operation Vue components';

    public function handle(): int
    {
        $baseDir = resource_path('js/pages/Report/ServerOperation');

        // Create directory
        if (!File::isDirectory($baseDir)) {
            File::makeDirectory($baseDir, 0755, true);
            $this->info("✓ Created directory");
        }

        // Create Index.vue
        File::put("$baseDir/Index.vue", $this->getIndexVueContent());
        $this->info("✓ Created Index.vue");

        // Create TabSummary.vue
        File::put("$baseDir/TabSummary.vue", $this->getTabSummaryContent());
        $this->info("✓ Created TabSummary.vue");

        // Create TabServerData.vue
        File::put("$baseDir/TabServerData.vue", $this->getTabServerDataContent());
        $this->info("✓ Created TabServerData.vue");

        // Create TabTemperature.vue
        File::put("$baseDir/TabTemperature.vue", $this->getTabTemperatureContent());
        $this->info("✓ Created TabTemperature.vue");

        $this->info("\n✅ Setup complete! Now run: npm run build");
        return self::SUCCESS;
    }

    private function getIndexVueContent(): string
    {
        return file_get_contents(__DIR__ . '/../../../ServerOp/IndexVue.txt') ?: '';
    }

    private function getTabSummaryContent(): string
    {
        return file_get_contents(__DIR__ . '/../../../ServerOp/TabSummaryVue.txt') ?: '';
    }

    private function getTabServerDataContent(): string
    {
        return file_get_contents(__DIR__ . '/../../../ServerOp/TabServerDataVue.txt') ?: '';
    }

    private function getTabTemperatureContent(): string
    {
        return file_get_contents(__DIR__ . '/../../../ServerOp/TabTemperatureVue.txt') ?: '';
    }
}
