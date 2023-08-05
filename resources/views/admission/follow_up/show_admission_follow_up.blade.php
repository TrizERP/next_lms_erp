@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Admission Enquiry</h4>
            </div>
        </div>
        <div class="card">
            @if ($message = Session::get('success'))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $message }}</strong>
            </div>
            @endif

            @php
            if(isset($data['data']))
            {
                $edata = $data['data'];
            }
            @endphp
            <div class="row">                
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('admission_follow_up.store') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("POST") }}
                        @csrf
                        <div class="row">            
                            <div class="col-md-3 form-group">
                                <label>Next Followup Date</label>
                                <input type="text" id='follow_up_date' required name="follow_up_date" class="form-control mydatepicker">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Name </label>
                                <input type="text" id='name' name="name" class="form-control" value="{{$edata['first_name']}} {{ $edata['middle_name'] }} {{ $edata['last_name']}}" disabled="disabled">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Mobile Number </label>
                                <input type="text" id='mobile_no' name="mobile_no" class="form-control" value="{{$edata['mobile']}}" disabled="disabled">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Remarks </label>
                                <input type="text" id='remarks' required name="remarks" class="form-control">
                                <input type="hidden" id='enquiry_id' value="{{$data['enquiry_id']}}" name="enquiry_id" class="form-control">
                                <input type="hidden" id='module_type' value="{{$data['module']}}" name="module_type" class="form-control">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Enquiry Status</label>
                                <select name="status" id="status" required="required" class="form-control">
                                    <option value="open"> Open</option>
                                    <option value="close"> Close</option>
                                </select>
                            </div>
                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Save" class="btn btn-success" >
                                </center>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>   
        @if(isset($data['followUpData']))
            <div class="card">
                <div class="row">                
                    <div class="col-lg-12 col-sm-12 col-xs-12">
                        <div class="table-responsive">
                            {!! App\Helpers\get_school_details("","","") !!}
                            <table id="example" class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>Sr. No</th>
                                        <th>Next Followup Date</th>
                                        <th>Created On Date</th>
                                        <th>Remarks</th>
                                        <!-- <th>Action</th> -->
                                    </tr>
                                </thead>
                                <tbody>
                                @php
                                $j=1;
                                @endphp
                                    @foreach($data['followUpData'] as $key => $data)
                                    <tr>
                                        <td>{{$j}}</td>
                                        <td>{{date('d-m-Y', strtotime($data['follow_up_date']))}}</td>
                                        <td>{{date('d-m-Y H:i:s', strtotime($data['created_on']))}}</td>
                                        <td>{{$data['remarks']}}</td>

                                        <!-- <td><a href="{{ route('admission_enquiry.edit',$data['id'])}}"><button style="float:left;" type="button" class="btn btn-info btn-outline btn-circle btn m-r-5"><i class="ti-pencil-alt"></i></button></a>
                                        <form action="{{ route('admission_enquiry.destroy', $data['id'])}}" method="post">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-info btn-outline btn-circle btn m-r-5"><i class="ti-trash"></i></button>
                                        </form>
                                        </td> -->
                                    </tr>
                                    @php
                                $j++;
                                @endphp
                                    @endforeach

                                </tbody>

                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif
        </div>
    </div>
</div>

@include('includes.footerJs')

@include('includes.footer')
