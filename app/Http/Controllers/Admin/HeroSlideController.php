<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Publik\SiteImageController;
use App\Http\Requests\Admin\HeroSlideRequest;
use App\Models\HeroSlide;
use App\Support\ImageVariants;
use App\Support\SitePhotos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Slide hero beranda (Konten Website > Beranda). Hanya Admin (HeroSlidePolicy di route).
 */
class HeroSlideController extends Controller
{
    public function store(HeroSlideRequest $request): RedirectResponse
    {
        $slide = new HeroSlide($request->fields());
        $slide->forceFill([
            'photo_path' => SitePhotos::storeHero($request->photo()->getRealPath()),
            'position' => (int) HeroSlide::max('position') + 1,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ])->save();

        return to_route('admin.content.edit')->with(['status' => 'Slide ditambahkan.', 'warning' => $request->sharpnessWarning()]);
    }

    public function update(HeroSlideRequest $request, HeroSlide $slide): RedirectResponse
    {
        $slide->fill($request->fields())->forceFill(['updated_by' => $request->user()->id]);

        if ($request->photo()) {
            $old = $slide->photo_path;
            $slide->forceFill(['photo_path' => SitePhotos::storeHero($request->photo()->getRealPath())]);
        }

        $slide->save();

        if (isset($old)) {
            ImageVariants::delete(SiteImageController::DISK, $old, ImageVariants::HERO);
        }

        return to_route('admin.content.edit')->with(['status' => 'Slide disimpan.', 'warning' => $request->sharpnessWarning()]);
    }

    /** Tukar urutan dengan slide di atas ("naik") atau di bawahnya ("turun"). */
    public function move(HeroSlide $slide, string $direction): RedirectResponse
    {
        DB::transaction(function () use ($slide, $direction) {
            $slides = HeroSlide::orderBy('position')->orderBy('id')->lockForUpdate()->get()->values();
            $index = $slides->search(fn (HeroSlide $item) => $item->is($slide));
            $target = $direction === 'naik' ? $index - 1 : $index + 1;

            if (isset($slides[$target])) {
                [$slides[$index], $slides[$target]] = [$slides[$target], $slides[$index]];
            }

            $slides->each(fn (HeroSlide $item, int $i) => $item->forceFill(['position' => $i + 1])->save());
        });

        return to_route('admin.content.edit')->with('status', 'Urutan slide diubah.');
    }

    public function destroy(HeroSlide $slide): RedirectResponse
    {
        $slide->delete();

        HeroSlide::orderBy('position')->orderBy('id')->get()
            ->each(fn (HeroSlide $item, int $i) => $item->forceFill(['position' => $i + 1])->save());

        return to_route('admin.content.edit')->with('status', 'Slide dihapus.');
    }
}
