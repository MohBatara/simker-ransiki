{{-- Titik penanda deretan geser (di dalam @foreach, komponen scrollDots): aktif = pil 20px brand, lainnya 6px. --}}
<button type="button" x-on:click="go({{ $loop->index }})" x-bind:aria-current="active === {{ $loop->index }}" aria-label="Kartu {{ $loop->iteration }} dari {{ $loop->count }}" class="flex size-11 items-center justify-center rounded-full">
    <span @class(['block h-1.5 rounded-full transition-[width,background-color] duration-250 motion-reduce:transition-none', 'w-5 bg-brand' => $loop->first, 'w-1.5 bg-line-input' => ! $loop->first]) x-bind:class="{ 'w-5 bg-brand': active === {{ $loop->index }}, 'w-1.5 bg-line-input': active !== {{ $loop->index }} }" aria-hidden="true"></span>
</button>
