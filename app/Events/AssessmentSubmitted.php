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
// Inside AssessmentService.php

private function dispatchAssessmentEvent(array $assessmentData, array $processedResults): void
{
    // Calculate probability of knowing (pKnow)
    $pKnow = $processedResults['percentage'] / 100;
    
    // 🎯 THIS IS WHERE THE EVENT IS CREATED AND DISPATCHED!
    event(new AssessmentSubmitted(
        student_id: $assessmentData['student_id'],
        sub_institute_id: $assessmentData['sub_institute_id'] ?? '',
        assessmentId: (string)$assessmentData['question_paper_id'],
        conceptId: $assessmentData['concept_id'] ?? 'general',
        isCorrect: $processedResults['percentage'] >= 60,
        pKnow: $pKnow,
        timeTaken: $assessmentData['time_taken'] ?? 0,
        confidence: $assessmentData['confidence'] ?? 0.8,
        kasbaDimensions: $assessmentData['kasba_dimensions'] ?? [],
        metadata: [
            'total_questions' => $processedResults['total_questions'],
            'correct_answers' => $processedResults['total_right'],
            'wrong_answers' => $processedResults['total_wrong'],
            'obtain_marks' => $processedResults['obtain_marks'],
            'mastery_level' => $processedResults['percentage']
        ]
    ));
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
