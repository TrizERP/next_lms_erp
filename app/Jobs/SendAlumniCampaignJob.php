<?php

namespace App\Jobs;

use App\Services\Alumni\AlumniMessaging;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Sends one alumni campaign. With QUEUE_CONNECTION=sync (this application's default) it runs inline; with a real queue it
 * runs in the background and the campaign status moves queued -> sending -> sent.
 *
 * AlumniMessaging::runCampaign claims the campaign with a conditional UPDATE and logs each message under a unique key, so
 * a duplicate dispatch, a worker restart or a manual re-run cannot message anyone twice. The job is also unique per
 * campaign so two copies never queue at once.
 */
class SendAlumniCampaignJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 1800;
    public int $uniqueFor = 3600;

    public function __construct(public int $campaignId)
    {
    }

    public function uniqueId(): string
    {
        return 'alumni-campaign-' . $this->campaignId;
    }

    public function handle(AlumniMessaging $messaging): void
    {
        $messaging->runCampaign($this->campaignId);
    }
}
