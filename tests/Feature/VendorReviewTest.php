<?php

use App\Models\BusinessProfile;
use App\Models\Order;
use App\Models\User;
use App\Models\VendorReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('umkm can review a vendor after completing an order with rating comment and optional image', function () {
    Storage::fake('local');

    $vendor = User::factory()->create(['role' => 'vendor']);
    BusinessProfile::factory()->for($vendor)->create([
        'verification_status' => 'approved',
    ]);

    $buyer = User::factory()->create(['role' => 'umkm']);
    BusinessProfile::factory()->for($buyer)->create();

    $order = Order::query()->create([
        'user_id' => $buyer->id,
        'vendor_user_id' => $vendor->id,
        'type' => Order::TYPE_DIRECT,
        'product_name' => 'Pouch Foil Kopi',
        'quantity' => 500,
        'packaging_details' => 'Kraft foil tebal',
        'status' => Order::STATUS_RECEIVED,
        'payment_status' => 'paid',
    ]);

    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    $response = $this->actingAs($buyer)->postJson(route('orders.review.store', $order), [
        'rating' => 5,
        'comment' => 'Kualitas kemasan pouch sangat tebal dan rapi, pengerjaan cepat!',
        'image' => UploadedFile::fake()->createWithContent('hasil-kemasan.png', $png),
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('review.rating', 5)
        ->assertJsonPath('review.comment', 'Kualitas kemasan pouch sangat tebal dan rapi, pengerjaan cepat!');

    $this->assertDatabaseHas('vendor_reviews', [
        'order_id' => $order->id,
        'user_id' => $buyer->id,
        'vendor_user_id' => $vendor->id,
        'rating' => 5,
    ]);

    $review = VendorReview::where('order_id', $order->id)->firstOrFail();
    expect($review->image_path)->not->toBeNull();
    Storage::disk('local')->assertExists($review->image_path);

    // List reviews for vendor
    $listResponse = $this->actingAs($buyer)->getJson(route('vendor.reviews.list', $vendor));
    $listResponse->assertOk()
        ->assertJsonPath('average_rating', 5)
        ->assertJsonPath('total_reviews', 1)
        ->assertJsonCount(1, 'reviews');

    // Duplicate review should be rejected
    $duplicateResponse = $this->actingAs($buyer)->postJson(route('orders.review.store', $order), [
        'rating' => 4,
        'comment' => 'Review kedua',
    ]);
    $duplicateResponse->assertStatus(422);
});

test('orders that have not been received yet cannot be reviewed', function () {
    $vendor = User::factory()->create(['role' => 'vendor']);
    $buyer = User::factory()->create(['role' => 'umkm']);

    $order = Order::query()->create([
        'user_id' => $buyer->id,
        'vendor_user_id' => $vendor->id,
        'type' => Order::TYPE_DIRECT,
        'product_name' => 'Pouch Foil Kopi',
        'quantity' => 500,
        'packaging_details' => 'Kraft foil tebal',
        'status' => Order::STATUS_SHIPPED,
        'payment_status' => 'paid',
    ]);

    $response = $this->actingAs($buyer)->postJson(route('orders.review.store', $order), [
        'rating' => 5,
        'comment' => 'Pesanan belum sampai tapi sudah mau review',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Ulasan baru dapat diberikan setelah pesanan ditandai telah diterima.');
});
