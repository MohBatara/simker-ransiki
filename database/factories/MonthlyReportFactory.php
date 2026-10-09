<?php

namespace Database\Factories;

use App\Enums\ReportStatus;
use App\Models\MonthlyReport;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonthlyReport>
 */
class MonthlyReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'period' => '2026-09-01',
            'status' => ReportStatus::Draft,
        ];
    }

    public function status(ReportStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
