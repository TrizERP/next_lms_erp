<?php

namespace App\Console\Commands;

use App\Models\ONetDataCategory;
use App\Models\ONetDataSubCategory;
use App\Models\ONetDataTable;
use App\Models\ONetDataTableList;
use App\Models\ONetOccupationDetailAbilitiesSummery;
use App\Models\ONetOccupationDetailEducationSummery;
use App\Models\ONetOccupationDetailInterestSummery;
use App\Models\ONetOccupationDetailJobZoneSummery;
use App\Models\ONetOccupationDetailKnowledgeSummery;
use App\Models\ONetOccupationDetailListSummary;
use App\Models\ONetOccupationDetailSkillSummery;
use App\Models\ONetOccupationDetailTechSkillSummery;
use App\Models\ONetOccupationDetailWorkActivitySummery;
use App\Models\ONetOccupationDetailWorkStyleSummery;
use App\Models\ONetOccupationDetailWorkValueSummery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Support\Collection;
use Opcodes\LogViewer\Log;

class TestFunction1 extends Command implements ToCollection
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:function';

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
        /*ONetDataCategory::insert([
           'category' => 'Industry'
        ]);
        dd('done');*/
        //$this->insertSubCategory();
        //$this->insertRecord();
        //dd('done');


        // Your logic using the passed data...

        //dd('Command executed with argument: ' . $argumentValue . ' and option: ' . $optionValue);

        $path = storage_path('app/public/All_Industries.csv');

        // Read the CSV file
        Excel::import(new TestFunction, $path);
        dd('done');


    }

    public function insertSubCategory()
    {
        // Industry
         /*ONetDataSubCategory::insert([
             ['o_net_data_category_id' => 12, 'sub_category_name' => 'Accommodation and Food Services', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => ''],
             ['o_net_data_category_id' => 12, 'sub_category_name' => 'Administrative and Support Services', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => ''],
             ['o_net_data_category_id' => 12, 'sub_category_name' => 'Agriculture, Forestry, Fishing, and Hunting', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => ''],
             ['o_net_data_category_id' => 12, 'sub_category_name' => 'Arts, Entertainment, and Recreation', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => ''],
             ['o_net_data_category_id' => 12, 'sub_category_name' => 'Construction', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => ''],
             ['o_net_data_category_id' => 12, 'sub_category_name' => 'Educational Services', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => ''],
             ['o_net_data_category_id' => 12, 'sub_category_name' => 'Finance and Insurance', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => ''],
             ['o_net_data_category_id' => 12, 'sub_category_name' => 'Government', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => ''],
             ['o_net_data_category_id' => 12, 'sub_category_name' => 'Health Care and Social Assistance', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => ''],
             ['o_net_data_category_id' => 12, 'sub_category_name' => 'Information', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => ''],
             ['o_net_data_category_id' => 12, 'sub_category_name' => 'Management of Companies and Enterprises', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => ''],
             ['o_net_data_category_id' => 12, 'sub_category_name' => 'Manufacturing', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => ''],
             ['o_net_data_category_id' => 12, 'sub_category_name' => 'Mining, Quarrying, and Oil and Gas Extraction', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => ''],
             ['o_net_data_category_id' => 12, 'sub_category_name' => 'Other Services (Except Public Administration)', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => ''],
             ['o_net_data_category_id' => 12, 'sub_category_name' => 'Professional, Scientific, and Technical Services', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => ''],
             ['o_net_data_category_id' => 12, 'sub_category_name' => 'Real Estate and Rental and Leasing', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => ''],
             ['o_net_data_category_id' => 12, 'sub_category_name' => 'Retail Trade', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => ''],
             ['o_net_data_category_id' => 12, 'sub_category_name' => 'Transportation and Warehousing', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => ''],
             ['o_net_data_category_id' => 12, 'sub_category_name' => 'Utilities', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => ''],
             ['o_net_data_category_id' => 12, 'sub_category_name' => 'Wholesale Trade', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => ''],
             ['o_net_data_category_id' => 12, 'sub_category_name' => 'All Industries', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => ''],

         ]);*/
       //STEM
       /* ONetDataSubCategory::insert([
           ['o_net_data_category_id' => 11, 'sub_category_name' => 'Managerial', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => ''],
           ['o_net_data_category_id' => 11, 'sub_category_name' => 'Postsecondary Teaching', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => ''],
           ['o_net_data_category_id' => 11, 'sub_category_name' => 'Research, Development, Design, and Practitioners', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => ''],
           ['o_net_data_category_id' => 11, 'sub_category_name' => 'Sales', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => ''],
           ['o_net_data_category_id' => 11, 'sub_category_name' => 'Technologists and Technicians', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => ''],
           ['o_net_data_category_id' => 11, 'sub_category_name' => 'All STEM Occupations', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => ''],
           ['o_net_data_category_id' => 11, 'sub_category_name' => '— Architecture and Engineering', 'parent_id' => 326, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => ''],
           ['o_net_data_category_id' => 11, 'sub_category_name' => '— Computer and Mathematical', 'parent_id' => 326, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => ''],
           ['o_net_data_category_id' => 11, 'sub_category_name' => '— Healthcare Practitioners and Technical', 'parent_id' => 326, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => ''],
           ['o_net_data_category_id' => 11, 'sub_category_name' => '— Life, Physical, and Social Science', 'parent_id' => 326, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => ''],
           ['o_net_data_category_id' => 11, 'sub_category_name' => '— Architecture and Engineering', 'parent_id' => 328, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => ''],
           ['o_net_data_category_id' => 11, 'sub_category_name' => '— Computer and Mathematical', 'parent_id' => 328, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => ''],
           ['o_net_data_category_id' => 11, 'sub_category_name' => '— Healthcare Practitioners and Technical', 'parent_id' => 328, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => ''],
           ['o_net_data_category_id' => 11, 'sub_category_name' => '— Life, Physical, and Social Science', 'parent_id' => 328, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => ''],
       ]);*/

        // interests

      /*  ONetDataSubCategory::insert([
            ['o_net_data_category_id' => 2, 'sub_category_name' => 'Realistic', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => 'Work involves designing, building, or repairing of equipment, materials, or structures, engaging in physical activity, or working outdoors. Realistic occupations are often associated with engineering, mechanics and electronics, construction, woodworking, transportation, machine operation, agriculture, animal services, physical or manual labor, athletics, or protective services.'],
            ['o_net_data_category_id' => 2, 'sub_category_name' => 'Investigative', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => 'Work involves studying and researching non-living objects, living organisms, disease or other forms of impairment, or human behavior. Investigative occupations are often associated with physical, life, medical, or social sciences, and can be found in the fields of humanities, mathematics/statistics, information technology, or health care service.'],
            ['o_net_data_category_id' => 2, 'sub_category_name' => 'Artistic', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => 'Work involves creating original visual artwork, performances, written works, food, or music for a variety of media, or applying artistic principles to the design of various objects and materials. Artistic occupations are often associated with visual arts, applied arts and design, performing arts, music, creative writing, media, or culinary art.'],
            ['o_net_data_category_id' => 2, 'sub_category_name' => 'Social', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => 'Work involves helping, teaching, advising, assisting, or providing service to others. Social occupations are often associated with social, health care, personal service, teaching/education, or religious activities.'],
            ['o_net_data_category_id' => 2, 'sub_category_name' => 'Enterprising', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => 'Work involves managing, negotiating, marketing, or selling, typically in a business setting, or leading or advising people in political and legal situations. Enterprising occupations are often associated with business initiatives, sales, marketing/advertising, finance, management/administration, professional advising, public speaking, politics, or law.'],
            ['o_net_data_category_id' => 2, 'sub_category_name' => 'Conventional', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => 'Work involves following procedures and regulations to organize information or data, typically in a business setting. Conventional occupations are often associated with office work, accounting, mathematics/statistics, information technology, finance, or human resources.'],
        ]);*/


        // work values
        /*ONetDataSubCategory::insert([
         ['o_net_data_category_id' => 9, 'sub_category_name' => 'Achievement', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => 'Occupations that satisfy this work value are results oriented and allow employees to use their strongest abilities, giving them a feeling of accomplishment. Corresponding needs are Ability Utilization and Achievement.'],
         ['o_net_data_category_id' => 9, 'sub_category_name' => 'Independence', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => 'Occupations that satisfy this work value allow employees to work on their own and make decisions. Corresponding needs are Creativity, Responsibility and Autonomy.'],
         ['o_net_data_category_id' => 9, 'sub_category_name' => 'Recognition', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => 'Occupations that satisfy this work value offer advancement, potential for leadership, and are often considered prestigious. Corresponding needs are Advancement, Authority, Recognition and Social Status.'],
         ['o_net_data_category_id' => 9, 'sub_category_name' => 'Relationships', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => 'Occupations that satisfy this work value allow employees to provide service to others and work with co-workers in a friendly non-competitive environment. Corresponding needs are Co-workers, Moral Values and Social Service.'],
         ['o_net_data_category_id' => 9, 'sub_category_name' => 'Support', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => 'Occupations that satisfy this work value offer supportive management that stands behind employees. Corresponding needs are Company Policies, Supervision: Human Relations and Supervision: Technical.'],
         ['o_net_data_category_id' => 9, 'sub_category_name' => 'Working Conditions', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => 'Occupations that satisfy this work value offer job security and good working conditions. Corresponding needs are Activity, Compensation, Independence, Security, Variety and Working Conditions.'],
     ]);*/
        // work styles
        /*ONetDataSubCategory::insert([
            ['o_net_data_category_id' => 8, 'sub_category_name' => 'Achievement Orientation', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => 'Job requires personal goal setting, trying to succeed at those goals, and striving to be competent in own work.'],
            ['o_net_data_category_id' => 8, 'sub_category_name' => 'Adjustment', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => 'Job requires maturity, poise, flexibility, and restraint to cope with pressure, stress, criticism, setbacks, personal and work-related problems, etc.'],
            ['o_net_data_category_id' => 8, 'sub_category_name' => 'Conscientiousness', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => 'Job requires dependability, commitment to doing the job correctly and carefully, and being trustworthy, accountable, and attentive to details.'],
            ['o_net_data_category_id' => 8, 'sub_category_name' => 'Independence', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => 'Job requires developing one\'s own ways of doing things, guiding oneself with little or no supervision, and depending on oneself to get things done.'],
            ['o_net_data_category_id' => 8, 'sub_category_name' => 'Interpersonal Orientation', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => 'Job requires being pleasant, cooperative, sensitive to others, easy to get along with, and having a preference for associating with other organization members.'],
            ['o_net_data_category_id' => 8, 'sub_category_name' => 'Practical Intelligence', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => 'Job requires generating useful ideas and thinking things through logically.'],
            ['o_net_data_category_id' => 8, 'sub_category_name' => 'Social Influence', 'parent_id' => 0, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => 'Job requires having an impact on others in the organization, and displaying energy and leadership.'],
            ['o_net_data_category_id' => 8, 'sub_category_name' => 'Achievement/Effort', 'parent_id' => 290, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => 'Job requires establishing and maintaining personally challenging achievement goals and exerting effort toward mastering tasks.'],
            ['o_net_data_category_id' => 8, 'sub_category_name' => 'Initiative', 'parent_id' => 290, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => 'Job requires a willingness to take on responsibilities and challenges.'],
            ['o_net_data_category_id' => 8, 'sub_category_name' => 'Persistence', 'parent_id' => 290, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => 'Job requires persistence in the face of obstacles.'],
            ['o_net_data_category_id' => 8, 'sub_category_name' => 'Adaptability/Flexibility', 'parent_id' => 291, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => 'Job requires being open to change (positive or negative) and to considerable variety in the workplace.'],
            ['o_net_data_category_id' => 8, 'sub_category_name' => 'Self-Control', 'parent_id' => 291, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => 'Job requires maintaining composure, keeping emotions in check, controlling anger, and avoiding aggressive behavior, even in very difficult situations.'],
            ['o_net_data_category_id' => 8, 'sub_category_name' => 'Stress Tolerance', 'parent_id' => 291, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => 'Job requires accepting criticism and dealing calmly and effectively with high-stress situations.'],
            ['o_net_data_category_id' => 8, 'sub_category_name' => 'Attention to Detail', 'parent_id' => 292, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => 'Job requires being careful about detail and thorough in completing work tasks.'],
            ['o_net_data_category_id' => 8, 'sub_category_name' => 'Dependability', 'parent_id' => 292, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => 'Job requires being reliable, responsible, and dependable, and fulfilling obligations.'],
            ['o_net_data_category_id' => 8, 'sub_category_name' => 'Integrity', 'parent_id' => 292, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => 'Job requires being honest and ethical.'],
            ['o_net_data_category_id' => 8, 'sub_category_name' => 'Concern for Others', 'parent_id' => 294, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => 'Job requires being sensitive to others\' needs and feelings and being understanding and helpful on the job. '],
            ['o_net_data_category_id' => 8, 'sub_category_name' => 'Cooperation', 'parent_id' => 294, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => 'Job requires being pleasant with others on the job and displaying a good-natured, cooperative attitude.'],
            ['o_net_data_category_id' => 8, 'sub_category_name' => 'Social Orientation', 'parent_id' => 294, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => 'Job requires preferring to work with others rather than alone, and being personally connected with others on the job.'],
            ['o_net_data_category_id' => 8, 'sub_category_name' => 'Analytical Thinking', 'parent_id' => 295, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => 'Job requires analyzing information and using logic to address work-related issues and problems.'],
            ['o_net_data_category_id' => 8, 'sub_category_name' => 'Innovation', 'parent_id' => 295, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => 'Job requires creativity and alternative thinking to develop new ideas for and answers to work-related problems.'],
            ['o_net_data_category_id' => 8, 'sub_category_name' => 'Leadership', 'parent_id' => 296, 'sub_parent_id' => 0, 'child_id' => 0, 'description' => 'Job requires a willingness to lead, take charge, and offer opinions and direction.'],
        ]);*/

        // work context
       /* ONetDataSubCategory::insert([
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Interpersonal Relationships', 'parent_id' => 0, 'sub_parent_id' => 0, 'description' => 'This category describes the context of the job in terms of human interaction processes.
'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Physical Work Conditions', 'parent_id' => 0, 'sub_parent_id' => 0, 'description' => 'This category describes the work context as it relates to the interactions between the worker and the physical job environment.
'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Structural Job Characteristics', 'parent_id' => 0, 'sub_parent_id' => 0, 'description' => 'This category involves the relationships or interactions between the worker and the structural characteristics of the job.
'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Communication', 'parent_id' => 209, 'sub_parent_id' => 0, 'description' => 'Types and frequency of interactions with other people that are required as part of this job.
'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Conflictual Contact', 'parent_id' => 209, 'sub_parent_id' => 0, 'description' => 'Amount of conflict that the worker will encounter as part of this job.
'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Responsibility for Others', 'parent_id' => 209, 'sub_parent_id' => 0, 'description' => 'Amount of responsibility the worker has for other workers as a part of this job.
'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Role Relationships', 'parent_id' => 209, 'sub_parent_id' => 0, 'description' => 'Importance of different types of interactions with others both inside and outside the organization.
'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Body Positioning', 'parent_id' => 210, 'sub_parent_id' => 0, 'description' => 'Amount of time the worker will spend in a variety of physical positions on this job.
'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Environmental Conditions', 'parent_id' => 210, 'sub_parent_id' => 0, 'description' => 'Description of extreme environmental conditions the worker will be placed in as part of this job.
'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Job Hazards', 'parent_id' => 210, 'sub_parent_id' => 0, 'description' => 'Descriptions of types of hazardous conditions the worker could be exposed to as part of this job. This includes the frequency of exposure, and the likelihood and degree of injury if exposed.
'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Work Attire', 'parent_id' => 210, 'sub_parent_id' => 0, 'description' => 'Dress requirements of this job.
'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Work Setting', 'parent_id' => 210, 'sub_parent_id' => 0, 'description' => 'Description of physical surroundings that the worker will face as part of this job.
'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Competition', 'parent_id' => 211, 'sub_parent_id' => 0, 'description' => 'Amount of competition that the worker will face as part of this job.
'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Criticality of Position', 'parent_id' => 211, 'sub_parent_id' => 0, 'description' => 'Amount of impact the worker has on final products and their outcomes.
'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Pace and Scheduling', 'parent_id' => 211, 'sub_parent_id' => 0, 'description' => 'Description of the role that time plays in the way the worker performs the tasks required by this job.
'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Routine versus Challenging Work', 'parent_id' => 211, 'sub_parent_id' => 0, 'description' => 'The relative amounts of routine versus challenging work the worker will perform as part of this job.
'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Communication Methods', 'parent_id' => 209, 'sub_parent_id' => 212, 'description' => 'How frequently does this job require the use of the following communication methods?
'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Contact With Others', 'parent_id' => 209, 'sub_parent_id' => 212, 'description' => 'How much does this job require the worker to be in contact with others (face-to-face, by telephone, or otherwise) in order to perform it?
'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Deal With Physically Aggressive People', 'parent_id' => 209, 'sub_parent_id' => 213, 'description' => 'How frequently does this job require the worker to deal with physical aggression of violent individuals?
'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Deal With Unpleasant or Angry People', 'parent_id' => 209, 'sub_parent_id' => 213, 'description' => 'How frequently does the worker have to deal with unpleasant, angry, or discourteous individuals as part of the job requirements?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Frequency of Conflict Situations', 'parent_id' => 209, 'sub_parent_id' => 213, 'description' => 'How often are there conflict situations the employee has to face in this job?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Responsibility for Outcomes and Results', 'parent_id' => 209, 'sub_parent_id' => 214, 'description' => 'How responsible is the worker for work outcomes and results of other workers?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Responsible for Others\' Health and Safety', 'parent_id' => 209, 'sub_parent_id' => 214, 'description' => 'How much responsibility is there for the health and safety of others in this job?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Job Interactions', 'parent_id' => 209, 'sub_parent_id' => 215, 'description' => 'How important are interactions requiring the worker to:'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Time Spent in Body Positions', 'parent_id' => 210, 'sub_parent_id' => 216, 'description' => 'How much time in a usual work period does the worker spend:'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Frequency in Environmental Conditions', 'parent_id' => 210, 'sub_parent_id' => 217, 'description' => 'How often during a usual work period is the worker exposed to the following conditions:'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Frequency of Exposure to Job Hazards', 'parent_id' => 210, 'sub_parent_id' => 218, 'description' => 'How often does this job require the worker to be exposed to the following hazards?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Frequency of Wearing Work Attire', 'parent_id' => 210, 'sub_parent_id' => 219, 'description' => 'How often does the worker wear:'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Frequency Required to Work:', 'parent_id' => 210, 'sub_parent_id' => 220, 'description' => 'How frequently does this job require the worker to work:'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Physical Proximity', 'parent_id' => 210, 'sub_parent_id' => 220, 'description' => 'To what extent does this job require the worker to perform job tasks in close physical proximity to other people?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Level of Competition', 'parent_id' => 211, 'sub_parent_id' => 221, 'description' => 'To what extent does this job require the worker to compete or to be aware of competitive pressures?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Consequence of Error', 'parent_id' => 211, 'sub_parent_id' => 222, 'description' => 'How serious would the result usually be if the worker made a mistake that was not readily correctable?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Freedom to Make Decisions', 'parent_id' => 211, 'sub_parent_id' => 222, 'description' => 'How much decision making freedom, without supervision, does the job offer?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Impact of Decisions', 'parent_id' => 211, 'sub_parent_id' => 222, 'description' => 'The frequency and nature of the impact of worker\'s decisions on the organization.'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Duration of Typical Work Week', 'parent_id' => 211, 'sub_parent_id' => 223, 'description' => 'Number of hours typically worked in one week.'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Pace Determined by Speed of Equipment', 'parent_id' => 211, 'sub_parent_id' => 223, 'description' => 'How important is it to this job that the pace is determined by the speed of equipment or machinery? (This does not refer to keeping busy at all times on this job.)'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Time Pressure', 'parent_id' => 211, 'sub_parent_id' => 223, 'description' => 'How often does this job require the worker to meet strict deadlines?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Work Schedules', 'parent_id' => 211, 'sub_parent_id' => 223, 'description' => 'How regular are the work schedules for this job?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Degree of Automation', 'parent_id' => 211, 'sub_parent_id' => 224, 'description' => 'How automated is the job?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Importance of Being Exact or Accurate', 'parent_id' => 211, 'sub_parent_id' => 224, 'description' => 'How important is being very exact or highly accurate in performing this job?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Importance of Repeating Same Tasks', 'parent_id' => 211, 'sub_parent_id' => 224, 'description' => 'How important is repeating the same physical activities (e.g., key entry) or mental activities (e.g., checking entries in a ledger) over and over, without stopping, to performing this job?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Structured versus Unstructured Work', 'parent_id' => 211, 'sub_parent_id' => 224, 'description' => 'To what extent is this job structured for the worker, rather than allowing the worker to determine tasks, priorities, and goals?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Electronic Mail', 'parent_id' => 209, 'sub_parent_id' => 212, 'child_id' => 225, 'description' => 'How often do you use electronic mail in this job?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Face-to-Face Discussions', 'parent_id' => 209, 'sub_parent_id' => 212, 'child_id' => 225, 'description' => 'How often do you have to have face-to-face discussions with individuals or teams in this job?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Letters and Memos', 'parent_id' => 209, 'sub_parent_id' => 212, 'child_id' => 225, 'description' => 'How often does the job require written letters and memos?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Public Speaking', 'parent_id' => 209, 'sub_parent_id' => 212, 'child_id' => 225, 'description' => 'How often do you have to perform public speaking in this job?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Telephone', 'parent_id' => 209, 'sub_parent_id' => 212, 'child_id' => 225, 'description' => 'How often do you have telephone conversations in this job?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Coordinate or Lead Others', 'parent_id' => 209, 'sub_parent_id' => 215, 'child_id' => 232, 'description' => 'How important is it to coordinate or lead others in accomplishing work activities in this job?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Deal With External Customers', 'parent_id' => 209, 'sub_parent_id' => 215, 'child_id' => 232, 'description' => 'How important is it to work with external customers or the public in this job?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Work With Work Group or Team', 'parent_id' => 209, 'sub_parent_id' => 215, 'child_id' => 232, 'description' => 'How important is it to work with others in a group or team in this job?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Spend Time Bending or Twisting the Body', 'parent_id' => 210, 'sub_parent_id' => 215, 'child_id' => 233, 'description' => 'How much does this job require bending or twisting your body?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Spend Time Climbing Ladders, Scaffolds, or Poles', 'parent_id' => 210, 'sub_parent_id' => 215, 'child_id' => 233, 'description' => 'How much does this job require climbing ladders, scaffolds, or poles?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Spend Time Keeping or Regaining Balance', 'parent_id' => 210, 'sub_parent_id' => 215, 'child_id' => 233, 'description' => 'How much does this job require keeping or regaining your balance?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Spend Time Kneeling, Crouching, Stooping, or Crawling', 'parent_id' => 210, 'sub_parent_id' => 215, 'child_id' => 233, 'description' => 'How much does this job require kneeling, crouching, stooping or crawling?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Spend Time Making Repetitive Motions', 'parent_id' => 210, 'sub_parent_id' => 215, 'child_id' => 233, 'description' => 'How much does this job require making repetitive motions?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Spend Time Sitting', 'parent_id' => 210, 'sub_parent_id' => 215, 'child_id' => 233, 'description' => 'How much does this job require sitting?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Spend Time Standing', 'parent_id' => 210, 'sub_parent_id' => 215, 'child_id' => 233, 'description' => 'How much does this job require standing?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Spend Time Using Your Hands to Handle, Control, or Feel Objects, Tools, or Controls', 'parent_id' => 210, 'sub_parent_id' => 215, 'child_id' => 233, 'description' => 'How much does this job require using your hands to handle, control, or feel objects, tools or controls?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Spend Time Walking and Running', 'parent_id' => 210, 'sub_parent_id' => 215, 'child_id' => 233, 'description' => 'How much does this job require walking and running?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Cramped Work Space, Awkward Positions', 'parent_id' => 210, 'sub_parent_id' => 215, 'child_id' => 234, 'description' => 'How often does this job require working in cramped work spaces that requires getting into awkward positions?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Exposed to Contaminants', 'parent_id' => 210, 'sub_parent_id' => 215, 'child_id' => 234, 'description' => 'How often does this job require working exposed to contaminants (such as pollutants, gases, dust or odors)?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Exposed to Whole Body Vibration', 'parent_id' => 210, 'sub_parent_id' => 215, 'child_id' => 234, 'description' => 'How often does this job require exposure to whole body vibration (e.g., operate a jackhammer)?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Extremely Bright or Inadequate Lighting', 'parent_id' => 210, 'sub_parent_id' => 215, 'child_id' => 234, 'description' => 'How often does this job require working in extremely bright or inadequate lighting conditions?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Sounds, Noise Levels Are Distracting or Uncomfortable', 'parent_id' => 210, 'sub_parent_id' => 215, 'child_id' => 234, 'description' => 'How often does this job require working exposed to sounds and noise levels that are distracting or uncomfortable?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Very Hot or Cold Temperatures', 'parent_id' => 210, 'sub_parent_id' => 215, 'child_id' => 234, 'description' => 'How often does this job require working in very hot (above 90 F degrees) or very cold (below 32 F degrees) temperatures?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Exposed to Disease or Infections', 'parent_id' => 210, 'sub_parent_id' => 215, 'child_id' => 235, 'description' => 'How often does this job require exposure to disease/infections?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Exposed to Hazardous Conditions', 'parent_id' => 210, 'sub_parent_id' => 215, 'child_id' => 235, 'description' => 'How often does this job require exposure to hazardous conditions?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Exposed to Hazardous Equipment', 'parent_id' => 210, 'sub_parent_id' => 215, 'child_id' => 235, 'description' => 'How often does this job require exposure to hazardous equipment?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Exposed to High Places', 'parent_id' => 210, 'sub_parent_id' => 215, 'child_id' => 235, 'description' => 'How often does this job require exposure to high places?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Exposed to Minor Burns, Cuts, Bites, or Stings', 'parent_id' => 210, 'sub_parent_id' => 215, 'child_id' => 235, 'description' => 'How often does this job require exposure to minor burns, cuts, bites, or stings?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Exposed to Radiation', 'parent_id' => 210, 'sub_parent_id' => 215, 'child_id' => 235, 'description' => 'How often does this job require exposure to radiation?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Wear Common Protective or Safety Equipment such as Safety Shoes, Glasses, Gloves, Hearing Protection, Hard Hats, or Life Jackets', 'parent_id' => 210, 'sub_parent_id' => 219, 'child_id' => 236, 'description' => 'How much does this job require wearing common protective or safety equipment such as safety shoes, glasses, gloves, hard hats or life jackets?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Wear Specialized Protective or Safety Equipment such as Breathing Apparatus, Safety Harness, Full Protection Suits, or Radiation Protection', 'parent_id' => 210, 'sub_parent_id' => 219, 'child_id' => 236, 'description' => 'How much does this job require wearing specialized protective or safety equipment such as breathing apparatus, safety harness, full protection suits, or radiation protection?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'In an Enclosed Vehicle or Equipment', 'parent_id' => 210, 'sub_parent_id' => 220, 'child_id' => 237, 'description' => 'How often does this job require working in a closed vehicle or equipment (e.g., car)?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'In an Open Vehicle or Equipment', 'parent_id' => 210, 'sub_parent_id' => 220, 'child_id' => 237, 'description' => 'How often does this job require working in an open vehicle or equipment (e.g., tractor)?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Indoors, Environmentally Controlled', 'parent_id' => 210, 'sub_parent_id' => 220, 'child_id' => 237, 'description' => 'How often does this job require working indoors in environmentally controlled conditions?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Indoors, Not Environmentally Controlled', 'parent_id' => 210, 'sub_parent_id' => 220, 'child_id' => 237, 'description' => 'How often does this job require working indoors in non-controlled environmental conditions (e.g., warehouse without heat)?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Outdoors, Exposed to Weather', 'parent_id' => 210, 'sub_parent_id' => 220, 'child_id' => 237, 'description' => 'How often does this job require working outdoors, exposed to all weather conditions?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Outdoors, Under Cover', 'parent_id' => 210, 'sub_parent_id' => 220, 'child_id' => 237, 'description' => 'How often does this job require working outdoors, under cover (e.g., structure with roof but no walls)?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Frequency of Decision Making', 'parent_id' => 211, 'sub_parent_id' => 222, 'child_id' => 242, 'description' => 'How frequently is the worker required to make decisions that affect other people, the financial resources, and/or the image and reputation of the organization?'],
            ['o_net_data_category_id' => 7, 'sub_category_name' => 'Impact of Decisions on Co-workers or Company Results', 'parent_id' => 211, 'sub_parent_id' => 222, 'child_id' => 242, 'description' => 'What results do your decisions usually have on other people or the image or reputation or financial resources of your employer?'],
        ]);*/
        // work activities
        /* ONetDataSubCategory::insert([
              ['o_net_data_category_id' => 6, 'sub_category_name' => 'Information Input', 'parent_id' => 0,'sub_parent_id'  => 0, 'description' => 'Where and how are the information and data gained that are needed to perform this job?'],
              ['o_net_data_category_id' => 6, 'sub_category_name' => 'Interacting With Others', 'parent_id' => 0,'sub_parent_id'  => 0, 'description' => 'What interactions with other persons or supervisory activities occur while performing this job?'],
              ['o_net_data_category_id' => 6, 'sub_category_name' => 'Mental Processes', 'parent_id' => 0,'sub_parent_id'  => 0, 'description' => 'What processing, planning, problem-solving, decision-making, and innovating activities are performed with job-relevant information?'],
              ['o_net_data_category_id' => 6, 'sub_category_name' => 'Work Output', 'parent_id' => 0,'sub_parent_id'  => 0, 'description' => 'What physical activities are performed, what equipment and vehicles are operated/controlled, and what complex/technical activities are accomplished as job outputs?'],
              ['o_net_data_category_id' => 6, 'sub_category_name' => 'Identifying and Evaluating Job-Relevant Information', 'parent_id' => 155,'sub_parent_id'  => 0, 'description' => 'How is information interpreted to perform this job?'],
              ['o_net_data_category_id' => 6, 'sub_category_name' => 'Looking for and Receiving Job-Related Information', 'parent_id' => 155,'sub_parent_id'  => 0, 'description' => 'How is information obtained to perform this job?'],
              ['o_net_data_category_id' => 6, 'sub_category_name' => 'Estimating the Quantifiable Characteristics of Products, Events, or Information', 'parent_id' => 155,'sub_parent_id'  => 159, 'description' => 'Estimating sizes, distances, and quantities; or determining time, costs, resources, or materials needed to perform a work activity.'],
              ['o_net_data_category_id' => 6, 'sub_category_name' => 'Identifying Objects, Actions, and Events', 'parent_id' => 155,'sub_parent_id'  => 159, 'description' => 'Identifying information by categorizing, estimating, recognizing differences or similarities, and detecting changes in circumstances or events.'],
              ['o_net_data_category_id' => 6, 'sub_category_name' => 'Inspecting Equipment, Structures, or Materials', 'parent_id' => 155,'sub_parent_id'  => 159, 'description' => 'Inspecting equipment, structures, or materials to identify the cause of errors or other problems or defects.'],
                ['o_net_data_category_id' => 6, 'sub_category_name' => 'Getting Information', 'parent_id' => 155,'sub_parent_id'  => 160, 'description' => 'Observing, receiving, and otherwise obtaining information from all relevant sources.'],
                ['o_net_data_category_id' => 6, 'sub_category_name' => 'Monitoring Processes, Materials, or Surroundings', 'parent_id' => 155,'sub_parent_id'  => 160, 'description' => 'Monitoring and reviewing information from materials, events, or the environment, to detect or assess problems.'],
                ['o_net_data_category_id' => 6, 'sub_category_name' => 'Administering', 'parent_id' => 156,'sub_parent_id'  => 0, 'description' => 'What administrative, staffing, monitoring, or controlling activities are done while performing this job?'],
                ['o_net_data_category_id' => 6, 'sub_category_name' => 'Communicating and Interacting', 'parent_id' => 156,'sub_parent_id'  => 0, 'description' => 'What interactions with other people occur while performing this job?'],
                ['o_net_data_category_id' => 6, 'sub_category_name' => 'Coordinating, Developing, Managing, and Advising', 'parent_id' => 156,'sub_parent_id'  => 0, 'description' => 'What coordinating, managerial, or advisory activities are done while performing this job?'],
            ['o_net_data_category_id' => 6, 'sub_category_name' => 'Monitoring and Controlling Resources', 'parent_id' => 156,'sub_parent_id'  => 166, 'description' => 'Monitoring and controlling resources and overseeing the spending of money.'],
            ['o_net_data_category_id' => 6, 'sub_category_name' => 'Performing Administrative Activities', 'parent_id' => 156,'sub_parent_id'  => 166, 'description' => 'Performing day-to-day administrative tasks such as maintaining information files and processing paperwork.'],
            ['o_net_data_category_id' => 6, 'sub_category_name' => 'Staffing Organizational Units', 'parent_id' => 156,'sub_parent_id'  => 166, 'description' => 'Recruiting, interviewing, selecting, hiring, and promoting employees in an organization.'],
            ['o_net_data_category_id' => 6, 'sub_category_name' => 'Information and Data Processing', 'parent_id' => 157,'sub_parent_id'  => 0, 'description' => 'How is information processed to perform this job?'],
            ['o_net_data_category_id' => 6, 'sub_category_name' => 'Reasoning and Decision Making', 'parent_id' => 157,'sub_parent_id'  => 0, 'description' => 'What decisions are made and problems solved in performing this job?'],
            ['o_net_data_category_id' => 6, 'sub_category_name' => 'Analyzing Data or Information', 'parent_id' => 157,'sub_parent_id'  => 172, 'description' => 'Identifying the underlying principles, reasons, or facts of information by breaking down information or data into separate parts.'],
            ['o_net_data_category_id' => 6, 'sub_category_name' => 'Evaluating Information to Determine Compliance with Standards', 'parent_id' => 157,'sub_parent_id'  => 172, 'description' => 'Using relevant information and individual judgment to determine whether events or processes comply with laws, regulations, or standards.'],
            ['o_net_data_category_id' => 6, 'sub_category_name' => 'Judging the Qualities of Objects, Services, or People', 'parent_id' => 157,'sub_parent_id'  => 172, 'description' => 'Assessing the value, importance, or quality of things or people.'],
            ['o_net_data_category_id' => 6, 'sub_category_name' => 'Processing Information', 'parent_id' => 157,'sub_parent_id'  => 172, 'description' => 'Compiling, coding, categorizing, calculating, tabulating, auditing, or verifying information or data.'],
            ['o_net_data_category_id' => 6, 'sub_category_name' => 'Developing Objectives and Strategies', 'parent_id' => 157,'sub_parent_id'  => 173, 'description' => 'Establishing long-range objectives and specifying the strategies and actions to achieve them.'],
            ['o_net_data_category_id' => 6, 'sub_category_name' => 'Making Decisions and Solving Problems', 'parent_id' => 157,'sub_parent_id'  => 173, 'description' => 'Analyzing information and evaluating results to choose the best solution and solve problems.'],
            ['o_net_data_category_id' => 6, 'sub_category_name' => 'Organizing, Planning, and Prioritizing Work', 'parent_id' => 157,'sub_parent_id'  => 173, 'description' => 'Developing specific goals and plans to prioritize, organize, and accomplish your work.'],
            ['o_net_data_category_id' => 6, 'sub_category_name' => 'Scheduling Work and Activities', 'parent_id' => 157,'sub_parent_id'  => 173, 'description' => 'Scheduling events, programs, and activities, as well as the work of others.'],
            ['o_net_data_category_id' => 6, 'sub_category_name' => 'Thinking Creatively', 'parent_id' => 157,'sub_parent_id'  => 173, 'description' => 'Developing, designing, or creating new applications, ideas, relationships, systems, or products, including artistic contributions.'],
            ['o_net_data_category_id' => 6, 'sub_category_name' => 'Updating and Using Relevant Knowledge', 'parent_id' => 157,'sub_parent_id'  => 173, 'description' => 'Keeping up-to-date technically and applying new knowledge to your job.'],
            ['o_net_data_category_id' => 6, 'sub_category_name' => 'Performing Complex and Technical Activities', 'parent_id' => 158,'sub_parent_id'  => 0, 'description' => 'What skilled activities using coordinated movements are done to perform this job?'],
            ['o_net_data_category_id' => 6, 'sub_category_name' => 'Performing Physical and Manual Work Activities', 'parent_id' => 158,'sub_parent_id'  => 0, 'description' => 'What activities using the body and hands are done to perform this job?'],
            ['o_net_data_category_id' => 6, 'sub_category_name' => 'Documenting/Recording Information', 'parent_id' => 158,'sub_parent_id'  => 184, 'description' => 'Entering, transcribing, recording, storing, or maintaining information in written or electronic/magnetic form.'],
            ['o_net_data_category_id' => 6, 'sub_category_name' => 'Drafting, Laying Out, and Specifying Technical Devices, Parts, and Equipment', 'parent_id' => 158,'sub_parent_id'  => 184, 'description' => 'Providing documentation, detailed instructions, drawings, or specifications to tell others about how devices, parts, equipment, or structures are to be fabricated, constructed, assembled, modified, maintained, or used.'],
            ['o_net_data_category_id' => 6, 'sub_category_name' => 'Repairing and Maintaining Electronic Equipment', 'parent_id' => 158,'sub_parent_id'  => 184, 'description' => 'Servicing, repairing, calibrating, regulating, fine-tuning, or testing machines, devices, and equipment that operate primarily on the basis of electrical or electronic (not mechanical) principles.'],
            ['o_net_data_category_id' => 6, 'sub_category_name' => 'Repairing and Maintaining Mechanical Equipment', 'parent_id' => 158,'sub_parent_id'  => 184, 'description' => 'Servicing, repairing, adjusting, and testing machines, devices, moving parts, and equipment that operate primarily on the basis of mechanical (not electronic) principles.'],
            ['o_net_data_category_id' => 6, 'sub_category_name' => 'Working with Computers', 'parent_id' => 158,'sub_parent_id'  => 184, 'description' => 'Using computers and computer systems (including hardware and software) to program, write software, set up functions, enter data, or process information.'],
            ['o_net_data_category_id' => 6, 'sub_category_name' => 'Controlling Machines and Processes', 'parent_id' => 158,'sub_parent_id'  => 185, 'description' => 'Using either control mechanisms or direct physical activity to operate machines or processes (not including computers or vehicles).'],
            ['o_net_data_category_id' => 6, 'sub_category_name' => 'Handling and Moving Objects', 'parent_id' => 158,'sub_parent_id'  => 185, 'description' => 'Using hands and arms in handling, installing, positioning, and moving materials, and manipulating things.'],
            ['o_net_data_category_id' => 6, 'sub_category_name' => 'Operating Vehicles, Mechanized Devices, or Equipment', 'parent_id' => 158,'sub_parent_id'  => 185, 'description' => 'Running, maneuvering, navigating, or driving vehicles or mechanized equipment, such as forklifts, passenger vehicles, aircraft, or watercraft.'],
            ['o_net_data_category_id' => 6, 'sub_category_name' => 'Performing General Physical Activities', 'parent_id' => 158,'sub_parent_id'  => 185, 'description' => 'Performing physical activities that require considerable use of your arms and legs and moving your whole body, such as climbing, lifting, balancing, walking, stooping, and handling materials.'],
            ['o_net_data_category_id' => 6, 'sub_category_name' => 'Assisting and Caring for Others', 'parent_id' => 156,'sub_parent_id'  => 167, 'description' => 'Providing personal assistance, medical attention, emotional support, or other personal care to others such as coworkers, customers, or patients.'],
            ['o_net_data_category_id' => 6, 'sub_category_name' => 'Communicating with People Outside the Organization', 'parent_id' => 156,'sub_parent_id'  => 167, 'description' => 'Communicating with people outside the organization, representing the organization to customers, the public, government, and other external sources. This information can be exchanged in person, in writing, or by telephone or e-mail.'],
            ['o_net_data_category_id' => 6, 'sub_category_name' => 'Communicating with Supervisors, Peers, or Subordinates', 'parent_id' => 156,'sub_parent_id'  => 167, 'description' => 'Providing information to supervisors, co-workers, and subordinates by telephone, in written form, e-mail, or in person.'],
            ['o_net_data_category_id' => 6, 'sub_category_name' => 'Establishing and Maintaining Interpersonal Relationships', 'parent_id' => 156,'sub_parent_id'  => 167, 'description' => 'Developing constructive and cooperative working relationships with others, and maintaining them over time.'],
            ['o_net_data_category_id' => 6, 'sub_category_name' => 'Interpreting the Meaning of Information for Others', 'parent_id' => 156,'sub_parent_id'  => 167, 'description' => 'Translating or explaining what information means and how it can be used.'],
            ['o_net_data_category_id' => 6, 'sub_category_name' => 'Performing for or Working Directly with the Public', 'parent_id' => 156,'sub_parent_id'  => 167, 'description' => 'Performing for people or dealing directly with the public. This includes serving customers in restaurants and stores, and receiving clients or guests.'],
            ['o_net_data_category_id' => 6, 'sub_category_name' => 'Resolving Conflicts and Negotiating with Others', 'parent_id' => 156,'sub_parent_id'  => 167, 'description' => 'Handling complaints, settling disputes, and resolving grievances and conflicts, or otherwise negotiating with others.'],
            ['o_net_data_category_id' => 6, 'sub_category_name' => 'Selling or Influencing Others', 'parent_id' => 156,'sub_parent_id'  => 167, 'description' => 'Convincing others to buy merchandise/goods or to otherwise change their minds or actions.'],
            ['o_net_data_category_id' => 6, 'sub_category_name' => 'Coaching and Developing Others', 'parent_id' => 156,'sub_parent_id'  => 168, 'description' => 'Identifying the developmental needs of others and coaching, mentoring, or otherwise helping others to improve their knowledge or skills.'],
            ['o_net_data_category_id' => 6, 'sub_category_name' => 'Coordinating the Work and Activities of Others', 'parent_id' => 156,'sub_parent_id'  => 168, 'description' => 'Getting members of a group to work together to accomplish tasks.'],
            ['o_net_data_category_id' => 6, 'sub_category_name' => 'Developing and Building Teams', 'parent_id' => 156,'sub_parent_id'  => 168, 'description' => 'Encouraging and building mutual trust, respect, and cooperation among team members.'],
            ['o_net_data_category_id' => 6, 'sub_category_name' => 'Guiding, Directing, and Motivating Subordinates', 'parent_id' => 156,'sub_parent_id'  => 168, 'description' => 'Providing guidance and direction to subordinates, including setting performance standards and monitoring performance.'],
            ['o_net_data_category_id' => 6, 'sub_category_name' => 'Providing Consultation and Advice to Others', 'parent_id' => 156,'sub_parent_id'  => 168, 'description' => 'Providing guidance and expert advice to management or other groups on technical, systems-, or process-related topics.'],
            ['o_net_data_category_id' => 6, 'sub_category_name' => 'Training and Teaching Others', 'parent_id' => 156,'sub_parent_id'  => 168, 'description' => 'Identifying the educational needs of others, developing formal educational or training programs or classes, and teaching or instructing others.'],
        ]);*/
        // skills cross-functional
        /*ONetDataSubCategory::insert([
            ['o_net_data_category_id' => 5, 'sub_category_name' => 'Complex Problem Solving Skills', 'parent_id' => 0,'sub_parent_id'  => 0, 'description' => 'Developed capacities used to solve novel, ill-defined problems in complex, real-world settings.'],
            ['o_net_data_category_id' => 5, 'sub_category_name' => 'Resource Management Skills', 'parent_id' => 0,'sub_parent_id'  => 0, 'description' => 'Developed capacities used to allocate resources efficiently.'],
            ['o_net_data_category_id' => 5, 'sub_category_name' => 'Social Skills', 'parent_id' => 0,'sub_parent_id'  => 0, 'description' => 'Developed capacities used to work with people to achieve goals.'],
            ['o_net_data_category_id' => 5, 'sub_category_name' => 'Systems Skills', 'parent_id' => 0,'sub_parent_id'  => 0, 'description' => 'Developed capacities used to understand, monitor, and improve socio-technical systems.'],
            ['o_net_data_category_id' => 5, 'sub_category_name' => 'Technical Skills', 'parent_id' => 0,'sub_parent_id'  => 0, 'description' => 'Developed capacities used to design, set-up, operate, and correct malfunctions involving application of machines or technological systems.'],
            ['o_net_data_category_id' => 5, 'sub_category_name' => 'Complex Problem Solving', 'parent_id' => 125,'sub_parent_id'  => 0, 'description' => 'Identifying complex problems and reviewing related information to develop and evaluate options and implement solutions.'],
            ['o_net_data_category_id' => 5, 'sub_category_name' => 'Management of Financial Resources', 'parent_id' => 126,'sub_parent_id'  => 0, 'description' => 'Determining how money will be spent to get the work done, and accounting for these expenditures.'],
            ['o_net_data_category_id' => 5, 'sub_category_name' => 'Management of Material Resources', 'parent_id' => 126,'sub_parent_id'  => 0, 'description' => 'Obtaining and seeing to the appropriate use of equipment, facilities, and materials needed to do certain work.'],
            ['o_net_data_category_id' => 5, 'sub_category_name' => 'Management of Personnel Resources', 'parent_id' => 126,'sub_parent_id'  => 0, 'description' => 'Motivating, developing, and directing people as they work, identifying the best people for the job.'],
            ['o_net_data_category_id' => 5, 'sub_category_name' => 'Time Management', 'parent_id' => 126,'sub_parent_id'  => 0, 'description' => 'Managing one\'s own time and the time of others.
        '],
            ['o_net_data_category_id' => 5, 'sub_category_name' => 'Coordination', 'parent_id' => 127,'sub_parent_id'  => 0, 'description' => 'Adjusting actions in relation to others\' actions.'],
            ['o_net_data_category_id' => 5, 'sub_category_name' => 'Instructing', 'parent_id' => 127,'sub_parent_id'  => 0, 'description' => 'Teaching others how to do something.'],
            ['o_net_data_category_id' => 5, 'sub_category_name' => 'Negotiation', 'parent_id' => 127,'sub_parent_id'  => 0, 'description' => 'Bringing others together and trying to reconcile differences.'],
            ['o_net_data_category_id' => 5, 'sub_category_name' => 'Persuasion', 'parent_id' => 127,'sub_parent_id'  => 0, 'description' => 'Persuading others to change their minds or behavior.'],
            ['o_net_data_category_id' => 5, 'sub_category_name' => 'Service Orientation', 'parent_id' => 127,'sub_parent_id'  => 0, 'description' => 'Actively looking for ways to help people.'],
            ['o_net_data_category_id' => 5, 'sub_category_name' => 'Social Perceptiveness', 'parent_id' => 127,'sub_parent_id'  => 0, 'description' => 'Being aware of others\' reactions and understanding why they react as they do.'],
            ['o_net_data_category_id' => 5, 'sub_category_name' => 'Judgment and Decision Making', 'parent_id' => 128,'sub_parent_id'  => 0, 'description' => 'Considering the relative costs and benefits of potential actions to choose the most appropriate one.'],
            ['o_net_data_category_id' => 5, 'sub_category_name' => 'Systems Analysis', 'parent_id' => 128,'sub_parent_id'  => 0, 'description' => 'Determining how a system should work and how changes in conditions, operations, and the environment will affect outcomes.'],
            ['o_net_data_category_id' => 5, 'sub_category_name' => 'Systems Evaluation', 'parent_id' => 128,'sub_parent_id'  => 0, 'description' => 'Identifying measures or indicators of system performance and the actions needed to improve or correct performance, relative to the goals of the system.'],
            ['o_net_data_category_id' => 5, 'sub_category_name' => 'Equipment Maintenance', 'parent_id' => 129,'sub_parent_id'  => 0, 'description' => 'Performing routine maintenance on equipment and determining when and what kind of maintenance is needed.'],
            ['o_net_data_category_id' => 5, 'sub_category_name' => 'Equipment Selection', 'parent_id' => 129,'sub_parent_id'  => 0, 'description' => 'Determining the kind of tools and equipment needed to do a job.'],
            ['o_net_data_category_id' => 5, 'sub_category_name' => 'Installation', 'parent_id' => 129,'sub_parent_id'  => 0, 'description' => 'Installing equipment, machines, wiring, or programs to meet specifications.'],
            ['o_net_data_category_id' => 5, 'sub_category_name' => 'Operation and Control', 'parent_id' => 129,'sub_parent_id'  => 0, 'description' => 'Controlling operations of equipment or systems.'],
            ['o_net_data_category_id' => 5, 'sub_category_name' => 'Operations Analysis', 'parent_id' => 129,'sub_parent_id'  => 0, 'description' => 'Analyzing needs and product requirements to create a design.'],
            ['o_net_data_category_id' => 5, 'sub_category_name' => 'Operations Monitoring', 'parent_id' => 129,'sub_parent_id'  => 0, 'description' => 'Watching gauges, dials, or other indicators to make sure a machine is working properly.'],
            ['o_net_data_category_id' => 5, 'sub_category_name' => 'Programming', 'parent_id' => 129,'sub_parent_id'  => 0, 'description' => 'Writing computer programs for various purposes.'],
            ['o_net_data_category_id' => 5, 'sub_category_name' => 'Quality Control Analysis', 'parent_id' => 129,'sub_parent_id'  => 0, 'description' => 'Conducting tests and inspections of products, services, or processes to evaluate quality or performance.'],
            ['o_net_data_category_id' => 5, 'sub_category_name' => 'Repairing', 'parent_id' => 129,'sub_parent_id'  => 0, 'description' => 'Repairing machines or systems using the needed tools.'],
            ['o_net_data_category_id' => 5, 'sub_category_name' => 'Technology Design', 'parent_id' => 129,'sub_parent_id'  => 0, 'description' => 'Generating or adapting equipment and technology to serve user needs.'],
            ['o_net_data_category_id' => 5, 'sub_category_name' => 'Troubleshooting', 'parent_id' => 129,'sub_parent_id'  => 0, 'description' => 'Determining causes of operating errors and deciding what to do about it.'],
        ]);*/

        //skills basic
        /*ONetDataSubCategory::insert([
            ['o_net_data_category_id' => 4, 'sub_category_name' => 'Content', 'parent_id' => 0,'sub_parent_id'  => 0, 'description' => 'Background structures needed to work with and acquire more specific skills in a variety of different domains.'],
            ['o_net_data_category_id' => 4, 'sub_category_name' => 'Process', 'parent_id' => 0,'sub_parent_id'  => 0, 'description' => 'Procedures that contribute to the more rapid acquisition of knowledge and skill across a variety of domains.'],
            ['o_net_data_category_id' => 4, 'sub_category_name' => 'Active Listening', 'parent_id' => 113,'sub_parent_id'  => 0, 'description' => 'Giving full attention to what other people are saying, taking time to understand the points being made, asking questions as appropriate, and not interrupting at inappropriate times.
'],
            ['o_net_data_category_id' => 4, 'sub_category_name' => 'Mathematics', 'parent_id' => 113,'sub_parent_id'  => 0, 'description' => 'Using mathematics to solve problems.
'],
            ['o_net_data_category_id' => 4, 'sub_category_name' => 'Reading Comprehension', 'parent_id' => 113,'sub_parent_id'  => 0, 'description' => 'Understanding written sentences and paragraphs in work-related documents.'],
            ['o_net_data_category_id' => 4, 'sub_category_name' => 'Science', 'parent_id' => 113,'sub_parent_id'  => 0, 'description' => 'Using scientific rules and methods to solve problems.'],
            ['o_net_data_category_id' => 4, 'sub_category_name' => 'Speaking', 'parent_id' => 113,'sub_parent_id'  => 0, 'description' => 'Talking to others to convey information effectively.'],
            ['o_net_data_category_id' => 4, 'sub_category_name' => 'Writing', 'parent_id' => 113,'sub_parent_id'  => 0, 'description' => 'Communicating effectively in writing as appropriate for the needs of the audience.'],
            ['o_net_data_category_id' => 4, 'sub_category_name' => 'Active Learning', 'parent_id' => 114,'sub_parent_id'  => 0, 'description' => 'Understanding the implications of new information for both current and future problem-solving and decision-making.'],
            ['o_net_data_category_id' => 4, 'sub_category_name' => 'Critical Thinking', 'parent_id' => 114,'sub_parent_id'  => 0, 'description' => 'Using logic and reasoning to identify the strengths and weaknesses of alternative solutions, conclusions, or approaches to problems.'],
            ['o_net_data_category_id' => 4, 'sub_category_name' => 'Learning Strategies', 'parent_id' => 114,'sub_parent_id'  => 0, 'description' => 'Selecting and using training/instructional methods and procedures appropriate for the situation when learning or teaching new things.'],
            ['o_net_data_category_id' => 4, 'sub_category_name' => 'Monitoring', 'parent_id' => 114,'sub_parent_id'  => 0, 'description' => 'Monitoring/Assessing performance of yourself, other individuals, or organizations to make improvements or take corrective action.'],
        ]);*/

        //knowledge
        /*ONetDataSubCategory::insert([
            ['o_net_data_category_id' => 3, 'sub_category_name' => 'Arts and Humanities', 'parent_id' => 0,'sub_parent_id'  => 0, 'description' => 'Knowledge of facts and principles related to the branches of learning concerned with human thought, language, and the arts.'],
            ['o_net_data_category_id' => 3, 'sub_category_name' => 'Business and Management', 'parent_id' => 0,'sub_parent_id'  => 0, 'description' => 'Knowledge of principles and facts related to business administration and accounting, human and material resource management in organizations, sales and marketing, economics, and office information and organizing systems.'],
            ['o_net_data_category_id' => 3, 'sub_category_name' => 'Communications', 'parent_id' => 0,'sub_parent_id'  => 0, 'description' => 'Knowledge of the science and art of delivering information.'],
            ['o_net_data_category_id' => 3, 'sub_category_name' => 'Engineering and Technology', 'parent_id' => 0,'sub_parent_id'  => 0, 'description' => 'Knowledge of the design, development, and application of technology for specific purposes.'],
            ['o_net_data_category_id' => 3, 'sub_category_name' => 'Health Services', 'parent_id' => 0,'sub_parent_id'  => 0, 'description' => 'Knowledge of principles and facts regarding diagnosing, curing, and preventing disease, and improving and preserving physical and mental health and well-being.'],
            ['o_net_data_category_id' => 3, 'sub_category_name' => 'Law and Public Safety', 'parent_id' => 0,'sub_parent_id'  => 0, 'description' => 'Knowledge of regulations and methods for maintaining people and property free from danger, injury, or damage; the rules of public conduct established and enforced by legislation, and the political process establishing such rules.'],
            ['o_net_data_category_id' => 3, 'sub_category_name' => 'Manufacturing and Production', 'parent_id' => 0,'sub_parent_id'  => 0, 'description' => 'Knowledge of principles and facts related to the production, processing, storage, and distribution of manufactured and agricultural goods.'],
            ['o_net_data_category_id' => 3, 'sub_category_name' => 'Mathematics and Science', 'parent_id' => 0,'sub_parent_id'  => 0, 'description' => 'Knowledge of the history, theories, methods, and applications of the physical, biological, social, mathematical, and geography.'],
            ['o_net_data_category_id' => 3, 'sub_category_name' => 'English Language', 'parent_id' => 72,'sub_parent_id'  => 0, 'description' => 'Knowledge of the structure and content of the English language including the meaning and spelling of words, rules of composition, and grammar.'],
            ['o_net_data_category_id' => 3, 'sub_category_name' => 'Fine Arts', 'parent_id' => 72,'sub_parent_id'  => 0, 'description' => 'Knowledge of the theory and techniques required to compose, produce, and perform works of music, dance, visual arts, drama, and sculpture.'],
            ['o_net_data_category_id' => 3, 'sub_category_name' => 'Foreign Language', 'parent_id' => 72,'sub_parent_id'  => 0, 'description' => 'Knowledge of the structure and content of a foreign (non-English) language including the meaning and spelling of words, rules of composition and grammar, and pronunciation.'],
            ['o_net_data_category_id' => 3, 'sub_category_name' => 'History and Archeology', 'parent_id' => 72,'sub_parent_id'  => 0, 'description' => 'Knowledge of historical events and their causes, indicators, and effects on civilizations and cultures.'],
            ['o_net_data_category_id' => 3, 'sub_category_name' => 'Philosophy and Theology', 'parent_id' => 72,'sub_parent_id'  => 0, 'description' => 'Knowledge of different philosophical systems and religions. This includes their basic principles, values, ethics, ways of thinking, customs, practices, and their impact on human culture.'],
            ['o_net_data_category_id' => 3, 'sub_category_name' => 'Administration and Management', 'parent_id' => 73,'sub_parent_id'  => 0, 'description' => 'Knowledge of business and management principles involved in strategic planning, resource allocation, human resources modeling, leadership technique, production methods, and coordination of people and resources.'],
            ['o_net_data_category_id' => 3, 'sub_category_name' => 'Administrative', 'parent_id' => 73,'sub_parent_id'  => 0, 'description' => 'Knowledge of administrative and office procedures and systems such as word processing, managing files and records, stenography and transcription, designing forms, and workplace terminology.'],
            ['o_net_data_category_id' => 3, 'sub_category_name' => 'Customer and Personal Service', 'parent_id' => 73,'sub_parent_id'  => 0, 'description' => 'Knowledge of principles and processes for providing customer and personal services. This includes customer needs assessment, meeting quality standards for services, and evaluation of customer satisfaction.'],
            ['o_net_data_category_id' => 3, 'sub_category_name' => 'Economics and Accounting', 'parent_id' => 73,'sub_parent_id'  => 0, 'description' => 'Knowledge of economic and accounting principles and practices, the financial markets, banking, and the analysis and reporting of financial data.'],
            ['o_net_data_category_id' => 3, 'sub_category_name' => 'Personnel and Human Resources', 'parent_id' => 73,'sub_parent_id'  => 0, 'description' => 'Knowledge of principles and procedures for personnel recruitment, selection, training, compensation and benefits, labor relations and negotiation, and personnel information systems.'],
            ['o_net_data_category_id' => 3, 'sub_category_name' => 'Sales and Marketing', 'parent_id' => 73,'sub_parent_id'  => 0, 'description' => 'Knowledge of principles and methods for showing, promoting, and selling products or services. This includes marketing strategy and tactics, product demonstration, sales techniques, and sales control systems.'],
            ['o_net_data_category_id' => 3, 'sub_category_name' => 'Communications and Media', 'parent_id' => 74,'sub_parent_id'  => 0, 'description' => 'Knowledge of media production, communication, and dissemination techniques and methods. This includes alternative ways to inform and entertain via written, oral, and visual media.'],
            ['o_net_data_category_id' => 3, 'sub_category_name' => 'Telecommunications', 'parent_id' => 74,'sub_parent_id'  => 0, 'description' => 'Knowledge of transmission, broadcasting, switching, control, and operation of telecommunications systems.'],
            ['o_net_data_category_id' => 3, 'sub_category_name' => 'Building and Construction', 'parent_id' => 75,'sub_parent_id'  => 0, 'description' => 'Knowledge of materials, methods, and the tools involved in the construction or repair of houses, buildings, or other structures such as highways and roads.'],
            ['o_net_data_category_id' => 3, 'sub_category_name' => 'Computers and Electronics', 'parent_id' => 75,'sub_parent_id'  => 0, 'description' => 'Knowledge of circuit boards, processors, chips, electronic equipment, and computer hardware and software, including applications and programming.'],
            ['o_net_data_category_id' => 3, 'sub_category_name' => 'Design', 'parent_id' => 75,'sub_parent_id'  => 0, 'description' => 'Knowledge of design techniques, tools, and principles involved in production of precision technical plans, blueprints, drawings, and models.'],
            ['o_net_data_category_id' => 3, 'sub_category_name' => 'Engineering and Technology', 'parent_id' => 75,'sub_parent_id'  => 0, 'description' => 'Knowledge of the practical application of engineering science and technology. This includes applying principles, techniques, procedures, and equipment to the design and production of various goods and services.'],
            ['o_net_data_category_id' => 3, 'sub_category_name' => 'Mechanical', 'parent_id' => 75,'sub_parent_id'  => 0, 'description' => 'Knowledge of machines and tools, including their designs, uses, repair, and maintenance.'],
            ['o_net_data_category_id' => 3, 'sub_category_name' => 'Medicine and Dentistry', 'parent_id' => 76,'sub_parent_id'  => 0, 'description' => 'Knowledge of the information and techniques needed to diagnose and treat human injuries, diseases, and deformities. This includes symptoms, treatment alternatives, drug properties and interactions, and preventive health-care measures.'],
            ['o_net_data_category_id' => 3, 'sub_category_name' => 'Therapy and Counseling', 'parent_id' => 76,'sub_parent_id'  => 0, 'description' => 'Knowledge of principles, methods, and procedures for diagnosis, treatment, and rehabilitation of physical and mental dysfunctions, and for career counseling and guidance.'],
            ['o_net_data_category_id' => 3, 'sub_category_name' => 'Law and Government', 'parent_id' => 77,'sub_parent_id'  => 0, 'description' => 'Knowledge of laws, legal codes, court procedures, precedents, government regulations, executive orders, agency rules, and the democratic political process.'],
            ['o_net_data_category_id' => 3, 'sub_category_name' => 'Public Safety and Security', 'parent_id' => 77,'sub_parent_id'  => 0, 'description' => 'Knowledge of relevant equipment, policies, procedures, and strategies to promote effective local, state, or national security operations for the protection of people, data, property, and institutions.'],
            ['o_net_data_category_id' => 3, 'sub_category_name' => 'Food Production', 'parent_id' => 78,'sub_parent_id'  => 0, 'description' => 'Knowledge of techniques and equipment for planting, growing, and harvesting food products (both plant and animal) for consumption, including storage/handling techniques.'],
            ['o_net_data_category_id' => 3, 'sub_category_name' => 'Production and Processing', 'parent_id' => 78,'sub_parent_id'  => 0, 'description' => 'Knowledge of raw materials, production processes, quality control, costs, and other techniques for maximizing the effective manufacture and distribution of goods.'],
            ['o_net_data_category_id' => 3, 'sub_category_name' => 'Biology', 'parent_id' => 79,'sub_parent_id'  => 0, 'description' => 'Knowledge of plant and animal organisms, their tissues, cells, functions, interdependencies, and interactions with each other and the environment.'],
            ['o_net_data_category_id' => 3, 'sub_category_name' => 'Chemistry', 'parent_id' => 79,'sub_parent_id'  => 0, 'description' => 'Knowledge of the chemical composition, structure, and properties of substances and of the chemical processes and transformations that they undergo. This includes uses of chemicals and their interactions, danger signs, production techniques, and disposal methods.'],
            ['o_net_data_category_id' => 3, 'sub_category_name' => 'Geography', 'parent_id' => 79,'sub_parent_id'  => 0, 'description' => 'Knowledge of principles and methods for describing the features of land, sea, and air masses, including their physical characteristics, locations, interrelationships, and distribution of plant, animal, and human life.'],
            ['o_net_data_category_id' => 3, 'sub_category_name' => 'Mathematics', 'parent_id' => 79,'sub_parent_id'  => 0, 'description' => 'Knowledge of arithmetic, algebra, geometry, calculus, statistics, and their applications.'],
            ['o_net_data_category_id' => 3, 'sub_category_name' => 'Physics', 'parent_id' => 79,'sub_parent_id'  => 0, 'description' => 'Knowledge and prediction of physical principles, laws, their interrelationships, and applications to understanding fluid, material, and atmospheric dynamics, and mechanical, electrical, atomic and sub-atomic structures and processes.'],
            ['o_net_data_category_id' => 3, 'sub_category_name' => 'Psychology', 'parent_id' => 79,'sub_parent_id'  => 0, 'description' => 'Knowledge of human behavior and performance; individual differences in ability, personality, and interests; learning and motivation; psychological research methods; and the assessment and treatment of behavioral and affective disorders.'],
            ['o_net_data_category_id' => 3, 'sub_category_name' => 'Sociology and Anthropology', 'parent_id' => 79,'sub_parent_id'  => 0, 'description' => 'Knowledge of group behavior and dynamics, societal trends and influences, human migrations, ethnicity, cultures, and their history and origins.'],
            ['o_net_data_category_id' => 3, 'sub_category_name' => 'Education and Training', 'parent_id' => 0,'sub_parent_id'  => 0, 'description' => 'Knowledge of principles and methods for curriculum and training design, teaching and instruction for individuals and groups, and the measurement of training effects.'],
            ['o_net_data_category_id' => 3, 'sub_category_name' => 'Transportation', 'parent_id' => 0,'sub_parent_id'  => 0, 'description' => 'Knowledge of principles and methods for moving people or goods by air, rail, sea, or road, including the relative costs and benefits.'],
        ]);*/
        // abilites
        /*ONetDataSubCategory::insert([
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Cognitive Abilities', 'parent_id' => 0,'sub_parent_id'  => 0, 'description' => 'Abilities that influence the acquisition and application of knowledge in problem solving.'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Physical Abilities', 'parent_id' => 0, 'sub_parent_id'  => 0,'description' => 'Abilities that influence strength, endurance, flexibility, balance and coordination.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Psychomotor Abilities', 'parent_id' => 0, 'sub_parent_id'  => 0,'description' => 'Abilities that influence the capacity to manipulate and control objects.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Sensory Abilities', 'parent_id' => 0, 'sub_parent_id'  => 0,'description' => 'Abilities that influence visual, auditory and speech perception.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Attentiveness', 'parent_id' => 1 , 'sub_parent_id'  => 0,'description' => 'Abilities related to application of attention.'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Idea Generation and Reasoning Abilities', 'sub_parent_id'  => 0,'parent_id' => 1, 'description' => 'Abilities that influence the application and manipulation of information in problem solving.
' ],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Memory', 'parent_id' => 1,'sub_parent_id'  => 0, 'description' => 'Abilities related to the recall of available information.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Perceptual Abilities', 'parent_id' => 1, 'sub_parent_id'  => 0,'description' => 'Abilities related to the acquisition and organization of visual information.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Quantitative Abilities', 'parent_id' => 1, 'sub_parent_id'  => 0,'description' => 'Abilities that influence the solution of problems involving mathematical relationships.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Spatial Abilities', 'parent_id' => 1,'sub_parent_id'  => 0, 'description' => 'Abilities related to the manipulation and organization of spatial information.
' ],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Verbal Abilities', 'parent_id' => 1, 'sub_parent_id'  => 0,'description' => 'Abilities that influence the acquisition and application of verbal information in problem solving.
'],

    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Endurance', 'parent_id' => 2, 'sub_parent_id'  => 0,'description' => 'The ability to exert oneself physically over long periods without getting out of breath.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Flexibility, Balance, and Coordination','sub_parent_id'  => 0, 'parent_id' => 2, 'description' => 'Abilities related to the control of gross body movements.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Physical Strength Abilities', 'parent_id' => 2,'sub_parent_id'  => 0, 'description' => 'Abilities related to the capacity to exert force.
'],

    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Control Movement Abilities', 'parent_id' => 3, 'sub_parent_id'  => 0,'description' => 'Abilities related to the control and manipulation of objects in time and space.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Fine Manipulative Abilities', 'parent_id' => 3, 'sub_parent_id'  => 0,'description' => 'Abilities related to the manipulation of objects.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Reaction Time and Speed Abilities', 'parent_id' => 3, 'sub_parent_id'  => 0,'description' => 'Abilities related to speed of manipulation of objects.
'],

    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Auditory and Speech Abilities', 'parent_id' => 4, 'sub_parent_id'  => 0,'description' => 'Abilities related to auditory and oral input.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Visual Abilities', 'parent_id' => 4,'sub_parent_id'  => 0, 'description' => 'Abilities related to visual sensory input.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Selective Attention', 'parent_id' => 1,'sub_parent_id'  => 5, 'description' => 'The ability to concentrate on a task over a period of time without being distracted.'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Time Sharing', 'parent_id' => 1,'sub_parent_id'  => 5, 'description' => 'The ability to shift back and forth between two or more activities or sources of information (such as speech, sounds, touch, or other sources).'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Category Flexibility', 'parent_id' => 1,'sub_parent_id'  => 6, 'description' => 'The ability to generate or use different sets of rules for combining or grouping things in different ways.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Deductive Reasoning', 'parent_id' => 1,'sub_parent_id'  => 6, 'description' => 'The ability to apply general rules to specific problems to produce answers that make sense.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Fluency of Ideas', 'parent_id' => 1,'sub_parent_id'  => 6, 'description' => 'The ability to come up with a number of ideas about a topic (the number of ideas is important, not their quality, correctness, or creativity).
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Inductive Reasoning', 'parent_id' => 1,'sub_parent_id'  => 6, 'description' => 'The ability to combine pieces of information to form general rules or conclusions (includes finding a relationship among seemingly unrelated events).
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Information Ordering', 'parent_id' => 1,'sub_parent_id'  => 6, 'description' => 'The ability to arrange things or actions in a certain order or pattern according to a specific rule or set of rules (e.g., patterns of numbers, letters, words, pictures, mathematical operations).
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Originality', 'parent_id' => 1,'sub_parent_id'  => 6, 'description' => 'The ability to come up with unusual or clever ideas about a given topic or situation, or to develop creative ways to solve a problem.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Problem Sensitivity', 'parent_id' => 1,'sub_parent_id'  => 6, 'description' => 'The ability to tell when something is wrong or is likely to go wrong. It does not involve solving the problem, only recognizing that there is a problem.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Memorization', 'parent_id' => 1,'sub_parent_id'  => 7, 'description' => 'The ability to remember information such as words, numbers, pictures, and procedures.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Flexibility of Closure', 'parent_id' => 1,'sub_parent_id'  => 8, 'description' => 'The ability to identify or detect a known pattern (a figure, object, word, or sound) that is hidden in other distracting material.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Perceptual Speed', 'parent_id' => 1,'sub_parent_id'  => 8, 'description' => 'The ability to quickly and accurately compare similarities and differences among sets of letters, numbers, objects, pictures, or patterns. The things to be compared may be presented at the same time or one after the other. This ability also includes comparing a presented object with a remembered object.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Speed of Closure', 'parent_id' => 1,'sub_parent_id'  => 8, 'description' => 'The ability to quickly make sense of, combine, and organize information into meaningful patterns.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Mathematical Reasoning', 'parent_id' => 1,'sub_parent_id'  => 9, 'description' => 'The ability to choose the right mathematical methods or formulas to solve a problem.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Number Facility', 'parent_id' => 1,'sub_parent_id'  => 9, 'description' => 'The ability to add, subtract, multiply, or divide quickly and correctly.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Spatial Orientation', 'parent_id' => 1,'sub_parent_id'  => 10, 'description' => 'The ability to know your location in relation to the environment or to know where other objects are in relation to you.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Visualization', 'parent_id' => 1,'sub_parent_id'  => 10, 'description' => 'The ability to imagine how something will look after it is moved around or when its parts are moved or rearranged.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Oral Comprehension', 'parent_id' => 1,'sub_parent_id'  => 11, 'description' => 'The ability to listen to and understand information and ideas presented through spoken words and sentences.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Oral Expression', 'parent_id' => 1,'sub_parent_id'  => 11, 'description' => 'The ability to communicate information and ideas in speaking so others will understand.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Written Comprehension', 'parent_id' => 1,'sub_parent_id'  => 11, 'description' => 'The ability to read and understand information and ideas presented in writing.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Written Expression', 'parent_id' => 1,'sub_parent_id'  => 11, 'description' => 'The ability to communicate information and ideas in writing so others will understand.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Stamina', 'parent_id' => 1,'sub_parent_id'  => 12, 'description' => 'The ability to exert yourself physically over long periods of time without getting winded or out of breath.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Dynamic Flexibility', 'parent_id' => 1,'sub_parent_id'  => 13, 'description' => 'The ability to quickly and repeatedly bend, stretch, twist, or reach out with your body, arms, and/or legs.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Extent Flexibility', 'parent_id' => 1,'sub_parent_id'  => 13, 'description' => 'The ability to bend, stretch, twist, or reach with your body, arms, and/or legs.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Gross Body Coordination', 'parent_id' => 1,'sub_parent_id'  => 13, 'description' => 'The ability to coordinate the movement of your arms, legs, and torso together when the whole body is in motion.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Gross Body Equilibrium', 'parent_id' => 1,'sub_parent_id'  => 13, 'description' => 'The ability to keep or regain your body balance or stay upright when in an unstable position.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Dynamic Strength', 'parent_id' => 1,'sub_parent_id'  => 14, 'description' => 'The ability to exert muscle force repeatedly or continuously over time. This involves muscular endurance and resistance to muscle fatigue.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Explosive Strength', 'parent_id' => 1,'sub_parent_id'  => 14, 'description' => 'The ability to use short bursts of muscle force to propel oneself (as in jumping or sprinting), or to throw an object.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Static Strength', 'parent_id' => 1,'sub_parent_id'  => 14, 'description' => 'The ability to exert maximum muscle force to lift, push, pull, or carry objects.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Trunk Strength', 'parent_id' => 1,'sub_parent_id'  => 14, 'description' => 'The ability to use your abdominal and lower back muscles to support part of the body repeatedly or continuously over time without "giving out" or fatiguing.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Control Precision', 'parent_id' => 1,'sub_parent_id'  => 15, 'description' => 'The ability to quickly and repeatedly adjust the controls of a machine or a vehicle to exact positions.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Multilimb Coordination', 'parent_id' => 1,'sub_parent_id'  => 15, 'description' => 'The ability to coordinate two or more limbs (for example, two arms, two legs, or one leg and one arm) while sitting, standing, or lying down. It does not involve performing the activities while the whole body is in motion.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Rate Control', 'parent_id' => 1,'sub_parent_id'  => 15, 'description' => 'The ability to time your movements or the movement of a piece of equipment in anticipation of changes in the speed and/or direction of a moving object or scene.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Response Orientation', 'parent_id' => 1,'sub_parent_id'  => 15, 'description' => 'The ability to choose quickly between two or more movements in response to two or more different signals (lights, sounds, pictures). It includes the speed with which the correct response is started with the hand, foot, or other body part.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Arm-Hand Steadiness', 'parent_id' => 1,'sub_parent_id'  => 16, 'description' => 'The ability to keep your hand and arm steady while moving your arm or while holding your arm and hand in one position.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Finger Dexterity', 'parent_id' => 1,'sub_parent_id'  => 16, 'description' => 'The ability to make precisely coordinated movements of the fingers of one or both hands to grasp, manipulate, or assemble very small objects.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Manual Dexterity', 'parent_id' => 1,'sub_parent_id'  => 16, 'description' => 'The ability to quickly move your hand, your hand together with your arm, or your two hands to grasp, manipulate, or assemble objects.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Reaction Time', 'parent_id' => 1,'sub_parent_id'  => 17, 'description' => 'The ability to quickly respond (with the hand, finger, or foot) to a signal (sound, light, picture) when it appears.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Speed of Limb Movement', 'parent_id' => 1,'sub_parent_id'  => 17, 'description' => 'The ability to quickly move the arms and legs.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Wrist-Finger Speed', 'parent_id' => 1,'sub_parent_id'  => 17, 'description' => 'The ability to make fast, simple, repeated movements of the fingers, hands, and wrists.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Auditory Attention', 'parent_id' => 1,'sub_parent_id'  => 18, 'description' => 'The ability to focus on a single source of sound in the presence of other distracting sounds.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Hearing Sensitivity', 'parent_id' => 1,'sub_parent_id'  => 18, 'description' => 'The ability to detect or tell the differences between sounds that vary in pitch and loudness.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Sound Localization', 'parent_id' => 1,'sub_parent_id'  => 18, 'description' => 'The ability to tell the direction from which a sound originated.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Speech Clarity', 'parent_id' => 1,'sub_parent_id'  => 18, 'description' => 'The ability to speak clearly so others can understand you.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Speech Recognition', 'parent_id' => 1,'sub_parent_id'  => 18, 'description' => 'The ability to identify and understand the speech of another person.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Depth Perception', 'parent_id' => 1,'sub_parent_id'  => 19, 'description' => 'The ability to judge which of several objects is closer or farther away from you, or to judge the distance between you and an object.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Far Vision', 'parent_id' => 1,'sub_parent_id'  => 19, 'description' => 'The ability to see details at a distance.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Glare Sensitivity', 'parent_id' => 1,'sub_parent_id'  => 19, 'description' => 'The ability to see objects in the presence of a glare or bright lighting.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Near Vision', 'parent_id' => 1,'sub_parent_id'  => 19, 'description' => 'The ability to see details at close range (within a few feet of the observer).
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Night Vision', 'parent_id' => 1,'sub_parent_id'  => 19, 'description' => 'The ability to see under low-light conditions.
'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Peripheral Vision', 'parent_id' => 1,'sub_parent_id'  => 19, 'description' => 'The ability to see objects or movement of objects to one\'s side when the eyes are looking ahead.'],
    ['o_net_data_category_id' => 1, 'sub_category_name' => 'Visual Color Discrimination', 'parent_id' => 1,'sub_parent_id'  => 19, 'description' => 'The ability to match or detect differences between colors, including shades of color and brightness.'],
]);*/
    }

    public function insertRecord()
    {
        $codes = ['25-1124.00', '43-5011.01', '25-1064.00', '25-1125.00', '17-2112.01', '43-4161.00', '19-2041.03', '19-3032.00', '15-1299.09', '25-9031.00', '27-3091.00', '11-3031.03', '23-1023.00', '13-1075.00', '25-1112.00', '25-1082.00', '13-1081.00', '13-1081.01', '13-1111.00', '13-1161.00', '11-2021.00', '21-1013.00', '25-1022.00', '15-2021.00', '21-1023.00', '21-1014.00', '25-2022.00', '41-9012.00', '13-1199.06', '15-2031.00', '13-2052.00', '25-1126.00', '19-2012.00', '25-1065.00', '19-3094.00', '43-3061.00', '29-1223.00', '25-1066.00', '27-3031.00', '11-3061.00', '11-9199.01', '13-1041.07', '21-1015.00', '19-3034.00', '25-2031.00', '19-4061.00', '25-1113.00', '19-3041.00', '25-1067.00', '25-2057.00', '25-2058.00', '15-2041.00', '13-1199.05', '27-2012.04', '13-1151.00', '11-3031.01', '27-2023.00', '13-1022.00'];

        foreach ($codes as $code) {
            \Illuminate\Support\Facades\Log::info($code);
            if (!ONetOccupationDetailListSummary::where('related', 'like', '%' . $code . '%')->first()) {
                $response = Http::withHeaders([
                    'Authorization' => 'Basic ' . base64_encode('trizinnovation:4225aej'),
                    'Accept' => 'application/json'
                ])->get("https://services.onetcenter.org/ws/online/occupations/" . $code . "/summary/tasks?display=long");

                $data = $response->json();
                if (isset($data['task'])) {
                    foreach ($data['task'] as $task) {
                        ONetOccupationDetailListSummary::insert([
                            'o_net_occupation_detail_list_id' => 0,
                            'name' => $task['name'],
                            'related' => $task['related']
                        ]);
                    }
                }
            }

            if (!ONetOccupationDetailTechSkillSummery::where('related', 'like', '%' . $code
                . '%')->first()) {
                $response = Http::withHeaders([
                    'Authorization' => 'Basic ' . base64_encode('trizinnovation:4225aej'),
                    'Accept' => 'application/json'
                ])->get("https://services.onetcenter.org/ws/online/occupations/" . $code
                    . "/summary/technology_skills?display=long");

                $data = $response->json();
                if (isset($data['category'])) {
                    foreach ($data['category'] as $category) {
                        ONetOccupationDetailTechSkillSummery::insert([
                            'o_net_occupation_detail_list_id' => 0,
                            'title_id' => $category['title']['id'],
                            'name' => $category['title']['name'],
                            'related' => $category['related'],
                            'example' => json_encode($category['example'])
                        ]);
                    }
                }
            }

            if (!ONetOccupationDetailKnowledgeSummery::where('related', 'like', '%' . $code
                . '%')->first()) {
                $response = Http::withHeaders([
                    'Authorization' => 'Basic ' . base64_encode('trizinnovation:4225aej'),
                    'Accept' => 'application/json'
                ])->get("https://services.onetcenter.org/ws/online/occupations/" . $code
                    . "/summary/knowledge?display=long");

                $data = $response->json();
                if (isset($data['element'])) {
                    foreach ($data['element'] as $element) {
                        ONetOccupationDetailKnowledgeSummery::insert([
                            'o_net_occupation_detail_list_id' => 0,
                            'element_id' => $element['id'],
                            'name' => $element['name'],
                            'related' => $element['related'],
                            'description' => $element['description'],
                        ]);
                    }
                }
            }

            if (!ONetOccupationDetailSkillSummery::where('related', 'like', '%' . $code
                . '%')->first()) {
                $response = Http::withHeaders([
                    'Authorization' => 'Basic ' . base64_encode('trizinnovation:4225aej'),
                    'Accept' => 'application/json'
                ])->get("https://services.onetcenter.org/ws/online/occupations/" . $code
                    . "/summary/skills?display=long");

                $data = $response->json();
                if (isset($data['element'])) {
                    foreach ($data['element'] as $element) {
                        ONetOccupationDetailSkillSummery::insert([
                            'o_net_occupation_detail_list_id' => 0,
                            'element_id' => $element['id'],
                            'name' => $element['name'],
                            'related' => $element['related'],
                            'description' => $element['description'],
                        ]);
                    }
                }
            }

            if (!ONetOccupationDetailAbilitiesSummery::where('related', 'like', '%' . $code
                . '%')->first()) {
                $response = Http::withHeaders([
                    'Authorization' => 'Basic ' . base64_encode('trizinnovation:4225aej'),
                    'Accept' => 'application/json'
                ])->get("https://services.onetcenter.org/ws/online/occupations/" . $code
                    . "/summary/abilities?display=long");

                $data = $response->json();
                if (isset($data['element'])) {
                    foreach ($data['element'] as $element) {
                        ONetOccupationDetailAbilitiesSummery::insert([
                            'o_net_occupation_detail_list_id' => 0,
                            'element_id' => $element['id'],
                            'name' => $element['name'],
                            'related' => $element['related'],
                            'description' => $element['description'],
                        ]);
                    }
                }
            }

            if (!ONetOccupationDetailWorkActivitySummery::where('related', 'like', '%' . $code
                . '%')->first()) {
                $response = Http::withHeaders([
                    'Authorization' => 'Basic ' . base64_encode('trizinnovation:4225aej'),
                    'Accept' => 'application/json'
                ])->get("https://services.onetcenter.org/ws/online/occupations/" . $code
                    . "/summary/work_activities?display=long");

                $data = $response->json();
                if (isset($data['element'])) {
                    foreach ($data['element'] as $element) {
                        ONetOccupationDetailWorkActivitySummery::insert([
                            'o_net_occupation_detail_list_id' => 0,
                            'element_id' => $element['id'],
                            'name' => $element['name'],
                            'related' => $element['related'],
                            'description' => $element['description'],
                        ]);
                    }
                }
            }

            if (!ONetOccupationDetailWorkStyleSummery::where('related', 'like', '%' . $code
                . '%')->first()) {
                $response = Http::withHeaders([
                    'Authorization' => 'Basic ' . base64_encode('trizinnovation:4225aej'),
                    'Accept' => 'application/json'
                ])->get("https://services.onetcenter.org/ws/online/occupations/" . $code
                    . "/summary/work_styles?display=long");

                $data = $response->json();
                if (isset($data['element'])) {
                    foreach ($data['element'] as $element) {
                        ONetOccupationDetailWorkStyleSummery::insert([
                            'o_net_occupation_detail_list_id' => 0,
                            'element_id' => $element['id'],
                            'name' => $element['name'],
                            'related' => $element['related'],
                            'description' => $element['description'],
                        ]);
                    }
                }
            }
            if (!ONetOccupationDetailWorkValueSummery::where('related', 'like', '%' . $code
                . '%')->first()) {
                $response = Http::withHeaders([
                    'Authorization' => 'Basic ' . base64_encode('trizinnovation:4225aej'),
                    'Accept' => 'application/json'
                ])->get("https://services.onetcenter.org/ws/online/occupations/" . $code
                    . "/summary/work_values?display=long");

                $data = $response->json();
                if (isset($data['element'])) {
                    foreach ($data['element'] as $element) {
                        ONetOccupationDetailWorkValueSummery::insert([
                            'o_net_occupation_detail_list_id' => 0,
                            'element_id' => $element['id'],
                            'name' => $element['name'],
                            'related' => $element['related'],
                            'description' => $element['description'],
                        ]);
                    }
                }
            }

            if (!ONetOccupationDetailJobZoneSummery::where('code', 'like', '%' . $code
                . '%')->first()) {
                $response = Http::withHeaders([
                    'Authorization' => 'Basic ' . base64_encode('trizinnovation:4225aej'),
                    'Accept' => 'application/json'
                ])->get("https://services.onetcenter.org/ws/online/occupations/" . $code
                    . "/summary/job_zone?display=long");

                $data = $response->json();
                if (isset($data['code'])) {
                    //foreach ($data['element'] as $element) {
                    ONetOccupationDetailJobZoneSummery::insert([
                        'o_net_occupation_detail_list_id' => 0,
                        'title' => $data['title'],
                        'education' => $data['education'],
                        'related_experience' => $data['related_experience'],
                        'job_training' => $data['job_training'],
                        'job_zone_examples' => $data['job_zone_examples'],
                        'svp_range' => $data['svp_range'],
                        'code' => $code

                    ]);
                    //}
                }
            }

            if (!ONetOccupationDetailEducationSummery::where('code', 'like', '%' . $code
                . '%')->first()) {
                $response = Http::withHeaders([
                    'Authorization' => 'Basic ' . base64_encode('trizinnovation:4225aej'),
                    'Accept' => 'application/json'
                ])->get("https://services.onetcenter.org/ws/online/occupations/" . $code
                    . "/summary/education?display=long");

                $data = $response->json();
                if (isset($data['level_required'])) {
                    foreach ($data['level_required']['category'] as $element) {
                        ONetOccupationDetailEducationSummery::insert([
                            'o_net_occupation_detail_list_id' => 0,
                            'name' => $element['name'],
                            'score_scale' => $element['score']['scale'] ?? null,
                            'score_value' => $element['score']['value'] ?? null,
                            'description' => $element['description'] ?? null,
                            'code' => $code

                        ]);
                    }
                }
            }

            if (!ONetOccupationDetailInterestSummery::where('related', 'like', '%' . $code
                . '%')->first()) {
                $response = Http::withHeaders([
                    'Authorization' => 'Basic ' . base64_encode('trizinnovation:4225aej'),
                    'Accept' => 'application/json'
                ])->get("https://services.onetcenter.org/ws/online/occupations/" . $code
                    . "/summary/interests?display=long");

                $data = $response->json();
                if (isset($data['element'])) {
                    foreach ($data['element'] as $element) {
                        ONetOccupationDetailInterestSummery::insert([
                            'o_net_occupation_detail_list_id' => 0,
                            'element_id' => $element['id'],
                            'name' => $element['name'],
                            'related' => $element['related'],
                            'description' => $element['description'],
                        ]);
                    }
                }
            }
        }
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $key => $row) {

            if ($key == 0) continue;

                ONetDataTable::insert([
                    'importance' => 0,
                    'values' => 0,
                    'level' => 0,
                    'job_zone' => '',
                    'code' => $row[0],
                    'occupation' => $row[1],
                    'occupation_type' => '',
                    'employee_by_this_industry' => $row[4],
                    'projected_growth' => $row[2],
                    'projected_growth_openings' => $row[3],
                    'o_net_sub_category_id' => 358,
                ]);
            /*ONetDataTableList::insert([
                'list_name' => $row[4],
            ]);*/
        }
    }
}
