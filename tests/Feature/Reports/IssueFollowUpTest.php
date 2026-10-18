<?php

use App\Enums\FollowUpStatus;
use App\Enums\IssueKind;
use App\Enums\ReportStatus;
use App\Models\IssueFollowUp;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\User;
use App\Models\Village;
use App\Notifications\IssueFollowUpUpdated;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Spatie\SimpleExcel\SimpleExcelReader;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-12 10:00:00', 'Asia/Jayapura'));

    $this->opd = Opd::factory()->create(['name' => 'Dinas Contoh', 'short_name' => 'Contoh']);
    $this->village = Village::factory()->create(['name' => 'Kampung Contoh 1']);
    $this->organization = Organization::factory()->create(['name' => 'KUB Binaan', 'village_id' => $this->village->id]);
    $this->organization->opds()->attach($this->opd);
    $this->officer = User::factory()->opd($this->opd)->create(['name' => 'Petugas Contoh']);
    $this->members = User::factory()->kelompok($this->organization)->count(2)->create();
    $this->inactiveMember = User::factory()->kelompok($this->organization)->create(['is_active' => false]);

    $this->report = MonthlyReport::factory()->for($this->organization)->status(ReportStatus::Submitted)
        ->create(['period' => '2026-09-01', 'submitted_at' => '2026-10-02 09:00:00']);
    $this->issue = $this->report->issues()->create(['kind' => IssueKind::Support, 'category' => 'pelatihan', 'description' => 'Pelatihan pengemasan']);
});

afterEach(fn () => Carbon::setTestNow());

function followUpPayload(array $overrides = []): array
{
    return ['status' => 'planned', 'note' => 'Pelatihan dijadwalkan November.', 'acted_on' => '2026-10-12', ...$overrides];
}

test('OPD pembina mengubah status dengan catatan; riwayat dan status terkini tersimpan', function () {
    $this->actingAs($this->officer)->get("/opd/laporan/masalah/{$this->issue->id}")
        ->assertOk()->assertSee('Pelatihan pengemasan')->assertSee('Ubah status tindak lanjut')->assertSee('Belum ada tindak lanjut');

    $this->post("/opd/laporan/masalah/{$this->issue->id}/tindak-lanjut", followUpPayload(['opd_id' => Opd::factory()->create()->id]))
        ->assertRedirect("/opd/laporan/masalah/{$this->issue->id}")
        ->assertSessionHas('status', 'Status diubah menjadi direncanakan. Kelompok mendapat notifikasi.');

    $issue = $this->issue->fresh();
    $history = IssueFollowUp::sole();
    expect($issue->follow_up_status)->toBe(FollowUpStatus::Planned)
        // OPD selalu penanggung jawab atas nama OPD-nya sendiri, walau mengirim opd_id lain.
        ->and($issue->follow_up_opd_id)->toBe($this->opd->id)
        ->and($issue->follow_up_at)->not->toBeNull()
        ->and($history->only(['user_id', 'opd_id', 'note']))->toBe(['user_id' => $this->officer->id, 'opd_id' => $this->opd->id, 'note' => 'Pelatihan dijadwalkan November.'])
        ->and($history->from_status)->toBe(FollowUpStatus::New)
        ->and($history->to_status)->toBe(FollowUpStatus::Planned)
        ->and($history->acted_on->format('Y-m-d'))->toBe('2026-10-12');

    $this->get("/opd/laporan/masalah/{$this->issue->id}")->assertSee('Direncanakan')->assertSee('Petugas Contoh (Contoh)')->assertSee('Pelatihan dijadwalkan November.');
});

test('catatan tindakan wajib dan tanggal tidak boleh setelah hari ini', function (array $payload, string $field) {
    $this->actingAs($this->officer)
        ->from("/opd/laporan/masalah/{$this->issue->id}")
        ->post("/opd/laporan/masalah/{$this->issue->id}/tindak-lanjut", followUpPayload($payload))
        ->assertRedirect("/opd/laporan/masalah/{$this->issue->id}")
        ->assertSessionHasErrors($field);

    expect($this->issue->fresh()->follow_up_status)->toBe(FollowUpStatus::New)
        ->and(IssueFollowUp::count())->toBe(0);
})->with([
    'catatan kosong' => [['note' => ''], 'note'],
    'catatan terlalu singkat' => [['note' => 'ok'], 'note'],
    'status tidak dikenal' => [['status' => 'gabung'], 'status'],
    'tanggal besok' => [['acted_on' => '2026-10-13'], 'acted_on'],
]);

test('notifikasi ke semua akun aktif kelompok, sekali per perubahan status', function () {
    Notification::fake();
    $this->actingAs($this->officer);

    $this->post("/opd/laporan/masalah/{$this->issue->id}/tindak-lanjut", followUpPayload());
    Notification::assertSentTo($this->members, IssueFollowUpUpdated::class);
    Notification::assertNotSentTo($this->inactiveMember, IssueFollowUpUpdated::class);
    Notification::assertNotSentTo($this->officer, IssueFollowUpUpdated::class);
    Notification::assertSentTimes(IssueFollowUpUpdated::class, 2);

    // Status sama (catatan tambahan, atau kirim ganda): riwayat bertambah, tanpa notifikasi baru.
    $this->post("/opd/laporan/masalah/{$this->issue->id}/tindak-lanjut", followUpPayload(['note' => 'Peserta sudah didata.']))
        ->assertSessionHas('status', 'Catatan tindakan disimpan.');
    Notification::assertSentTimes(IssueFollowUpUpdated::class, 2);
    expect(IssueFollowUp::count())->toBe(2);

    $this->post("/opd/laporan/masalah/{$this->issue->id}/tindak-lanjut", followUpPayload(['status' => 'done', 'note' => 'Pelatihan selesai.']));
    Notification::assertSentTimes(IssueFollowUpUpdated::class, 4);
});

test('teks notifikasi kelompok dan tujuannya ke detail laporan', function () {
    $member = $this->members->first();
    $this->actingAs($this->officer)->post("/opd/laporan/masalah/{$this->issue->id}/tindak-lanjut", followUpPayload(['status' => 'in_progress']));

    $this->actingAs($member)->get('/notifikasi')
        ->assertSee('Kebutuhan &quot;Pelatihan&quot; dari laporan September 2026: sedang ditindaklanjuti.', false)
        ->assertSee('Pelatihan dijadwalkan November.');

    $notification = $member->notifications()->sole();
    $this->get("/notifikasi/{$notification->id}")->assertRedirect("/kelompok/laporan/{$this->report->id}");
});

test('OPD non-binaan tidak bisa melihat atau mengubah tindak lanjut (404)', function () {
    $this->actingAs(User::factory()->opd(Opd::factory()->create())->create());

    $this->get("/opd/laporan/masalah/{$this->issue->id}")->assertNotFound();
    $this->post("/opd/laporan/masalah/{$this->issue->id}/tindak-lanjut", followUpPayload())->assertNotFound();

    expect($this->issue->fresh()->follow_up_status)->toBe(FollowUpStatus::New)
        ->and(IssueFollowUp::count())->toBe(0);
});

test('isian dari draf atau laporan yang sedang direvisi tidak bisa ditindaklanjuti peninjau', function (ReportStatus $status) {
    $this->report->update(['status' => $status]);

    $this->actingAs($this->officer)->get("/opd/laporan/masalah/{$this->issue->id}")->assertNotFound();
    $this->post("/opd/laporan/masalah/{$this->issue->id}/tindak-lanjut", followUpPayload())->assertNotFound();
})->with([ReportStatus::Draft, ReportStatus::Revision]);

test('kelompok hanya bisa melihat: tidak bisa mengubah status, termasuk lewat Policy langsung', function () {
    $this->actingAs($this->officer)->post("/opd/laporan/masalah/{$this->issue->id}/tindak-lanjut", followUpPayload());
    $member = $this->members->first();
    $this->actingAs($member);

    $this->post("/opd/laporan/masalah/{$this->issue->id}/tindak-lanjut", followUpPayload(['status' => 'done']))->assertForbidden();
    $this->post("/admin/laporan/masalah/{$this->issue->id}/tindak-lanjut", followUpPayload(['status' => 'done']))->assertForbidden();
    expect($member->can('view', $this->issue))->toBeTrue()
        ->and($member->can('followUp', $this->issue))->toBeFalse()
        ->and($this->issue->fresh()->follow_up_status)->toBe(FollowUpStatus::Planned);

    $this->get("/kelompok/laporan/{$this->report->id}")->assertOk()
        ->assertSee('Direncanakan')->assertSee('Tindak lanjut (1)')->assertSee('Pelatihan dijadwalkan November.')
        ->assertDontSee('Ubah status tindak lanjut');
    $this->get('/kelompok')->assertOk()
        ->assertSee('Tindak lanjut masalah &amp; kebutuhan', false)->assertSee('Direncanakan')->assertSee('Pelatihan dijadwalkan November.');
});

test('admin mengubah status kelompok mana pun dan memilih OPD penanggung jawab', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post("/admin/laporan/masalah/{$this->issue->id}/tindak-lanjut", followUpPayload(['status' => 'unable', 'opd_id' => $this->opd->id, 'note' => 'Anggaran tahun ini habis.']))
        ->assertRedirect("/admin/laporan/masalah/{$this->issue->id}");

    expect($this->issue->fresh()->only(['follow_up_status', 'follow_up_opd_id']))->toBe(['follow_up_status' => FollowUpStatus::Unable, 'follow_up_opd_id' => $this->opd->id]);
});

test('riwayat tindak lanjut tetap tersambung saat laporan direvisi dan disimpan ulang', function () {
    $this->actingAs($this->officer)->post("/opd/laporan/masalah/{$this->issue->id}/tindak-lanjut", followUpPayload(['status' => 'reviewed']));
    $this->report->update(['status' => ReportStatus::Revision]);

    $this->actingAs($this->members->first())->patchJson("/kelompok/laporan/{$this->report->id}/draf", [
        'supports' => [
            ['id' => $this->issue->id, 'category' => 'pelatihan', 'description' => 'Pelatihan pengemasan dan label'],
            ['id' => null, 'category' => 'modal', 'description' => 'Modal kerja'],
        ],
    ])->assertOk();

    $issue = $this->issue->fresh();
    expect($issue->description)->toBe('Pelatihan pengemasan dan label')
        ->and($issue->follow_up_status)->toBe(FollowUpStatus::Reviewed)
        ->and($issue->followUps()->count())->toBe(1)
        ->and($this->report->issues()->count())->toBe(2);
});

test('filter status, kampung, dan kebutuhan yang belum ditindaklanjuti; kartu dasbor menautkannya', function () {
    $otherVillage = Village::factory()->create(['name' => 'Kampung Contoh 2']);
    $elsewhere = Organization::factory()->create(['name' => 'KUB Seberang', 'village_id' => $otherVillage->id]);
    $elsewhere->opds()->attach($this->opd);
    $recent = MonthlyReport::factory()->for($elsewhere)->status(ReportStatus::Verified)->create(['period' => '2026-09-01', 'submitted_at' => '2026-10-05 09:00:00']);
    $recentNeed = $recent->issues()->create(['kind' => IssueKind::Support, 'category' => 'bantuan_alat', 'description' => 'Mesin pres baru']);
    $old = MonthlyReport::factory()->for($elsewhere)->status(ReportStatus::Verified)->create(['period' => '2026-07-01', 'submitted_at' => '2026-08-05 09:00:00']);
    $staleNeed = $old->issues()->create(['kind' => IssueKind::Support, 'category' => 'modal', 'description' => 'Modal kerja lama']);
    $old->issues()->create(['kind' => IssueKind::Problem, 'category' => 'modal', 'description' => 'Masalah lama']);
    $doneLongAgo = $old->issues()->create(['kind' => IssueKind::Support, 'category' => 'pelatihan', 'description' => 'Sudah selesai']);
    $doneLongAgo->forceFill(['follow_up_status' => FollowUpStatus::Done, 'follow_up_at' => '2026-08-10 09:00:00'])->save();

    $this->actingAs($this->officer);
    $descriptions = fn (string $query) => $this->get('/opd/laporan/masalah?'.$query)->viewData('issues')->pluck('description')->sort()->values()->all();

    expect($descriptions('status=done'))->toBe(['Sudah selesai'])
        ->and($descriptions("village={$this->village->id}"))->toBe(['Pelatihan pengemasan'])
        ->and($descriptions('kind=support&lama=30'))->toBe(['Modal kerja lama']);

    $this->get('/opd/laporan/masalah?kind=support&lama=30')->assertSee('Belum ditindaklanjuti lebih dari 30 hari');

    $dashboard = $this->get('/opd')->assertOk()->assertSee('1 kebutuhan belum ditindaklanjuti')->assertSee('/opd/laporan/masalah?kind=support&amp;lama=30', false);
    expect($dashboard->viewData('staleNeeds'))->toBe(1);

    // Status Ditinjau yang diubah baru-baru ini tidak dihitung tertunda lagi.
    $this->post("/opd/laporan/masalah/{$staleNeed->id}/tindak-lanjut", followUpPayload(['status' => 'reviewed']));
    expect($this->get('/opd')->viewData('staleNeeds'))->toBe(0);
    expect($recentNeed->fresh()->follow_up_status)->toBe(FollowUpStatus::New);
});

test('unduh Excel: isi sama dengan daftar yang terlihat, termasuk status tindak lanjut', function () {
    $foreign = Organization::factory()->create();
    $foreign->opds()->attach(Opd::factory()->create());
    MonthlyReport::factory()->for($foreign)->status(ReportStatus::Verified)->create(['submitted_at' => now()])
        ->issues()->create(['kind' => IssueKind::Problem, 'category' => 'modal', 'description' => 'Rahasia kelompok lain']);
    $this->actingAs($this->officer)->post("/opd/laporan/masalah/{$this->issue->id}/tindak-lanjut", followUpPayload());

    $this->get('/opd/laporan/masalah')->assertSee('/opd/laporan/masalah/unduh', false);
    $response = $this->get('/opd/laporan/masalah/unduh')->assertOk()->assertDownload('masalah-kebutuhan-2026-10-12.xlsx');
    $rows = SimpleExcelReader::create($response->baseResponse->getFile()->getPathname(), 'xlsx')->noHeaderRow()->getRows()->all();

    expect($rows)->toHaveCount(2)
        ->and($rows[0][0])->toBe('Kelompok')
        ->and(array_slice($rows[1], 0, 9))->toBe([
            'KUB Binaan', 'Kampung Contoh 1', 'September 2026', 'Kebutuhan', 'Pelatihan', 'Pelatihan pengemasan',
            'Direncanakan', 'Contoh', 'Pelatihan dijadwalkan November.',
        ]);

    $this->actingAs($this->members->first())->get('/opd/laporan/masalah/unduh')->assertForbidden();
});

test('OPD tidak bisa dihapus dari data master selama dipakai sebagai penanggung jawab tindak lanjut', function () {
    $responsible = Opd::factory()->create(['name' => 'OPD Penanggung Jawab']);
    $this->actingAs(User::factory()->admin()->create())
        ->post("/admin/laporan/masalah/{$this->issue->id}/tindak-lanjut", followUpPayload(['opd_id' => $responsible->id]));

    $this->from('/admin/pengaturan/data-master/opd')->delete("/admin/pengaturan/data-master/opd/{$responsible->id}")
        ->assertSessionHas('error', fn (string $message) => str_contains($message, '1 tindak lanjut'));
    expect(Opd::whereKey($responsible->id)->exists())->toBeTrue();
});
