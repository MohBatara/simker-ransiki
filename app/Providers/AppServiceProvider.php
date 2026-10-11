<?php

namespace App\Providers;

use App\Models\License;
use App\Models\MonthlyReport;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use App\Support\PublicDirectory;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        // Angka lonceng: satu query per halaman area login.
        View::composer('components.layouts.app', fn ($view) => $view->with('unreadNotifications', auth()->user()?->unreadNotifications()->count() ?? 0));

        // "Lupa kata sandi": 5 per menit per IP dan 3 per jam per email. Jawabannya tetap pesan umum yang sama.
        RateLimiter::for('password-reset', fn (Request $request) => [
            Limit::perMinute(5)->by('ip|'.$request->ip()),
            Limit::perHour(3)->by('email|'.Str::lower(trim((string) $request->input('email')))),
        ]);

        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));
    }
}
