<?php
declare(strict_types=1);

namespace App\Events;

use App\Models\ConsultationMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class NewConsultationMessage implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $id;
    public string $consultationId;
    public string $senderId;
    public string $senderName;
    public string $senderRole;
    public string $content;
    public string $createdAt;

    public function __construct(ConsultationMessage $message)
    {
        $message->loadMissing('sender');

        $this->id             = $message->id;
        $this->consultationId = $message->consultation_id;
        $this->senderId       = $message->sender_id;
        $this->senderName     = $message->sender?->name ?? 'Usuario';
        $this->senderRole     = $message->sender?->role ?? 'unknown';
        $this->content        = $message->content;
        $this->createdAt      = $message->created_at?->toIso8601String() ?? now()->toIso8601String();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('consultation.' . $this->consultationId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'consultation.message.created';
    }
}
