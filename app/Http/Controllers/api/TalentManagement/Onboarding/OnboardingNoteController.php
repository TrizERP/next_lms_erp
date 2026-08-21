<?php

namespace App\Http\Controllers\api\TalentManagement\Onboarding;

use App\Http\Controllers\Controller;
use App\Http\Controllers\api\TalentManagement\Onboarding\Concerns\ResolvesOnboardingContext;
use App\Models\TalentManagement\TalentOnboardingJourney;
use App\Models\TalentManagement\TalentOnboardingNote;
use Illuminate\Http\Request;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Onboarding\OnboardingNoteController`.
 *
 * The sidebar Notes card ("Add Note" and its empty state).
 *
 * talent_onboarding_journeys.notes is a single TEXT column and cannot record who
 * wrote what and when, which is exactly what this card shows.
 */
class OnboardingNoteController extends Controller
{
    use ResolvesOnboardingContext;

    /** GET /api/onboarding/journeys/{journeyId}/notes */
    public function index(Request $request, $journeyId)
    {
        $context = $this->onboardingContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $journey = $this->findJourney($tenant, $journeyId);
        if (!$journey instanceof TalentOnboardingJourney) {
            return $journey;
        }

        $visibility = $this->activeOnbFilter($request->input('visibility'));

        $notes = TalentOnboardingNote::where('sub_institute_id', $tenant)
            ->where('journey_id', $journey->id)
            ->when($visibility, fn ($q) => $q->where('visibility', $visibility))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return $this->onboardingResponse($notes->map(fn ($note) => $this->presentNote($note))->all());
    }

    /** POST /api/onboarding/journeys/{journeyId}/notes */
    public function store(Request $request, $journeyId)
    {
        $context = $this->onboardingContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $journey = $this->findJourney($tenant, $journeyId);
        if (!$journey instanceof TalentOnboardingJourney) {
            return $journey;
        }

        $validated = $request->validate([
            'note'       => 'required|string',
            'visibility' => 'nullable|in:internal,shared',
        ]);

        $note = TalentOnboardingNote::create([
            'sub_institute_id' => $tenant,
            'journey_id'       => (int) $journey->id,
            'note'             => $validated['note'],
            'visibility'       => $validated['visibility'] ?? 'internal',
            // Snapshotted so the card keeps its author even if the user is removed.
            'author_name'      => $this->resolveOnbActorName($context['user_id']),
            'created_by'       => $context['user_id'],
            'updated_by'       => $context['user_id'],
        ]);

        $this->logOnboardingActivity(
            $tenant,
            $context['user_id'],
            'created_note',
            'added a note',
            'note',
            $note->id,
            mb_substr($note->note, 0, 60),
            null,
            (int) $journey->id
        );

        return $this->onboardingResponse($this->presentNote($note), 'Note added', 201);
    }

    /** PUT /api/onboarding/notes/{id} */
    public function update(Request $request, $id)
    {
        $context = $this->onboardingContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $note = TalentOnboardingNote::where('sub_institute_id', $tenant)->find($id);

        if (!$note) {
            return $this->onboardingError('Note not found', 404);
        }

        $validated = $request->validate([
            'note'       => 'sometimes|required|string',
            'visibility' => 'nullable|in:internal,shared',
        ]);

        $changes = $this->diffOnboardingChanges($note->getOriginal(), $validated, [
            'note'       => 'Note',
            'visibility' => 'Visibility',
        ]);

        $note->fill($validated);
        $note->updated_by = $context['user_id'];
        $note->save();

        if (!empty($changes)) {
            $this->logOnboardingActivity(
                $tenant,
                $context['user_id'],
                'updated_note',
                'edited a note',
                'note',
                $note->id,
                mb_substr($note->note, 0, 60),
                $changes,
                (int) $note->journey_id
            );
        }

        return $this->onboardingResponse($this->presentNote($note), 'Note updated');
    }

    /** DELETE /api/onboarding/notes/{id} */
    public function destroy(Request $request, $id)
    {
        $context = $this->onboardingContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $note = TalentOnboardingNote::where('sub_institute_id', $tenant)->find($id);

        if (!$note) {
            return $this->onboardingError('Note not found', 404);
        }

        $journeyId = (int) $note->journey_id;
        $excerpt = mb_substr($note->note, 0, 60);

        $note->deleted_by = $context['user_id'];
        $note->save();
        $note->delete();

        $this->logOnboardingActivity(
            $tenant,
            $context['user_id'],
            'deleted_note',
            'deleted a note',
            'note',
            (int) $id,
            $excerpt,
            null,
            $journeyId
        );

        return $this->onboardingResponse(['id' => (int) $id], 'Note deleted');
    }

    private function presentNote(TalentOnboardingNote $note): array
    {
        return [
            'id'          => (int) $note->id,
            'journey_id'  => (int) $note->journey_id,
            'note'        => $note->note,
            'visibility'  => $note->visibility,
            'author_name' => $note->author_name,
            'author_id'   => $note->created_by ? (int) $note->created_by : null,
            'initials'    => $this->onbInitialsOf($note->author_name),
            'created_at'  => optional($note->created_at)->toDateTimeString(),
            'created_label' => $this->onbDateTimeLabel($note->created_at),
        ];
    }
}
