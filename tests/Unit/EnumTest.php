<?php

use App\Enums\AttachmentVisibility;
use App\Enums\ChangeAction;
use App\Enums\FollowUpStatus;
use App\Enums\IssueKind;
use App\Enums\LegalStatus;
use App\Enums\LicenseStatus;
use App\Enums\OrganizationStatus;
use App\Enums\PostCategory;
use App\Enums\PostStatus;
use App\Enums\ProblemCategory;
use App\Enums\ProfileStatus;
use App\Enums\ReportStatus;
use App\Enums\ReviewAction;
use App\Enums\Role;
use App\Enums\SupportCategory;

$enums = [
    AttachmentVisibility::class, FollowUpStatus::class, IssueKind::class, LegalStatus::class, LicenseStatus::class,
    OrganizationStatus::class, ProblemCategory::class, ReportStatus::class, ReviewAction::class,
    Role::class, SupportCategory::class, PostCategory::class, PostStatus::class, ProfileStatus::class, ChangeAction::class,
];

test('setiap nilai enum punya label Indonesia', function (string $enum) {
    foreach ($enum::cases() as $case) {
        expect($case->label())->not->toBeEmpty()->not->toBe($case->value);
    }

    expect($enum::options())->toHaveCount(count($enum::cases()));
})->with($enums);

test('setiap status punya warna di peta StatusTone', function (string $enum) {
    foreach ($enum::cases() as $case) {
        expect(App\Support\StatusTone::of($case))->toBeIn(App\Support\StatusTone::TONES);
    }
})->with([OrganizationStatus::class, ReportStatus::class, LicenseStatus::class, LegalStatus::class, FollowUpStatus::class, PostStatus::class, ProfileStatus::class]);
