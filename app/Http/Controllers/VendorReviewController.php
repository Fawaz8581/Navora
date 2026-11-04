<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use App\Models\VendorReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VendorReviewController extends Controller
{
    public function store(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->role === 'umkm' && $order->user_id === $user->id, 403);
        abort_unless($order->vendor_user_id !== null, 422, 'Pesanan tidak memiliki vendor yang valid.');

        // Ulasan hanya dapat diberikan setelah UMKM menandai pesanan telah sampai/diterima
        $canReview = $order->status === Order::STATUS_RECEIVED;
        abort_unless($canReview, 422, 'Ulasan baru dapat diberikan setelah pesanan ditandai telah diterima.');

        abort_if($order->review()->exists(), 422, 'Anda sudah memberikan ulasan untuk pesanan ini.');

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'min:3', 'max:1000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ], [
            'rating.required' => 'Pilih bintang ulasan.',
            'rating.between' => 'Rating bintang harus antara 1 sampai 5.',
            'comment.required' => 'Tuliskan penjelasan pengalaman pesananmu.',
            'comment.min' => 'Penjelasan minimal 3 karakter.',
            'comment.max' => 'Penjelasan maksimal 1.000 karakter.',
            'image.image' => 'File harus berupa gambar.',
            'image.max' => 'Ukuran gambar maksimal 5MB.',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store("vendor-reviews/{$order->id}", 'local');
        }

        $review = $order->review()->create([
            'user_id' => $user->id,
            'vendor_user_id' => $order->vendor_user_id,
            'rating' => $validated['rating'],
            'comment' => trim($validated['comment']),
            'image_path' => $imagePath,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ulasan berhasil dikirim! Terima kasih atas masukanmu.',
            'review' => [
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'image_url' => $review->image_path ? route('vendor.reviews.image', $review) : null,
                'created_at' => $review->created_at?->format('d M Y H:i'),
            ],
        ], 201);
    }

    public function image(VendorReview $review): BinaryFileResponse
    {
        abort_unless($review->image_path, 404);
        abort_unless(Storage::disk('local')->exists($review->image_path), 404);

        return response()->file(Storage::disk('local')->path($review->image_path));
    }

    public function listByVendor(User $vendor): JsonResponse
    {
        abort_unless($vendor->role === 'vendor', 404);

        $reviews = $vendor->vendorReviews()
            ->with(['user.businessProfile', 'order'])
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(fn (VendorReview $review): array => [
                'id' => $review->id,
                'reviewer_name' => $review->user?->businessProfile?->business_name ?? $review->user?->name ?? 'Pemilik Usaha',
                'product_name' => $review->order?->product_name ?? 'Produk',
                'rating' => $review->rating,
                'comment' => $review->comment,
                'image_url' => $review->image_path ? route('vendor.reviews.image', $review) : null,
                'created_at' => $review->created_at?->format('d M Y'),
            ]);

        $averageRating = round((float) ($vendor->vendorReviews()->avg('rating') ?: 0), 1);
        $totalReviews = $vendor->vendorReviews()->count();

        return response()->json([
            'average_rating' => $averageRating,
            'total_reviews' => $totalReviews,
            'reviews' => $reviews,
        ]);
    }
}
