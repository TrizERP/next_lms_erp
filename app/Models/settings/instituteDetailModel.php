<?php

namespace App\Models\settings;

use Illuminate\Database\Eloquent\Model;

class instituteDetailModel extends Model
{
    protected $table = "institute_detail";
    public $timestamps = false;
    protected $fillable = [
        'id',
        'sub_institute_id',
        'principal_name',
        'principal_mobile',
        'manager_name',
        'manager_mobile',
        'college_location_condition',
        'total_seats_for_exam',
        'total_furniture',
        'electricity_condition',
        'generator_inverter_condition',
        'drinking_water_condition',
        'toilet_condition',
        'fire_fighting_condition',
        'parking_condition',
        'school_to_road_condition_distance',
        'cctv_condition',
        'total_rooms_with_size',
        'storeroom_condition',
        'college_boundary_gate_condition',
        'principal_house_inside_college',
        'declared_dibar',
        'data_available_AISHE',
        'trustee_conflict',
        'affilitated_college_condition',
        'created_on'
        ];
}

?>

