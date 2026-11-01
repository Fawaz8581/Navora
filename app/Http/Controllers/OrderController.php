<?php

namespace App\Http\Controllers;

use App\Jobs\AdvanceSimulatedOrder;
use App\Models\BusinessProfile;
use App\Models\Order;
use App\Models\OrderParticipant;
use App\Models\User;
use App\Models\VendorSubscription;
use App\Services\MidtransSnapService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['umkm', 'vendor'], true), 403);

        $validated = $request->validate([
            'type' => ['sometimes', Rule::in([Order::TYPE_DIRECT, Order::TYPE_GROUP])],
        ]);
        $type = $validated['type'] ?? Order::TYPE_DIRECT;

        $orders = Order::query()
            ->where('type', $type)
            ->where(function (Builder $query) use ($user, $type): void {
                $query
                    ->where('user_id', $user->id)
                    ->orWhere('vendor_user_id', $user->id)
                    ->orWhereHas('participants', fn (Builder $participants) => $participants->where('user_id', $user->id));

                if ($type === Order::TYPE_GROUP) {
                    $query->orWhere('type', Order::TYPE_GROUP);
                }
            })
            ->with([
                'creator:id,name',
                'vendor:id,name',
                'vendor.businessProfile:id,user_id,business_name,business_address,rajaongkir_area_id,rajaongkir_area_name',
                'participants.user:id,name',
                'statusHistory.actor:id,name',
            ])
            ->latest('id')
            ->paginate(50);

        $orders->getCollection()->each(fn (Order $order) => $this->syncSimulatedTracking($order));

        $presentedOrders = $orders->getCollection()
            ->map(fn (Order $order): array => $this->presentOrder($order, $user))
            ->values();

        $vendors = $user->role === 'umkm'
            ? User::query()
                ->where('role', 'vendor')
                ->whereHas('businessProfile', fn (Builder $profile) => $profile
                    ->where('verification_status', 'approved')
                    ->whereNotNull('completed_at'))
                ->with([
                    'businessProfile:id,user_id,business_name',
                    'vendorSubscriptions' => fn (HasMany $subscriptions) => $subscriptions
                        ->where('payment_status', 'paid')
                        ->where('ends_at', '>', now())
                        ->latest('id')
                        ->limit(1),
                ])
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $vendor): array => [
                    'id' => $vendor->id,
                    'name' => $vendor->businessProfile?->business_name ?: $vendor->name,
                    'is_premium' => $vendor->vendorSubscriptions->isNotEmpty(),
                ])
                ->values()
            : collect();

        $openGroupCount = $user->role === 'umkm'
            ? Order::query()
                ->where('type', Order::TYPE_GROUP)
                ->where('status', Order::STATUS_WAITING)
                ->whereRaw('orders.quantity > (select coalesce(sum(order_participants.quantity), 0) from order_participants where order_participants.order_id = orders.id)')
                ->count()
            : 0;

        return response()->json([
            'orders' => $presentedOrders,
            'vendors' => $vendors,
            'next_page_url' => $orders->nextPageUrl(),
            'open_group_count' => $openGroupCount,
        ]);
    }

    public function vendorIncome(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->role === 'vendor', 403);

        $paidOrders = Order::query()
            ->where('vendor_user_id', $user->id)
            ->where('payment_status', 'paid');

        $sales = (clone $paidOrders)
            ->with([
                'creator:id,name',
                'creator.businessProfile:id,user_id,business_name',
            ])
            ->select([
                'id',
                'user_id',
                'product_name',
                'quantity',
                'unit_price',
                'total_amount',
                'subtotal_amount',
                'paid_at',
            ])
            ->latest('paid_at')
            ->paginate(50);

        $directRevenue = Order::query()
            ->where('vendor_user_id', $user->id)
            ->where('type', Order::TYPE_DIRECT)
            ->where('payment_status', 'paid')
            ->sum(DB::raw('COALESCE(subtotal_amount, total_amount)'));
        $paidGroupParticipants = OrderParticipant::query()
            ->where('payment_status', 'paid')
            ->whereHas('order', fn (Builder $query) => $query
                ->where('vendor_user_id', $user->id)
                ->where('type', Order::TYPE_GROUP));

        return response()->json([
            'summary' => [
                'total_revenue' => (int) $directRevenue
                    + (int) (clone $paidGroupParticipants)->sum(DB::raw('COALESCE(subtotal_amount, payment_amount)')),
                'completed_orders' => Order::query()
                    ->where('vendor_user_id', $user->id)
                    ->where('type', Order::TYPE_DIRECT)
                    ->where('payment_status', 'paid')
                    ->count()
                    + (clone $paidGroupParticipants)->count(),
            ],
            'sales' => $sales->getCollection()->map(fn (Order $order): array => [
                'id' => $order->id,
                'product_name' => $order->product_name,
                'buyer_name' => $order->creator?->businessProfile?->business_name ?: $order->creator?->name,
                'quantity' => $order->quantity,
                'unit_price' => $order->unit_price,
                'total_amount' => $order->subtotal_amount ?? $order->total_amount,
                'paid_at' => $order->paid_at?->toIso8601String(),
            ])->values(),
            'next_page_url' => $sales->nextPageUrl(),
        ]);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canView($order, $user), 404);

        $this->syncSimulatedTracking($order);

        $order->load([
            'creator:id,name',
            'vendor:id,name',
            'vendor.businessProfile:id,user_id,business_name,business_address,rajaongkir_area_id,rajaongkir_area_name',
            'participants.user:id,name',
            'statusHistory.actor:id,name',
        ]);

        return response()->json(['order' => $this->presentOrder($order, $user)]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->role === 'umkm', 403);

        $validated = $request->validate([
            'type' => ['required', Rule::in([Order::TYPE_DIRECT, Order::TYPE_GROUP])],
            'product_name' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'between:1,10000000'],
            'packaging_details' => ['required', 'string', 'max:2000'],
            'delivery_contact_name' => ['required', 'string', 'max:255'],
            'delivery_contact_phone' => ['required', 'string', 'max:40', 'regex:/^[0-9+().\\s-]{8,40}$/'],
            'delivery_address' => ['required', 'string', 'max:1000'],
            'delivery_area_id' => ['required', 'string', 'max:32'],
            'delivery_area_name' => ['required', 'string', 'max:255'],
            'vendor_user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'vendor')],
            'initial_quantity' => [
                Rule::requiredIf($request->input('type') === Order::TYPE_GROUP),
                'nullable',
                'integer',
                'min:1',
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'product_name.required' => 'Nama produk wajib diisi.',
            'quantity.required' => 'Jumlah pesanan wajib diisi.',
            'quantity.between' => 'Jumlah pesanan harus antara 1 dan 10.000.000 pcs.',
            'packaging_details.required' => 'Spesifikasi kemasan wajib diisi.',
            'delivery_contact_name.required' => 'Nama penerima wajib diisi.',
            'delivery_contact_phone.required' => 'Nomor telepon penerima wajib diisi.',
            'delivery_contact_phone.regex' => 'Nomor telepon penerima tidak valid.',
            'delivery_address.required' => 'Alamat lengkap tujuan pengiriman wajib diisi.',
            'delivery_area_id.required' => 'Pilih area tujuan pengiriman dari hasil pencarian lokasi.',
            'delivery_area_name.required' => 'Area tujuan pengiriman wajib dipilih.',
            'initial_quantity.required' => 'Jumlah awal patungan wajib diisi.',
            'initial_quantity.min' => 'Jumlah awal patungan minimal 1 pcs.',
        ]);

        if (isset($validated['vendor_user_id']) && ! $this->isVerifiedVendor((int) $validated['vendor_user_id'])) {
            throw ValidationException::withMessages([
                'vendor_user_id' => 'Pilih vendor yang sudah menyelesaikan profil dan verifikasi.',
            ]);
        }
        $this->assertVendorRfqCapacity((int) $validated['vendor_user_id']);

        if (($validated['type'] ?? null) === Order::TYPE_GROUP
            && (int) ($validated['initial_quantity'] ?? 0) > (int) $validated['quantity']) {
            throw ValidationException::withMessages([
                'initial_quantity' => 'Jumlah awal tidak boleh melebihi target patungan.',
            ]);
        }

        $order = DB::transaction(function () use ($validated, $user): Order {
            $initialQuantity = $validated['type'] === Order::TYPE_GROUP
                ? (int) $validated['initial_quantity']
                : 0;
            $groupTargetMet = $validated['type'] === Order::TYPE_GROUP
                && $initialQuantity === (int) $validated['quantity'];

            $order = Order::create([
                'user_id' => $user->id,
                'vendor_user_id' => $validated['vendor_user_id'] ?? null,
                'type' => $validated['type'],
                'product_name' => $validated['product_name'],
                'quantity' => $validated['quantity'],
                'packaging_details' => $validated['packaging_details'],
                'delivery_contact_name' => $validated['delivery_contact_name'],
                'delivery_contact_phone' => $validated['delivery_contact_phone'],
                'delivery_address' => $validated['delivery_address'],
                'delivery_area_id' => $validated['delivery_area_id'],
                'delivery_area_name' => $validated['delivery_area_name'],
                'notes' => $validated['notes'] ?? null,
                'status' => Order::STATUS_WAITING,
                'is_simulated' => false,
                'payment_status' => 'awaiting_quote',
                'quote_status' => 'awaiting_vendor',
            ]);

            $order->statusHistory()->create([
                'user_id' => $user->id,
                'status' => Order::STATUS_WAITING,
                'note' => $groupTargetMet
                    ? 'Pesanan dibuat. Target patungan terpenuhi; menunggu konfirmasi.'
                    : 'Pesanan dibuat.',
            ]);

            if ($validated['type'] === Order::TYPE_GROUP) {
                $order->participants()->create([
                    'user_id' => $user->id,
                    'quantity' => $initialQuantity,
                    'delivery_contact_name' => $validated['delivery_contact_name'],
                    'delivery_contact_phone' => $validated['delivery_contact_phone'],
                    'delivery_address' => $validated['delivery_address'],
                    'delivery_area_id' => $validated['delivery_area_id'],
                    'delivery_area_name' => $validated['delivery_area_name'],
                ]);
            }

            return $order;
        });

        $order->load(['creator:id,name', 'vendor:id,name', 'vendor.businessProfile:id,user_id,business_name,business_address,rajaongkir_area_id,rajaongkir_area_name', 'participants.user:id,name', 'statusHistory.actor:id,name']);

        return response()->json(['order' => $this->presentOrder($order, $user)], 201);
    }

    public function storeQuote(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->role === 'umkm', 403);

        $validated = $request->validate([
            'vendor_user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'vendor')],
            'product_name' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'between:1,10000000'],
            'packaging_details' => ['required', 'string', 'max:2000'],
            'target_unit_price' => ['nullable', 'integer', 'between:1,1000000000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'vendor_user_id.required' => 'Pilih vendor yang menerima permintaan.',
            'product_name.required' => 'Nama produk wajib diisi.',
            'quantity.required' => 'Jumlah pesanan wajib diisi.',
            'quantity.between' => 'Jumlah pesanan harus antara 1 dan 10.000.000 pcs.',
            'packaging_details.required' => 'Spesifikasi kemasan wajib diisi.',
        ]);

        if (! $this->isVerifiedVendor((int) $validated['vendor_user_id'])) {
            throw ValidationException::withMessages([
                'vendor_user_id' => 'Vendor tidak lagi memiliki profil yang terverifikasi.',
            ]);
        }
        $this->assertVendorRfqCapacity((int) $validated['vendor_user_id']);

        $order = DB::transaction(function () use ($validated, $user): Order {
            $order = Order::create([
                'user_id' => $user->id,
                'vendor_user_id' => $validated['vendor_user_id'],
                'type' => Order::TYPE_DIRECT,
                'product_name' => $validated['product_name'],
                'quantity' => $validated['quantity'],
                'packaging_details' => $validated['packaging_details'],
                'target_unit_price' => $validated['target_unit_price'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => Order::STATUS_WAITING,
                'is_simulated' => false,
                'payment_status' => 'awaiting_quote',
                'quote_status' => 'awaiting_vendor',
            ]);

            $order->statusHistory()->create([
                'user_id' => $user->id,
                'status' => Order::STATUS_WAITING,
                'note' => 'Permintaan penawaran dikirim ke vendor.',
            ]);
            $order->messages()->create([
                'user_id' => $user->id,
                'message' => 'Permintaan penawaran untuk '.$order->product_name.' sejumlah '
                    .number_format($order->quantity, 0, ',', '.').' pcs.'
                    ."\nSpesifikasi: ".$order->packaging_details
                    .($order->target_unit_price
                        ? "\nTarget harga: Rp".number_format($order->target_unit_price, 0, ',', '.').' per pcs.'
                        : '')
                    .(filled($order->notes) ? "\n".$order->notes : ''),
            ]);

            return $order;
        });

        $order->load(['creator:id,name', 'vendor:id,name', 'vendor.businessProfile:id,user_id,business_name,business_address,rajaongkir_area_id,rajaongkir_area_name', 'participants.user:id,name', 'statusHistory.actor:id,name']);

        return response()->json(['order' => $this->presentOrder($order, $user)], 201);
    }

    public function updateDeliveryDetails(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->role === 'umkm', 403);
        $participant = $order->type === Order::TYPE_GROUP
            ? $order->participants()->where('user_id', $user->id)->first()
            : null;
        abort_unless(
            ($order->type === Order::TYPE_DIRECT && $order->user_id === $user->id)
                || ($order->type === Order::TYPE_GROUP && $participant !== null),
            404,
        );

        $validated = $request->validate([
            'delivery_contact_name' => ['required', 'string', 'max:255'],
            'delivery_contact_phone' => ['required', 'string', 'max:40', 'regex:/^[0-9+().\\s-]{8,40}$/'],
            'delivery_address' => ['required', 'string', 'max:1000'],
            'delivery_area_id' => ['required', 'string', 'max:32'],
            'delivery_area_name' => ['required', 'string', 'max:255'],
        ], [
            'delivery_contact_name.required' => 'Nama penerima wajib diisi.',
            'delivery_contact_phone.required' => 'Nomor telepon penerima wajib diisi.',
            'delivery_contact_phone.regex' => 'Nomor telepon penerima tidak valid.',
            'delivery_address.required' => 'Alamat lengkap tujuan pengiriman wajib diisi.',
            'delivery_area_id.required' => 'Pilih area tujuan dari hasil pencarian lokasi.',
            'delivery_area_name.required' => 'Pilih area tujuan pengiriman.',
        ]);

        DB::transaction(function () use ($order, $participant, $validated): void {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($lockedOrder->status !== Order::STATUS_WAITING) {
                throw ValidationException::withMessages([
                    'order' => 'Data penerima hanya dapat diubah saat pesanan masih menunggu.',
                ]);
            }

            if ($participant) {
                $lockedParticipant = $lockedOrder->participants()->lockForUpdate()->findOrFail($participant->id);
                if ($lockedParticipant->payment_status === 'paid'
                    || ($lockedParticipant->payment_status === 'pending' && filled($lockedParticipant->payment_order_id))) {
                    throw ValidationException::withMessages([
                        'order' => 'Data penerima tidak dapat diubah setelah pembayaran dimulai.',
                    ]);
                }

                $subtotal = (int) ($lockedParticipant->subtotal_amount ?? 0);
                $fee = (int) ($lockedParticipant->platform_fee_amount ?? 0);
                $lockedParticipant->forceFill([
                    ...$validated,
                    'shipping_amount' => null,
                    'shipping_courier' => null,
                    'shipping_service' => null,
                    'payment_amount' => $lockedOrder->unit_price === null ? null : $subtotal + $fee,
                ])->save();
                if ($lockedOrder->unit_price !== null) {
                    $lockedOrder->forceFill([
                        'subtotal_amount' => (int) $lockedOrder->participants()->sum('subtotal_amount'),
                        'shipping_amount' => (int) $lockedOrder->participants()->sum('shipping_amount'),
                        'platform_fee_amount' => (int) $lockedOrder->participants()->sum('platform_fee_amount'),
                        'total_amount' => (int) $lockedOrder->participants()->sum('payment_amount'),
                    ])->save();
                }
            } else {
                if ($lockedOrder->payment_status === 'paid'
                    || ($lockedOrder->payment_status === 'pending' && filled($lockedOrder->payment_order_id))) {
                    throw ValidationException::withMessages([
                        'order' => 'Data penerima tidak dapat diubah setelah pembayaran dimulai.',
                    ]);
                }

                $subtotal = (int) ($lockedOrder->subtotal_amount ?? 0);
                $fee = (int) ($lockedOrder->platform_fee_amount ?? 0);
                $lockedOrder->forceFill([
                    ...$validated,
                    'shipping_amount' => null,
                    'shipping_courier' => null,
                    'shipping_service' => null,
                    'total_amount' => $lockedOrder->unit_price === null ? null : $subtotal + $fee,
                ])->save();
            }
        });

        return $this->show($request, $order->refresh());
    }

    public function setPrice(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->role === 'vendor' && $order->vendor_user_id === $user->id, 404);
        abort_unless($this->isVerifiedVendor($user->id), 403);

        $validated = $request->validate([
            'unit_price' => ['required', 'integer', 'between:1,1000000000'],
            'shipping_weight_per_unit_grams' => ['required', 'integer', 'between:1,30000'],
        ], [
            'shipping_weight_per_unit_grams.between' => 'Berat per pcs harus antara 1 gram dan 30 kg agar dapat dihitung sebagai paket kurir.',
        ]);

        $order = DB::transaction(function () use ($order, $user, $validated): Order {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            $canSetPrice = in_array($lockedOrder->quote_status, ['awaiting_vendor', 'rejected'], true);

            if ($lockedOrder->status !== Order::STATUS_WAITING
                || $lockedOrder->is_simulated
                || $lockedOrder->payment_status !== 'awaiting_quote'
                || ! $canSetPrice) {
                throw ValidationException::withMessages([
                    'order' => 'Harga hanya dapat ditetapkan untuk pesanan yang masih menunggu penawaran.',
                ]);
            }

            if ($lockedOrder->type === Order::TYPE_GROUP
                && (int) $lockedOrder->participants()->sum('quantity') !== $lockedOrder->quantity) {
                throw ValidationException::withMessages([
                    'order' => 'Target patungan harus terpenuhi sebelum vendor menetapkan harga final.',
                ]);
            }

            $unitPrice = (int) $validated['unit_price'];
            $subtotal = $unitPrice * $lockedOrder->quantity;
            $platformFee = Order::platformFeeFor($subtotal);
            $totalAmount = $subtotal + $platformFee;

            if ($totalAmount > 9007199254740991) {
                throw ValidationException::withMessages([
                    'unit_price' => 'Total pesanan melebihi batas nilai yang dapat diproses dengan aman.',
                ]);
            }

            $lockedOrder->forceFill([
                'unit_price' => $unitPrice,
                'subtotal_amount' => $subtotal,
                'shipping_amount' => null,
                'platform_fee_amount' => $platformFee,
                'shipping_weight_per_unit_grams' => $validated['shipping_weight_per_unit_grams'],
                'shipping_courier' => null,
                'shipping_service' => null,
                'total_amount' => $totalAmount,
                'payment_status' => $lockedOrder->type === Order::TYPE_DIRECT
                    ? 'awaiting_approval'
                    : 'awaiting_approval',
                'quote_status' => 'pending_buyer',
            ])->save();

            if ($lockedOrder->type === Order::TYPE_GROUP) {
                $lockedOrder->participants()->get()->each(function ($participant) use ($unitPrice): void {
                    $subtotal = $unitPrice * $participant->quantity;
                    $fee = Order::platformFeeFor($subtotal);
                    $participant->forceFill([
                        'subtotal_amount' => $subtotal,
                        'shipping_amount' => null,
                        'platform_fee_amount' => $fee,
                        'shipping_courier' => null,
                        'shipping_service' => null,
                        'payment_amount' => $subtotal + $fee,
                        'payment_status' => 'awaiting_approval',
                        'quote_status' => 'pending_buyer',
                    ])->save();
                });
            }

            $lockedOrder->statusHistory()->create([
                'user_id' => $user->id,
                'status' => $lockedOrder->status,
                'note' => 'Vendor menetapkan harga final Rp'.number_format($unitPrice, 0, ',', '.').' per pcs.',
            ]);
            if ($lockedOrder->type === Order::TYPE_DIRECT) {
                $lockedOrder->messages()->create([
                    'user_id' => $user->id,
                    'message' => 'Penawaran final: Rp'.number_format($unitPrice, 0, ',', '.')
                        .' per pcs; subtotal produk Rp'.number_format($subtotal, 0, ',', '.')
                        .' dan biaya platform '.config('services.navora.platform_fee_percent').'% (ongkir dihitung setelah memilih kurir).',
                ]);
            }

            return $lockedOrder;
        });

        $order->load([
            'creator:id,name',
            'vendor:id,name',
            'vendor.businessProfile:id,user_id,business_name,business_address,rajaongkir_area_id,rajaongkir_area_name',
            'participants.user:id,name',
            'statusHistory.actor:id,name',
        ]);

        return response()->json(['order' => $this->presentOrder($order, $user)]);
    }

    public function decideQuote(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->role === 'umkm', 403);
        $participant = $order->type === Order::TYPE_GROUP
            ? $order->participants()->where('user_id', $user->id)->first()
            : null;
        abort_unless(
            ($order->type === Order::TYPE_DIRECT && $order->user_id === $user->id)
                || ($order->type === Order::TYPE_GROUP && $participant !== null),
            404,
        );

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['accept', 'reject'])],
        ]);

        DB::transaction(function () use ($order, $participant, $user, $validated): void {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            $lockedParticipant = $participant
                ? $lockedOrder->participants()->lockForUpdate()->findOrFail($participant->id)
                : null;
            $currentQuoteStatus = $lockedParticipant?->quote_status ?? $lockedOrder->quote_status;

            if ($currentQuoteStatus !== 'pending_buyer'
                || ! in_array($lockedOrder->payment_status, ['awaiting_approval', 'pending'], true)
                || $lockedOrder->status !== Order::STATUS_WAITING) {
                throw ValidationException::withMessages([
                    'decision' => 'Penawaran ini sudah diproses atau tidak lagi menunggu keputusan.',
                ]);
            }

            $accepted = $validated['decision'] === 'accept';
            $message = $accepted
                ? 'Pembeli menyetujui penawaran harga final.'
                : 'Pembeli menolak penawaran harga. Vendor dapat mengirim harga revisi.';

            if ($lockedParticipant) {
                if (! $accepted && $lockedOrder->participants()->whereNotNull('paid_at')->exists()) {
                    throw ValidationException::withMessages([
                        'decision' => 'Penawaran tidak dapat ditolak setelah ada peserta yang membayar.',
                    ]);
                }

                $lockedParticipant->forceFill([
                    'quote_status' => $accepted ? 'accepted' : 'rejected',
                    'payment_status' => $accepted ? 'awaiting_approval' : 'awaiting_quote',
                ])->save();

                if ($accepted
                    && ! $lockedOrder->participants()->where('quote_status', '!=', 'accepted')->exists()) {
                    $lockedOrder->forceFill([
                        'quote_status' => 'accepted',
                        'payment_status' => 'pending',
                    ])->save();
                    $lockedOrder->participants()->where('quote_status', 'accepted')->update([
                        'payment_status' => 'pending',
                    ]);
                } elseif (! $accepted) {
                    $lockedOrder->forceFill([
                        'quote_status' => 'rejected',
                        'payment_status' => 'awaiting_quote',
                        'unit_price' => null,
                        'total_amount' => null,
                    ])->save();
                    $lockedOrder->participants()->update([
                        'quote_status' => 'rejected',
                        'payment_amount' => null,
                        'payment_status' => 'awaiting_quote',
                        'payment_order_id' => null,
                        'snap_token' => null,
                        'snap_redirect_url' => null,
                    ]);
                } else {
                    $lockedOrder->forceFill([
                        'quote_status' => 'pending_buyer',
                        'payment_status' => 'awaiting_approval',
                    ])->save();
                }
            } else {
                $lockedOrder->forceFill([
                    'quote_status' => $accepted ? 'accepted' : 'rejected',
                    'payment_status' => $accepted ? 'pending' : 'awaiting_quote',
                    ...(! $accepted ? [
                        'unit_price' => null,
                        'total_amount' => null,
                        'payment_order_id' => null,
                        'snap_token' => null,
                        'snap_redirect_url' => null,
                    ] : []),
                ])->save();
            }

            $lockedOrder->statusHistory()->create([
                'user_id' => $user->id,
                'status' => $lockedOrder->status,
                'note' => $message,
            ]);
            $lockedOrder->messages()->create([
                'user_id' => $user->id,
                'message' => $message,
            ]);
        });

        return $this->show($request, $order->refresh());
    }

    public function checkout(Request $request, Order $order, MidtransSnapService $midtrans): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->role === 'umkm', 403);

        if ($order->type === Order::TYPE_DIRECT) {
            abort_unless($order->user_id === $user->id, 404);
        } else {
            abort_unless($order->participants()->where('user_id', $user->id)->exists(), 404);
        }

        $payment = DB::transaction(function () use ($order, $user): array {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($lockedOrder->status !== Order::STATUS_WAITING
                || $lockedOrder->unit_price === null
                || $lockedOrder->total_amount === null
                || $lockedOrder->total_amount < 1) {
                throw ValidationException::withMessages([
                    'payment' => 'Pesanan belum memiliki harga final atau sudah tidak menerima pembayaran.',
                ]);
            }

            if ($lockedOrder->type === Order::TYPE_DIRECT) {
                if ($lockedOrder->quote_status !== 'accepted') {
                    throw ValidationException::withMessages([
                        'payment' => 'Setujui penawaran final vendor sebelum melanjutkan pembayaran.',
                    ]);
                }

                if ($lockedOrder->payment_status === 'paid') {
                    throw ValidationException::withMessages(['payment' => 'Pesanan ini sudah dibayar.']);
                }

                if (! filled($lockedOrder->delivery_contact_name)
                    || ! filled($lockedOrder->delivery_contact_phone)
                    || ! filled($lockedOrder->delivery_address)
                    || ! filled($lockedOrder->delivery_area_id)) {
                    throw ValidationException::withMessages([
                        'payment' => 'Lengkapi biodata penerima dan lokasi pengiriman di tab Pesanan sebelum membayar.',
                    ]);
                }

                if (! $lockedOrder->shipping_service || $lockedOrder->shipping_amount === null) {
                    throw ValidationException::withMessages([
                        'payment' => 'Pilih layanan kurir dan hitung ongkir sebelum membayar.',
                    ]);
                }

                if (! in_array($lockedOrder->payment_status, ['pending', 'failed'], true)) {
                    throw ValidationException::withMessages(['payment' => 'Pembayaran pesanan belum tersedia.']);
                }

                if ($lockedOrder->payment_status === 'pending'
                    && is_string($lockedOrder->snap_token)
                    && $lockedOrder->snap_token !== '') {
                    return ['token' => $lockedOrder->snap_token];
                }

                $orderId = $lockedOrder->payment_status === 'failed' || ! $lockedOrder->payment_order_id
                    ? 'NAVORA-ORDER-'.Str::uuid()
                    : $lockedOrder->payment_order_id;

                $lockedOrder->forceFill([
                    'payment_order_id' => $orderId,
                    'payment_status' => 'pending',
                    'snap_token' => null,
                    'snap_redirect_url' => null,
                ])->save();

                return [
                    'order_id' => $orderId,
                    'amount' => (int) $lockedOrder->total_amount,
                ];
            }

            $participant = $lockedOrder->participants()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->quote_status !== 'accepted') {
                throw ValidationException::withMessages([
                    'payment' => 'Semua peserta harus menyetujui penawaran sebelum pembayaran dimulai.',
                ]);
            }

            if ($participant->payment_status === 'paid') {
                throw ValidationException::withMessages(['payment' => 'Bagian pesananmu sudah dibayar.']);
            }

            if (! filled($participant->delivery_contact_name)
                || ! filled($participant->delivery_contact_phone)
                || ! filled($participant->delivery_address)
                || ! filled($participant->delivery_area_id)) {
                throw ValidationException::withMessages([
                    'payment' => 'Lengkapi biodata penerima dan lokasi pengiriman bagianmu sebelum membayar.',
                ]);
            }

            if ($participant->payment_amount === null || $participant->payment_amount < 1) {
                throw ValidationException::withMessages(['payment' => 'Nominal pembayaran bagian pesanan tidak valid.']);
            }

            if (! $participant->shipping_service || $participant->shipping_amount === null) {
                throw ValidationException::withMessages([
                    'payment' => 'Pilih layanan kurir dan hitung ongkir bagianmu sebelum membayar.',
                ]);
            }

            if (! in_array($participant->payment_status, ['pending', 'failed'], true)) {
                throw ValidationException::withMessages(['payment' => 'Pembayaran bagian pesananmu belum tersedia.']);
            }

            if ($participant->payment_status === 'pending'
                && is_string($participant->snap_token)
                && $participant->snap_token !== '') {
                return ['token' => $participant->snap_token];
            }

            $orderId = $participant->payment_status === 'failed' || ! $participant->payment_order_id
                ? 'NAVORA-PART-'.Str::uuid()
                : $participant->payment_order_id;

            $participant->forceFill([
                'payment_order_id' => $orderId,
                'payment_status' => 'pending',
                'snap_token' => null,
                'snap_redirect_url' => null,
            ])->save();

            return [
                'order_id' => $orderId,
                'amount' => (int) $participant->payment_amount,
            ];
        });

        if (isset($payment['token'])) {
            return response()->json(['token' => $payment['token']]);
        }

        try {
            $checkout = $midtrans->createOrderTransaction(
                (string) $payment['order_id'],
                (int) $payment['amount'],
                $user,
            );
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            Log::warning('Midtrans order checkout could not be created.', [
                'order_id' => $order->id,
                'payment_order_id' => $payment['order_id'],
                'exception' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Checkout Midtrans belum berhasil dibuat. Silakan coba lagi.',
            ], 502);
        }

        if ($order->type === Order::TYPE_DIRECT) {
            $order->forceFill([
                'snap_token' => $checkout['token'],
                'snap_redirect_url' => $checkout['redirect_url'],
            ])->save();
        } else {
            $order->participants()
                ->where('user_id', $user->id)
                ->firstOrFail()
                ->forceFill([
                    'snap_token' => $checkout['token'],
                    'snap_redirect_url' => $checkout['redirect_url'],
                ])->save();
        }

        return response()->json(['token' => $checkout['token']]);
    }

    public function refreshPayment(
        Request $request,
        Order $order,
        MidtransSnapService $midtrans,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($this->canView($order, $user), 404);

        $participant = null;
        if ($order->type === Order::TYPE_GROUP) {
            $participant = $order->participants()->where('user_id', $user->id)->first();
            abort_unless($user->id === $order->vendor_user_id || $participant !== null, 404);
            abort_unless(is_string($participant?->payment_order_id) && $participant->payment_order_id !== '', 422);
            $paymentOrderId = $participant->payment_order_id;
            $expectedAmount = (int) $participant->payment_amount;
        } else {
            abort_unless($user->id === $order->user_id || $user->id === $order->vendor_user_id, 404);
            abort_unless(is_string($order->payment_order_id) && $order->payment_order_id !== '', 422);
            $paymentOrderId = $order->payment_order_id;
            $expectedAmount = (int) $order->total_amount;
        }

        try {
            $transaction = $midtrans->getTransactionStatus($paymentOrderId);
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            Log::warning('Midtrans order payment status could not be refreshed.', [
                'order_id' => $order->id,
                'payment_order_id' => $paymentOrderId,
                'exception' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Status Midtrans belum berhasil diperiksa. Coba lagi sebentar.',
            ], 502);
        }

        abort_unless(
            $transaction['order_id'] === $paymentOrderId
                && $this->amountMatches($transaction['gross_amount'], $expectedAmount),
            422,
        );

        DB::transaction(function () use ($order, $participant, $transaction): void {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            $transactionStatus = $transaction['transaction_status'];
            $details = array_filter([
                'midtrans_transaction_id' => $transaction['transaction_id'] ?? null,
                'payment_type' => $transaction['payment_type'] ?? null,
                'midtrans_transaction_status' => $transactionStatus,
            ], fn (?string $value): bool => $value !== null && $value !== '');
            $outcome = $this->paymentOutcome($transaction);

            if ($participant === null) {
                $lockedOrder->forceFill($details)->save();
                if ($outcome === 'paid' && ! $lockedOrder->paid_at) {
                    $lockedOrder->forceFill([
                        'payment_status' => 'paid',
                        'paid_at' => now(),
                    ])->save();
                    $lockedOrder->statusHistory()->create([
                        'user_id' => null,
                        'status' => $lockedOrder->status,
                        'note' => 'Pembayaran berhasil diverifikasi oleh Midtrans.',
                    ]);
                } elseif ($outcome === 'failed' && ! $lockedOrder->paid_at) {
                    $lockedOrder->forceFill(['payment_status' => 'failed'])->save();
                }

                return;
            }

            $lockedParticipant = $lockedOrder->participants()->lockForUpdate()->findOrFail($participant->id);
            $lockedParticipant->forceFill($details)->save();

            if ($outcome === 'paid' && ! $lockedParticipant->paid_at) {
                $lockedParticipant->forceFill([
                    'payment_status' => 'paid',
                    'paid_at' => now(),
                ])->save();

                $lockedOrder->statusHistory()->create([
                    'user_id' => null,
                    'status' => $lockedOrder->status,
                    'note' => 'Pembayaran peserta berhasil diverifikasi oleh Midtrans.',
                ]);

                if (! $lockedOrder->participants()->where('payment_status', '!=', 'paid')->exists()) {
                    $lockedOrder->forceFill([
                        'payment_status' => 'paid',
                        'paid_at' => now(),
                    ])->save();
                }
            } elseif ($outcome === 'failed' && ! $lockedParticipant->paid_at) {
                $lockedParticipant->forceFill(['payment_status' => 'failed'])->save();
            }
        });

        $order->refresh()->load([
            'creator:id,name',
            'vendor:id,name',
            'vendor.businessProfile:id,user_id,business_name,business_address,rajaongkir_area_id,rajaongkir_area_name',
            'participants.user:id,name',
            'statusHistory.actor:id,name',
        ]);
        $presented = $this->presentOrder($order, $user);

        return response()->json([
            'order' => $presented,
            'payment_status' => $presented['current_payment_status'],
        ]);
    }

    public function join(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->role === 'umkm', 403);
        abort_unless($this->canView($order, $user), 404);

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'between:1,10000000'],
            'delivery_contact_name' => ['required', 'string', 'max:255'],
            'delivery_contact_phone' => ['required', 'string', 'max:40', 'regex:/^[0-9+().\\s-]{8,40}$/'],
            'delivery_address' => ['required', 'string', 'max:1000'],
            'delivery_area_id' => ['required', 'string', 'max:32'],
            'delivery_area_name' => ['required', 'string', 'max:255'],
        ], [
            'quantity.required' => 'Jumlah yang ingin diambil wajib diisi.',
            'quantity.between' => 'Jumlah harus antara 1 dan 10.000.000 pcs.',
            'delivery_contact_name.required' => 'Nama penerima wajib diisi.',
            'delivery_contact_phone.required' => 'Nomor telepon penerima wajib diisi.',
            'delivery_contact_phone.regex' => 'Nomor telepon penerima tidak valid.',
            'delivery_address.required' => 'Alamat lengkap tujuan pengiriman wajib diisi.',
            'delivery_area_id.required' => 'Pilih area tujuan dari hasil pencarian lokasi.',
            'delivery_area_name.required' => 'Pilih area tujuan pengiriman.',
        ]);

        DB::transaction(function () use ($order, $user, $validated): void {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($lockedOrder->type !== Order::TYPE_GROUP
                || $lockedOrder->status !== Order::STATUS_WAITING
                || ! in_array($lockedOrder->payment_status, ['awaiting_quote', 'not_required'], true)) {
                throw ValidationException::withMessages([
                    'order' => 'Pesanan patungan ini sudah tidak menerima peserta.',
                ]);
            }

            if ($lockedOrder->participants()->where('user_id', $user->id)->exists()) {
                throw ValidationException::withMessages([
                    'order' => 'Kamu sudah bergabung dalam pesanan patungan ini.',
                ]);
            }

            $committedQuantity = (int) $lockedOrder->participants()->sum('quantity');
            $quantity = (int) $validated['quantity'];

            if ($quantity > $lockedOrder->quantity - $committedQuantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'Jumlah melebihi sisa kebutuhan patungan.',
                ]);
            }

            $lockedOrder->participants()->create([
                'user_id' => $user->id,
                'quantity' => $quantity,
                'delivery_contact_name' => $validated['delivery_contact_name'],
                'delivery_contact_phone' => $validated['delivery_contact_phone'],
                'delivery_address' => $validated['delivery_address'],
                'delivery_area_id' => $validated['delivery_area_id'],
                'delivery_area_name' => $validated['delivery_area_name'],
            ]);

            if ($committedQuantity + $quantity === $lockedOrder->quantity) {
                $lockedOrder->statusHistory()->create([
                    'user_id' => $user->id,
                    'status' => Order::STATUS_WAITING,
                    'note' => 'Target patungan terpenuhi; menunggu konfirmasi.',
                ]);
            }
        });

        return $this->show($request, $order->refresh());
    }

    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canView($order, $user), 404);

        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(Order::STATUS_LABELS))],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'status.required' => 'Pilih status pesanan yang baru.',
        ]);

        $startsSimulation = DB::transaction(function () use ($order, $user, $validated): bool {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            $allowedStatuses = $this->allowedTransitions($lockedOrder, $user);

            if (! in_array($validated['status'], $allowedStatuses, true)) {
                throw ValidationException::withMessages([
                    'status' => 'Perubahan status tidak valid atau kamu tidak memiliki akses untuk melakukannya.',
                ]);
            }

            $startsSimulation = $validated['status'] === Order::STATUS_PROCESSING
                && $lockedOrder->payment_status === 'not_required';
            $lockedOrder->update([
                'status' => $validated['status'],
                'is_simulated' => $startsSimulation,
            ]);
            $note = match (true) {
                $startsSimulation => 'Pesanan dikonfirmasi; simulasi status otomatis dimulai.',
                $validated['status'] === Order::STATUS_PROCESSING => 'Pesanan dikonfirmasi vendor setelah pembayaran diterima.',
                $validated['status'] === Order::STATUS_RECEIVED && $user->role === 'umkm' => 'UMKM mengonfirmasi pesanan telah diterima.',
                default => $validated['note'] ?? null,
            };
            $lockedOrder->statusHistory()->create([
                'user_id' => $user->id,
                'status' => $validated['status'],
                'note' => $note,
            ]);

            return $startsSimulation;
        });

        if ($startsSimulation) {
            AdvanceSimulatedOrder::dispatch($order->id, Order::STATUS_PROCESSING)
                ->delay(now()->addSeconds(20));
        }

        return $this->show($request, $order->refresh());
    }

    private function syncSimulatedTracking(Order $order): void
    {
        if (! $order->is_simulated || ! in_array($order->status, [
            Order::STATUS_PROCESSING,
            Order::STATUS_PRODUCTION,
            Order::STATUS_SHIPPED,
        ], true)) {
            return;
        }

        $latestHistory = $order->relationLoaded('statusHistory')
            ? $order->statusHistory->sortByDesc('id')->first()
            : $order->statusHistory()->latest('id')->first();

        if (! $latestHistory?->created_at
            || $latestHistory->created_at->copy()
                ->addSeconds(Order::SIMULATION_STAGE_DELAY_SECONDS)
                ->isFuture()) {
            return;
        }

        $advanced = false;

        DB::transaction(function () use ($order, &$advanced): void {
            $lockedOrder = Order::query()->lockForUpdate()->find($order->id);

            if (! $lockedOrder?->is_simulated) {
                return;
            }

            while (($nextStatus = $lockedOrder->nextSimulatedStatus()) !== null) {
                $latestHistory = $lockedOrder->statusHistory()->latest('id')->first();

                if (! $latestHistory?->created_at) {
                    return;
                }

                $transitionAt = $latestHistory->created_at->copy()
                    ->addSeconds(Order::SIMULATION_STAGE_DELAY_SECONDS);

                if ($transitionAt->isFuture()) {
                    return;
                }

                $lockedOrder->update(['status' => $nextStatus]);

                $history = $lockedOrder->statusHistory()->make([
                    'user_id' => null,
                    'status' => $nextStatus,
                    'note' => Order::simulatedStatusNote($nextStatus),
                ]);
                $history->created_at = $transitionAt;
                $history->updated_at = $transitionAt;
                $history->save();

                $advanced = true;
            }
        });

        if ($advanced) {
            $order->status = Order::query()->findOrFail($order->id)->status;
            $order->unsetRelation('statusHistory');
            $order->load('statusHistory.actor:id,name');

            if (in_array($order->status, [Order::STATUS_PRODUCTION, Order::STATUS_SHIPPED], true)) {
                AdvanceSimulatedOrder::dispatch($order->id, $order->status)
                    ->delay(now()->addSeconds(Order::SIMULATION_STAGE_DELAY_SECONDS));
            }
        }
    }

    private function isVerifiedVendor(int $userId): bool
    {
        return BusinessProfile::query()
            ->where('user_id', $userId)
            ->where('verification_status', 'approved')
            ->whereNotNull('completed_at')
            ->exists();
    }

    private function assertVendorRfqCapacity(int $vendorUserId): void
    {
        $hasActiveSubscription = VendorSubscription::query()
            ->where('vendor_user_id', $vendorUserId)
            ->where('payment_status', 'paid')
            ->where('ends_at', '>', now())
            ->exists();

        if ($hasActiveSubscription) {
            return;
        }

        $monthlyLimit = (int) config('services.navora.free_vendor_monthly_rfq_limit');
        $monthlyRequests = Order::query()
            ->where('vendor_user_id', $vendorUserId)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        if ($monthlyRequests >= $monthlyLimit) {
            throw ValidationException::withMessages([
                'vendor_user_id' => 'Kuota permintaan vendor gratis bulan ini sudah penuh. Vendor perlu mengaktifkan Premium untuk menerima permintaan tanpa batas.',
            ]);
        }
    }

    private function canView(Order $order, User $user): bool
    {
        if ($order->user_id === $user->id || $order->vendor_user_id === $user->id) {
            return true;
        }

        if ($order->type === Order::TYPE_GROUP && in_array($user->role, ['umkm', 'vendor'], true)) {
            return true;
        }

        return $order->participants()->where('user_id', $user->id)->exists();
    }

    private function amountMatches(mixed $grossAmount, int $expected): bool
    {
        $grossAmount = (string) $grossAmount;

        if (preg_match('/^\d+(?:\.\d+)?$/D', $grossAmount) !== 1) {
            return false;
        }

        [$wholeAmount, $fractionalAmount] = array_pad(explode('.', $grossAmount, 2), 2, '');

        return (int) $wholeAmount === $expected
            && trim($fractionalAmount, '0') === '';
    }

    /**
     * @param  array{transaction_status: string, fraud_status?: ?string}  $transaction
     */
    private function paymentOutcome(array $transaction): ?string
    {
        $transactionStatus = $transaction['transaction_status'];

        if ($transactionStatus === 'settlement'
            || ($transactionStatus === 'capture' && ($transaction['fraud_status'] ?? null) === 'accept')) {
            return 'paid';
        }

        if (in_array($transactionStatus, ['deny', 'cancel', 'expire', 'failure'], true)
            || ($transactionStatus === 'capture' && ($transaction['fraud_status'] ?? null) === 'deny')) {
            return 'failed';
        }

        return null;
    }

    private function hasPaymentAttempt(Order $order): bool
    {
        return $order->payment_order_id !== null
            || $order->participants->contains(
                fn ($participant): bool => $participant->payment_order_id !== null,
            );
    }

    /**
     * @return list<string>
     */
    private function allowedTransitions(Order $order, User $user): array
    {
        if ($order->is_simulated) {
            return [];
        }

        if ($order->user_id === $user->id && $user->role === 'umkm') {
            if ($order->status === Order::STATUS_SHIPPED
                && $order->payment_status === 'paid') {
                return [Order::STATUS_RECEIVED];
            }

            if ($order->payment_status === 'paid'
                || ($order->payment_status === 'pending' && $this->hasPaymentAttempt($order))) {
                return [];
            }

            return match ($order->status) {
                Order::STATUS_WAITING, Order::STATUS_PROCESSING, Order::STATUS_PRODUCTION => [Order::STATUS_CANCELLED],
                default => [],
            };
        }

        if ($order->vendor_user_id !== $user->id) {
            return [];
        }

        $transitions = match ($order->status) {
            Order::STATUS_WAITING => [Order::STATUS_PROCESSING, Order::STATUS_CANCELLED],
            Order::STATUS_PROCESSING => [Order::STATUS_PRODUCTION, Order::STATUS_CANCELLED],
            Order::STATUS_PRODUCTION => [Order::STATUS_SHIPPED, Order::STATUS_CANCELLED],
            default => [],
        };

        if (! in_array($order->payment_status, ['paid', 'not_required'], true)) {
            $transitions = array_values(array_filter(
                $transitions,
                fn (string $status): bool => $status !== Order::STATUS_PROCESSING,
            ));
        }

        if ($order->payment_status === 'paid'
            || ($order->payment_status === 'pending' && $this->hasPaymentAttempt($order))) {
            $transitions = array_values(array_filter(
                $transitions,
                fn (string $status): bool => $status !== Order::STATUS_CANCELLED,
            ));
        }

        return $transitions;
    }

    /**
     * @return array<string, mixed>
     */
    private function presentOrder(Order $order, User $user): array
    {
        $participantQuantity = (int) $order->participants->sum('quantity');
        $isBuyer = $order->user_id === $user->id;
        $isAssignedVendor = $order->vendor_user_id === $user->id;
        $currentParticipant = $order->participants->firstWhere('user_id', $user->id);
        $isGroupTargetMet = $order->type === Order::TYPE_GROUP
            && $participantQuantity === $order->quantity;
        $shippingService = $order->type === Order::TYPE_DIRECT
            ? $order->shipping_service
            : $currentParticipant?->shipping_service;
        $shippingAmount = $order->type === Order::TYPE_DIRECT
            ? $order->shipping_amount
            : $currentParticipant?->shipping_amount;
        $currentPaymentStatus = $order->type === Order::TYPE_DIRECT
            ? $order->payment_status
            : $currentParticipant?->payment_status;
        $deliveryContactName = $order->type === Order::TYPE_DIRECT
            ? $order->delivery_contact_name
            : $currentParticipant?->delivery_contact_name;
        $deliveryContactPhone = $order->type === Order::TYPE_DIRECT
            ? $order->delivery_contact_phone
            : $currentParticipant?->delivery_contact_phone;
        $deliveryAddress = $order->type === Order::TYPE_DIRECT
            ? $order->delivery_address
            : $currentParticipant?->delivery_address;
        $deliveryAreaId = $order->type === Order::TYPE_DIRECT
            ? $order->delivery_area_id
            : $currentParticipant?->delivery_area_id;
        $deliveryDetailsComplete = filled($deliveryContactName)
            && filled($deliveryContactPhone)
            && filled($deliveryAddress)
            && filled($deliveryAreaId);
        $vendorOriginConfigured = filled($order->vendor?->businessProfile?->business_address)
            && filled($order->vendor?->businessProfile?->rajaongkir_area_id);
        $canSelectShipping = $user->role === 'umkm'
            && $order->status === Order::STATUS_WAITING
            && $order->quote_status === 'accepted'
            && in_array($currentPaymentStatus, ['pending', 'failed'], true)
            && $deliveryDetailsComplete
            && $vendorOriginConfigured;
        $canEditDeliveryDetails = $user->role === 'umkm'
            && ($order->type === Order::TYPE_DIRECT
                ? $isBuyer
                : $currentParticipant !== null)
            && $order->status === Order::STATUS_WAITING
            && $currentPaymentStatus !== 'paid'
            && ($order->type === Order::TYPE_DIRECT
                ? ! ($order->payment_status === 'pending' && filled($order->payment_order_id))
                : ! ($currentParticipant?->payment_status === 'pending'
                    && filled($currentParticipant?->payment_order_id)));

        return [
            'id' => $order->id,
            'type' => $order->type,
            'vendor_user_id' => $order->vendor_user_id,
            'is_quote_request' => $order->type === Order::TYPE_DIRECT
                && $order->vendor_user_id !== null
                && $order->payment_status !== 'not_required',
            'type_label' => Order::TYPE_LABELS[$order->type] ?? 'Jenis pesanan tidak dikenal',
            'product_name' => $order->product_name,
            'quantity' => $order->quantity,
            'packaging_details' => $order->packaging_details,
            'target_unit_price' => $order->target_unit_price,
            'delivery_contact_name' => $isBuyer || $isAssignedVendor
                ? $deliveryContactName
                : null,
            'delivery_contact_phone' => $isBuyer || $isAssignedVendor
                ? $deliveryContactPhone
                : null,
            'delivery_area_id' => ($isBuyer || $isAssignedVendor)
                ? $deliveryAreaId
                : null,
            'delivery_address' => $order->type === Order::TYPE_DIRECT && ($isBuyer || $isAssignedVendor)
                ? $order->delivery_address
                : ($currentParticipant && ($isBuyer || $isAssignedVendor)
                    ? $currentParticipant->delivery_address
                    : null),
            'delivery_area_name' => $order->type === Order::TYPE_DIRECT
                ? $order->delivery_area_name
                : $currentParticipant?->delivery_area_name,
            'notes' => $order->notes,
            'status' => $order->status,
            'status_label' => Order::STATUS_LABELS[$order->status] ?? 'Status tidak dikenal',
            'is_simulated' => $order->is_simulated,
            'unit_price' => $order->unit_price,
            'total_amount' => $order->total_amount,
            'subtotal_amount' => $order->type === Order::TYPE_DIRECT
                ? $order->subtotal_amount
                : $currentParticipant?->subtotal_amount,
            'shipping_amount' => $shippingAmount,
            'platform_fee_amount' => $order->type === Order::TYPE_DIRECT
                ? $order->platform_fee_amount
                : $currentParticipant?->platform_fee_amount,
            'shipping_service' => $shippingService,
            'shipping_courier' => $order->type === Order::TYPE_DIRECT
                ? $order->shipping_courier
                : $currentParticipant?->shipping_courier,
            'payment_status' => $order->payment_status,
            'quote_status' => $order->quote_status,
            'payment_receipt' => ($isBuyer || $isAssignedVendor) && $order->payment_status === 'paid'
                ? [
                    'transaction_id' => $order->midtrans_transaction_id,
                    'payment_order_id' => $order->payment_order_id,
                    'payment_type' => $order->payment_type,
                    'transaction_status' => $order->midtrans_transaction_status,
                    'paid_at' => $order->paid_at?->toIso8601String(),
                ]
                : null,
            'payment_amount' => $order->type === Order::TYPE_DIRECT
                ? $order->total_amount
                : $currentParticipant?->payment_amount,
            'vendor_origin_configured' => $vendorOriginConfigured,
            'delivery_details_complete' => $deliveryDetailsComplete,
            'can_edit_delivery_details' => $canEditDeliveryDetails,
            'shipping_block_reason' => $user->role === 'umkm'
                && $order->status === Order::STATUS_WAITING
                && $order->quote_status === 'accepted'
                && in_array($currentPaymentStatus, ['pending', 'failed'], true)
                    ? (! $vendorOriginConfigured
                        ? 'Vendor perlu melengkapi alamat lengkap dan area asal pengiriman di Profil usaha sebelum ongkir dapat dihitung.'
                        : (! $deliveryDetailsComplete
                            ? 'Lengkapi biodata penerima dan lokasi pengiriman di tab Pesanan sebelum memilih kurir.'
                            : null))
                    : null,
            'can_select_shipping' => $canSelectShipping,
            'can_set_price' => $isAssignedVendor
                && $order->status === Order::STATUS_WAITING
                && ! $order->is_simulated
                && $order->payment_status === 'awaiting_quote'
                && ($order->type === Order::TYPE_GROUP
                    ? $isGroupTargetMet
                    : in_array($order->quote_status, ['awaiting_vendor', 'rejected'], true)),
            'can_decide_quote' => $user->role === 'umkm'
                && ($order->type === Order::TYPE_DIRECT
                    ? $isBuyer && $order->quote_status === 'pending_buyer'
                    : $currentParticipant?->quote_status === 'pending_buyer'),
            'can_pay' => $user->role === 'umkm'
                && $order->status === Order::STATUS_WAITING
                && filled($shippingService)
                && $shippingAmount !== null
                && ($order->type === Order::TYPE_DIRECT
                    ? $isBuyer && in_array($order->payment_status, ['pending', 'failed'], true)
                    : $currentParticipant !== null
                        && in_array($currentParticipant->payment_status, ['pending', 'failed'], true)),
            'current_payment_status' => $order->type === Order::TYPE_DIRECT
                ? $order->payment_status
                : $currentParticipant?->payment_status,
            'created_at' => $order->created_at?->toIso8601String(),
            'creator_name' => $order->type === Order::TYPE_GROUP && ! $isBuyer && ! $isAssignedVendor
                ? 'Penyelenggara'
                : ($order->creator?->name ?? 'Pengguna'),
            'vendor_name' => $order->vendor?->businessProfile?->business_name ?: $order->vendor?->name,
            'participants' => $order->participants->values()->map(fn ($participant, int $index): array => [
                'name' => $isBuyer || $isAssignedVendor
                    ? ($participant->user?->name ?? 'Pengguna')
                    : ($participant->user_id === $user->id ? 'Kamu' : 'Peserta '.($index + 1)),
                'quantity' => $participant->quantity,
                'payment_status' => $isBuyer || $isAssignedVendor || $participant->user_id === $user->id
                    ? $participant->payment_status
                    : null,
                'payment_amount' => $isBuyer || $isAssignedVendor || $participant->user_id === $user->id
                    ? $participant->payment_amount
                    : null,
                'subtotal_amount' => $isBuyer || $isAssignedVendor || $participant->user_id === $user->id
                    ? $participant->subtotal_amount
                    : null,
                'shipping_amount' => $isBuyer || $isAssignedVendor || $participant->user_id === $user->id
                    ? $participant->shipping_amount
                    : null,
                'platform_fee_amount' => $isBuyer || $isAssignedVendor || $participant->user_id === $user->id
                    ? $participant->platform_fee_amount
                    : null,
                'delivery_area_name' => $isBuyer || $isAssignedVendor || $participant->user_id === $user->id
                    ? $participant->delivery_area_name
                    : null,
                'delivery_contact_name' => $isBuyer || $isAssignedVendor || $participant->user_id === $user->id
                    ? $participant->delivery_contact_name
                    : null,
                'delivery_contact_phone' => $isBuyer || $isAssignedVendor || $participant->user_id === $user->id
                    ? $participant->delivery_contact_phone
                    : null,
                'delivery_address' => $isBuyer || $isAssignedVendor || $participant->user_id === $user->id
                    ? $participant->delivery_address
                    : null,
                'payment_receipt' => ($isBuyer || $isAssignedVendor || $participant->user_id === $user->id)
                    && $participant->payment_status === 'paid'
                    ? [
                        'transaction_id' => $participant->midtrans_transaction_id,
                        'payment_order_id' => $participant->payment_order_id,
                        'payment_type' => $participant->payment_type,
                        'transaction_status' => $participant->midtrans_transaction_status,
                        'paid_at' => $participant->paid_at?->toIso8601String(),
                    ]
                    : null,
            ])->values(),
            'committed_quantity' => $participantQuantity,
            'remaining_quantity' => max(0, $order->quantity - $participantQuantity),
            'can_join' => $user->role === 'umkm'
                && $order->type === Order::TYPE_GROUP
                && $order->status === Order::STATUS_WAITING
                && in_array($order->payment_status, ['awaiting_quote', 'not_required'], true)
                && $participantQuantity < $order->quantity
                && ! $order->participants->contains('user_id', $user->id),
            'can_refresh_payment' => $order->type === Order::TYPE_DIRECT
                ? ($isBuyer || $isAssignedVendor)
                    && $order->payment_status === 'pending'
                    && filled($order->payment_order_id)
                : $currentParticipant !== null
                    && $currentParticipant->payment_status === 'pending'
                    && filled($currentParticipant->payment_order_id),
            'allowed_transitions' => collect($this->allowedTransitions($order, $user))
                ->map(fn (string $status): array => [
                    'status' => $status,
                    'label' => Order::STATUS_LABELS[$status] ?? 'Status tidak dikenal',
                ])
                ->values(),
            'history' => $order->statusHistory
                ->sortBy('id')
                ->values()
                ->map(fn ($history): array => [
                    'status' => $history->status,
                    'status_label' => Order::STATUS_LABELS[$history->status] ?? 'Status tidak dikenal',
                    'note' => $history->note,
                    'actor_name' => $order->is_simulated && $history->user_id === null
                        ? 'Simulasi otomatis'
                        : ($order->type === Order::TYPE_GROUP && ! $isBuyer && ! $isAssignedVendor
                            ? ($history->user_id === $user->id ? 'Kamu' : ($history->user_id === $order->vendor_user_id ? 'Vendor' : 'Peserta'))
                            : ($history->actor?->name ?? 'Sistem')),
                    'created_at' => $history->created_at?->toIso8601String(),
                ]),
        ];
    }
}
