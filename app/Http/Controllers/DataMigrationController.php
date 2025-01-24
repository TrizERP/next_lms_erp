<?php

namespace App\Http\Controllers;

use App\Services\Neo4jService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
class DataMigrationController extends Controller
{
    public function migrateDataToNeo4j()
    {
        try {
            $neo4jService = new Neo4jService();

            // Fetch data from MySQL
            $mysqlData = DB::table('lms_data_content_neo4j')->limit(100)->get();
            // echo "<pre>";print_r($mysqlData);exit;
            $count=100;
            foreach ($mysqlData as $key=>$data) {
                // echo $data->chapter.'<br>';
                // Prepare data object for Neo4j
                // $data = (object) [
                //     'institute_name'   => $row->institute_name,
                //     'acedemic_section' => $row->acedemic_section,
                //     'standard'         => $row->standard,
                //     'subject'          => $row->subject,
                //     'chapter'          => $row->chapter,
                //     'source'           => $row->source,
                //     'title'            => $row->title,
                //     'filepath'         => $row->filepath,
                // ];
                
                // echo 'key-'.$key.'   data-'.$data->acedemic_section.'<br>';
                $sectionNode = $neo4jService->createOrGetNode('AcademicSection', 'acedemic_section', $data->acedemic_section ?? '-');
                $standardNode = $neo4jService->createOrGetNode('Standard', 'standard', $data->standard ?? '-');
                $subjectNode = $neo4jService->createOrGetNode('Subject', 'subject', $data->subject  ?? '-');
                $chapterNode = $neo4jService->createOrGetNode('Chapter', 'chapter', $data->chapter  ?? '-');
//  echo "<pre>";print_r($sectionNode['acedemic_section'] );exit;
                
                $r1 = $neo4jService->createRelationship($sectionNode['acedemic_section'], $standardNode['standard'],'r1', 'OFFERS','acedemic_section','standard');
                // echo "<pre>";print_r($relation);exit;
                $r2 =$neo4jService->createRelationship($standardNode['standard'], $subjectNode['subject'],'r2', 'OFFERS','standard','subject');
                //echo "<pre>";print_r($relation);
                $r3 = $neo4jService->createRelationship($subjectNode['subject'], $chapterNode['chapter'],'r3', 'OFFERS','subject','chapter');
                // $count--;
                // if($count==0){
                //     break;
                // }else{
                    // echo "<pre>";print_r($neo4jService);
                // }
                    
            }
            // exit;
            Log::info('Data successfully migrated from MySQL to Neo4j.');
            return "Relation created successfully!!!";
        } catch (\Exception $e) {
            Log::error('Error migrating data: ' . $e->getMessage());
            return $e->getMessage();
        }
    }
}