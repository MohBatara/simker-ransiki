@use('App\Support\ReportForm')

<section id="bagian-b" tabindex="-1" aria-label="B. Produksi & penjualan" x-cloak x-show="section === 'b'" class="focus:outline-none">
    <x-card title="B. Produksi & penjualan" description="Satu baris untuk setiap produk. Satuan produksi dan penjualan boleh berbeda.">
        @if ($previous && $previous['items'])
            <x-slot:actions>
                <x-button variant="secondary" icon="document-duplicate" x-on:click="copyPrevious('items')">Salin dari bulan lalu</x-button>
            </x-slot:actions>
        @endif

        <div class="space-y-4">
            <p x-show="! data.items.length" class="text-ink-soft">
                Belum ada produk. Tambahkan produk yang diproduksi atau dijual bulan ini.
                @if ($products->isEmpty())
                    Produk yang didaftarkan di menu <a href="{{ route('kelompok.products.index') }}" class="text-brand underline hover:text-brand-hover">Produk</a> bisa langsung dipilih di sini.
                @endif
            </p>

            <template x-for="(item, i) in data.items" :key="i">
                <div role="group" class="space-y-4 rounded-control border border-line p-4" x-bind:aria-label="`Produk ${i + 1}`">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-ui font-semibold text-ink" x-text="`Produk ${i + 1}`"></p>
                        <x-button variant="ghost" x-on:click="removeItem(i)" class="-my-2 -mr-2">Hapus</x-button>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        @if ($products->isNotEmpty())
                            <x-field label="Produk" live="`items.${i}.product_name`" bind-for="`item-${i}-choice`">
                                <x-select
                                    x-bind:id="`item-${i}-choice`"
                                    x-model="item.choice"
                                    x-on:change="item.choice = $event.target.value; choose(item)"
                                    placeholder="Pilih produk"
                                    :options="$products->pluck('name', 'id')->all()"
                                >
                                    <option value="lain">Produk lain (tulis nama)</option>
                                </x-select>
                            </x-field>
                        @endif
                        <x-field x-show="item.choice === 'lain'" label="Nama produk" :live="$products->isEmpty() ? '`items.${i}.product_name`' : null" bind-for="`item-${i}-name`">
                            <x-input
                                x-bind:id="`item-${i}-name`"
                                maxlength="255"
                                x-model="item.product_name"
                                x-bind:aria-invalid="!! error(`items.${i}.product_name`)"
                                x-bind:class="error(`items.${i}.product_name`) && 'border-fix! focus:border-fix! focus:ring-fix!'"
                            />
                        </x-field>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid grid-cols-[minmax(0,1fr)_minmax(0,7rem)] gap-3">
                            <x-field label="Jumlah produksi" live="`items.${i}.production_qty`" bind-for="`item-${i}-production_qty`" optional>
                                <x-number-input x-bind:id="`item-${i}-production_qty`" model="item.production_qty" kind="decimal" live="`items.${i}.production_qty`" />
                            </x-field>
                            <x-field label="Satuan" live="`items.${i}.production_unit`" bind-for="`item-${i}-production_unit`">
                                <x-input
                                    x-bind:id="`item-${i}-production_unit`"
                                    maxlength="30"
                                    placeholder="kg"
                                    x-model="item.production_unit"
                                    x-bind:aria-invalid="!! error(`items.${i}.production_unit`)"
                                    x-bind:class="error(`items.${i}.production_unit`) && 'border-fix! focus:border-fix! focus:ring-fix!'"
                                />
                            </x-field>
                        </div>
                        <div class="grid grid-cols-[minmax(0,1fr)_minmax(0,7rem)] gap-3">
                            <x-field label="Jumlah terjual" live="`items.${i}.sales_qty`" bind-for="`item-${i}-sales_qty`" optional>
                                <x-number-input x-bind:id="`item-${i}-sales_qty`" model="item.sales_qty" kind="decimal" live="`items.${i}.sales_qty`" />
                            </x-field>
                            <x-field label="Satuan" live="`items.${i}.sales_unit`" bind-for="`item-${i}-sales_unit`">
                                <x-input
                                    x-bind:id="`item-${i}-sales_unit`"
                                    maxlength="30"
                                    placeholder="kg"
                                    x-model="item.sales_unit"
                                    x-bind:aria-invalid="!! error(`items.${i}.sales_unit`)"
                                    x-bind:class="error(`items.${i}.sales_unit`) && 'border-fix! focus:border-fix! focus:ring-fix!'"
                                />
                            </x-field>
                        </div>
                        <x-field label="Pendapatan dari produk ini" live="`items.${i}.revenue`" bind-for="`item-${i}-revenue`" optional>
                            <x-number-input x-bind:id="`item-${i}-revenue`" model="item.revenue" kind="money" live="`items.${i}.revenue`" />
                        </x-field>
                    </div>
                </div>
            </template>

            <div class="flex flex-wrap items-center gap-3">
                <x-button variant="secondary" icon="plus" x-on:click="addItem()" x-bind:disabled="data.items.length >= {{ ReportForm::MAX_ITEMS }}">Tambah produk</x-button>
                <p x-cloak x-show="data.items.length >= {{ ReportForm::MAX_ITEMS }}" class="text-caption text-ink-muted">Paling banyak {{ ReportForm::MAX_ITEMS }} produk.</p>
            </div>
        </div>
    </x-card>
</section>
