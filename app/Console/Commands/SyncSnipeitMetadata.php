<?php

namespace App\Console\Commands;

use App\Models\SnipeitCompany;
use App\Models\SnipeitFieldset;
use App\Models\SnipeitManufacturer;
use App\Models\SnipeitModel;
use App\Models\SnipeitSupplier;
use App\Models\SnipeitUser;
use App\Services\SnipeItService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SyncSnipeitMetadata extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'snipeit:sync-metadata {--force : Force refresh from API}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync metadata from Snipe-IT API to local database (users, models, manufacturers, suppliers, companies, fieldsets)';

    private SnipeItService $snipe;

    public function __construct(SnipeItService $snipe)
    {
        parent::__construct();
        $this->snipe = $snipe;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startTime = microtime(true);
        $forceRefresh = $this->option('force');

        $this->info('🔄 Starting Snipe-IT metadata sync...');
        $this->newLine();

        try {
            // Sync all metadata tables
            $this->syncUsers($forceRefresh);
            $this->syncModels($forceRefresh);
            $this->syncManufacturers($forceRefresh);
            $this->syncSuppliers($forceRefresh);
            $this->syncCompanies($forceRefresh);
            $this->syncFieldsets($forceRefresh);

            // Clear metadata cache so next request gets fresh data
            Cache::forget('asset_metadata');
            $this->info('🗑️  Cleared metadata cache');

            $elapsed = round(microtime(true) - $startTime, 2);
            $this->newLine();
            $this->info("✅ Metadata sync completed in {$elapsed}s");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Sync failed: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }

    private function syncUsers(bool $forceRefresh): void
    {
        $this->info('👥 Syncing users...');
        
        // Fetch from API with pagination
        $pool = $this->snipe->requestPool([
            'users_p1' => ['users', ['limit' => 500, 'offset' => 0]],
            'users_p2' => ['users', ['limit' => 500, 'offset' => 500]],
        ], $forceRefresh);

        $users = array_merge($pool['users_p1']['rows'] ?? [], $pool['users_p2']['rows'] ?? []);
        
        $syncedCount = 0;
        $now = now();

        foreach ($users as $user) {
            $id = $user['id'] ?? null;
            if (!$id) continue;

            SnipeitUser::updateOrCreate(
                ['id' => $id],
                [
                    'username' => $user['username'] ?? null,
                    'first_name' => $user['first_name'] ?? null,
                    'last_name' => $user['last_name'] ?? null,
                    'email' => $user['email'] ?? null,
                    'department' => $user['department']['name'] ?? null,
                    'company' => $user['company']['name'] ?? null,
                    'raw_data' => $user,
                    'synced_at' => $now,
                ]
            );
            $syncedCount++;
        }

        $this->line("   ✓ Synced {$syncedCount} users");
    }

    private function syncModels(bool $forceRefresh): void
    {
        $this->info('📦 Syncing models...');
        
        $pool = $this->snipe->requestPool([
            'models_p1' => ['models', ['limit' => 500, 'offset' => 0]],
            'models_p2' => ['models', ['limit' => 500, 'offset' => 500]],
            'models_p3' => ['models', ['limit' => 500, 'offset' => 1000]],
        ], $forceRefresh);

        $models = array_merge(
            $pool['models_p1']['rows'] ?? [],
            $pool['models_p2']['rows'] ?? [],
            $pool['models_p3']['rows'] ?? []
        );
        
        $syncedCount = 0;
        $now = now();

        foreach ($models as $model) {
            $id = $model['id'] ?? null;
            if (!$id) continue;

            SnipeitModel::updateOrCreate(
                ['id' => $id],
                [
                    'name' => $model['name'] ?? '',
                    'model_number' => $model['model_number'] ?? null,
                    'manufacturer_id' => $model['manufacturer']['id'] ?? null,
                    'manufacturer_name' => $model['manufacturer']['name'] ?? null,
                    'category_id' => $model['category']['id'] ?? null,
                    'category_name' => $model['category']['name'] ?? null,
                    'fieldset_id' => $model['fieldset']['id'] ?? null,
                    'fieldset_name' => $model['fieldset']['name'] ?? null,
                    'notes' => $model['notes'] ?? null,
                    'raw_data' => $model,
                    'synced_at' => $now,
                ]
            );
            $syncedCount++;
        }

        $this->line("   ✓ Synced {$syncedCount} models");
    }

    private function syncManufacturers(bool $forceRefresh): void
    {
        $this->info('🏭 Syncing manufacturers...');
        
        $rows = $this->snipe->fetchRows('manufacturers', ['limit' => 500], 500, $forceRefresh);
        
        $syncedCount = 0;
        $now = now();

        foreach ($rows as $item) {
            $id = $item['id'] ?? null;
            if (!$id) continue;

            SnipeitManufacturer::updateOrCreate(
                ['id' => $id],
                [
                    'name' => $item['name'] ?? '',
                    'url' => $item['url'] ?? null,
                    'support_url' => $item['support_url'] ?? null,
                    'support_phone' => $item['support_phone'] ?? null,
                    'support_email' => $item['support_email'] ?? null,
                    'raw_data' => $item,
                    'synced_at' => $now,
                ]
            );
            $syncedCount++;
        }

        $this->line("   ✓ Synced {$syncedCount} manufacturers");
    }

    private function syncSuppliers(bool $forceRefresh): void
    {
        $this->info('🚚 Syncing suppliers...');
        
        $rows = $this->snipe->fetchRows('suppliers', ['limit' => 500], 500, $forceRefresh);
        
        $syncedCount = 0;
        $now = now();

        foreach ($rows as $item) {
            $id = $item['id'] ?? null;
            if (!$id) continue;

            SnipeitSupplier::updateOrCreate(
                ['id' => $id],
                [
                    'name' => $item['name'] ?? '',
                    'address' => $item['address'] ?? null,
                    'address2' => $item['address2'] ?? null,
                    'city' => $item['city'] ?? null,
                    'state' => $item['state'] ?? null,
                    'country' => $item['country'] ?? null,
                    'zip' => $item['zip'] ?? null,
                    'phone' => $item['phone'] ?? null,
                    'fax' => $item['fax'] ?? null,
                    'email' => $item['email'] ?? null,
                    'contact' => $item['contact'] ?? null,
                    'notes' => $item['notes'] ?? null,
                    'raw_data' => $item,
                    'synced_at' => $now,
                ]
            );
            $syncedCount++;
        }

        $this->line("   ✓ Synced {$syncedCount} suppliers");
    }

    private function syncCompanies(bool $forceRefresh): void
    {
        $this->info('🏢 Syncing companies...');
        
        $rows = $this->snipe->fetchRows('companies', ['limit' => 500], 500, $forceRefresh);
        
        $syncedCount = 0;
        $now = now();

        foreach ($rows as $item) {
            $id = $item['id'] ?? null;
            if (!$id) continue;

            SnipeitCompany::updateOrCreate(
                ['id' => $id],
                [
                    'name' => $item['name'] ?? '',
                    'raw_data' => $item,
                    'synced_at' => $now,
                ]
            );
            $syncedCount++;
        }

        $this->line("   ✓ Synced {$syncedCount} companies");
    }

    private function syncFieldsets(bool $forceRefresh): void
    {
        $this->info('📋 Syncing fieldsets...');
        
        $rows = $this->snipe->fetchRows('fieldsets', ['limit' => 500], 500, $forceRefresh);
        
        $syncedCount = 0;
        $now = now();

        foreach ($rows as $item) {
            $id = $item['id'] ?? null;
            if (!$id) continue;

            SnipeitFieldset::updateOrCreate(
                ['id' => $id],
                [
                    'name' => $item['name'] ?? '',
                    'fields' => $item['fields'] ?? null,
                    'raw_data' => $item,
                    'synced_at' => $now,
                ]
            );
            $syncedCount++;
        }

        $this->line("   ✓ Synced {$syncedCount} fieldsets");
    }
}
