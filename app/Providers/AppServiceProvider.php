<?php

namespace App\Providers;

use App\Models\License;
use App\Models\MonthlyReport;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use App\Support\PublicDirectory;
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

        // Tampilan paginasi bawaan Laravel memakai warna di luar token desain.
        Paginator::defaultView('partials.pagination');

        // Unggah file: 30 per menit per akun (CLAUDE.md Bagian 9).
        View::composer('components.layouts.public', fn ($view) => $view->with('footer', PublicDirectory::footer()));

        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));
    }
}
