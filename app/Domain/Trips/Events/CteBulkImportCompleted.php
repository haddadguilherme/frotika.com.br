<?php

declare(strict_types=1);

namespace App\Domain\Trips\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Avisa, em tempo real, o usuário que disparou a importação de que o lote
 * terminou de processar. Transmitido no canal privado do próprio usuário
 * (App.Models.User.{id}). Carrega só escalares — nunca um model Eloquent — para
 * não depender do TenantContext ao serializar/reidratar na fila de broadcast.
 */
final class CteBulkImportCompleted implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public readonly int $userId,
        public readonly string $uuid,
        public readonly int $total,
        public readonly int $imported,
        public readonly int $failed,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'cte-import.completed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'uuid' => $this->uuid,
            'total' => $this->total,
            'imported' => $this->imported,
            'failed' => $this->failed,
            'url' => route('cte.import.result', ['batch' => $this->uuid]),
        ];
    }
}
