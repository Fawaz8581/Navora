<?php

namespace App\Providers;

use App\Models\AdCampaign;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;

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
        View::composer(['home', 'workspace', 'auth.partials.layout'], function (ViewInstance $view): void {
            $findActiveCampaign = fn (array $placements): ?AdCampaign => AdCampaign::query()
                ->where('status', AdCampaign::STATUS_ACTIVE)
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>', now())
                ->whereIn('placement', $placements)
                ->inRandomOrder()
                ->first();

            $view->with([
                'activeAdvertisement' => $findActiveCampaign(['top', 'global']),
                'sidebarAdvertisement' => $findActiveCampaign(['sidebar']),
            ]);
        });
    }
}
