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
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:200', Rule::unique('posts', 'slug')->ignore($post)],
            'category' => ['required', Rule::enum(PostCategory::class)],
            'summary' => ['nullable', 'string', 'max:300'],
            'body' => ['required', 'string', 'max:50000'],
            'status' => ['required', Rule::enum(PostStatus::class)],
            'published_at' => ['nullable', 'date'],
            'organizations' => ['array', 'max:20'],
            'organizations.*' => ['integer', Rule::exists('organizations', 'id')],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.unique' => 'Alamat ini sudah dipakai berita lain. Ubah alamatnya atau kosongkan agar dibuat otomatis.',
            'organizations.max' => 'Pilih paling banyak 20 kelompok terkait.',
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'judul',
            'slug' => 'alamat',
            'category' => 'kategori',
            'summary' => 'ringkasan',
            'body' => 'isi',
            'published_at' => 'tanggal terbit',
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

        return $data;
    }
}
