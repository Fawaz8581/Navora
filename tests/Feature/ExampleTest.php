<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the Navora homepage explains its marketplace and audience', function () {
    $response = $this->get('/');

    $response
        ->assertOk()
        ->assertSee('Navora — Kemasan yang tepat, mulai dari skala UMKM', false)
        ->assertSee('Kemasan yang tepat,', false)
        ->assertSee('Patungan capai MOQ', false)
        ->assertSee('Vendor dan maklon', false)
        ->assertSee('Coba asisten demo', false)
        ->assertSee('/css/home.css', false)
        ->assertSee('/js/home.js', false);
});
