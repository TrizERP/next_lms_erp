<?php

namespace App\Http\Controllers\library;

use App\Http\Controllers\Controller;
use function App\Helpers\is_mobile;
use Illuminate\Http\Request;
use DB;

class LostandDamage extends Controller
{
	  public function index(Request $request)
    {
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $data['bookdata'] =  DB::table('mst_item_status')
            ->where('sub_institute_id', $sub_institute_id)
            ->pluck('item_status_name', 'id')
            ->toArray();
        
        $query = DB::table('item_scan_details as a')
            ->join('mst_item_status as b', 'b.id', '=', 'a.item_status_id')
            ->join('library_items as c', 'c.item_code', '=', 'a.item_code')
            ->join('library_books as d', 'd.id', '=', 'c.book_id')
            ->select(
                'a.item_code',
                'd.title',
                // 'c.collection_type',
                'd.material_resource_type as collection_type',
                'a.remarks',
                'b.item_status_name',
            )
            ->where('a.sub_institute_id', $sub_institute_id)
            ->where('a.syear', $syear)
            ->where('a.item_status_id', '!=',0)
            ->whereNull('a.deleted_at'); // Assuming 3 is the ID for Lost and Damage status

            // ->when('a.item_status_id', $validated['item_status'])

        $data['bookdata'] = $results = $query->get()->toArray();
        $data['item_status_arr'] = DB::table('mst_item_status')
            ->where('sub_institute_id', $sub_institute_id)
            ->pluck('item_status_name', 'id')
            ->toArray();
    
        $type = $request->input('type');

        return is_mobile($type, "library/reports/LostAndDamage", $data, "view");
    }

    public function create(Request $request)
    {
        // echo `<pre>`;print_r($request->all());echo `</pre>`;exit;
        // This method is used to create a new lost and damage report
        $data = [];
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        $data['item_code']=$item_code = $request->input('item_code');
        $data['item_status']=$item_status = $request->input('status');

        $data['item_status_arr'] = DB::table('mst_item_status')
            ->where('sub_institute_id', $sub_institute_id)
            ->pluck('item_status_name', 'id')
            ->toArray();
        
        $query = DB::table('item_scan_details as a')
            ->join('mst_item_status as b', 'b.id', '=', 'a.item_status_id')
            ->join('library_items as c', 'c.item_code', '=', 'a.item_code')
            ->join('library_books as d', 'd.id', '=', 'c.book_id')
            ->select(
                'a.item_code',
                'd.title',
                // 'c.collection_type',
                'd.material_resource_type as collection_type',
                'a.remarks',
                'b.item_status_name',
            )
            ->where('a.sub_institute_id', $sub_institute_id)
            ->where('a.syear', $syear)
            ->where('a.item_status_id', '!=',0)
            ->whereNull('a.deleted_at');
            // ->when('a.item_status_id', $validated['item_status']);
            

        if (isset($request->item_code) && $request->item_code != '') {
            $query->where('a.item_code', $request->item_code);
        }
        if (isset($request->status) && $request->status != '') {
            // echo "here";exit;
            $query->where('a.item_status_id', $request->status);
        }

        $data['bookdata'] = $results = $query->get()->toArray();
        // echo `<pre>`;print_r($results[0]->title);echo `</pre>`;exit;
        $type = $request->input('type');

        return is_mobile($type, "library/reports/LostAndDamage", $data, "view");
    }
}

