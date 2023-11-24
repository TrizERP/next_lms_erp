<?php

namespace App\Console\Commands;

use App\Models\ONetOccupationDetail;
use App\Models\ONetOccupationDetailList;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncONetOccupationDetailList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:o-net-occupation-detail-list';

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
        /*$resource_list = ['Tasks','Technology Skills','Knowledge','Skills','Abilities','Work Activities','Job Zone','Education','Interests','Work Styles','Work Values'];
        $occupationDetails = ONetOccupationDetail::where('o_net_data_category_id',1)->get();
        foreach ($occupationDetails as $occupationDetail) {
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode('trizinnovation:4225aej'),
                'Accept' => 'application/json'
            ])->get($occupationDetail['href']);

            $data = $response->json();

            if (isset($data['summary_resources']['resource'])) {
                foreach ($data['summary_resources']['resource'] as $summary_resource) {
                    if (in_array($summary_resource['title'],$resource_list)) {
                       ONetOccupationDetailList::insert([
                           'o_net_occupation_detail_id' => $occupationDetail['id'],
                           'title' => $data['title'],
                           'description' => $data['description'],
                           'resource_title' => $summary_resource['title'],
                           'href' => $summary_resource['href'],
                       ]);
                    }
                }
            }
        }*/

        $resource_list = ['Tasks','Technology Skills','Knowledge','Skills','Abilities','Work Activities','Job Zone','Education','Interests','Work Styles','Work Values'];
        $categories = ['abilities','interests','knowledge','skills','technology_skills','work_activities','work_context','work_styles','work_values'];
        foreach ($categories as $key => $category) {
            $occupationDetails = ONetOccupationDetail::where('o_net_data_category_id', $key+1)->get();
            foreach ($occupationDetails as $occupationDetail) {
                $response = Http::withHeaders([
                    'Authorization' => 'Basic ' . base64_encode('trizinnovation:4225aej'),
                    'Accept' => 'application/json'
                ])->get($occupationDetail['href']);

                $data = $response->json();

                if (isset($data['summary_resources']['resource'])) {
                    foreach ($data['summary_resources']['resource'] as $summary_resource) {
                        if (in_array($summary_resource['title'], $resource_list)) {
                            ONetOccupationDetailList::insert([
                                'o_net_occupation_detail_id' => $occupationDetail['id'],
                                'o_net_data_category_id' => $occupationDetail['o_net_data_category_id'],
                                'title' => $data['title'],
                                'description' => $data['description'],
                                'resource_title' => $summary_resource['title'],
                                'href' => $summary_resource['href'],
                            ]);
                        }
                    }
                }
            }
        }

        dd('done');
    }
}
