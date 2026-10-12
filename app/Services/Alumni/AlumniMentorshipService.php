<?php

namespace App\Services\Alumni;

use Illuminate\Support\Facades\DB;

/**
 * Mentorship workflow rules (spec section 23):
 *   Student request -> Mentor matching -> Alumni acceptance -> Mentoring session -> Feedback -> Engagement record
 *
 * Request status: open -> matched -> accepted -> completed ; cancelled before completion. A mentor who declines sends
 * the request back to open (remembered in declined_mentor_ids so they are not suggested again).
 *
 * MATCHING IS SIMPLE AND EXPLAINABLE, NOT AI: words in the request's career area (weighted double) and guidance text
 * are compared with the mentor's expertise, industry, role and topics. Each suggestion lists the words that matched, so
 * a coordinator can see why. A mentor appears only if they are an active mentor, a verified/active alumnus, under their
 * stated capacity, and have not already declined this request. The final choice is always a person's.
 */
class AlumniMentorshipService
{
    /** Statuses that occupy one of a mentor's capacity slots. */
    public const OCCUPYING = ['matched', 'accepted'];

    private const STOP = ['the', 'and', 'for', 'with', 'about', 'from', 'that', 'this', 'want', 'need', 'help', 'guidance', 'career', 'advice', 'into', 'how', 'get', 'would', 'like'];

    public function __construct(private AlumniService $alumni)
    {
    }

    public function load(int $tenant, int $mentorAlumniId): int
    {
        return DB::table('alumni_mentorship_requests')->where('sub_institute_id', $tenant)
            ->where('mentor_alumni_id', $mentorAlumniId)->whereIn('status', self::OCCUPYING)->count();
    }

    /** @return array<int, array<string, mixed>> ranked suggestions, best first */
    public function suggest(int $tenant, object $request, int $limit = 10): array
    {
        $declined = array_filter(array_map('intval', explode(',', (string) $request->declined_mentor_ids)));
        $areaTokens = $this->tokens((string) $request->career_area);
        $textTokens = array_diff($this->tokens((string) $request->guidance_needed), $areaTokens);

        $mentors = DB::table('alumni_mentor_profiles as m')
            ->join('alumni as a', function ($j) {
                $j->on('a.id', '=', 'm.alumni_id')->on('a.sub_institute_id', '=', 'm.sub_institute_id');
            })
            ->where('m.sub_institute_id', $tenant)->where('m.status', 'active')->whereIn('a.status', ['verified', 'active'])
            ->get(['m.*', 'a.full_name', 'a.batch', 'a.alumni_code']);

        $out = [];
        foreach ($mentors as $m) {
            if (in_array((int) $m->alumni_id, $declined, true)) {
                continue;
            }
            $load = $this->load($tenant, (int) $m->alumni_id);
            if ($load >= (int) $m->capacity) {
                continue;
            }
            $profile = $this->tokens(implode(' ', [$m->expertise, $m->industry, $m->job_role, $m->preferred_topics]));
            $hitsArea = array_values(array_intersect($areaTokens, $profile));
            $hitsText = array_values(array_intersect($textTokens, $profile));
            $score = count($hitsArea) * 2 + count($hitsText);
            $preferred = (int) $request->preferred_mentor_alumni_id === (int) $m->alumni_id;
            if ($preferred) {
                $score += 5;
            }
            $out[] = [
                'alumni_id' => (int) $m->alumni_id, 'full_name' => $m->full_name, 'alumni_code' => $m->alumni_code, 'batch' => $m->batch,
                'expertise' => $m->expertise, 'industry' => $m->industry, 'job_role' => $m->job_role, 'mode' => $m->mode,
                'available_days' => $m->available_days, 'open_slots' => (int) $m->capacity - $load, 'score' => $score,
                'preferred_by_student' => $preferred, 'matched_on' => array_values(array_unique(array_merge($hitsArea, $hitsText))),
            ];
        }
        usort($out, fn ($a, $b) => [$b['score'], $b['open_slots']] <=> [$a['score'], $a['open_slots']]);

        return array_slice($out, 0, $limit);
    }

    /** @throws \DomainException with a message safe to show the user */
    public function match(int $tenant, int $requestId, int $mentorAlumniId, ?int $userId): object
    {
        return DB::transaction(function () use ($tenant, $requestId, $mentorAlumniId, $userId) {
            $req = DB::table('alumni_mentorship_requests')->where('sub_institute_id', $tenant)->where('id', $requestId)->lockForUpdate()->first();
            if (! $req) {
                throw new \DomainException('Request not found.');
            }
            if ($req->status !== 'open') {
                throw new \DomainException('Only an open request can be matched to a mentor.');
            }
            $mentor = DB::table('alumni_mentor_profiles as m')
                ->join('alumni as a', function ($j) {
                    $j->on('a.id', '=', 'm.alumni_id')->on('a.sub_institute_id', '=', 'm.sub_institute_id');
                })
                ->where('m.sub_institute_id', $tenant)->where('m.alumni_id', $mentorAlumniId)->first(['m.*', 'a.status as alumni_status']);
            if (! $mentor || $mentor->status !== 'active') {
                throw new \DomainException('That alumnus is not an active mentor.');
            }
            if (! in_array($mentor->alumni_status, ['verified', 'active'], true)) {
                throw new \DomainException('Only verified or active alumni can mentor.');
            }
            if (in_array($mentorAlumniId, array_map('intval', explode(',', (string) $req->declined_mentor_ids)), true)) {
                throw new \DomainException('That mentor has already declined this request.');
            }
            if ($this->load($tenant, $mentorAlumniId) >= (int) $mentor->capacity) {
                throw new \DomainException('That mentor has no free mentoring slots.');
            }
            DB::table('alumni_mentorship_requests')->where('id', $requestId)->update([
                'status' => 'matched', 'mentor_alumni_id' => $mentorAlumniId, 'matched_by' => $userId,
                'matched_at' => now(), 'updated_at' => now(),
            ]);

            return DB::table('alumni_mentorship_requests')->where('id', $requestId)->first();
        });
    }

    /** The mentor's answer (recorded by staff until alumni can sign in themselves). */
    public function respond(int $tenant, int $requestId, bool $accept, ?string $note): object
    {
        return DB::transaction(function () use ($tenant, $requestId, $accept, $note) {
            $req = DB::table('alumni_mentorship_requests')->where('sub_institute_id', $tenant)->where('id', $requestId)->lockForUpdate()->first();
            if (! $req) {
                throw new \DomainException('Request not found.');
            }
            if ($req->status !== 'matched') {
                throw new \DomainException('Only a matched request can be accepted or declined.');
            }
            if ($accept) {
                $upd = ['status' => 'accepted', 'responded_at' => now(), 'response_note' => $note, 'updated_at' => now()];
            } else {
                $declined = array_filter(array_map('intval', explode(',', (string) $req->declined_mentor_ids)));
                $declined[] = (int) $req->mentor_alumni_id;
                $upd = ['status' => 'open', 'mentor_alumni_id' => null, 'matched_by' => null, 'matched_at' => null,
                    'declined_mentor_ids' => implode(',', array_unique($declined)), 'responded_at' => now(), 'response_note' => $note, 'updated_at' => now()];
            }
            DB::table('alumni_mentorship_requests')->where('id', $requestId)->update($upd);

            return DB::table('alumni_mentorship_requests')->where('id', $requestId)->first();
        });
    }

    /** @return array<int, string> */
    private function tokens(string $text): array
    {
        $words = preg_split('/[^a-z0-9+#]+/i', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter($words, fn ($w) => mb_strlen($w) >= 3 && ! in_array($w, self::STOP, true))));
    }
}
