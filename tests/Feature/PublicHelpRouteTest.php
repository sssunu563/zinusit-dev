<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicHelpRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_help_center_is_accessible_without_auth(): void
    {
        $response = $this->get('/help');
        $response->assertOk();
    }

    public function test_public_help_article_is_readable_without_auth(): void
    {
        $user = User::factory()->create();
        $article = Article::create([
            'title' => 'Panduan WiFi Kantor',
            'slug' => 'panduan-wifi-kantor',
            'category' => 'Network & WiFi',
            'content' => 'Langkah koneksi ke wifi kantor...',
            'author_id' => $user->id,
            'is_published' => true,
        ]);

        $response = $this->get("/help/{$article->slug}");
        $response->assertOk();
    }
}
