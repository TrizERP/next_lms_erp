<?php

namespace App\Console\Commands;

use App\Models\ONetOccupationDetailList;
use App\Models\ONetOccupationDetailListSummary;
use App\Models\ONetOccupationDetailTechSkillSummery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncONetOccupationDetailTechSkillSummary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:o-net-occupation-detail-tech-skill-summary';

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
            $occupationDetailLists = ONetOccupationDetailList::where([['resource_title', 'Technology Skills'], ['o_net_data_category_id', $key + 1]])->get();
            foreach ($occupationDetailLists as $occupationDetailList) {
                $response = Http::withHeaders([
                    'Authorization' => 'Basic ' . base64_encode('trizinnovation:4225aej'),
                    'Accept' => 'application/json'
                ])->get($occupationDetailList['href'] . '?display=long');

                $data = $response->json();

                if (isset($data['category'])) {
                    foreach ($data['category'] as $category) {
                        ONetOccupationDetailTechSkillSummery::insert([
                            'o_net_occupation_detail_list_id' => $occupationDetailList['id'],
                            'title_id' => $category['title']['id'],
                            'name' => $category['title']['name'],
                            'related' => $category['related'],
                            'example' => json_encode($category['example'])
                        ]);
                    }
                }
            }
        }

        dd('done');
    }
}
