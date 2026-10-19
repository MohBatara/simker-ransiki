<?php

namespace App\Http\Controllers\Shared;

use App\Actions\Organizations\SaveOrganization;
use App\Enums\LicenseStatus;
use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\LicenseRequest;
use App\Http\Requests\Organizations\OrganizationRequest;
use App\Models\License;
use App\Models\MonthlyReport;
use App\Models\Organization;
use App\Support\DuplicateAlert;
use App\Support\DuplicateCandidates;
use App\Support\Exports\OrganizationList;
use App\Support\Exports\Spreadsheet;
use App\Support\Format;
use App\Support\OrganizationOptions;
use App\Support\OrganizationOverview;
use App\Support\OrganizationPanel;
use App\Support\ProfileReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Daftar dan detail kelompok untuk Admin dan OPD. Isi daftar disaring scope visibleTo; hak per kelompok
 * dicek Policy di route. Route name diawali area pengguna: admin.organizations.* atau opd.organizations.*.
 */
class OrganizationController extends Controller
{
    public const FILTERS = ['q', 'type', 'village', 'sector', 'opd', 'status', 'akun', 'laporan', 'periode'];

    public function index(Request $request): View
    {
        $filters = $request->only(self::FILTERS);

        $query = Organization::visibleTo($request->user())->filter($filters);

        // Ringkasan dihitung dari daftar yang sudah tersaring, agar sama dengan isi tabel.
        $statusCounts = (clone $query)->toBase()->selectRaw('organizations.status, COUNT(*) as total')->groupBy('organizations.status')->pluck('total', 'status');
        $totals = (clone $query)->toBase()
            ->selectRaw('COUNT(*) as organizations, COUNT(DISTINCT organizations.village_id) as villages, SUM(organizations.members_count) as members, COUNT(organizations.members_count) as members_filled')
            ->first();
        $sectors = DB::table('organization_sector')->whereIn('organization_id', (clone $query)->select('organizations.id'))->distinct()->count('business_sector_id');

        $organizations = $query
            ->with(['institutionType:id,name', 'village:id,name', 'sectors:id,name'])
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        // Popup pratinjau dirender bersama halaman untuk ?pratinjau=id (alias lama ?pilih=id), hanya bila terlihat pengguna.
        $previewId = $request->integer('pratinjau') ?: $request->integer('pilih');
        $selected = $previewId ? Organization::visibleTo($request->user())->find($previewId) : null;

        return view('shared.organizations.index', [
            'area' => $this->area($request),
            'organizations' => $organizations,
            'statusCounts' => ['inactive' => (int) ($statusCounts['inactive'] ?? 0), 'closed' => (int) ($statusCounts['closed'] ?? 0)],
            'totals' => [
                'organizations' => (int) $totals->organizations,
                'villages' => (int) $totals->villages,
                'sectors' => $sectors,
                'members' => $totals->members_filled ? (int) $totals->members : null,
                'members_filled' => (int) $totals->members_filled,
            ],
            'panel' => $selected ? OrganizationPanel::data($selected) : null,
            'filters' => $filters,
            'options' => OrganizationOptions::all(),
        ]);
    }

    /** "Unduh Excel": isi sama dengan daftar di layar (visibleTo + filter aktif), tanpa paginasi. */
    public function export(Request $request): BinaryFileResponse
    {
        $query = Organization::visibleTo($request->user())->filter($request->only(self::FILTERS));

        return Spreadsheet::download('kelompok-'.now()->format('Y-m-d').'.xlsx', [OrganizationList::sheet($query)]);
    }

    /** Potongan HTML panel detail untuk daftar kelompok di desktop (diambil saat baris diklik). */
    public function summary(Request $request, Organization $organization): View
    {
        return view('shared.organizations._panel', [
            'area' => $this->area($request),
            'panel' => OrganizationPanel::data($organization),
        ]);
    }

    public function show(Request $request, Organization $organization): View
    {
        return $this->detail($request, $organization, 'profil', [
            'organization' => $organization->load(['institutionType', 'village', 'sectors', 'opds', 'updater']),
            'attachments' => $organization->attachments()->with('uploader')->latest()->get()->each->setRelation('attachable', $organization),
        ]);
    }

    public function products(Request $request, Organization $organization): View
    {
        return $this->detail($request, $organization, 'produk', [
            'products' => $organization->products()->orderBy('name')->get(),
        ]);
    }

    public function licenses(Request $request, Organization $organization): View
    {
        return $this->detail($request, $organization, 'izin', [
            'licenses' => $organization->licenses()->withCount('attachments')->orderBy('type')->get(),
            'licenseTypes' => LicenseRequest::SUGGESTED_TYPES,
        ]);
    }

    public function license(Request $request, Organization $organization, License $license): View
    {
        $area = $this->area($request);

        return view('shared.organizations.license-detail', [
            'organization' => $organization,
            'license' => $license,
            'attachments' => $license->attachments()->with('uploader')->latest()->get()->each->setRelation('attachable', $license),
            'backUrl' => route($area.'.organizations.licenses', $organization),
            'breadcrumbs' => [
                ['label' => $area === 'opd' ? 'Kelompok binaan' : 'Kelompok', 'href' => route($area.'.organizations.index')],
                ['label' => $organization->name, 'href' => route($area.'.organizations.show', $organization)],
                ['label' => 'Izin', 'href' => route($area.'.organizations.licenses', $organization)],
                ['label' => $license->type],
            ],
        ]);
    }

    public function reports(Request $request, Organization $organization): View
    {
        return $this->detail($request, $organization, 'laporan', [
            'reports' => $organization->monthlyReports()->with('enterer:id,role')->orderByDesc('period')->paginate(12),
            'entryPeriods' => $request->user()->can('create', [MonthlyReport::class, $organization]) ? $this->entryPeriods($organization) : [],
        ]);
    }

    /** Tab Riwayat: perubahan profil, produk, dan izin, terbaru dulu. */
    public function history(Request $request, Organization $organization): View
    {
        return $this->detail($request, $organization, 'riwayat', [
            'logs' => $organization->changeLogs()->with('user.opd')->latest('id')->paginate(20),
        ]);
    }

    public function users(Request $request, Organization $organization): View
    {
        return $this->detail($request, $organization, 'akun', [
            'users' => $organization->users()->orderBy('name')->get(),
        ]);
    }

    public function edit(Request $request, Organization $organization): View
    {
        $area = $this->area($request);

        return view('shared.organizations.form-page', [
            'organization' => $organization->load(['sectors', 'opds']),
            'options' => OrganizationOptions::all(),
            'canAdminister' => $request->user()->can('administer', $organization),
            'action' => route($area.'.organizations.update', $organization),
            'cancelUrl' => route($area.'.organizations.show', $organization),
        ]);
    }

    public function update(OrganizationRequest $request, Organization $organization, SaveOrganization $save): RedirectResponse
    {
        $data = $request->organizationData();
        $identityChanged = array_key_exists('name', $data)
            && ($data['name'] !== $organization->name || (int) $data['village_id'] !== $organization->village_id);

        $candidates = $identityChanged
            ? (new DuplicateCandidates)->for($data['name'], (int) $data['village_id'], $organization->id)
            : collect();

        if (! $request->boolean('confirm_similar') && $candidates->contains('strong', true)) {
            return back()->withInput()->with('duplicates', $candidates->all());
        }

        $save->handle($request->user(), $data, $organization);
        DuplicateAlert::organizationSaved($request->user(), $organization, $candidates, 'ubah');

        return redirect()->route($this->area($request).'.organizations.show', $organization)
            ->with('status', 'Profil kelompok diperbarui.')
            ->with('duplicates', $candidates->where('strong', false)->values()->all());
    }

    /** "Tandai profil terverifikasi" (Admin dan OPD pembina). */
    public function verifyProfile(Request $request, Organization $organization): RedirectResponse
    {
        ProfileReview::verify($organization, $request->user());

        return back()->with('status', 'Profil ditandai terverifikasi.');
    }

    /** Bulan yang bisa dipilih di "Isi laporan": sejak wajib lapor sampai bulan ini, terbaru dulu. */
    private function entryPeriods(Organization $organization): array
    {
        return $organization->reportingPeriods()->mapWithKeys(fn ($period) => [$period->format('Y-m') => Format::month($period)])->all();
    }

    private function detail(Request $request, Organization $organization, string $tab, array $data): View
    {
        $organization->loadMissing(['institutionType', 'village', 'sectors:id,name', 'opds:id,name,short_name', 'profileVerifier.opd'])
            ->loadCount([
                'monthlyReports', 'users', 'products', 'licenses', 'opds',
                // Strip angka di kepala detail (tampilan saja).
                'licenses as issued_licenses_count' => fn ($query) => $query->where('status', LicenseStatus::Issued),
                'monthlyReports as verified_reports_count' => fn ($query) => $query->where('status', ReportStatus::Verified),
            ]);

        return view('shared.organizations.detail', [
            'area' => $this->area($request),
            'organization' => $organization,
            'overview' => $tab === 'profil' ? OrganizationOverview::data($organization, withStatusHistory: true) : null,
            'tab' => $tab,
            ...$data,
        ]);
    }

    /** "admin" atau "opd"; dipakai sebagai awalan nama route. */
    private function area(Request $request): string
    {
        return $request->user()->role->area();
    }
}
