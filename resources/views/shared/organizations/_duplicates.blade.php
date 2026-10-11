{{--
    Daftar kemungkinan data ganda (App\Support\DuplicateCandidates::for). Kandidat di kampung yang sama
    (peringatan kuat) di atas. Variabel: $candidates, $confirm (tampilkan centang "tetap simpan").
--}}
@php
    $strong = collect($candidates)->contains('strong', true);
@endphp

<x-alert tone="wait" :title="$strong ? 'Nama mirip ditemukan di kampung yang sama' : 'Ada kelompok dengan nama mirip di kampung lain'">
    <p>{{ $strong ? 'Periksa apakah kelompok ini sudah terdaftar:' : 'Mungkin bukan data ganda, tetapi sebaiknya diperiksa:' }}</p>
    <ul class="mt-2 space-y-2">
        @foreach ($candidates as $candidate)
            <li>
                <x-link :href="route('admin.organizations.show', $candidate['id'])" target="_blank" rel="noopener" inline>{{ $candidate['name'] }}</x-link>
                <span class="block text-caption text-ink-soft">
                    {{ collect([$candidate['type'], $candidate['village'], $candidate['strong'] ? 'kampung sama' : null, $candidate['status']])->filter()->join(' · ') }}
                </span>
            </li>
        @endforeach
    </ul>
    @if ($confirm)
        <div class="mt-3">
            <x-checkbox name="confirm_similar">Saya sudah memeriksa, tetap simpan</x-checkbox>
        </div>
    @endif
</x-alert>
