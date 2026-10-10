<?php

use App\Enums\ReportStatus;
use App\Enums\ReviewAction;
use App\Models\Attachment;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\ReportSubmitted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00', 'Asia/Jayapura'));

    $this->opd = Opd::factory()->create();
    $this->organization = Organization::factory()->create(['reporting_starts_on' => '2026-07-01']);
    $this->organization->opds()->attach($this->opd);
    $this->user = User::factory()->kelompok($this->organization)->create();
    $this->actingAs($this->user);
});

afterEach(fn () => Carbon::setTestNow());

function completeReport(Organization $organization, array $attributes = []): MonthlyReport
{
    return MonthlyReport::factory()->for($organization)->create([
        'period' => '2026-09-01',
        'activity_summary' => 'Panen dan pengeringan kakao.',
        'members_active' => 12,
        ...$attributes,
    ]);
}

test('mengajukan draf: status diajukan, tercatat di riwayat, lalu terkunci', function () {
    $report = completeReport($this->organization);

    $this->post("/kelompok/laporan/{$report->id}/ajukan")
        ->assertRedirect("/kelompok/laporan/{$report->id}")
        ->assertSessionHas('status', 'Laporan diajukan.');

    $report->refresh();
    expect($report->status)->toBe(ReportStatus::Submitted)
        ->and($report->submitted_by)->toBe($this->user->id)
        ->and($report->submitted_at->equalTo(now()))->toBeTrue()
        ->and($report->reviews()->sole()->action)->toBe(ReviewAction::Submitted);

    $this->get("/kelompok/laporan/{$report->id}")
        ->assertOk()
        ->assertSee('Diajukan')
        ->assertSee('oleh '.$this->user->name)
        ->assertDontSee('Lanjutkan mengisi');

    $this->get("/kelompok/laporan/{$report->id}/ubah")->assertForbidden();
    $this->post("/kelompok/laporan/{$report->id}/ajukan")->assertForbidden();
    expect($report->reviews()->count())->toBe(1);
});

test('tenaga kerja dan angka keuangan boleh kosong saat diajukan; NULL tetap NULL', function () {
    $report = completeReport($this->organization, ['workforce_count' => null, 'income_sales' => 500000, 'costs' => null]);

    $this->post("/kelompok/laporan/{$report->id}/ajukan")->assertSessionHasNoErrors();

    $report->refresh();
    expect($report->status)->toBe(ReportStatus::Submitted)
        ->and($report->workforce_count)->toBeNull()
        ->and($report->costs)->toBeNull()
        ->and($report->profit)->toBeNull();
});

test('ajukan ditolak bila ringkasan aktivitas atau anggota aktif kosong, dan baris tidak lengkap', function () {
    $report = MonthlyReport::factory()->for($this->organization)->create(['period' => '2026-09-01']);
    $report->items()->create(['product_name' => null, 'production_qty' => 10]);
    $report->issues()->create(['kind' => 'problem', 'category' => null, 'description' => 'Tanpa kategori']);
    $report->issues()->create(['kind' => 'support', 'category' => 'lainnya', 'description' => null]);

    $this->post("/kelompok/laporan/{$report->id}/ajukan")
        ->assertRedirect("/kelompok/laporan/{$report->id}/ubah")
        ->assertSessionHas('error', 'Laporan belum bisa diajukan. Lengkapi isian yang ditandai.')
        ->assertSessionHasErrors([
            'activity_summary', 'members_active',
            'items.0.product_name', 'items.0.production_unit',
            'problems.0.category', 'supports.0.description',
        ]);

    expect($report->fresh()->status)->toBe(ReportStatus::Draft)->and($report->reviews()->count())->toBe(0);
});

test('anggota aktif 0 dianggap terisi', function () {
    $report = completeReport($this->organization, ['members_active' => 0]);

    $this->post("/kelompok/laporan/{$report->id}/ajukan")->assertSessionHasNoErrors();
    expect($report->fresh()->status)->toBe(ReportStatus::Submitted);
});

test('tidak ada aktivitas membuat bagian lain opsional', function () {
    $report = MonthlyReport::factory()->for($this->organization)->create(['period' => '2026-09-01', 'no_activity' => true]);

    $this->post("/kelompok/laporan/{$report->id}/ajukan")->assertSessionHasNoErrors();
    expect($report->fresh()->status)->toBe(ReportStatus::Submitted);
});

test('laporan bulan berjalan belum bisa diajukan', function () {
    $report = completeReport($this->organization, ['period' => '2026-10-01']);

    $this->get("/kelompok/laporan/{$report->id}/ubah")->assertOk()->assertSee('baru bisa diajukan mulai 1 November 2026');
    $this->post("/kelompok/laporan/{$report->id}/ajukan")->assertForbidden();

    expect($report->fresh()->status)->toBe(ReportStatus::Draft);
});

test('revisi dibuka kembali lalu diajukan ulang; waktu pengajuan pertama tetap', function () {
    $report = completeReport($this->organization, ['submitted_at' => '2026-10-05 09:00:00', 'submitted_by' => $this->user->id]);
    $report->forceFill(['status' => ReportStatus::Revision])->save();
    $report->reviews()->create(['user_id' => User::factory()->opd($this->opd)->create()->id, 'action' => ReviewAction::RevisionRequested, 'note' => 'Tambahkan foto kegiatan.']);

    $this->get("/kelompok/laporan/{$report->id}")->assertOk()->assertSee('Tambahkan foto kegiatan.')->assertSee('Perbaiki laporan');
    $this->patchJson("/kelompok/laporan/{$report->id}/draf", ['activity_summary' => 'Diperbaiki', 'members_active' => '12'])->assertOk();
    $this->post("/kelompok/laporan/{$report->id}/ajukan")->assertSessionHasNoErrors();

    $report->refresh();
    expect($report->status)->toBe(ReportStatus::Submitted)
        ->and($report->submitted_at->format('Y-m-d H:i'))->toBe('2026-10-05 09:00')
        ->and($report->wasSubmittedLate())->toBeFalse()
        ->and($report->reviews()->orderBy('id')->pluck('action')->map->value->all())->toBe(['revision_requested', 'submitted']);
});

test('pengajuan setelah tenggat diberi label diajukan terlambat', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-11 08:00:00', 'Asia/Jayapura'));
    $report = completeReport($this->organization);

    $this->post("/kelompok/laporan/{$report->id}/ajukan")->assertSessionHasNoErrors();

    expect($report->fresh()->wasSubmittedLate())->toBeTrue();
    $this->get("/kelompok/laporan/{$report->id}")->assertSee('Diajukan terlambat');
});

test('pengajuan mengirim notifikasi ke akun OPD pembina yang aktif saja', function () {
    Notification::fake();
    $opdUser = User::factory()->opd($this->opd)->create();
    $inactive = User::factory()->opd($this->opd)->create(['is_active' => false]);
    $otherOpd = User::factory()->opd(Opd::factory()->create())->create();
    $report = completeReport($this->organization);

    $this->post("/kelompok/laporan/{$report->id}/ajukan");

    Notification::assertSentTo($opdUser, ReportSubmitted::class, function (ReportSubmitted $notification) use ($opdUser, $report) {
        $data = $notification->toArray($opdUser);

        return $data['kind'] === 'report_submitted' && $data['monthly_report_id'] === $report->id
            && $data['period'] === '2026-09' && $data['resubmitted'] === false;
    });
    Notification::assertNotSentTo([$inactive, $otherOpd, $this->user], ReportSubmitted::class);
});

test('dokumentasi bisa diunggah ke draf, tidak ke laporan yang sudah diajukan', function () {
    Storage::fake('local');
    $report = completeReport($this->organization);

    $this->postJson("/lampiran/laporan/{$report->id}", ['file' => realUpload('kegiatan.jpg', imageBytes(40, 30))])->assertCreated();
    expect($report->attachments()->count())->toBe(1);

    $this->get("/kelompok/laporan/{$report->id}/ubah")->assertSee('kegiatan.jpg');

    $report->forceFill(['status' => ReportStatus::Submitted])->save();
    $this->postJson("/lampiran/laporan/{$report->id}", ['file' => realUpload('lagi.jpg', imageBytes(40, 30))])->assertForbidden();
    $this->delete('/lampiran/'.$report->attachments()->first()->id)->assertForbidden();
});

test('lampiran laporan terverifikasi tidak bisa dihapus kelompok maupun admin', function () {
    $report = completeReport($this->organization, ['status' => ReportStatus::Verified]);
    $attachment = Attachment::factory()->create(['attachable_type' => 'monthly_report', 'attachable_id' => $report->id]);

    $this->delete("/lampiran/{$attachment->id}")->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->delete("/lampiran/{$attachment->id}")->assertForbidden();

    expect($attachment->fresh())->not->toBeNull();
});
