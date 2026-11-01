<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class AdvanceSimulatedOrder implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $orderId, public string $expectedStatus) {}

    public function handle(): void
    {
        $nextStatus = DB::transaction(function (): ?string {
            $order = Order::query()->lockForUpdate()->find($this->orderId);

            if (! $order?->is_simulated || $order->status !== $this->expectedStatus) {
                return null;
            }

            $nextStatus = $order->nextSimulatedStatus();

            if ($nextStatus === null) {
                return null;
            }

            $order->update(['status' => $nextStatus]);
            $order->statusHistory()->create([
                'user_id' => null,
                'status' => $nextStatus,
                'note' => Order::simulatedStatusNote($nextStatus),
            ]);

            return $nextStatus;
        });

        if (in_array($nextStatus, [Order::STATUS_PRODUCTION, Order::STATUS_SHIPPED], true)) {
            self::dispatch($this->orderId, $nextStatus)
                ->delay(now()->addSeconds(Order::SIMULATION_STAGE_DELAY_SECONDS));
        }
    }
}
