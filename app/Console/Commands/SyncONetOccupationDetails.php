<?php

namespace App\Console\Commands;

use App\Models\ONetDataOccupation;
use App\Models\ONetOccupationDetail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncONetOccupationDetails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:o-net-occupation-detail';

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
        /*$occupationData = ONetDataOccupation::where('o_net_data_category_id', 1)->get();
        foreach ($occupationData as $occupation) {
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode('trizinnovation:4225aej'),
                'Accept' => 'application/json'
            ])->get($occupation['related']);

            $data = $response->json();
            if (isset($data['occupation'])) {
                foreach ($data['occupation'] as $dataOccupation) {
                    ONetOccupationDetail::insert([
                        'o_net_data_category_id' => $occupationData['o_net_data_category_id'],
                        'o_net_data_occupation_id' => $occupation['id'],
                        'element_id' => $data['element_id'],
                        'href' => $dataOccupation['href'],
                        'code' => $dataOccupation['code'],
                        'title' => $dataOccupation['title'],
                    ]);
                }
            }
        }*/

        $categories = ['abilities','interests','knowledge','skills','technology_skills','work_activities','work_context','work_styles','work_values'];
        foreach ($categories as $key => $category) {
            $occupationData = ONetDataOccupation::where('o_net_data_category_id', $key+1)->get();
            foreach ($occupationData as $occupation) {
                $response = Http::withHeaders([
                    'Authorization' => 'Basic ' . base64_encode('trizinnovation:4225aej'),
                    'Accept' => 'application/json'
                ])->get($occupation['related']);

                $data = $response->json();
                if (isset($data['occupation'])) {
                    foreach ($data['occupation'] as $dataOccupation) {
                        ONetOccupationDetail::insert([
                            'o_net_data_category_id' => $occupation['o_net_data_category_id'],
                            'o_net_data_occupation_id' => $occupation['id'],
                            'element_id' => $data['element_id'],
                            'href' => $dataOccupation['href'],
                            'code' => $dataOccupation['code'],
                            'title' => $dataOccupation['title'],
                        ]);
                    }
                }
            }
        }


        dd('done');
    }
}
