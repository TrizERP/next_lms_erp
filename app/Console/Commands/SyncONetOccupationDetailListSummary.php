<?php

namespace App\Console\Commands;

use App\Models\ONetDataOccupation;
use App\Models\ONetOccupationDetail;
use App\Models\ONetOccupationDetailList;
use App\Models\ONetOccupationDetailListSummary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncONetOccupationDetailListSummary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:o-net-occupation-detail-list-summary';

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
            $occupationDetailLists = ONetOccupationDetailList::where([['resource_title', 'Tasks'], ['o_net_data_category_id', $key+1]])->get();
            foreach ($occupationDetailLists as $occupationDetailList) {
                $response = Http::withHeaders([
                    'Authorization' => 'Basic ' . base64_encode('trizinnovation:4225aej'),
                    'Accept' => 'application/json'
                ])->get($occupationDetailList['href'] . '?display=long');

                $data = $response->json();

                if (isset($data['task'])) {
                    foreach ($data['task'] as $task) {
                        ONetOccupationDetailListSummary::insert([
                            'o_net_occupation_detail_list_id' => $occupationDetailList['id'],
                            'name' => $task['name'],
                            'related' => $task['related']
                        ]);
                    }
                }
            }
        }

        dd('done');
    }
}
