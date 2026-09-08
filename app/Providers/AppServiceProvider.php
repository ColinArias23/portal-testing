<?php

namespace App\Providers;

use App\Models\Employee;
use App\Models\Report;
use App\Policies\EmployeePolicy;
use App\Policies\ReportPolicy;
use App\Services\AiChat\Contracts\ChatServiceInterface;
use App\Services\AiChat\OllamaChatService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ChatServiceInterface::class, OllamaChatService::class);
    }

    public function boot(): void
    {
        Gate::policy(Report::class, ReportPolicy::class);
        Gate::policy(Employee::class, EmployeePolicy::class);

        $this->configureRateLimiting();
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('announcements', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('ai-chat', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('leave-submissions', function (Request $request) {
            return Limit::perMinute(6)->by($request->user()?->id ?: $request->ip());
        });
    }
}