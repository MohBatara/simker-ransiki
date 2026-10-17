<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Models\Opd;
use App\Models\SiteSetting;
use App\Support\Format;
use App\Support\PotentialBlocks;
use App\Support\PublicDirectory;
use App\Support\PublicImages;
use Illuminate\Support\Str;
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
        $settings = SiteSetting::values();
        $address = $settings['office_address'];

        return view('publik.contact', [
            'settings' => $settings,
            'officePhoto' => PublicImages::slot('kantor'),
            // Tautan pencarian alamat (bukan peta tertanam), sama seperti detail kelompok.
            'mapsUrl' => $address
                ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode(collect([preg_replace('/\s+/', ' ', $address)])
                    ->when(! Str::contains($address, 'Ransiki', ignoreCase: true), fn ($parts) => $parts->push('Distrik Ransiki'))
                    ->join(', '))
                : null,
            'opds' => Opd::orderBy('name')->get(['name', 'short_name', 'description']),
        ]);
    }
}
