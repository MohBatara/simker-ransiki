<section id="bagian-d" tabindex="-1" aria-label="D. Keuangan" x-cloak x-show="section === 'd'" class="focus:outline-none">
    <x-card title="D. Keuangan" description="Dalam Rupiah. Isi 0 bila memang tidak ada. Kosongkan bila belum diketahui.">
        <div class="grid gap-5 md:grid-cols-2">
            <x-field label="Pendapatan penjualan" for="income_sales" live="'income_sales'" optional>
                <x-number-input id="income_sales" model="data.income_sales" kind="money" live="'income_sales'" />
                <div x-cloak x-show="revenueTotal.count" class="flex flex-wrap items-center gap-x-3 gap-y-1 text-caption text-ink-soft">
                    <span>
                        Jumlah pendapatan per produk: <span class="tabular-nums" x-text="revenueTotalText"></span>
                        <span x-show="revenueTotal.count < data.items.length" x-text="`(diisi ${revenueTotal.count} dari ${data.items.length} produk)`"></span>
                    </span>
                    <x-link x-on:click="useRevenueTotal()">Pakai jumlah ini</x-link>
                </div>
            </x-field>
            <x-field label="Pendapatan lain" for="income_other" live="'income_other'" hint="Misalnya jasa sewa alat atau bunga simpanan." optional>
                <x-number-input id="income_other" model="data.income_other" kind="money" live="'income_other'" />
            </x-field>
            <x-field label="Biaya" for="costs" live="'costs'" hint="Bahan baku, upah, transportasi, dan biaya lain bulan ini." optional>
                <x-number-input id="costs" model="data.costs" kind="money" live="'costs'" />
            </x-field>
        </div>

        <div class="mt-5 border-t border-line pt-4">
            <p class="text-ui font-semibold text-ink">Laba</p>
            <p x-show="profitText" class="mt-1 text-card-title font-semibold tabular-nums text-ink" x-text="profitText"></p>
            <p class="mt-1 text-caption text-ink-muted" x-text="profitText ? 'Dihitung otomatis: penjualan + pendapatan lain − biaya.' : 'Dihitung otomatis setelah penjualan, pendapatan lain, dan biaya terisi. Isi 0 bila memang tidak ada.'"></p>
        </div>
    </x-card>
</section>
