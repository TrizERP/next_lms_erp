<?php

namespace App\Console\Commands;

use App\Models\ONetOccupationDetailJobZoneSummery;
use App\Models\ONetOccupationDetailList;
use App\Models\ONetOccupationDetailWorkActivitySummery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncONetOccupationDetailJobZoneSummary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:o-net-occupation-detail-job-zone-summary';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $categories = ['abilities', 'interests', 'knowledge', 'skills', 'technology_skills', 'work_activities', 'work_context', 'work_styles', 'work_values'];
        foreach ($categories as $key => $category) {
            $occupationDetailLists = ONetOccupationDetailList::where([['resource_title', 'Job Zone'], ['o_net_data_category_id', $key +1]])->get();
            foreach ($occupationDetailLists as $occupationDetailList) {
                $response = Http::withHeaders([
                    'Authorization' => 'Basic ' . base64_encode('trizinnovation:4225aej'),
                    'Accept' => 'application/json'
                ])->get($occupationDetailList['href'] . '?display=long');

                $data = $response->json();

                if (isset($data['code'])) {
                    //foreach ($data['element'] as $element) {
                    ONetOccupationDetailJobZoneSummery::insert([
                        'o_net_occupation_detail_list_id' => $occupationDetailList['id'],
                        'title' => $data['title'],
                        'education' => $data['education'],
                        'related_experience' => $data['related_experience'],
                        'job_training' => $data['job_training'],
                        'job_zone_examples' => $data['job_zone_examples'],
                        'svp_range' => $data['svp_range'],
                    ]);
                    //}
                }
            }
        }

        dd('done');
    }
}
