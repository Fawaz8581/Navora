<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'message' => ['required', 'string', 'max:2000'],
        ], [
            'message.required' => 'Pesan tidak boleh kosong.',
            'message.max' => 'Pesan maksimal 2.000 karakter.',
        ]);

        $message = $order->messages()->create([
            'user_id' => $user->id,
            'message' => trim($validated['message']),
        ]);
        $message->load('sender:id,name');

        return response()->json([
            'message' => [
                'id' => $message->id,
                'message' => $message->message,
                'sender_name' => $message->sender?->name ?? 'Pengguna',
                'is_mine' => true,
                'created_at' => $message->created_at?->toIso8601String(),
            ],
        ], 201);
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
