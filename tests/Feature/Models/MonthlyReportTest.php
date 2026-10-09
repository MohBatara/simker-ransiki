<?php

use App\Models\MonthlyReport;
use App\Models\Organization;
use Illuminate\Database\QueryException;

test('satu kelompok hanya punya satu laporan per bulan', function () {
    $organization = Organization::factory()->create();
    MonthlyReport::factory()->for($organization)->create(['period' => '2026-09-01']);

    MonthlyReport::factory()->for($organization)->create(['period' => '2026-09-15']);
})->throws(QueryException::class);

test('periode selalu disimpan sebagai tanggal 1', function () {
    $report = MonthlyReport::factory()->create(['period' => '2026-09-17']);

    expect($report->fresh()->period->toDateString())->toBe('2026-09-01');
});

test('laba dihitung otomatis dari pendapatan dan biaya', function () {
    $report = MonthlyReport::factory()->create([
        'income_sales' => 1_250_000,
        'income_other' => 100_000,
        'costs' => 400_000,
    ]);

    expect($report->fresh()->profit)->toBe(950_000);

    $report->update(['costs' => 2_000_000]);
    expect($report->fresh()->profit)->toBe(-650_000);
});

test('laba NULL bila salah satu komponen kosong, dan nol tetap nol', function () {
    $partial = MonthlyReport::factory()->create(['income_sales' => 500_000, 'income_other' => null, 'costs' => 100_000]);
    $zeros = MonthlyReport::factory()->create(['income_sales' => 0, 'income_other' => 0, 'costs' => 0]);

    expect($partial->fresh()->profit)->toBeNull()
        ->and($partial->fresh()->income_other)->toBeNull()
        ->and($zeros->fresh()->profit)->toBe(0);
});

test('laba tidak bisa diisi langsung', function () {
    $report = MonthlyReport::factory()->create(['profit' => 999]);

    expect($report->fresh()->profit)->toBeNull();
});
