<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use App\Services\AuthLogService;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthLogging();

        // Register morph map for Snipe-IT items and virtual types to prevent "Class not found" errors
        \Illuminate\Database\Eloquent\Relations\Relation::morphMap([
            'snipeit_assets'      => \App\Models\ActionLog::class,
            'snipeit_hardware'    => \App\Models\ActionLog::class,
            'snipeit_laptop'      => \App\Models\ActionLog::class,
            'snipeit_license'     => \App\Models\ActionLog::class,
            'snipeit_accessories' => \App\Models\ActionLog::class,
            'snipeit_consumable'  => \App\Models\ActionLog::class,
            'snipeit_component'   => \App\Models\ActionLog::class,

            // Operation and report logs
            'ServerOperation'     => \App\Models\ActionLog::class,
            'CctvOperation'       => \App\Models\ActionLog::class,
            'Bandwidth'           => \App\Models\ActionLog::class,
            'NetworkUptime'       => \App\Models\ActionLog::class,
            'AllReports'          => \App\Models\ActionLog::class,
            'InfraReport'         => \App\Models\ActionLog::class,
            'ServerDevice'        => \App\Models\ServerDevice::class,
            'CctvDevice'          => \App\Models\CctvDevice::class,

            // Short alias mappings
            'Stb'                 => \App\Models\Stb::class,
            'Peminjaman'          => \App\Models\Peminjaman::class,
            'Inspection'          => \App\Models\Inspection::class,
            'Ticket'              => \App\Models\Ticket::class,
            'AuditSession'        => \App\Models\AuditSession::class,
            'User'                => \App\Models\User::class,
        ]);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configureAuthLogging(): void
    {
        Event::listen(function (Logout $event): void {
            if (!$event->user) {
                return;
            }

            $request = app(Request::class);

            app(AuthLogService::class)->write(
                $request,
                'logout',
                'success',
                $event->user->email ?: $event->user->username,
                $event->user,
                [
                    'guard' => $event->guard,
                ],
            );
        });
    }
}
