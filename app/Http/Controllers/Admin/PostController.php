<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrganizationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PostCoverRequest;
use App\Http\Requests\Admin\PostRequest;
use App\Models\Organization;
use App\Models\Post;
use App\Support\Format;
use App\Support\ImageVariants;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Berita & Kegiatan (Admin). Tampil di website publik lewat Post::publiclyListed. */
class PostController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'kategori', 'status']);

        return view('admin.posts.index', [
            'posts' => Post::filter($filters)->orderByRaw('published_at IS NULL DESC')->latest('published_at')->latest('id')->paginate(20)->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('admin.posts.form', ['post' => new Post, ...$this->options()]);
    }

    public function store(PostRequest $request): RedirectResponse
    {
        $post = DB::transaction(function () use ($request) {
            $post = new Post($request->postData());
            $post->forceFill(['created_by' => $request->user()->id, 'updated_by' => $request->user()->id])->save();
            $post->organizations()->sync($request->validated('organizations'));

            return $post;
        });
        Cache::forget('public.sitemap');

        return redirect()->route('admin.posts.edit', $post)->with('status', 'Berita disimpan. Tambahkan foto sampul bila ada.');
    }

    public function edit(Post $post): View
    {
        return view('admin.posts.form', ['post' => $post->load('organizations:id'), ...$this->options()]);
    }

    public function update(PostRequest $request, Post $post): RedirectResponse
    {
        DB::transaction(function () use ($request, $post) {
            $post->fill($request->postData())->forceFill(['updated_by' => $request->user()->id])->save();
            $post->organizations()->sync($request->validated('organizations'));
        });
        Cache::forget('public.sitemap');

        return redirect()->route('admin.posts.edit', $post)->with('status', 'Berita disimpan.');
    }

    public function destroy(Post $post): RedirectResponse
    {
        $post->delete();
        Cache::forget('public.sitemap');

        return redirect()->route('admin.posts.index')->with('status', 'Berita dihapus.');
    }

    /** Pratinjau isi Markdown dengan aturan render yang sama seperti halaman publik. */
    public function preview(Request $request): JsonResponse
    {
        $request->validate(['body' => ['nullable', 'string', 'max:50000']]);

        return response()->json(['html' => Format::markdown($request->input('body')) ?? '']);
    }

    /** Diproses seperti foto sampul beranda: putar sesuai EXIF, tanpa metadata, + varian WebP 480/960/1600. */
    public function storeCover(PostCoverRequest $request, Post $post): JsonResponse
    {
        $path = 'posts/berita-'.$post->id.'-'.Str::uuid().'.jpg';
        ImageVariants::store(Post::PHOTO_DISK, $request->file('file')->getRealPath(), $path, ImageVariants::WIDE, ImageVariants::HERO_BUDGET);

        ImageVariants::delete(Post::PHOTO_DISK, $post->cover_photo_path, ImageVariants::WIDE);
        $post->forceFill(['cover_photo_path' => $path])->save();

        return response()->json(['message' => 'Foto sampul diperbarui.'], 201);
    }

    public function destroyCover(Post $post): RedirectResponse
    {
        ImageVariants::delete(Post::PHOTO_DISK, $post->cover_photo_path, ImageVariants::WIDE);
        $post->forceFill(['cover_photo_path' => null])->save();

        return back()->with('status', 'Foto sampul dihapus.');
    }

    private function options(): array
    {
        return [
            'organizations' => Organization::query()->with('village:id,name')->orderBy('name')
                ->get(['id', 'name', 'village_id', 'status', 'is_public'])
                ->map(fn (Organization $organization) => [
                    'id' => $organization->id,
                    'label' => $organization->name.' · '.$organization->village->name,
                    'note' => match (true) {
                        $organization->status === OrganizationStatus::Closed => 'Ditutup, tidak tampil di publik',
                        ! $organization->is_public => 'Tidak tampil di publik',
                        default => null,
                    },
                ]),
        ];
    }
}
