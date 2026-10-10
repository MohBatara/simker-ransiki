{{--
    Ringkasan error di atas form panjang: jumlah isian yang salah + tautan ke setiap isian. Saat halaman
    terbuka, fokus pindah ke isian pertama yang salah (resources/js/app.js, data-form-errors).
--}}
@if ($errors->any())
    @php
        $items = collect($errors->getMessages())->map(fn ($messages, $key) => [
            'id' => \App\Support\FormField::id($key),
            'message' => $messages[0],
        ])->values();
    @endphp
    <x-alert tone="fix" :title="'Ada '.$items->count().' isian yang perlu diperbaiki'" data-form-errors {{ $attributes }}>
        <ul class="mt-1 space-y-1">
            @foreach ($items as $item)
                <li><a href="#{{ $item['id'] }}" class="rounded-control underline underline-offset-2 hover:no-underline">{{ $item['message'] }}</a></li>
            @endforeach
        </ul>
    </x-alert>
@endif
