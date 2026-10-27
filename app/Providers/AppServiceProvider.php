<?php

namespace App\Providers;

use App\Models\AuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Throwable;

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

        Event::listen(Login::class, function (Login $event): void {
            $this->recordAuthenticationEvent('auth.login', 'Connexion réussie', $event->user->getAuthIdentifier(), $event->user->getAuthIdentifier(), [
                'result' => 'success',
                'guard' => $event->guard,
                'remembered' => $event->remember,
            ]);
        });

        Event::listen(Logout::class, function (Logout $event): void {
            $this->recordAuthenticationEvent('auth.logout', 'Déconnexion', $event->user->getAuthIdentifier(), $event->user->getAuthIdentifier(), [
                'result' => 'logout',
                'guard' => $event->guard,
            ]);
        });

        Event::listen(Failed::class, function (Failed $event): void {
            $identifier = $event->credentials['email'] ?? $event->credentials['username'] ?? null;
            $this->recordAuthenticationEvent('auth.failed', 'Tentative de connexion échouée', null, $event->user->getAuthIdentifier(), [
                'result' => 'failure',
                'guard' => $event->guard,
                'login_identifier' => is_string($identifier) ? mb_substr($identifier, 0, 255) : null,
            ]);
        });
    }

    /** @param array<string, mixed> $metadata */
    private function recordAuthenticationEvent(string $event, string $summary, int|string|null $actorId, int|string|null $subjectId, array $metadata): void
    {
        try {
            $request = app()->bound('request') ? request() : null;
            AuditLog::query()->create([
                'actor_id' => $actorId,
                'establishment_id' => null,
                'event' => $event,
                'subject_type' => 'user',
                'subject_id' => $subjectId,
                'summary' => $summary,
                'metadata' => [...$metadata, 'ip' => $request?->ip(), 'user_agent' => $request?->userAgent() ? mb_substr($request->userAgent(), 0, 1000) : null],
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::error('Impossible d’écrire un événement d’authentification dans le journal d’audit.', [
                'event' => $event,
                'exception' => $exception::class,
            ]);
        }
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
}
