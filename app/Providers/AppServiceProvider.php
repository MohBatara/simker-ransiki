<?php

namespace App\Providers;

use App\Models\License;
use App\Models\MonthlyReport;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

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
        Relation::enforceMorphMap([
            'organization' => Organization::class,
            'license' => License::class,
            'product' => Product::class,
            'monthly_report' => MonthlyReport::class,
            'user' => User::class,
        ]);

        // Lazy loading memicu N+1; di luar produksi langsung jadi error agar cepat ketahuan.
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
