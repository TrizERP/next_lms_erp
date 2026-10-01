<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What gets recorded when a user says "No, I'm fine" to the stuck-user popup.
 *
 * A DEDICATED TABLE, NOT THE EXISTING COMPLAINT/STUDENT-REQUEST MODULES
 *
 * This estate already has admin-services complaint management and a student-requests
 * queue, and neither is the right home for this: those are school-administrative
 * workflows (a parent's complaint, a document request) with their own approval and
 * notification rules. A row here is a product-support signal — "someone struggled to
 * use a screen" — read by whoever supports the software, not by school staff, and
 * forcing it into an unrelated table would mean either bending that table's meaning or
 * adding fields to it nothing else uses.
 *
 * THE SCREENSHOT IS PRIVATE BY CONSTRUCTION
 *
 * `screenshot_path` is a path on the `local` (non-public) disk, served only through an
 * authenticated, admin-gated route — never the `public` disk, which would make a
 * screenshot guessable-URL-reachable. A stuck-user screenshot can show anything the
 * screen was showing — another family's fee amount, a child's name — so "logged in"
 * is not enough; only `McpRequestContext::$isAdmin` may list tickets or fetch one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_assistance_tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();

            $table->unsignedBigInteger('user_id')->index();
            $table->string('user_name', 150)->nullable();
            $table->string('user_role', 60)->nullable();

            $table->string('module', 80)->nullable()->index();
            $table->string('page_title', 200)->nullable();
            $table->string('page_path', 300)->nullable();

            $table->unsignedInteger('idle_seconds');
            // What usePageAiContext() had at the moment of the prompt — page type,
            // filters, metrics, available actions. The same shape /api/ai/generate
            // already receives for this module, kept verbatim so a reviewer sees
            // exactly what the assistant could see.
            $table->json('context_snapshot')->nullable();

            // Path on the `local` disk; null if the screenshot failed to capture
            // (html2canvas can fail on some content) — a ticket with no image is
            // still a useful signal and must not be discarded for want of one.
            $table->string('screenshot_path', 300)->nullable();

            $table->string('status', 24)->default('open')->index(); // open | reviewed | dismissed
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->index(['sub_institute_id', 'status'], 'ai_assist_tickets_tenant_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_assistance_tickets');
    }
};
