<?php

namespace App\Http\Controllers;

use App\Jobs\AdvanceSimulatedOrder;
use App\Models\BusinessProfile;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['umkm', 'vendor'], true), 403);

        $orders = Order::query()
            ->where(function (Builder $query) use ($user): void {
                $query
                    ->where('user_id', $user->id)
                    ->orWhere('vendor_user_id', $user->id)
                    ->orWhereHas('participants', fn (Builder $participants) => $participants->where('user_id', $user->id))
                    ->orWhere('type', Order::TYPE_GROUP);
            })
            ->with([
                'creator:id,name',
                'vendor:id,name',
                'vendor.businessProfile:id,user_id,business_name',
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
                ->with('businessProfile:id,user_id,business_name')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $vendor): array => [
                    'id' => $vendor->id,
                    'name' => $vendor->businessProfile?->business_name ?: $vendor->name,
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

    public function show(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canView($order, $user), 404);

        $this->syncSimulatedTracking($order);

        $order->load([
            'creator:id,name',
            'vendor:id,name',
            'vendor.businessProfile:id,user_id,business_name',
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
            'delivery_address' => ['required_if:type,'.Order::TYPE_DIRECT, 'nullable', 'string', 'max:1000'],
            'vendor_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', 'vendor')],
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
            'delivery_address.required_if' => 'Alamat pengiriman wajib diisi untuk pesanan langsung.',
            'initial_quantity.required' => 'Jumlah awal patungan wajib diisi.',
            'initial_quantity.min' => 'Jumlah awal patungan minimal 1 pcs.',
        ]);

        if (isset($validated['vendor_user_id']) && ! $this->isVerifiedVendor((int) $validated['vendor_user_id'])) {
            throw ValidationException::withMessages([
                'vendor_user_id' => 'Pilih vendor yang sudah menyelesaikan profil dan verifikasi.',
            ]);
        }

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
                'delivery_address' => $validated['type'] === Order::TYPE_DIRECT
                    ? ($validated['delivery_address'] ?? null)
                    : null,
                'notes' => $validated['notes'] ?? null,
                'status' => Order::STATUS_WAITING,
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
                ]);
            }

            return $order;
        });

        $order->load(['creator:id,name', 'vendor:id,name', 'vendor.businessProfile:id,user_id,business_name', 'participants.user:id,name', 'statusHistory.actor:id,name']);

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
            'delivery_address' => ['required', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'vendor_user_id.required' => 'Pilih vendor yang menerima permintaan.',
            'product_name.required' => 'Nama produk wajib diisi.',
            'quantity.required' => 'Jumlah pesanan wajib diisi.',
            'quantity.between' => 'Jumlah pesanan harus antara 1 dan 10.000.000 pcs.',
            'packaging_details.required' => 'Spesifikasi kemasan wajib diisi.',
            'delivery_address.required' => 'Alamat pengiriman wajib diisi.',
        ]);

        if (! $this->isVerifiedVendor((int) $validated['vendor_user_id'])) {
            throw ValidationException::withMessages([
                'vendor_user_id' => 'Vendor tidak lagi memiliki profil yang terverifikasi.',
            ]);
        }

        $order = DB::transaction(function () use ($validated, $user): Order {
            $order = Order::create([
                'user_id' => $user->id,
                'vendor_user_id' => $validated['vendor_user_id'],
                'type' => Order::TYPE_DIRECT,
                'product_name' => $validated['product_name'],
                'quantity' => $validated['quantity'],
                'packaging_details' => $validated['packaging_details'],
                'delivery_address' => $validated['delivery_address'],
                'notes' => $validated['notes'] ?? null,
                'status' => Order::STATUS_PROCESSING,
                'is_simulated' => true,
            ]);

            $order->statusHistory()->createMany([
                [
                    'user_id' => $user->id,
                    'status' => Order::STATUS_WAITING,
                    'note' => 'Permintaan penawaran dari katalog dicatat.',
                ],
                [
                    'user_id' => null,
                    'status' => Order::STATUS_PROCESSING,
                    'note' => 'Simulasi otomatis dimulai; status berikutnya bukan konfirmasi produksi nyata.',
                ],
            ]);

            return $order;
        });

        AdvanceSimulatedOrder::dispatch($order->id, Order::STATUS_PROCESSING)
            ->delay(now()->addSeconds(20));

        $order->load(['creator:id,name', 'vendor:id,name', 'vendor.businessProfile:id,user_id,business_name', 'participants.user:id,name', 'statusHistory.actor:id,name']);

        return response()->json(['order' => $this->presentOrder($order, $user)], 201);
    }

    public function join(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->role === 'umkm', 403);
        abort_unless($this->canView($order, $user), 404);

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'between:1,10000000'],
        ], [
            'quantity.required' => 'Jumlah yang ingin diambil wajib diisi.',
            'quantity.between' => 'Jumlah harus antara 1 dan 10.000.000 pcs.',
        ]);

        DB::transaction(function () use ($order, $user, $validated): void {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($lockedOrder->type !== Order::TYPE_GROUP || $lockedOrder->status !== Order::STATUS_WAITING) {
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

            $startsSimulation = $validated['status'] === Order::STATUS_PROCESSING;
            $lockedOrder->update([
                'status' => $validated['status'],
                'is_simulated' => $startsSimulation,
            ]);
            $lockedOrder->statusHistory()->create([
                'user_id' => $user->id,
                'status' => $validated['status'],
                'note' => $startsSimulation
                    ? 'Pesanan dikonfirmasi; simulasi status otomatis dimulai.'
                    : ($validated['note'] ?? null),
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

    /**
     * @return list<string>
     */
    private function allowedTransitions(Order $order, User $user): array
    {
        if ($order->is_simulated) {
            return [];
        }

        if ($order->user_id === $user->id) {
            return match ($order->status) {
                Order::STATUS_WAITING, Order::STATUS_PROCESSING, Order::STATUS_PRODUCTION => [Order::STATUS_CANCELLED],
                default => [],
            };
        }

        if ($order->vendor_user_id !== $user->id) {
            return [];
        }

        return match ($order->status) {
            Order::STATUS_WAITING => [Order::STATUS_PROCESSING, Order::STATUS_CANCELLED],
            Order::STATUS_PROCESSING => [Order::STATUS_PRODUCTION, Order::STATUS_CANCELLED],
            Order::STATUS_PRODUCTION => [Order::STATUS_SHIPPED, Order::STATUS_CANCELLED],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function presentOrder(Order $order, User $user): array
    {
        $participantQuantity = (int) $order->participants->sum('quantity');
        $isBuyer = $order->user_id === $user->id;
        $isAssignedVendor = $order->vendor_user_id === $user->id;

        return [
            'id' => $order->id,
            'type' => $order->type,
            'type_label' => Order::TYPE_LABELS[$order->type] ?? 'Jenis pesanan tidak dikenal',
            'product_name' => $order->product_name,
            'quantity' => $order->quantity,
            'packaging_details' => $order->packaging_details,
            'delivery_address' => $order->type === Order::TYPE_DIRECT && ($isBuyer || $isAssignedVendor)
                ? $order->delivery_address
                : null,
            'notes' => $order->notes,
            'status' => $order->status,
            'status_label' => Order::STATUS_LABELS[$order->status] ?? 'Status tidak dikenal',
            'is_simulated' => $order->is_simulated,
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
            ])->values(),
            'committed_quantity' => $participantQuantity,
            'remaining_quantity' => max(0, $order->quantity - $participantQuantity),
            'can_join' => $user->role === 'umkm'
                && $order->type === Order::TYPE_GROUP
                && $order->status === Order::STATUS_WAITING
                && $participantQuantity < $order->quantity
                && ! $order->participants->contains('user_id', $user->id),
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
