<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Models\Opd;
use App\Models\SiteSetting;
use App\Support\Format;
use App\Support\PotentialBlocks;
use App\Support\PublicDirectory;
use App\Support\PublicImages;
use Illuminate\View\View;

/**
 * Halaman berisi teks dari Konten Website: Profil Ransiki dan Kontak.
 */
class PageController extends Controller
{
    public function profile(): View
    {
        $settings = SiteSetting::values();
        $villages = PublicDirectory::villages();

        // Data singkat: hanya baris yang diisi Admin; jumlah kampung dihitung dari Data Master.
        $facts = collect(SiteSetting::PROFILE_FACT_KEYS)
            ->mapWithKeys(fn (string $label, string $key) => [$label => $settings[$key]])
            ->put('Jumlah kampung', $villages->isNotEmpty() ? Format::number($villages->count()) : null)
            ->filter();

        return view('publik.profile', [
            'settings' => $settings,
            'text' => fn (string $key) => $settings[$key] ?? SiteSetting::DEFAULTS[$key] ?? null,
            'banner' => PublicImages::slot('profil') ?? PublicImages::slot('sampul') ?? PublicImages::heroDefault(),
            'officePhoto' => PublicImages::slot('kantor'),
            'facts' => $facts,
            'stats' => PublicDirectory::stats(),
            'potentials' => PotentialBlocks::all($settings),
            'villages' => $villages,
        ]);
    }

    public function contact(): View
    {
        return view('publik.contact', [
            'settings' => SiteSetting::values(),
            'opds' => Opd::orderBy('name')->get(['name', 'description']),
        ]);
    }
}
