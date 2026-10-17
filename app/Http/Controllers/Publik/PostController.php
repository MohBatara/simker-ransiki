<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Support\ImageVariants;
use App\Support\PostListing;
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
    public const COLUMNS = ['posts.id', 'posts.slug', 'posts.title', 'posts.category', 'posts.summary', 'posts.body', 'posts.cover_photo_path', 'posts.photo_caption', 'posts.photo_alt', 'posts.published_at', 'posts.updated_at'];

    /**
     * Daftar bergaya majalah (App\Support\PostListing): sorotan dan agenda di halaman 1 tanpa filter, daftar terbaru 10 per
     * halaman tanpa mengulang sorotan, arsip bulan, dan tab kategori dengan jumlah.
     */
    public function index(Request $request): View
    {
        $listing = PostListing::fromRequest($request);
        $posts = $listing->results();
        $agenda = $listing->agenda($posts->currentPage());

        return view('publik.posts.index', [
            'listing' => $listing,
            'posts' => $posts,
            'highlights' => $posts->onFirstPage() ? $listing->highlights() : collect(),
            'agenda' => $agenda['posts'],
            'agendaUpcoming' => $agenda['upcoming'],
            'counts' => $listing->counts(),
            'archive' => $listing->archive(),
        ]);
    }

    public function show(string $slug): View
    {
        $post = Post::query()->publiclyListed()->where('slug', $slug)
            ->with(['organizations' => fn ($query) => $query->publiclyListed()->orderBy('name')
                ->select(['organizations.id', 'organizations.slug', 'organizations.name', 'organizations.village_id', 'organizations.institution_type_id'])
                ->with(['village:id,name', 'institutionType:id,name'])])
            ->firstOrFail(self::COLUMNS);

        // Sebelumnya = lebih lama, Berikutnya = lebih baru (urut tanggal terbit lalu id).
        $neighbour = fn (string $direction) => Post::query()->publiclyListed()
            ->where(fn ($query) => $query->where('posts.published_at', $direction === 'older' ? '<' : '>', $post->published_at)
                ->orWhere(fn ($query) => $query->where('posts.published_at', $post->published_at)->where('posts.id', $direction === 'older' ? '<' : '>', $post->id)))
            ->orderBy('posts.published_at', $direction === 'older' ? 'desc' : 'asc')
            ->orderBy('posts.id', $direction === 'older' ? 'desc' : 'asc')
            ->first(['posts.id', 'posts.slug', 'posts.title', 'posts.category', 'posts.cover_photo_path']);

        return view('publik.posts.show', [
            'post' => $post,
            'previous' => $neighbour('older'),
            'next' => $neighbour('newer'),
            // Baca juga: kategori yang sama dulu, lalu terbaru.
            'others' => Post::query()->publiclyListed()->whereKeyNot($post->id)
                ->orderByRaw('posts.category = ? desc', [$post->category->value])
                ->latest('posts.published_at')->latest('posts.id')
                ->limit(3)->get(self::COLUMNS),
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
