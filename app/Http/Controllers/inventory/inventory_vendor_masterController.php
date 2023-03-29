<?php

namespace App\Http\Controllers\inventory;

use App\Http\Controllers\Controller;
use App\Models\inventory\inventory_vendor_masterModel;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;

class inventory_vendor_masterController extends Controller
{
    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $item_data['message'] = $data_arr['message'];
            }
        }
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data = inventory_vendor_masterModel::where(['sub_institute_id' => $sub_institute_id])->get();
        $item_data['status_code'] = 1;
        $item_data['data'] = $data;
        $type = $request->input('type');

        return is_mobile($type, "inventory/show_inventory_vendor_master", $item_data, "view");

    }

    public function create(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data = inventory_vendor_masterModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        return view('inventory/add_inventory_vendor_master', ['menu' => $data]);
    }

    public function store(Request $request)
    {
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $created_by = $request->session()->get('user_id');
        $vendor = new inventory_vendor_masterModel([
            'syear'                 => $syear,
            'sub_institute_id'      => $sub_institute_id,
            'vendor_name'           => $request->get('vendor_name'),
            'contact_number'        => $request->get('contact_number'),
            'short_name'            => $request->get('short_name'),
            'sort_order'            => $request->get('sort_order'),
            'address'               => $request->get('address'),
            'email'                 => $request->get('email'),
            'file_number'           => $request->get('file_number'),
            'file_location'         => $request->get('file_location'),
            'company_name'          => $request->get('company_name'),
            'business_type'         => $request->get('business_type'),
            'office_address'        => $request->get('office_address'),
            'office_contact_person' => $request->get('office_contact_person'),
            'office_number'         => $request->get('office_number'),
            'office_email'          => $request->get('office_email'),
            'tin_no'                => $request->get('tin_no'),
            'tin_date'              => $request->get('tin_date'),
            'registration_no'       => $request->get('registration_no'),
            'registration_date'     => $request->get('registration_date'),
            'serivce_tax_no'        => $request->get('serivce_tax_no'),
            'serivce_tax_date'      => $request->get('serivce_tax_date'),
            'pan_no'                => $request->get('pan_no'),
            'bank_account_no'       => $request->get('bank_account_no'),
            'bank_name'             => $request->get('bank_name'),
            'bank_branch'           => $request->get('bank_branch'),
            'bank_ifsc_code'        => $request->get('bank_ifsc_code'),
            'created_by'            => $created_by,
            'created_on'            => date('Y-m-d'),
            'created_ip_address'    => $_SERVER['REMOTE_ADDR'],
        ]);
        $vendor->save();

        $message['status_code'] = "1";
//        $message = array(
//            "message" => "Vendor Added Succesfully",
//        );
        $message = inventory_vendor_masterModel::where(['sub_institute_id' => $sub_institute_id])->get();

        $type = $request->input('type');

        return is_mobile($type, "add_inventory_vendor_master.index", $message, "redirect");

    }

    public function edit(Request $request, $id)
    {

        $type = $request->input('type');
        $data = inventory_vendor_masterModel::find($id);

        return is_mobile($type, "inventory/add_inventory_vendor_master", $data, "view");
    }

    public function update(Request $request, $id)
    {
        // \App\Helpers\ValidateInsertData('hostel_type_master', 'update'); 
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $created_by = $request->session()->get('user_id');
        $data = [
            'syear'                 => $syear,
            'sub_institute_id'      => $sub_institute_id,
            'vendor_name'           => $request->get('vendor_name'),
            'contact_number'        => $request->get('contact_number'),
            'short_name'            => $request->get('short_name'),
            'sort_order'            => $request->get('sort_order'),
            'address'               => $request->get('address'),
            'email'                 => $request->get('email'),
            'file_number'           => $request->get('file_number'),
            'file_location'         => $request->get('file_location'),
            'company_name'          => $request->get('company_name'),
            'business_type'         => $request->get('business_type'),
            'office_address'        => $request->get('office_address'),
            'office_contact_person' => $request->get('office_contact_person'),
            'office_number'         => $request->get('office_number'),
            'office_email'          => $request->get('office_email'),
            'tin_no'                => $request->get('tin_no'),
            'tin_date'              => $request->get('tin_date'),
            'registration_no'       => $request->get('registration_no'),
            'registration_date'     => $request->get('registration_date'),
            'serivce_tax_no'        => $request->get('serivce_tax_no'),
            'serivce_tax_date'      => $request->get('serivce_tax_date'),
            'pan_no'                => $request->get('pan_no'),
            'bank_account_no'       => $request->get('bank_account_no'),
            'bank_name'             => $request->get('bank_name'),
            'bank_branch'           => $request->get('bank_branch'),
            'bank_ifsc_code'        => $request->get('bank_ifsc_code'),
            'created_by'            => $created_by,
            'created_on'            => date('Y-m-d'),
            'created_ip_address'    => $_SERVER['REMOTE_ADDR'],
        ];

        inventory_vendor_masterModel::where(["id" => $id])->update($data);
        $message['status_code'] = "1";
        $message = [
            "message" => "Vendor Updated Successfully",
        ];
        $type = $request->input('type');

        return is_mobile($type, "add_inventory_vendor_master.index", $message, "redirect");
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        inventory_vendor_masterModel::where(["id" => $id])->delete();
        $message['status_code'] = "1";
        $message = [
            "message" => "Vendor Deleted successfully",
        ];

        return is_mobile($type, "add_inventory_vendor_master.index", $message, "redirect");
    }
}
