<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DuplicateDismissal;
use App\Support\DuplicateCandidates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * "Kemungkinan data ganda" (khusus Admin). Tidak ada penggabungan otomatis: Admin membuka kedua kelompok
 * dan memutuskan sendiri, atau menandai pasangan sebagai "Bukan data ganda" agar tidak muncul lagi.
 */
class DuplicateController extends Controller
{
    public function index(Request $request): View
    {
        $pairs = (new DuplicateCandidates)->pairs();
        $page = LengthAwarePaginator::resolveCurrentPage();

        return view('admin.organizations.duplicates', [
            'pairs' => new LengthAwarePaginator($pairs->forPage($page, 20)->values(), $pairs->count(), 20, $page, ['path' => $request->url()]),
            'strongCount' => $pairs->where('strong', true)->count(),
        ]);
    }

    public function dismiss(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first' => ['required', 'integer', Rule::exists('organizations', 'id')],
            'second' => ['required', 'integer', 'different:first', Rule::exists('organizations', 'id')],
        ]);

        DuplicateDismissal::dismiss((int) $data['first'], (int) $data['second'], $request->user());

        return back()->with('status', 'Pasangan ditandai bukan data ganda.');
    }
}
