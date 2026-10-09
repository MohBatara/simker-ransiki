<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'attachable_type' => 'organization',
            'attachable_id' => Organization::factory(),
            'disk' => 'local',
            'path' => 'attachments/'.Str::random(40).'.pdf',
            'original_name' => 'dokumen.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
        ];
    }
}
