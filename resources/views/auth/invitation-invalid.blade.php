<x-layouts.auth title="Undangan tidak berlaku">
    <h1 class="text-page-title font-semibold text-ink">Undangan tidak berlaku</h1>
    <x-alert tone="fix" class="mt-6">{{ $message }}</x-alert>
    <div class="mt-6 flex flex-col gap-3">
        <x-button :href="route('login')" class="w-full">Ke halaman masuk</x-button>
    </div>
</x-layouts.auth>
