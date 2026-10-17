<?php

namespace App\Http\Requests\Admin;

use App\Enums\PostCategory;
use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => $this->filled('slug') ? Str::slug((string) $this->input('slug')) : null,
            'organizations' => $this->input('organizations', []),
        ]);
    }

    public function rules(): array
    {
        $post = $this->route('post');

        return [
            'title' => ['required', 'string', 'max:90'],
            'slug' => ['nullable', 'string', 'max:200', Rule::unique('posts', 'slug')->ignore($post)],
            'category' => ['required', Rule::enum(PostCategory::class)],
            'summary' => ['nullable', 'string', 'max:200'],
            // Teks alternatif wajib bila berita sudah berfoto sampul (foto diunggah setelah berita disimpan pertama kali).
            'photo_alt' => [Rule::requiredIf(fn () => filled($post?->cover_photo_path)), 'nullable', 'string', 'max:150'],
            'photo_caption' => ['nullable', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:50000'],
            'status' => ['required', Rule::enum(PostStatus::class)],
            'published_at' => ['nullable', 'date'],
            // Tanggal kegiatan hanya untuk kategori Kegiatan; kategori lain diabaikan (dikosongkan saat simpan).
            'event_date' => ['exclude_unless:category,'.PostCategory::Activity->value, 'nullable', 'date'],
            'organizations' => ['array', 'max:20'],
            'organizations.*' => ['integer', Rule::exists('organizations', 'id')],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.unique' => 'Alamat ini sudah dipakai berita lain. Ubah alamatnya atau kosongkan agar dibuat otomatis.',
            'organizations.max' => 'Pilih paling banyak 20 kelompok terkait.',
            'title.max' => 'Judul paling banyak 90 karakter. Ringkas judulnya agar tetap mudah dibaca di Beranda.',
            'summary.max' => 'Ringkasan paling banyak 200 karakter. Cukup satu atau dua kalimat.',
            'photo_alt.required' => 'Isi teks alternatif foto sampul: jelaskan isi foto untuk pembaca layar.',
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'judul',
            'slug' => 'alamat',
            'category' => 'kategori',
            'summary' => 'ringkasan',
            'photo_alt' => 'teks alternatif foto',
            'photo_caption' => 'keterangan foto',
            'body' => 'isi',
            'published_at' => 'tanggal terbit',
            'event_date' => 'tanggal kegiatan',
        ];
    }

    /** Data siap simpan: alamat dibuat dari judul bila kosong; terbit tanpa tanggal = sekarang. */
    public function postData(): array
    {
        $data = Arr::except($this->validated(), ['organizations']);
        $post = $this->route('post');

        $data['slug'] ??= Post::uniqueSlug($data['title'], $post instanceof Post ? $post->id : null);
        $data['published_at'] = filled($data['published_at'] ?? null)
            ? Carbon::parse($data['published_at'])
            : ($data['status'] === PostStatus::Published->value ? now() : null);

        $data['event_date'] = $data['category'] === PostCategory::Activity->value && filled($data['event_date'] ?? null)
            ? Carbon::parse($data['event_date'])->toDateString()
            : null;

        return $data;
    }
}
