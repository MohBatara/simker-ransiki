<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\ProductRequest;
use App\Models\Product;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;

class ProductController extends Controller
{
    public function store(ProductRequest $request, Organization $organization): RedirectResponse
    {
        $organization->products()->create($request->validated());

        return back()->with('status', 'Produk ditambahkan.');
    }

    public function update(ProductRequest $request, Organization $organization, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        return back()->with('status', 'Produk diperbarui.');
    }

    public function destroy(Organization $organization, Product $product): RedirectResponse
    {
        $product->delete();

        return back()->with('status', 'Produk dihapus.');
    }
}
