<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicAssetCheckTest extends TestCase
{
    public function test_check_assets_page_is_accessible(): void
    {
        $response = $this->get('/check-assets');
        $response->assertStatus(200);
    }

    public function test_public_asset_url_redirects_to_unified_check_assets(): void
    {
        $response = $this->get('/a/TEST-TAG-123');
        $response->assertRedirect('/check-assets?tag=TEST-TAG-123');
    }

    public function test_lookup_endpoint_validates_required_tag(): void
    {
        $response = $this->getJson('/check-assets/lookup');
        $response->assertStatus(400);
    }
}
