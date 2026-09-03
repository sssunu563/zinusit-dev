<?php

namespace Tests\Feature;

use App\Models\AuthLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_auth_logs_page(): void
    {
        $response = $this->get('/auth-logs');

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_auth_logs_page(): void
    {
        $user = User::factory()->create();

        AuthLog::query()->create([
            'user_id' => $user->id,
            'identifier' => $user->email,
            'event' => 'login',
            'status' => 'success',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'meta' => ['sync_changed' => true],
        ]);

        $response = $this->actingAs($user)->get('/auth-logs');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('AuthLogs/Index')
            ->where('logs.data.0.event', 'login')
            ->where('logs.data.0.user.email', $user->email)
            ->where('logs.data.0.status', 'success'));
    }

    public function test_authenticated_users_can_filter_auth_logs_by_date_range(): void
    {
        $user = User::factory()->create();

        $olderLog = AuthLog::query()->create([
            'user_id' => $user->id,
            'identifier' => $user->email,
            'event' => 'login',
            'status' => 'failed',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'meta' => ['reason' => 'invalid_password'],
        ]);

        $recentLog = AuthLog::query()->create([
            'user_id' => $user->id,
            'identifier' => $user->email,
            'event' => 'logout',
            'status' => 'success',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'meta' => ['guard' => 'web'],
        ]);

        $olderLog->forceFill([
            'created_at' => CarbonImmutable::parse('2026-03-20 09:00:00'),
            'updated_at' => CarbonImmutable::parse('2026-03-20 09:00:00'),
        ])->save();

        $recentLog->forceFill([
            'created_at' => CarbonImmutable::parse('2026-03-31 10:00:00'),
            'updated_at' => CarbonImmutable::parse('2026-03-31 10:00:00'),
        ])->save();

        $response = $this->actingAs($user)->get('/auth-logs?from_date=2026-03-25&to_date=2026-03-31');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('AuthLogs/Index')
            ->where('filters.from_date', '2026-03-25')
            ->where('filters.to_date', '2026-03-31')
            ->where('logs.data', function (Collection $logs) use ($recentLog): bool {
                $firstLog = $logs->first();

                return $logs->count() === 1
                    && $firstLog['id'] === $recentLog->id
                    && $firstLog['event'] === 'logout';
            }));
    }

    public function test_authenticated_users_can_export_filtered_auth_logs_as_csv(): void
    {
        $user = User::factory()->create();

        $includedLog = AuthLog::query()->create([
            'user_id' => $user->id,
            'identifier' => $user->email,
            'event' => 'logout',
            'status' => 'success',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit Browser',
            'meta' => ['guard' => 'web'],
        ]);

        $excludedLog = AuthLog::query()->create([
            'user_id' => $user->id,
            'identifier' => $user->email,
            'event' => 'login',
            'status' => 'failed',
            'ip_address' => '127.0.0.2',
            'user_agent' => 'PHPUnit Browser',
            'meta' => ['reason' => 'invalid_password'],
        ]);

        $includedLog->forceFill([
            'created_at' => CarbonImmutable::parse('2026-03-31 10:00:00'),
            'updated_at' => CarbonImmutable::parse('2026-03-31 10:00:00'),
        ])->save();

        $excludedLog->forceFill([
            'created_at' => CarbonImmutable::parse('2026-03-20 10:00:00'),
            'updated_at' => CarbonImmutable::parse('2026-03-20 10:00:00'),
        ])->save();

        $response = $this->actingAs($user)->get('/auth-logs/export?event=logout&from_date=2026-03-25&to_date=2026-03-31');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $response->assertHeader('content-disposition');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Date,Event,Status,"User Name","User Email",Username,Identifier,"IP Address","User Agent",Meta', $csv);
        $this->assertStringContainsString('logout,success', $csv);
        $this->assertStringContainsString('guard: web', $csv);
        $this->assertStringNotContainsString('login,failed', $csv);
    }
}