<?php

test('the SupplierReady homepage explains the platform and its audience', function () {
    $response = $this->get('/');

    $response
        ->assertOk()
        ->assertSee('Hubungan supplier,', false)
        ->assertSee('Perusahaan anchor', false)
        ->assertSee('Supplier UMKM', false)
        ->assertSee('SupplierReady bukan pemberi pinjaman', false)
        ->assertSee('data-workflow-stage', false)
        ->assertSee('/css/home.css', false)
        ->assertSee('/js/home.js', false);
});
