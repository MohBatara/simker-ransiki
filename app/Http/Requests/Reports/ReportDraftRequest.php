<?php

namespace App\Http\Requests\Reports;

use App\Enums\ProblemCategory;
use App\Enums\SupportCategory;
use App\Support\NumberInput;
use App\Support\ReportForm;
use App\Support\Units;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Simpan draf (otomatis atau lewat tombol). Semua kolom opsional; yang dicek hanya bentuk isian.
 * Kelengkapan dicek saat laporan diajukan (App\Actions\Reports\SubmitReport). Hak ubah dicek di route.
 */
class ReportDraftRequest extends FormRequest
{
    public const MAX_MONEY = 999_999_999_999;

    public const MAX_COUNT = 1_000_000;

    // Angka sudah dibaca NumberInput::decimal: titik desimal, paling banyak 2 angka di belakangnya.
    private const QUANTITY_PATTERN = '/^\d{1,10}(\.\d{1,2})?$/';

    protected function prepareForValidation(): void
    {
        $data = ['no_activity' => $this->boolean('no_activity')];

        foreach (ReportForm::INTEGER_FIELDS as $field) {
            if ($this->has($field)) {
                $data[$field] = NumberInput::integer($this->input($field));
            }
        }

        if (is_array($this->input('items'))) {
            $data['items'] = array_map(fn ($item) => is_array($item) ? [
                ...$item,
                'production_unit' => Units::normalize($item['production_unit'] ?? null),
                'sales_unit' => Units::normalize($item['sales_unit'] ?? null),
                'production_qty' => NumberInput::decimal($item['production_qty'] ?? null),
                'sales_qty' => NumberInput::decimal($item['sales_qty'] ?? null),
                'revenue' => NumberInput::integer($item['revenue'] ?? null),
            ] : $item, $this->input('items'));
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        $count = ['nullable', 'integer', 'min:0', 'max:'.self::MAX_COUNT];
        $money = ['nullable', 'integer', 'min:0', 'max:'.self::MAX_MONEY];
        $quantity = ['nullable', 'regex:'.self::QUANTITY_PATTERN];
        $text = ['nullable', 'string', 'max:5000'];
        $unit = ['nullable', 'string', 'max:30'];

        return [
            'version' => ['nullable', 'integer'],
            'no_activity' => ['boolean'],
            'activity_summary' => $text,
            'activity_count' => $count,
            'achievements' => $text,
            'members_active' => $count,
            'workforce_count' => $count,
            'members_change' => ['nullable', 'integer', 'min:-'.self::MAX_COUNT, 'max:'.self::MAX_COUNT],
            'income_sales' => $money,
            'income_other' => $money,
            'costs' => $money,

            'items' => ['nullable', 'array', 'max:'.ReportForm::MAX_ITEMS],
            'items.*' => ['array'],
            'items.*.product_id' => ['nullable', 'integer', Rule::exists('products', 'id')->where('organization_id', $this->route('report')->organization_id)],
            'items.*.product_name' => ['nullable', 'string', 'max:255'],
            'items.*.production_qty' => $quantity,
            'items.*.production_unit' => $unit,
            'items.*.sales_qty' => $quantity,
            'items.*.sales_unit' => $unit,
            'items.*.revenue' => $money,

            'problems' => ['nullable', 'array', 'max:'.ReportForm::MAX_ISSUES],
            'problems.*' => ['array'],
            'problems.*.id' => ['nullable', 'integer'],
            'problems.*.category' => ['nullable', Rule::enum(ProblemCategory::class)],
            'problems.*.description' => ['nullable', 'string', 'max:1000'],

            'supports' => ['nullable', 'array', 'max:'.ReportForm::MAX_ISSUES],
            'supports.*' => ['array'],
            'supports.*.id' => ['nullable', 'integer'],
            'supports.*.category' => ['nullable', Rule::enum(SupportCategory::class)],
            'supports.*.description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        $whole = 'Angka tidak terbaca. Tulis angka bulat, misalnya 1.250.000.';
        $quantity = 'Jumlah tidak terbaca. Tulis misalnya 12,5 atau 1.250 (paling banyak 2 angka di belakang koma).';
        $tooLarge = 'Angka terlalu besar. Periksa kembali isiannya.';

        return [
            ...collect(ReportForm::INTEGER_FIELDS)->flatMap(fn (string $field) => [
                "{$field}.integer" => $whole,
                "{$field}.max" => $tooLarge,
                "{$field}.min" => $field === 'members_change' ? $tooLarge : 'Angka tidak boleh negatif.',
            ])->all(),
            'items.*.revenue.integer' => $whole,
            'items.*.revenue.max' => $tooLarge,
            'items.*.revenue.min' => 'Angka tidak boleh negatif.',
            'items.*.production_qty.regex' => $quantity,
            'items.*.sales_qty.regex' => $quantity,
            'items.*.product_id.exists' => 'Produk tidak ditemukan. Pilih produk lain.',
            'items.max' => 'Paling banyak '.ReportForm::MAX_ITEMS.' baris produk.',
            'problems.max' => 'Paling banyak '.ReportForm::MAX_ISSUES.' permasalahan.',
            'supports.max' => 'Paling banyak '.ReportForm::MAX_ISSUES.' kebutuhan.',
            'problems.*.category.enum' => 'Pilih kategori dari daftar.',
            'supports.*.category.enum' => 'Pilih kategori dari daftar.',
        ];
    }

    public function attributes(): array
    {
        return [
            'activity_summary' => 'ringkasan aktivitas',
            'achievements' => 'capaian',
            'items.*.product_name' => 'nama produk',
            'items.*.production_unit' => 'satuan produksi',
            'items.*.sales_unit' => 'satuan penjualan',
            'problems.*.description' => 'uraian',
            'supports.*.description' => 'uraian',
        ];
    }
}
