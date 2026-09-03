<?php

namespace Tests\Unit;

use App\Services\SnipeItService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SnipeItServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::clear();
        config([
            'services.snipeit.url' => 'https://snipeit.example.test',
            'services.snipeit.token' => 'test-token',
        ]);
    }

    public function test_fetch_rows_force_refresh_updates_cached_result(): void
    {
        Http::fakeSequence()
            ->push([
                'rows' => [[ 'id' => 1, 'name' => 'Old data' ]],
            ])
            ->push([
                'rows' => [[ 'id' => 1, 'name' => 'New data' ]],
            ]);

        $service = app(SnipeItService::class);

        $first = $service->fetchRows('hardware', [], 500, true);
        $this->assertSame('Old data', $first[0]['name']);

        $second = $service->fetchRows('hardware', [], 500, true);

        $this->assertSame('New data', $second[0]['name']);
    }

    public function test_flush_cache_for_asset_clears_list_and_record_cache(): void
    {
        $service = app(SnipeItService::class);

        $listKey = 'snipeit_api:' . md5('hardware' . serialize(['limit' => 500, 'offset' => 0]));
        $detailKey = 'snipeit_api:' . md5('hardware/42' . serialize([]));

        Cache::put($listKey, ['rows' => [['id' => 42, 'name' => 'Old list data']]], now()->addMinute());
        Cache::put($detailKey, ['id' => 42, 'name' => 'Old detail data'], now()->addMinute());

        $service->flushCacheForAsset('assets', 42);

        $this->assertNull(Cache::get($listKey));
        $this->assertNull(Cache::get($detailKey));
    }
}
