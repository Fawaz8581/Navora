<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OrderMessageController extends Controller
{
    public function index(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        $this->authorizeConversation($order, $user);

        $messages = $order->messages()
            ->with('sender:id,name')
            ->latest('id')
            ->limit(100)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (OrderMessage $message): array => [
                'id' => $message->id,
                'message' => $message->message,
                'image_url' => $message->image_path ? route('orders.messages.image', [$order, $message]) : null,
                'sender_name' => $message->sender?->name ?? 'Pengguna',
                'is_mine' => $message->user_id === $user->id,
                'created_at' => $message->created_at?->toIso8601String(),
            ]);

        return response()->json(['messages' => $messages]);
    }

    public function store(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        $this->authorizeConversation($order, $user);

        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:2000', 'required_without:image'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120', 'required_without:message'],
        ], [
            'message.required_without' => 'Pesan atau gambar harus diisi.',
            'message.max' => 'Pesan maksimal 2.000 karakter.',
            'image.image' => 'File harus berupa gambar.',
            'image.mimes' => 'Format gambar harus JPEG, PNG, atau WebP.',
            'image.max' => 'Ukuran gambar maksimal 5MB.',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store("order-messages/{$order->id}", 'local');
        }

        $message = $order->messages()->create([
            'user_id' => $user->id,
            'message' => trim((string) ($validated['message'] ?? '')),
            'image_path' => $imagePath,
        ]);
        $message->load('sender:id,name');

        return response()->json([
            'message' => [
                'id' => $message->id,
                'message' => $message->message,
                'image_url' => $message->image_path ? route('orders.messages.image', [$order, $message]) : null,
                'sender_name' => $message->sender?->name ?? 'Pengguna',
                'is_mine' => true,
                'created_at' => $message->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function image(Request $request, Order $order, OrderMessage $message): BinaryFileResponse
    {
        $user = $request->user();
        $this->authorizeConversation($order, $user);

        abort_unless($message->order_id === $order->id && $message->image_path, 404);
        abort_unless(Storage::disk('local')->exists($message->image_path), 404);

        return response()->file(Storage::disk('local')->path($message->image_path));
    }

    private function authorizeConversation(Order $order, User $user): void
    {
        abort_unless(
            $order->type === Order::TYPE_DIRECT
                && $order->vendor_user_id !== null
                && (($user->role === 'umkm' && $order->user_id === $user->id)
                    || ($user->role === 'vendor' && $order->vendor_user_id === $user->id)),
            404,
        );
    }
}
