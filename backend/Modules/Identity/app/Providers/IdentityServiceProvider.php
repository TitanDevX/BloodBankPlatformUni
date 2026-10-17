<?php

namespace Modules\Identity\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Modules\Identity\Http\ApiError;
use Modules\Identity\Models\Role;
use Nwidart\Modules\Support\ModuleServiceProvider;

class IdentityServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        Gate::define('manage-users', fn (User $user): bool => $user->role()
            ->where('code', Role::Administrator)
            ->exists());

        RateLimiter::for('identity-login', function (Request $request): Limit {
            $key = hash('sha256', Str::lower((string) $request->input('email')).'|'.$request->ip());

            return Limit::perMinute(5)->by($key)->response(
                fn (Request $request, array $headers) => ApiError::make(
                    429,
                    'RESOURCE_EXHAUSTED',
                    'LOGIN_RATE_LIMITED',
                    'Too many login attempts. Try again later.',
                )->withHeaders($headers),
            );
        });
    }

    /**
     * The name of the module.
     */
    protected string $name = 'Identity';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'identity';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    // protected array $commands = [];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * Define module schedules.
     *
     * @param  $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}
