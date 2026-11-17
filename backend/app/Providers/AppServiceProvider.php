<?php

namespace App\Providers;

use App\Models\InternshipApplication;
use App\Policies\InternshipApplicationPolicy;
use App\Services\ApplicationWorkflowService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ApplicationWorkflowService::class);
    }

    public function boot(): void
    {
        Gate::policy(InternshipApplication::class, InternshipApplicationPolicy::class);
    }
}