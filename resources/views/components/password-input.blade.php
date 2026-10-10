@props([
    'name',
    'autocomplete' => 'current-password',
])

{{-- Kata sandi dengan tombol mata untuk menampilkan/menyembunyikan isian. --}}
<div x-data="{ show: false }" class="relative">
    <x-input :name="$name" type="password" :autocomplete="$autocomplete" x-bind:type="show ? 'text' : 'password'" class="pr-12!" {{ $attributes }} />
    <button
        type="button"
        x-on:click="show = ! show"
        x-bind:aria-pressed="show"
        x-bind:aria-label="show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
        aria-label="Tampilkan kata sandi"
        class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-control text-ink-muted hover:text-ink md:w-10"
    >
        <x-heroicon-o-eye x-show="! show" class="size-5" />
        <x-heroicon-o-eye-slash x-cloak x-show="show" class="size-5" />
    </button>
</div>
