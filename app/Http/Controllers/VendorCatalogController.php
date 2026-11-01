<?php

namespace App\Http\Controllers;

use App\Models\BusinessProfile;
use App\Models\VendorListing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VendorCatalogController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === 'umkm', 403);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'max_moq' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'material' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
            'has_sample' => ['nullable', 'boolean'],
            'has_certificate' => ['nullable', 'boolean'],
            'max_price' => ['nullable', 'numeric', 'gt:0', 'max:1000000000'],
            'distance_km' => ['nullable', 'numeric', 'between:1,500'],
            'origin_lat' => ['required_with:origin_lng', 'nullable', 'numeric', 'between:-90,90'],
            'origin_lng' => ['required_with:origin_lat', 'nullable', 'numeric', 'between:-180,180'],
            'sort' => ['nullable', 'in:recommended,price,moq,distance'],
        ]);

        if (isset($validated['distance_km']) && ! isset($validated['origin_lat'], $validated['origin_lng'])) {
            throw ValidationException::withMessages([
                'distance_km' => 'Izinkan lokasi saat ini untuk memakai filter jarak.',
            ]);
        }

        $profiles = BusinessProfile::query()
            ->where('verification_status', 'approved')
            ->whereNotNull('completed_at')
            ->whereHas('user', fn (Builder $users) => $users->where('role', 'vendor'))
            ->with([
                'user:id,name',
                'user.vendorListings' => fn (HasMany $listings) => $listings
                    ->where('status', VendorListing::STATUS_ACTIVE)
                    ->latest('id')
                    ->limit(10),
            ])
            ->when($validated['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $matches) use ($search): void {
                    $matches
                        ->where('business_name', 'like', '%'.$search.'%')
                        ->orWhere('product_categories', 'like', '%'.$search.'%')
                        ->orWhere('materials', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%')
                        ->orWhereHas('user.vendorListings', fn (Builder $listings) => $listings
                            ->where('status', VendorListing::STATUS_ACTIVE)
                            ->where(function (Builder $matches) use ($search): void {
                                $matches
                                    ->where('name', 'like', '%'.$search.'%')
                                    ->orWhere('category', 'like', '%'.$search.'%')
                                    ->orWhere('material', 'like', '%'.$search.'%')
                                    ->orWhere('description', 'like', '%'.$search.'%');
                            }));
                });
            })
            ->when($validated['category'] ?? null, fn (Builder $query, string $category) => $query
                ->where('product_categories', 'like', '%'.$category.'%'))
            ->when($validated['max_moq'] ?? null, fn (Builder $query, int $maxMoq) => $query
                ->where('minimum_order_quantity', '<=', $maxMoq))
            ->when($validated['material'] ?? null, fn (Builder $query, string $material) => $query
                ->where('materials', 'like', '%'.$material.'%'))
            ->when($validated['location'] ?? null, fn (Builder $query, string $location) => $query
                ->where('location', 'like', '%'.$location.'%'))
            ->when(($validated['has_sample'] ?? false), fn (Builder $query) => $query
                ->whereNotNull('work_sample_paths')
                ->where('work_sample_paths', '!=', '[]'))
            ->when(($validated['has_certificate'] ?? false), fn (Builder $query) => $query
                ->whereNotNull('certificate_paths')
                ->where('certificate_paths', '!=', '[]'))
            ->when($validated['max_price'] ?? null, fn (Builder $query, string $maxPrice) => $query
                ->whereNotNull('indicative_price_per_unit')
                ->where('indicative_price_per_unit', '<=', $maxPrice))
            ->when(isset($validated['distance_km']), fn (Builder $query) => $query
                ->whereNotNull('latitude')
                ->whereNotNull('longitude'))
            ->orderBy('business_name')
            ->limit(1000)
            ->get();

        $vendors = $profiles->map(function (BusinessProfile $profile) use ($validated): array {
            $distance = isset($validated['origin_lat'], $validated['origin_lng'])
                && $profile->latitude !== null
                && $profile->longitude !== null
                    ? $this->distanceInKilometers(
                        (float) $validated['origin_lat'],
                        (float) $validated['origin_lng'],
                        (float) $profile->latitude,
                        (float) $profile->longitude,
                    )
                    : null;

            return [
                'id' => $profile->user_id,
                'name' => $profile->business_name ?: $profile->user?->name ?: 'Vendor',
                'category' => $profile->product_categories,
                'description' => $profile->description,
                'images' => collect($profile->work_sample_paths ?? [])
                    ->take(5)
                    ->keys()
                    ->map(fn (int $index): string => route('vendors.catalog.image', [$profile, $index]))
                    ->all(),
                'location' => $profile->location,
                'minimum_order_quantity' => $profile->minimum_order_quantity,
                'materials' => $profile->materials,
                'lead_time_days' => $profile->production_lead_time_days,
                'price_per_unit' => $profile->indicative_price_per_unit,
                'has_sample' => count($profile->work_sample_paths ?? []) > 0,
                'certificate_count' => count($profile->certificate_paths ?? []),
                'distance_km' => $distance,
                'listings' => $profile->user?->vendorListings
                    ->map(fn (VendorListing $listing): array => [
                        'name' => $listing->name,
                        'category' => $listing->category,
                        'material' => $listing->material,
                        'minimum_order_quantity' => $listing->minimum_order_quantity,
                        'description' => $listing->description,
                    ])
                    ->values()
                    ->all() ?? [],
            ];
        })
            ->when(isset($validated['distance_km']), fn ($results) => $results
                ->filter(fn (array $vendor) => $vendor['distance_km'] !== null
                    && $vendor['distance_km'] <= (float) $validated['distance_km']))
            ->sortBy(fn (array $vendor): array => match ($validated['sort'] ?? 'recommended') {
                'price' => [$vendor['price_per_unit'] === null, $vendor['price_per_unit'] ?? PHP_INT_MAX, $vendor['name']],
                'moq' => [$vendor['minimum_order_quantity'] ?? PHP_INT_MAX, $vendor['name']],
                'distance' => [$vendor['distance_km'] ?? PHP_INT_MAX, $vendor['name']],
                default => [0, $vendor['name']],
            })
            ->values()
            ->map(function (array $vendor): array {
                $vendor['distance_km'] = $vendor['distance_km'] === null
                    ? null
                    : round($vendor['distance_km'], 1);

                return $vendor;
            });

        return response()->json([
            'vendors' => $vendors,
            'count' => $vendors->count(),
        ]);
    }

    public function showSample(Request $request, BusinessProfile $businessProfile, int $index): BinaryFileResponse
    {
        abort_unless($request->user()->role === 'umkm', 403);
        abort_unless(
            $businessProfile->verification_status === 'approved' && $businessProfile->completed_at !== null,
            404,
        );

        $path = $businessProfile->work_sample_paths[$index] ?? null;
        $expectedDirectory = 'vendor-verification/'.$businessProfile->id.'/work_samples';

        abort_unless(is_string($path) && dirname($path) === $expectedDirectory, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        $mimeType = $disk->mimeType($path);
        abort_unless(in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true), 404);

        $response = response()->file($disk->path($path), [
            'Content-Type' => $mimeType,
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setPrivate();
        $response->setMaxAge(300);

        return $response;
    }

    private function distanceInKilometers(float $originLatitude, float $originLongitude, float $latitude, float $longitude): float
    {
        $latitudeDelta = deg2rad($latitude - $originLatitude);
        $longitudeDelta = deg2rad($longitude - $originLongitude);
        $distance = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($originLatitude)) * cos(deg2rad($latitude))
            * sin($longitudeDelta / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($distance), sqrt(1 - $distance));
    }
}
