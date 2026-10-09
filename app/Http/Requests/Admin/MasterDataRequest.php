<?php

namespace App\Http\Requests\Admin;

use App\Enums\MasterDataType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MasterDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        $type = $this->type();

        return collect($type->fields())->map(function (array $field, string $column) use ($type) {
            return array_filter([
                ($field['optional'] ?? false) ? 'nullable' : 'required',
                'string',
                'max:'.$field['max'],
                isset($field['pattern']) ? 'regex:'.$field['pattern'] : null,
                // Kolom nama memakai collation case-insensitive, jadi "Kampung A" dan "kampung a" dianggap sama.
                ($field['unique'] ?? false) ? Rule::unique($type->table(), $column)->ignore($this->route('id')) : null,
            ]);
        })->all();
    }

    public function attributes(): array
    {
        return collect($this->type()->fields())->map(fn (array $field) => lcfirst($field['label']))->all();
    }

    private function type(): MasterDataType
    {
        return $this->route('type');
    }
}
