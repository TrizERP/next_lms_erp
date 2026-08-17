<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * New PAL → Gamification (PAL V4 Gamification & Motivation System).
 *
 * These tables hold ONLY what the gamification layer must remember between
 * requests — awards, records, streak days, challenges, opt-ins and declared
 * interests. Everything derivable from learning activity (mastery tiers,
 * fluency, class progress, RIASEC signal counts, career pathway progress) is
 * computed at read time from the estate's existing PAL and LMS tables, so
 * there is no second source of truth for anything the rest of PAL already
 * owns.
 *
 * `pal_badges` is the one exception, and deliberately so: the badge catalogue
 * ships in config/pal_gamification.php and is mirrored into a table here so an
 * institute can retire a badge without a code change, and so the awarding
 * rules are joinable in SQL. The mirror is idempotent — re-running it updates
 * the shipped columns and leaves any institute edits to `status` alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- Badge catalogue -------------------------------------------------
        if (! Schema::hasTable('pal_badges')) {
            Schema::create('pal_badges', function (Blueprint $table) {
                $table->id();
                $table->string('badge_id')->unique();
                $table->string('name');
                $table->string('category')->index();
                $table->string('description')->nullable();
                $table->text('student_message')->nullable();
                $table->string('hpc_domain')->nullable();
                $table->string('casel_domain')->nullable();
                $table->string('ncdg_goal')->nullable();
                $table->string('rarity')->default('common');
                $table->float('hpc_evidence_weight')->default(0);
                $table->string('scope')->default('global');       // global | concept | subject
                $table->string('trigger_type');
                $table->json('trigger_config')->nullable();
                $table->boolean('challenge_mode_only')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->string('status')->default('active');      // active | retired
                $table->timestamps();
            });
        }

        // ---- Badge awards ----------------------------------------------------
        if (! Schema::hasTable('pal_learner_badges')) {
            Schema::create('pal_learner_badges', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('learner_id');
                $table->string('badge_id');
                // '' for global badges; a concept/subject key for scoped ones,
                // so "Subject champion" can be held once per subject.
                $table->string('scope_key')->default('');
                $table->timestamp('awarded_at')->nullable();
                $table->json('context')->nullable();
                // §10.3 teacher override — revoked awards are kept, never deleted.
                $table->timestamp('revoked_at')->nullable();
                $table->unsignedBigInteger('revoked_by')->nullable();
                $table->string('revoke_reason')->nullable();
                $table->timestamps();

                $table->unique(['learner_id', 'badge_id', 'scope_key'], 'pal_learner_badge_unique');
                $table->index(['learner_id', 'awarded_at']);
            });
        }

        // ---- Personal bests --------------------------------------------------
        if (! Schema::hasTable('pal_personal_bests')) {
            Schema::create('pal_personal_bests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('learner_id');
                $table->string('metric_key');
                $table->string('scope_type')->default('global');  // global | concept
                $table->string('scope_ref')->default('');
                $table->string('scope_label')->nullable();
                $table->decimal('best_value', 12, 4)->default(0);
                $table->timestamp('best_achieved_at')->nullable();
                $table->decimal('previous_value', 12, 4)->nullable();
                $table->timestamp('previous_achieved_at')->nullable();
                $table->json('context')->nullable();
                $table->timestamps();

                $table->unique(['learner_id', 'metric_key', 'scope_ref'], 'pal_personal_best_unique');
                $table->index(['learner_id', 'metric_key']);
            });
        }

        // Every time a record is broken. Drives /personal-best/history and the
        // "up from" copy without recomputing the whole timeline.
        if (! Schema::hasTable('pal_personal_best_events')) {
            Schema::create('pal_personal_best_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('learner_id');
                $table->string('metric_key');
                $table->string('scope_type')->default('global');
                $table->string('scope_ref')->default('');
                $table->string('scope_label')->nullable();
                $table->decimal('value', 12, 4);
                $table->decimal('previous_value', 12, 4)->nullable();
                $table->decimal('improvement_pct', 8, 2)->nullable();
                $table->timestamp('achieved_at')->nullable();
                $table->json('context')->nullable();
                $table->timestamps();

                $table->index(['learner_id', 'achieved_at']);
            });
        }

        // ---- Streaks ---------------------------------------------------------
        if (! Schema::hasTable('pal_learner_streaks')) {
            Schema::create('pal_learner_streaks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('learner_id')->unique();
                $table->unsignedInteger('current_streak')->default(0);
                $table->date('current_started_on')->nullable();
                $table->unsignedInteger('longest_streak')->default(0);
                $table->date('longest_streak_ended_on')->nullable();
                $table->date('last_active_date')->nullable();
                $table->date('grace_used_on')->nullable();
                $table->unsignedInteger('total_active_days')->default(0);
                $table->timestamp('recomputed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pal_streak_days')) {
            Schema::create('pal_streak_days', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('learner_id');
                $table->date('activity_date');
                $table->unsignedInteger('productive_minutes')->default(0);
                $table->unsignedInteger('qualifying_events')->default(0);
                $table->json('sources')->nullable();
                $table->boolean('qualified')->default(false);
                $table->timestamps();

                $table->unique(['learner_id', 'activity_date'], 'pal_streak_day_unique');
                $table->index(['learner_id', 'activity_date']);
            });
        }

        // ---- Team challenges -------------------------------------------------
        if (! Schema::hasTable('pal_team_challenges')) {
            Schema::create('pal_team_challenges', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sub_institute_id');
                $table->unsignedInteger('syear')->nullable();
                $table->unsignedBigInteger('grade_id')->nullable();
                $table->unsignedBigInteger('standard_id')->nullable();
                $table->unsignedBigInteger('division_id')->nullable();
                $table->unsignedBigInteger('teacher_id');
                $table->string('type');                       // §4.2 challenge types
                $table->string('title');
                $table->text('description')->nullable();
                $table->unsignedBigInteger('subject_id')->nullable();
                // The learnable unit the challenge targets, in the same
                // `source:id` form the rest of the module uses.
                $table->string('concept_ref')->nullable();
                $table->string('concept_label')->nullable();
                $table->string('target_metric');
                $table->decimal('target_value', 10, 4)->default(0);
                $table->string('target_tier')->nullable();    // mastery_sprint: mountain | sky
                $table->decimal('baseline_value', 10, 4)->nullable();
                $table->date('deadline')->nullable();
                $table->string('reward_title')->nullable();
                $table->text('reward_description')->nullable();
                $table->unsignedBigInteger('reward_content_id')->nullable();
                $table->boolean('reward_approved')->default(false);
                $table->string('status')->default('active');  // active | completed | ended
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->unsignedBigInteger('ended_by')->nullable();
                $table->string('ended_reason')->nullable();
                $table->timestamps();

                $table->index(['sub_institute_id', 'standard_id', 'division_id', 'status'], 'pal_team_challenge_class_idx');
                $table->index(['teacher_id', 'status']);
            });
        }

        if (! Schema::hasTable('pal_team_challenge_contributions')) {
            Schema::create('pal_team_challenge_contributions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('challenge_id');
                $table->unsignedBigInteger('learner_id');
                $table->boolean('contributed')->default(false);
                $table->decimal('contribution_value', 10, 4)->default(0);
                $table->timestamp('first_contributed_at')->nullable();
                $table->timestamp('evaluated_at')->nullable();
                $table->timestamps();

                $table->unique(['challenge_id', 'learner_id'], 'pal_team_challenge_contrib_unique');
            });
        }

        // ---- Challenge Mode --------------------------------------------------
        if (! Schema::hasTable('pal_challenge_mode_optins')) {
            Schema::create('pal_challenge_mode_optins', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('learner_id')->unique();
                $table->boolean('opted_in')->default(false);
                $table->timestamp('opted_in_at')->nullable();
                $table->timestamp('opted_out_at')->nullable();
                $table->timestamps();
            });
        }

        // Class-level availability switch (§6.1 "can be disabled by teacher at
        // class level, e.g. during exam period").
        if (! Schema::hasTable('pal_challenge_mode_settings')) {
            Schema::create('pal_challenge_mode_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sub_institute_id');
                $table->unsignedInteger('syear')->nullable();
                $table->unsignedBigInteger('standard_id')->nullable();
                $table->unsignedBigInteger('division_id')->nullable();
                $table->boolean('enabled')->default(true);
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->string('disabled_reason')->nullable();
                $table->timestamps();

                $table->unique(
                    ['sub_institute_id', 'syear', 'standard_id', 'division_id'],
                    'pal_challenge_mode_setting_unique'
                );
            });
        }

        if (! Schema::hasTable('pal_challenge_mode_scores')) {
            Schema::create('pal_challenge_mode_scores', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('learner_id');
                $table->unsignedBigInteger('sub_institute_id')->nullable();
                $table->unsignedInteger('syear')->nullable();
                $table->unsignedBigInteger('standard_id')->nullable();
                $table->unsignedBigInteger('division_id')->nullable();
                $table->date('week_start');
                $table->string('concept_ref')->nullable();
                $table->string('concept_label')->nullable();
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->unsignedInteger('score')->default(0);
                $table->unsignedTinyInteger('accuracy_pct')->default(0);
                $table->integer('speed_bonus')->default(0);
                $table->decimal('difficulty_rating', 4, 2)->default(0);
                $table->unsignedInteger('item_count')->default(0);
                $table->unsignedInteger('duration_seconds')->default(0);
                $table->json('payload')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();

                $table->index(['standard_id', 'division_id', 'week_start'], 'pal_challenge_score_class_week_idx');
                $table->index(['learner_id', 'week_start']);
            });
        }

        // ---- Career quest ----------------------------------------------------
        // Only the learner's own DECLARATIONS live here. Stage, RIASEC profile,
        // pathway ranking and skill progress are all recomputed from evidence.
        if (! Schema::hasTable('pal_career_quest_states')) {
            Schema::create('pal_career_quest_states', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('learner_id')->unique();
                $table->json('interest_declaration')->nullable();
                $table->timestamp('declared_at')->nullable();
                $table->string('chosen_primary_pathway')->nullable();
                $table->string('chosen_secondary_pathway')->nullable();
                $table->unsignedInteger('skills_target_primary')->nullable();
                $table->timestamp('report_generated_at')->nullable();
                $table->json('report_snapshot')->nullable();
                $table->timestamps();
            });
        }

        // ---- Notifications ---------------------------------------------------
        // The student-facing celebration queue (§8). Rows are written only when
        // a real record, badge or milestone is crossed.
        if (! Schema::hasTable('pal_gamification_notifications')) {
            Schema::create('pal_gamification_notifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('learner_id');
                $table->string('type');           // personal_best | badge | streak | team_challenge | career_quest
                $table->string('level')->default('medium'); // §8.1 celebration hierarchy
                $table->string('title');
                $table->text('body')->nullable();
                $table->json('context')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->index(['learner_id', 'read_at']);
                $table->index(['learner_id', 'created_at']);
            });
        }

        $this->syncBadgeCatalogue();
    }

    public function down(): void
    {
        foreach ([
            'pal_gamification_notifications',
            'pal_career_quest_states',
            'pal_challenge_mode_scores',
            'pal_challenge_mode_settings',
            'pal_challenge_mode_optins',
            'pal_team_challenge_contributions',
            'pal_team_challenges',
            'pal_streak_days',
            'pal_learner_streaks',
            'pal_personal_best_events',
            'pal_personal_bests',
            'pal_learner_badges',
            'pal_badges',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }

    /**
     * Mirror config/pal_gamification.php `badges` into `pal_badges`.
     *
     * Idempotent: shipped columns are refreshed on every run, `status` is left
     * alone so an institute that retired a badge keeps it retired.
     */
    private function syncBadgeCatalogue(): void
    {
        $badges = (array) config('pal_gamification.badges', []);
        if ($badges === []) {
            return;
        }

        $order = 0;
        foreach ($badges as $badge) {
            $badgeId = (string) ($badge['badge_id'] ?? '');
            if ($badgeId === '') {
                continue;
            }

            $trigger = (array) ($badge['trigger'] ?? []);
            $payload = [
                'name' => (string) ($badge['name'] ?? $badgeId),
                'category' => (string) ($badge['category'] ?? 'mastery'),
                'description' => $badge['description'] ?? null,
                'student_message' => $badge['student_message'] ?? null,
                'hpc_domain' => $badge['hpc_domain'] ?? null,
                'casel_domain' => $badge['casel_domain'] ?? null,
                'ncdg_goal' => $badge['ncdg_goal'] ?? null,
                'rarity' => (string) ($badge['rarity'] ?? 'common'),
                'hpc_evidence_weight' => (float) ($badge['hpc_evidence_weight'] ?? 0),
                'scope' => (string) ($badge['scope'] ?? 'global'),
                'trigger_type' => (string) ($trigger['type'] ?? 'never'),
                'trigger_config' => json_encode($trigger),
                'challenge_mode_only' => (bool) ($badge['challenge_mode_only'] ?? false),
                'sort_order' => $order++,
                'updated_at' => now(),
            ];

            $exists = DB::table('pal_badges')->where('badge_id', $badgeId)->exists();
            if ($exists) {
                DB::table('pal_badges')->where('badge_id', $badgeId)->update($payload);
                continue;
            }

            DB::table('pal_badges')->insert($payload + [
                'badge_id' => $badgeId,
                'status' => 'active',
                'created_at' => now(),
            ]);
        }
    }
};
