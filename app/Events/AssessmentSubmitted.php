<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AssessmentSubmitted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(
        public readonly string $student_id,
        public readonly string $sub_institute_id,
        public readonly string $assessmentId,
        public readonly string $conceptId,
        public readonly bool   $isCorrect,
        public readonly float  $pKnow,
        public readonly int    $timeTaken,
        public readonly float  $confidence,
        public readonly array  $kasbaDimensions = [],
        public readonly array  $metadata = [])
    {
        
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}
