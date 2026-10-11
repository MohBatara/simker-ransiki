<?php

namespace App\Http\Controllers\Publik;

use App\Enums\PostCategory;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Support\ImageVariants;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Berita & Kegiatan di website publik. Hanya post Terbit yang tanggal terbitnya sudah lewat (Post::publiclyListed);
 * kelompok terkait hanya yang terdaftar publik, dengan kolom publik saja.
 */
class PostController extends Controller
{
    public const COLUMNS = ['posts.id', 'posts.slug', 'posts.title', 'posts.category', 'posts.summary', 'posts.body', 'posts.cover_photo_path', 'posts.published_at', 'posts.updated_at'];

    public function index(Request $request): View
    {
        $category = PostCategory::tryFrom((string) $request->query('kategori'));

        return view('publik.posts.index', [
            'posts' => Post::query()->publiclyListed()
                ->when($category, fn ($query) => $query->where('category', $category))
                ->latest('published_at')->latest('id')
                ->paginate(12, self::COLUMNS)
                ->withQueryString(),
            'category' => $category,
        ]);
    }

    public function show(string $slug): View
    {
        $post = Post::query()->publiclyListed()->where('slug', $slug)
            ->with(['organizations' => fn ($query) => $query->publiclyListed()->orderBy('name')
                ->select(['organizations.id', 'organizations.slug', 'organizations.name', 'organizations.village_id'])
                ->with('village:id,name')])
            ->firstOrFail(self::COLUMNS);

        return view('publik.posts.show', [
            'post' => $post,
            'others' => Post::query()->publiclyListed()->whereKeyNot($post->id)->latest('published_at')->limit(3)->get(self::COLUMNS),
        ]);
    }

    /** Foto sampul: publik untuk berita yang tampil; selain itu hanya Admin (pratinjau di form). */
    public function photo(Request $request, Post $post): StreamedResponse
    {
        abort_unless(($post->isPubliclyVisible() || $request->user()?->isAdmin()) && $post->cover_photo_path
            && Storage::disk(Post::PHOTO_DISK)->exists($post->cover_photo_path), 404);

        [$path, $mime] = ImageVariants::resolve(Post::PHOTO_DISK, $post->cover_photo_path, $request->query('ukuran'), $request->query('format'), ImageVariants::WIDE);

        return Storage::disk(Post::PHOTO_DISK)->response($path, null, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => $post->isPubliclyVisible() ? 'public, max-age=604800' : 'private, no-store',
        ]);
    }
}
