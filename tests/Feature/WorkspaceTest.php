<?php

test('the workspace route renders the Navora marketplace demo and its assets', function () {
    $this->get('/ruang-kerja')
        ->assertOk()
        ->assertSee('Ruang Navora — Marketplace kemasan UMKM', false)
        ->assertSee('Cari vendor')
        ->assertSee('Patungan MOQ')
        ->assertSee('Permintaan penawaran')
        ->assertSee('Asisten kemasan')
        ->assertSee('PouchKita Studio')
        ->assertSee('data-page-panel="overview"', false)
        ->assertSee('data-page-panel="vendors"', false)
        ->assertSee('data-role-select', false)
        ->assertSee('/css/navora-app.css', false)
        ->assertSee('/js/navora-app.js', false);
});
