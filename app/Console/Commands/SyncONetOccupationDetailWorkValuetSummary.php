<?php

namespace App\Console\Commands;

use App\Models\ONetOccupationDetailList;
use App\Models\ONetOccupationDetailWorkStyleSummery;
use App\Models\ONetOccupationDetailWorkValueSummery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncONetOccupationDetailWorkValuetSummary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:o-net-occupation-detail-work-value-summary';

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
            $occupationDetailLists = ONetOccupationDetailList::where([['resource_title', 'Work Values'], ['o_net_data_category_id', $key + 1]])->get();
            foreach ($occupationDetailLists as $occupationDetailList) {
                $response = Http::withHeaders([
                    'Authorization' => 'Basic ' . base64_encode('trizinnovation:4225aej'),
                    'Accept' => 'application/json'
                ])->get($occupationDetailList['href'] . '?display=long');

                $data = $response->json();

                if (isset($data['element'])) {
                    foreach ($data['element'] as $element) {
                        ONetOccupationDetailWorkValueSummery::insert([
                            'o_net_occupation_detail_list_id' => $occupationDetailList['id'],
                            'element_id' => $element['id'],
                            'name' => $element['name'],
                            'related' => $element['related'],
                            'description' => $element['description'],
                        ]);
                    }
                }
            }
        }

        dd('done');
    }
}
