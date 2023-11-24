<?php

namespace App\Console\Commands;

use App\Models\ONetDataOccupation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncONetOccupationData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:o-net-occupation-data';

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
        $categories = ['abilities','interests','knowledge','skills','technology_skills','work_activities','work_context','work_styles','work_values'];
        foreach ($categories as $key => $category) {
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode('trizinnovation:4225aej'),
                'Accept' => 'application/json'
            ])->get('https://services.onetcenter.org/ws/online/occupations/11-1011.00/details/'.$category.'?display=long');

            $data = $response->json();

            if (isset($data['element'])) {
                foreach ($data['element'] as $element) {
                    ONetDataOccupation::insert([
                        'o_net_data_category_id' => $key+1,
                        'element_id' => $element['id'],
                        'related' => $element['related'],
                        'name' => $element['name'],
                        'description' => $element['description']
                    ]);
                }
            }
        }

       /* $response = Http::withHeaders([
            'Authorization' => 'Basic ' . base64_encode('trizinnovation:4225aej'),
            'Accept' => 'application/json'
        ])->get('https://services.onetcenter.org/ws/online/occupations/11-1011.00/details/interests?display=long');

        $data = $response->json();

        if (isset($data['element'])) {
            foreach ($data['element'] as $element) {
                ONetDataOccupation::insert([
                    'o_net_data_category_id' => 2,
                    'element_id' => $element['id'],
                    'related' => $element['related'],
                    'name' => $element['name'],
                    'description' => $element['description']
                ]);
            }
        }*/
        dd('done');
    }
}
