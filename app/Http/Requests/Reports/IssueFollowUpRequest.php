<?php

namespace App\Http\Requests\Reports;

use App\Enums\FollowUpStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ubah status tindak lanjut masalah/kebutuhan. Hak dicek Policy di route (followUp). Catatan tindakan wajib;
 * OPD penanggung jawab hanya dipilih Admin (OPD selalu atas nama OPD-nya, lihat FollowUpIssue).
 */
class IssueFollowUpRequest extends FormRequest
{
    public function rules(): array
    {
        $today = now(config('simker.reports.timezone'))->format('Y-m-d');

        return [
            'status' => ['required', Rule::enum(FollowUpStatus::class)],
            'note' => ['required', 'string', 'min:5', 'max:2000'],
            'acted_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$today],
            'opd_id' => $this->user()->isAdmin() ? ['nullable', 'integer', Rule::exists('opds', 'id')] : ['exclude'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Pilih status tindak lanjut.',
            'note.required' => 'Tulis catatan tindakan, misalnya apa yang sudah atau akan dilakukan.',
            'note.min' => 'Catatan tindakan terlalu singkat. Tulis paling sedikit 5 karakter.',
            'note.max' => 'Catatan tindakan paling panjang 2.000 karakter.',
            'acted_on.required' => 'Isi tanggal tindakan.',
            'acted_on.date_format' => 'Tanggal tindakan tidak valid.',
            'acted_on.before_or_equal' => 'Tanggal tindakan tidak boleh setelah hari ini.',
        ];
    }

    public function attributes(): array
    {
        return ['opd_id' => 'OPD penanggung jawab'];
    }

    public function status(): FollowUpStatus
    {
        return FollowUpStatus::from($this->validated('status'));
    }

    public function actedOn(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $this->validated('acted_on'));
    }
}
