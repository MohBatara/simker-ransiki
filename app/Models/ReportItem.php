<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'product_name', 'production_qty', 'production_unit', 'sales_qty', 'sales_unit', 'revenue'])]
class ReportItem extends Model
{
    protected function casts(): array
    {
        return [
            'production_qty' => 'decimal:2',
            'sales_qty' => 'decimal:2',
            'revenue' => 'integer',
        ];
    }

    public function monthlyReport(): BelongsTo
    {
        return $this->belongsTo(MonthlyReport::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
