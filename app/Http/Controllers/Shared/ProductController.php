<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\ProductRequest;
use App\Models\Product;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Dipakai Admin/OPD (URL berisi {organization}) dan Kelompok (kelompok dari akun). Hak dicek di
 * ProductRequest::authorize() dan Policy; data kelompok lain dijawab 404.
 */
class ProductController extends Controller
{
    public function store(ProductRequest $request, ?Organization $organization = null): RedirectResponse
    {
        $request->targetOrganization()->products()->create($request->validated());

        return back()->with('status', 'Produk ditambahkan.');
    }

    public function update(ProductRequest $request, ?Organization $organization = null, ?Product $product = null): RedirectResponse
    {
        $product->update($request->validated());

        return back()->with('status', 'Produk diperbarui.');
    }

    public function destroy(?Organization $organization = null, ?Product $product = null): RedirectResponse
    {
        Gate::authorize('delete', $product);

        $product->delete();

        return back()->with('status', 'Produk dihapus.');
    }
}
