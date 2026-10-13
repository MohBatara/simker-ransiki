{{--
    Baris meta berita: KATEGORI · tanggal · x menit baca. Variabel: $post, $short (opsional, tanggal "24 Sep 2026"),
    $reading (opsional, bawaan true), $class (opsional).
--}}
@use('App\Support\Format')

<p class="flex flex-wrap items-center gap-x-2 text-caption text-ink-muted {{ $class ?? '' }}">
    <span class="font-semibold tracking-wider text-clay uppercase">{{ $post->category->label() }}</span>
    <span aria-hidden="true">·</span>
    <time datetime="{{ $post->published_at->toAtomString() }}">{{ ($short ?? false) ? Format::dateShort($post->published_at) : Format::date($post->published_at) }}</time>
    @if ($reading ?? true)
        <span aria-hidden="true">·</span>
        <span>{{ $post->readingMinutes() }} menit baca</span>
    @endif
</p>
