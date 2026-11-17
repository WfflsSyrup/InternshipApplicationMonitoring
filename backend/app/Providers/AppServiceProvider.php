<?php

namespace App\Providers;

use App\Models\AutomationJob;
use App\Models\InternshipApplication;
use App\Policies\AutomationJobPolicy;
use App\Policies\InternshipApplicationPolicy;
use App\Services\ApplicationWorkflowService;
use App\Services\Automation\AutomationDispatcherService;
use App\Services\Automation\AutomationJobService;
use App\Services\Automation\AutomationSweepService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ApplicationWorkflowService::class);
        $this->app->singleton(AutomationJobService::class);
        $this->app->singleton(AutomationDispatcherService::class);
        $this->app->singleton(AutomationSweepService::class);
    }

    public function boot(): void
    {
        Gate::policy(InternshipApplication::class, InternshipApplicationPolicy::class);
        Gate::policy(AutomationJob::class, AutomationJobPolicy::class);
    }
}