<?php

namespace App\Support;

use App\Enums\OrganizationStatus;
use App\Models\DuplicateDismissal;
use App\Models\Organization;
use Illuminate\Support\Collection;

/**
 * Kemungkinan data ganda kelompok. Kemiripan nama dihitung di PHP (App\Support\SimilarNames) karena jumlah
 * kelompok kecil. Nama mirip di kampung yang sama = peringatan kuat; di kampung lain = peringatan ringan.
 * Pasangan yang sudah ditandai "Bukan data ganda" (duplicate_dismissals) tidak pernah muncul lagi.
 * Dipakai form tambah/ubah kelompok (Admin), halaman "Kemungkinan data ganda", dan pratinjau impor.
 */
class DuplicateCandidates
{
    /** @var Collection<int, array{id: int, name: string, status: OrganizationStatus, type: ?string, village_id: ?int, village: ?string, parts: array}> */
    private Collection $organizations;

    private array $dismissed;

    private int $minSimilarity;

    public function __construct()
    {
        $this->minSimilarity = (int) config('simker.duplicates.min_similarity');
        $this->dismissed = DuplicateDismissal::keys();
        $this->organizations = Organization::query()
            ->with(['institutionType:id,name', 'village:id,name'])
            ->orderBy('name')
            ->get(['id', 'name', 'status', 'institution_type_id', 'village_id'])
            ->map(fn (Organization $organization) => [
                'id' => $organization->id,
                'name' => $organization->name,
                'status' => $organization->status,
                'type' => $organization->institutionType?->name,
                'village_id' => $organization->village_id,
                'village' => $organization->village?->name,
                'parts' => SimilarNames::split($organization->name),
            ]);
    }

    /**
     * Kelompok yang namanya mirip dengan $name, peringatan kuat (kampung sama) lebih dulu.
     *
     * @return Collection<int, array{id: int, name: string, status: ?string, type: ?string, village: ?string, strong: bool}>
     */
    public function for(string $name, ?int $villageId, ?int $exceptId = null): Collection
    {
        $parts = SimilarNames::split($name);

        return $this->organizations
            ->reject(fn (array $other) => $other['id'] === $exceptId
                || ($exceptId && isset($this->dismissed[DuplicateDismissal::key($exceptId, $other['id'])])))
            ->filter(fn (array $other) => SimilarNames::partsAreSimilar($parts, $other['parts'], $this->minSimilarity))
            ->map(fn (array $other) => [...$this->public($other), 'strong' => $villageId !== null && $other['village_id'] === $villageId])
            ->sortBy([['strong', 'desc'], ['name', 'asc']])
            ->values();
    }

    /**
     * Semua pasangan kelompok yang mungkin ganda, kecuali yang sudah ditandai dan pasangan yang salah satunya
     * sudah ditutup (biasanya data gandanya memang sudah diselesaikan dengan menutup salah satu).
     *
     * @return Collection<int, array{first: array, second: array, strong: bool}>
     */
    public function pairs(): Collection
    {
        $open = $this->organizations->reject(fn (array $organization) => $organization['status'] === OrganizationStatus::Closed)->values();
        $pairs = collect();

        foreach ($open as $index => $first) {
            foreach ($open->slice($index + 1) as $second) {
                if (isset($this->dismissed[DuplicateDismissal::key($first['id'], $second['id'])])
                    || ! SimilarNames::partsAreSimilar($first['parts'], $second['parts'], $this->minSimilarity)) {
                    continue;
                }

                $pairs->push([
                    'first' => $this->public($first),
                    'second' => $this->public($second),
                    'strong' => $first['village_id'] === $second['village_id'],
                ]);
            }
        }

        return $pairs->sortBy([['strong', 'desc'], fn ($a, $b) => strcmp($a['first']['name'], $b['first']['name'])])->values();
    }

    /** Hanya teks dan angka, agar aman disimpan di session (flash). */
    private function public(array $organization): array
    {
        return [
            'id' => $organization['id'],
            'name' => $organization['name'],
            'status' => $organization['status'] === OrganizationStatus::Active ? null : $organization['status']->label(),
            'type' => $organization['type'],
            'village' => $organization['village'],
        ];
    }
}
