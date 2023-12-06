<?php

namespace App\Http\Controllers\library;

use App\Http\Controllers\Controller;
use App\Models\school_setup\sub_std_mapModel;
use App\Models\LibraryBook;
use App\Models\LibraryBookCirculation;
use App\Models\LibraryItem;
use App\Models\student\tblstudentModel;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;


class LibraryReportController extends Controller
{
    use GetsJwtToken;

    public function index(Request $request)
    {
        $data['data'] = [];
        $type = $request->input('type');

        return is_mobile($type, "library/library_report", $data, "view");
    }

    public function show_library_report(Request $request)
    {
        $syear = session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');
        $term_id = session()->get('term_id');
        $type = $request->input('type');
        $report_of = $request->input('report_of');
        $material_resource = $request->input('material_resource');
        $author = $request->input('author');
        $publisher_name = $request->input('publisher_name');
        $publishing_place = $request->input('to_dapublishing_placete');
        $language = $request->input('language');
        $subject = $request->input('subject');

        if ($report_of == 'material_resource') 
        {
            $get_material_resources = DB::table("library_books")
            ->where("sub_institute_id", "=", $sub_institute_id)
            ->where("material_resource_type", "=", $material_resource)
            ->get()->toArray();
            
            $data['material_resources'] = $get_material_resources;

            return is_mobile($type, "library/material_resource_show", $data, "view");
        }

        if ($report_of == 'author') 
        {
            $get_author_names = DB::table("library_books")
            ->where("sub_institute_id", "=", $sub_institute_id)
            ->where("author_name", "=", $author)
            ->get()->toArray();
            
            $data['author_names'] = $get_author_names;

            return is_mobile($type, "library/author_show", $data, "view");
        }

        if ($report_of == 'publisher_name') 
        {
            $get_publisher_names = DB::table("library_books")
            ->where("sub_institute_id", "=", $sub_institute_id)
            ->where("publisher_name", "=", $publisher_name)
            ->get()->toArray();
            
            $data['publisher_names'] = $get_publisher_names;

            return is_mobile($type, "library/publisher_name_show", $data, "view");
        }

        if ($report_of == 'publishing_place') 
        {
            $get_publishing_places = DB::table("library_books")
            ->where("sub_institute_id", "=", $sub_institute_id)
            ->where("publish_place", "=", $publishing_place)
            ->get()->toArray();
            
            $data['publishing_places'] = $get_publishing_places;

            return is_mobile($type, "library/publishing_place_show", $data, "view");
        }

        if ($report_of == 'language') 
        {
            $get_language = DB::table("library_books")
            ->where("sub_institute_id", "=", $sub_institute_id)
            ->where("language", "=", $language)
            ->get()->toArray();
            
            $data['language'] = $get_language;

            return is_mobile($type, "library/language_show", $data, "view");
        }

        if ($report_of == 'subject') 
        {
            $get_subject = DB::table("library_books")
            ->where("sub_institute_id", "=", $sub_institute_id)
            ->where("subject", "=", $subject)
            ->get()->toArray();
            
            $data['subject'] = $get_subject;

            return is_mobile($type, "library/subject_show", $data, "view");
        }
    }
}
