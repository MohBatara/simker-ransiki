<?php

use App\Models\BusinessSector;
use App\Models\InstitutionType;
use App\Models\Organization;
use App\Models\Post;
use App\Models\Product;

test('efek scroll: progressive enhancement, angka akhir dirender server, tiap bagian memakai sistem yang sama', function () {
    $sector = BusinessSector::factory()->create(['name' => 'Perikanan']);
    $organization = Organization::factory()->public()->create(['institution_type_id' => InstitutionType::factory()->create()->id]);
    $organization->sectors()->attach($sector);
    Product::factory()->for($organization)->count(2)->create(['is_public' => true, 'is_featured' => true]);
    Post::factory()->count(3)->create();

    $html = $this->get('/')->assertOk()->getContent();

    // Kelas penyembunyi hanya lewat JS: tidak ada data-reveal-state di HTML server; kelas motion dipasang skrip head.
    expect($html)->not->toContain('data-reveal-state')
        ->toContain("if (! matchMedia('(prefers-reduced-motion: reduce)').matches) document.documentElement.classList.add('motion');")
        // Hero: intro berurutan + parallax; strip muncul setelah intro dan menghitung angka.
        ->toContain('data-intro>')->toContain('x-parallax="40"')->toContain('data-intro-late')
        ->toMatch('#<span x-count\.600>1</span> kelompok#')
        // Bagian lain.
        ->toContain('x-reveal:stagger.fade-up.70')->toContain('x-reveal:scale-in')
        ->toContain('x-reveal:fade-up')->toContain('x-reveal:stagger.slide-left.70.200')->toContain('x-reveal:stagger.fade-up.100')
        // Angka statistik: nilai akhir sudah ada di HTML (SEO, tanpa JS tetap benar).
        ->toMatch('#<span x-count>1</span>#')
        // Header ringkas setelah digulir dan tombol kembali ke atas.
        ->toContain("\$store.scroll.pastHeader")->toContain('aria-label="Kembali ke atas"')->toContain("\$store.scroll.pastFold");
});
