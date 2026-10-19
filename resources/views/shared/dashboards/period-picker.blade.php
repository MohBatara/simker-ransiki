{{-- Pemilih bulan dasbor; langsung berganti saat dipilih. Variabel: $periods, $period. --}}
<form novalidate method="GET" class="w-full md:w-56">
    <label for="periode" class="sr-only">Bulan</label>
    <x-select name="periode" :options="$periods" :selected="$period->format('Y-m')" x-data x-on:change="$el.form.requestSubmit()" />
    <noscript><x-button type="submit" variant="secondary" class="mt-2">Tampilkan</x-button></noscript>
</form>
