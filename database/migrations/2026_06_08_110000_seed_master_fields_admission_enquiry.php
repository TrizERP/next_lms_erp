<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        $fields = [
            // Basic Details Section
            ['module' => 'admission_enquiry', 'field_name' => 'enquiry_no', 'field_label' => 'Enquiry Number', 'field_type' => 'text', 'is_mandatory' => 1, 'is_visible' => 1, 'sort_order' => 1, 'section' => 'Basic Details', 'main_menu' => 173],
            ['module' => 'admission_enquiry', 'field_name' => 'first_name', 'field_label' => 'Student Name', 'field_type' => 'text', 'is_mandatory' => 1, 'is_visible' => 1, 'sort_order' => 2, 'section' => 'Basic Details', 'main_menu' => 173],
            ['module' => 'admission_enquiry', 'field_name' => 'middle_name', 'field_label' => 'Middle Name(Father Name)', 'field_type' => 'text', 'is_mandatory' => 1, 'is_visible' => 1, 'sort_order' => 3, 'section' => 'Basic Details', 'main_menu' => 173],
            ['module' => 'admission_enquiry', 'field_name' => 'last_name', 'field_label' => 'Surname', 'field_type' => 'text', 'is_mandatory' => 1, 'is_visible' => 1, 'sort_order' => 4, 'section' => 'Basic Details', 'main_menu' => 173],
            ['module' => 'admission_enquiry', 'field_name' => 'mobile', 'field_label' => 'Mobile (SMS Number)', 'field_type' => 'text', 'is_mandatory' => 1, 'is_visible' => 1, 'sort_order' => 5, 'section' => 'Basic Details', 'main_menu' => 173],
            ['module' => 'admission_enquiry', 'field_name' => 'email', 'field_label' => 'Email', 'field_type' => 'email', 'is_mandatory' => 0, 'is_visible' => 1, 'sort_order' => 6, 'section' => 'Basic Details', 'main_menu' => 173],
            ['module' => 'admission_enquiry', 'field_name' => 'admission_standard', 'field_label' => 'Admission Standard', 'field_type' => 'dropdown', 'is_mandatory' => 1, 'is_visible' => 1, 'sort_order' => 7, 'section' => 'Basic Details', 'main_menu' => 173],
            ['module' => 'admission_enquiry', 'field_name' => 'date_of_birth', 'field_label' => 'Date of Birth', 'field_type' => 'date', 'is_mandatory' => 1, 'is_visible' => 1, 'sort_order' => 8, 'section' => 'Basic Details', 'main_menu' => 173],
            ['module' => 'admission_enquiry', 'field_name' => 'age', 'field_label' => 'Age', 'field_type' => 'text', 'is_mandatory' => 0, 'is_visible' => 1, 'sort_order' => 9, 'section' => 'Basic Details', 'main_menu' => 173],
            ['module' => 'admission_enquiry', 'field_name' => 'address', 'field_label' => 'Address', 'field_type' => 'textarea', 'is_mandatory' => 0, 'is_visible' => 1, 'sort_order' => 10, 'section' => 'Basic Details', 'main_menu' => 173],
            ['module' => 'admission_enquiry', 'field_name' => 'gender', 'field_label' => 'Gender', 'field_type' => 'radio', 'field_value' => '{"M":"Male","F":"Female"}', 'is_mandatory' => 1, 'is_visible' => 1, 'sort_order' => 11, 'section' => 'Basic Details', 'main_menu' => 173],

            // Previous Education Section
            ['module' => 'admission_enquiry', 'field_name' => 'previous_school_name', 'field_label' => "Previous School's Name", 'field_type' => 'text', 'is_mandatory' => 0, 'is_visible' => 1, 'sort_order' => 1, 'section' => 'Previous Education', 'main_menu' => 173],
            ['module' => 'admission_enquiry', 'field_name' => 'previous_standard', 'field_label' => 'Previous Standard', 'field_type' => 'dropdown', 'is_mandatory' => 0, 'is_visible' => 1, 'sort_order' => 2, 'section' => 'Previous Education', 'main_menu' => 173, 'use_table_data' => 'standard'],

            // Additional Details Section
            ['module' => 'admission_enquiry', 'field_name' => 'followup_date', 'field_label' => 'Followup Date', 'field_type' => 'date', 'is_mandatory' => 0, 'is_visible' => 1, 'sort_order' => 1, 'section' => 'Additional Details', 'main_menu' => 173],
            ['module' => 'admission_enquiry', 'field_name' => 'remarks', 'field_label' => 'Remarks', 'field_type' => 'text', 'is_mandatory' => 0, 'is_visible' => 1, 'sort_order' => 2, 'section' => 'Additional Details', 'main_menu' => 173],
            ['module' => 'admission_enquiry', 'field_name' => 'source_of_enquiry', 'field_label' => 'Source of enquiry', 'field_type' => 'text', 'is_mandatory' => 0, 'is_visible' => 1, 'sort_order' => 3, 'section' => 'Additional Details', 'main_menu' => 173],
            ['module' => 'admission_enquiry', 'field_name' => 'category', 'field_label' => 'Category', 'field_type' => 'dropdown', 'is_mandatory' => 0, 'is_visible' => 1, 'sort_order' => 4, 'section' => 'Additional Details', 'main_menu' => 173],
            ['module' => 'admission_enquiry', 'field_name' => 'send_sms', 'field_label' => 'Send Sms', 'field_type' => 'dropdown', 'field_value' => '{"0":"No","1":"Yes"}', 'is_mandatory' => 0, 'is_visible' => 1, 'sort_order' => 5, 'section' => 'Additional Details', 'main_menu' => 173],
            ['module' => 'admission_enquiry', 'field_name' => 'sms_message', 'field_label' => 'Sms Message', 'field_type' => 'textarea', 'is_mandatory' => 0, 'is_visible' => 1, 'sort_order' => 6, 'section' => 'Additional Details', 'main_menu' => 173],

            // Additional Fields for specific institutes
            ['module' => 'admission_enquiry', 'field_name' => 'mobile_number_father', 'field_label' => "Father's Mobile No.", 'field_type' => 'text', 'is_mandatory' => 0, 'is_visible' => 1, 'sort_order' => 1, 'section' => 'Parent Details', 'main_menu' => 173],
            ['module' => 'admission_enquiry', 'field_name' => 'mobile_number_mother', 'field_label' => "Mother's Mobile No.", 'field_type' => 'text', 'is_mandatory' => 0, 'is_visible' => 1, 'sort_order' => 2, 'section' => 'Parent Details', 'main_menu' => 173],

            // Fees Details Section
            ['module' => 'admission_enquiry', 'field_name' => 'admission_fees', 'field_label' => 'Admission Form Charges', 'field_type' => 'text', 'is_mandatory' => 0, 'is_visible' => 1, 'sort_order' => 1, 'section' => 'Fees Details', 'main_menu' => 173],
            ['module' => 'admission_enquiry', 'field_name' => 'fees_circular_form_no', 'field_label' => 'Fees Circular Form No', 'field_type' => 'text', 'is_mandatory' => 0, 'is_visible' => 1, 'sort_order' => 2, 'section' => 'Fees Details', 'main_menu' => 173],
            ['module' => 'admission_enquiry', 'field_name' => 'fees_amount', 'field_label' => 'Fees Amount', 'field_type' => 'text', 'is_mandatory' => 0, 'is_visible' => 1, 'sort_order' => 3, 'section' => 'Fees Details', 'main_menu' => 173],
            ['module' => 'admission_enquiry', 'field_name' => 'fees_remark', 'field_label' => 'Fees Remark', 'field_type' => 'textarea', 'is_mandatory' => 0, 'is_visible' => 1, 'sort_order' => 4, 'section' => 'Fees Details', 'main_menu' => 173],
            ['module' => 'admission_enquiry', 'field_name' => 'payment_type', 'field_label' => 'Select Payment Type', 'field_type' => 'dropdown', 'field_value' => '{"cash":"Cash","online":"Online"}', 'is_mandatory' => 0, 'is_visible' => 1, 'sort_order' => 5, 'section' => 'Fees Details', 'main_menu' => 173],

            // Siblings Section
            ['module' => 'admission_enquiry', 'field_name' => 'siblings', 'field_label' => 'Siblings', 'field_type' => 'text', 'is_mandatory' => 0, 'is_visible' => 1, 'sort_order' => 1, 'section' => 'Siblings', 'main_menu' => 173],
        ];

        foreach ($fields as $index => $field) {
            $field['created_at'] = now();
            $field['updated_at'] = now();
            DB::table('master_fields')->insert($field);
        }
    }

    public function down()
    {
        DB::table('master_fields')->where('module', 'admission_enquiry')->delete();
    }
};