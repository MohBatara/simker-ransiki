<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MasterDataType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MasterDataRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MasterDataController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('admin.master.items', MasterDataType::Village);
    }

    public function items(Request $request, MasterDataType $type): View
    {
        $search = trim((string) $request->query('q'));

        $items = $type->query()
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($type, $search) {
                foreach ($type->searchColumns() as $column) {
                    $query->orWhere($column, 'like', '%'.$search.'%');
                }
            }))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.master.index', compact('type', 'items', 'search'));
    }

    public function store(MasterDataRequest $request, MasterDataType $type): RedirectResponse
    {
        $type->modelClass()::create($request->validated());

        return back()->with('status', ucfirst($type->noun()).' ditambahkan.');
    }

    public function update(MasterDataRequest $request, MasterDataType $type, int $id): RedirectResponse
    {
        $type->modelClass()::findOrFail($id)->update($request->validated());

        return back()->with('status', ucfirst($type->noun()).' diperbarui.');
    }

    public function destroy(MasterDataType $type, int $id): RedirectResponse
    {
        $record = $type->query()->findOrFail($id);

        // Dicek di server, bukan hanya dengan menyembunyikan tombol hapus.
        if ($usage = $type->usage($record)) {
            return back()->with('error', ucfirst($type->noun())." \"{$record->name}\" masih dipakai {$usage}, jadi tidak bisa dihapus.");
        }

        $record->delete();

        return back()->with('status', ucfirst($type->noun()).' dihapus.');
    }
}
