<?php

namespace App\Console\Commands;

use App\Models\SnipeitAccessory;
use App\Models\SnipeitAsset;
use App\Models\SnipeitCategory;
use App\Models\SnipeitComponent;
use App\Models\SnipeitConsumable;
use App\Models\SnipeitLicense;
use App\Models\SnipeitLocation;
use App\Models\SnipeitStatusLabel;
use App\Models\SnipeitUser;
use App\Services\SnipeItService;
use Illuminate\Console\Command;

class SyncSnipeItData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'snipeit:sync 
                            {--type=all : Sync specific type: all, status, categories, locations, users, assets, consumables, licenses, accessories, components}
                            {--force : Force refresh, bypass cache}
                            {--limit=500 : Limit items per API call}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Snipe-IT data to local database mirror for performance optimization';

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
        $type = $this->option('type');
        $forceRefresh = $this->option('force');
        $limit = (int) $this->option('limit');

        $this->info('🔄 Starting Snipe-IT data sync...');
        $startTime = now();

        try {
            match ($type) {
                'status' => $this->syncStatusLabels($forceRefresh, $limit),
                'categories' => $this->syncCategories($forceRefresh, $limit),
                'locations' => $this->syncLocations($forceRefresh, $limit),
                'users' => $this->syncUsers($forceRefresh, $limit),
                'assets' => $this->syncAssets($forceRefresh, $limit),
                'consumables' => $this->syncConsumables($forceRefresh, $limit),
                'licenses' => $this->syncLicenses($forceRefresh, $limit),
                'accessories' => $this->syncAccessories($forceRefresh, $limit),
                'components' => $this->syncComponents($forceRefresh, $limit),
                'all' => $this->syncAll($forceRefresh, $limit),
                default => $this->error("Unknown type: {$type}"),
            };

            $duration = now()->diffInSeconds($startTime);
            $this->info("✅ Sync completed in {$duration} seconds");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Sync failed: {$e->getMessage()}");
            $this->error($e->getTraceAsString());

            return Command::FAILURE;
        }
    }

    private function syncAll(bool $forceRefresh, int $limit): void
    {
        $this->syncStatusLabels($forceRefresh, $limit);
        $this->syncCategories($forceRefresh, $limit);
        $this->syncLocations($forceRefresh, $limit);
        $this->syncUsers($forceRefresh, $limit);
        $this->syncAssets($forceRefresh, $limit);
        $this->syncConsumables($forceRefresh, $limit);
        $this->syncLicenses($forceRefresh, $limit);
        $this->syncAccessories($forceRefresh, $limit);
        $this->syncComponents($forceRefresh, $limit);
    }

    private function syncStatusLabels(bool $forceRefresh, int $limit): void
    {
        $this->info('📊 Syncing status labels...');

        $rows = $this->snipe->fetchRows('statuslabels', [], $limit, $forceRefresh);
        $synced = 0;

        foreach ($rows as $row) {
            SnipeitStatusLabel::updateOrCreate(
                ['id' => $row['id']],
                [
                    'name' => $row['name'] ?? '-',
                    'status_type' => $row['status_type'] ?? null,
                    'raw_data' => $row,
                    'synced_at' => now(),
                ]
            );
            $synced++;
        }

        $this->line("   ✓ Synced {$synced} status labels");
    }

    private function syncCategories(bool $forceRefresh, int $limit): void
    {
        $this->info('📁 Syncing categories...');

        $rows = $this->snipe->fetchRows('categories', [], $limit, $forceRefresh);
        $synced = 0;

        foreach ($rows as $row) {
            SnipeitCategory::updateOrCreate(
                ['id' => $row['id']],
                [
                    'name' => $row['name'] ?? '-',
                    'category_type' => $row['category_type'] ?? null,
                    'raw_data' => $row,
                    'synced_at' => now(),
                ]
            );
            $synced++;
        }

        $this->line("   ✓ Synced {$synced} categories");
    }

    private function syncLocations(bool $forceRefresh, int $limit): void
    {
        $this->info('📍 Syncing locations...');

        $rows = $this->snipe->fetchRows('locations', [], $limit, $forceRefresh);
        $synced = 0;

        foreach ($rows as $row) {
            SnipeitLocation::updateOrCreate(
                ['id' => $row['id']],
                [
                    'name' => $row['name'] ?? '-',
                    'address' => $row['address'] ?? null,
                    'city' => $row['city'] ?? null,
                    'raw_data' => $row,
                    'synced_at' => now(),
                ]
            );
            $synced++;
        }

        $this->line("   ✓ Synced {$synced} locations");
    }

    private function syncUsers(bool $forceRefresh, int $limit): void
    {
        $this->info('👥 Syncing users...');

        $rows = $this->snipe->fetchRows('users', [], $limit, $forceRefresh);
        $synced = 0;

        foreach ($rows as $row) {
            SnipeitUser::updateOrCreate(
                ['snipeit_id' => $row['id']],
                [
                    'name' => $row['name'] ?? '-',
                    'first_name' => $row['first_name'] ?? null,
                    'last_name' => $row['last_name'] ?? null,
                    'username' => $row['username'] ?? null,
                    'email' => $row['email'] ?? null,
                    'phone' => $row['phone'] ?? null,
                    'jobtitle' => $row['jobtitle'] ?? null,
                    'employee_num' => $row['employee_num'] ?? null,
                    'manager_id' => $row['manager']['id'] ?? null,
                    'manager_name' => $row['manager']['name'] ?? null,
                    'location_id' => $row['location']['id'] ?? null,
                    'location_name' => $row['location']['name'] ?? null,
                    'department_id' => $row['department']['id'] ?? null,
                    'department' => $row['department']['name'] ?? null,
                    'company_id' => $row['company']['id'] ?? null,
                    'company' => $row['company']['name'] ?? null,
                    'activated' => $row['activated'] ?? false,
                    'avatar' => $row['avatar'] ?? null,
                    'raw_data' => $row,
                    'snipeit_created_at' => isset($row['created_at']['datetime']) ? $row['created_at']['datetime'] : null,
                    'snipeit_updated_at' => isset($row['updated_at']['datetime']) ? $row['updated_at']['datetime'] : null,
                ]
            );
            $synced++;
        }

        $this->line("   ✓ Synced {$synced} users");
    }

    private function syncAssets(bool $forceRefresh, int $limit): void
    {
        $this->info('💻 Syncing hardware assets...');

        // Fetch all assets tanpa filter
        $rows = $this->snipe->fetchRows('hardware', [], $limit, $forceRefresh);
        $synced = 0;

        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();

        foreach ($rows as $row) {
            SnipeitAsset::updateOrCreate(
                ['id' => $row['id']],
                [
                    'name' => $row['name'] ?? $row['asset_tag'] ?? '-',
                    'asset_tag' => $row['asset_tag'] ?? null,
                    'serial' => $row['serial'] ?? null,
                    'model_name' => $row['model']['name'] ?? null,
                    'status_id' => $row['status_label']['id'] ?? null,
                    'category_id' => $row['category']['id'] ?? null,
                    'location_id' => $row['location']['id'] ?? null,
                    'assigned_to' => $row['assigned_to']['id'] ?? null,
                    'assigned_type' => $row['assigned_type'] ?? null,
                    'notes' => $row['notes'] ?? null,
                    'raw_data' => $row,
                    'synced_at' => now(),
                ]
            );
            $synced++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->line("   ✓ Synced {$synced} hardware assets");
    }

    private function syncConsumables(bool $forceRefresh, int $limit): void
    {
        $this->info('🔋 Syncing consumables...');

        $rows = $this->snipe->fetchRows('consumables', [], $limit, $forceRefresh);
        $synced = 0;

        foreach ($rows as $row) {
            SnipeitConsumable::updateOrCreate(
                ['id' => $row['id']],
                [
                    'name' => $row['name'] ?? '-',
                    'category_id' => $row['category']['id'] ?? null,
                    'qty' => $row['qty'] ?? 0,
                    'remaining' => $row['remaining'] ?? 0,
                    'raw_data' => $row,
                    'synced_at' => now(),
                ]
            );
            $synced++;
        }

        $this->line("   ✓ Synced {$synced} consumables");
    }

    private function syncLicenses(bool $forceRefresh, int $limit): void
    {
        $this->info('📜 Syncing licenses...');

        $rows = $this->snipe->fetchRows('licenses', [], $limit, $forceRefresh);
        $synced = 0;

        foreach ($rows as $row) {
            SnipeitLicense::updateOrCreate(
                ['id' => $row['id']],
                [
                    'name' => $row['name'] ?? '-',
                    'product_key' => $row['product_key'] ?? null,
                    'category_id' => $row['category']['id'] ?? null,
                    'seats' => $row['seats'] ?? 0,
                    'free_seats_count' => $row['free_seats_count'] ?? 0,
                    'expiration_date' => $row['expiration_date'] ?? null,
                    'raw_data' => $row,
                    'synced_at' => now(),
                ]
            );
            $synced++;
        }

        $this->line("   ✓ Synced {$synced} licenses");
    }

    private function syncAccessories(bool $forceRefresh, int $limit): void
    {
        $this->info('🔌 Syncing accessories...');

        $rows = $this->snipe->fetchRows('accessories', [], $limit, $forceRefresh);
        $synced = 0;

        foreach ($rows as $row) {
            SnipeitAccessory::updateOrCreate(
                ['id' => $row['id']],
                [
                    'name' => $row['name'] ?? '-',
                    'category_id' => $row['category']['id'] ?? null,
                    'qty' => $row['qty'] ?? 0,
                    'remaining_qty' => $row['remaining_qty'] ?? 0,
                    'raw_data' => $row,
                    'synced_at' => now(),
                ]
            );
            $synced++;
        }

        $this->line("   ✓ Synced {$synced} accessories");
    }

    private function syncComponents(bool $forceRefresh, int $limit): void
    {
        $this->info('⚙️ Syncing components...');

        $rows = $this->snipe->fetchRows('components', [], $limit, $forceRefresh);
        $synced = 0;

        foreach ($rows as $row) {
            SnipeitComponent::updateOrCreate(
                ['id' => $row['id']],
                [
                    'name' => $row['name'] ?? '-',
                    'category_id' => $row['category']['id'] ?? null,
                    'qty' => $row['qty'] ?? 0,
                    'remaining' => $row['remaining'] ?? 0,
                    'raw_data' => $row,
                    'synced_at' => now(),
                ]
            );
            $synced++;
        }

        $this->line("   ✓ Synced {$synced} components");
    }
}
