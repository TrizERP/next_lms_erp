@include('includes.headcss')

@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Create Request Type</h4> </div>
            </div>

        <div class="row">
            <div class="white-box">
                <div class="panel-body">
                    @if ($sessionData = Session::get('data'))
                    @if($sessionData['status_code'] == 1)
                    <div class="alert alert-success alert-block">
                    @else
                    <div class="alert alert-danger alert-block">
                    @endif
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $sessionData['message'] }}</strong>
                    </div>
                    @endif
                    @if(isset($edit_data))
                    <form action="{{ route('student_change_request_type.update',$edit_data['ID']) }}" enctype="multipart/form-data" method="post">
                    	{{ method_field("PUT") }}
                    @else
                    <form action="{{ route('student_change_request_type.store') }}" enctype="multipart/form-data" method="post">
                    @endif

                    	@csrf
                        <div class="col-md-3 form-group">
                        	<label>Request Title</label>
                            <input type="text" name="REQUEST_TITLE" @if(isset($edit_data['REQUEST_TITLE'])) value="{{$edit_data['REQUEST_TITLE']}}" @endif class="form-control">
                        </div>
                        <div class="col-md-3 form-group">
                        	<label>Proof Document Required</label>
                            <select name="PROOF_DOCUMENT_REQUIED" @if(isset($edit_data['PROOF_DOCUMENT_REQUIED'])) value="{{$edit_data['PROOF_DOCUMENT_REQUIED']}}" @endif class="form-control">
                            	<option value="">Select Doc Required</option>
                            	<option value="Y" @if(isset($edit_data['PROOF_DOCUMENT_REQUIED'])) @if($edit_data['PROOF_DOCUMENT_REQUIED'] == 'Y') SELECTED @endif @endif>Yes</option>
                            	<option value="N" @if(isset($edit_data['PROOF_DOCUMENT_REQUIED'])) @if($edit_data['PROOF_DOCUMENT_REQUIED'] == 'N') SELECTED @endif @endif>No</option>
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                        	<label>Proof Document Name</label>
                            <input type="text" name="PROOF_DOCUMENT_NAME" @if(isset($edit_data['PROOF_DOCUMENT_NAME'])) value="{{$edit_data['PROOF_DOCUMENT_NAME']}}" @endif class="form-control">
                        </div>
                        <div class="col-md-3 form-group">
                        	<label>Amount</label>
                            <input type="text" name="AMOUNT" @if(isset($edit_data['AMOUNT'])) value="{{$edit_data['AMOUNT']}}" @endif class="form-control">
                        </div>
                        <div class="col-md-2 form-group">
                        	@if(isset($edit_data))
                            <input type="submit" name="submit" value="Update" class="btn btn-success" >
                        	@else
                            <input type="submit" name="submit" value="Save" class="btn btn-success" >
                        	@endif
                        </div>
                    </form>
                </div>

        @if(isset($data['request_data']))
        @php
        $j = 1;
            if(isset($data['request_data'])){
                $request_data = $data['request_data'];
            }
        @endphp
                <div class="table-responsive">
                    <table id="example" class="table table-striped table-bordered display">
                    <thead>
                        <tr>
                            <th>Sr No</th>
                            <th>Request Title</th>
                            <th>Proff Document Required</th>
                            <th>Proff Document Name</th>
                            <th>Amount</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($request_data as $key => $value)
                            <tr>
                                <td> {{$j++}} </td>
                                <td> {{$value['REQUEST_TITLE']}} </td>
                                <td> {{$value['PROOF_DOCUMENT_REQUIED']}} </td>
                                <td> {{$value['PROOF_DOCUMENT_NAME']}} </td>
                                <td> {{$value['AMOUNT']}} </td>
                                 <td><a href="{{ route('student_change_request_type.edit',$value['ID'])}}"><button style="float:left;" type="button" class="btn btn-info btn-outline btn-circle btn m-r-5"><i class="ti-pencil-alt"></i></button></a>
                                    <form action="{{ route('student_change_request_type.destroy', $value['ID'])}}" method="post">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-info btn-outline btn-circle btn m-r-5"><i class="ti-trash"></i></button>
                                    </form>
                                    </td>
                            </tr>
                        @endforeach
                    </tbody>

                    </table>
                    <div class="col-md-12 form-group">
                        <center>
                            <input type="submit" name="submit" value="Submit" class="btn btn-success" >
                        </center>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>

@include('includes.footerJs')
<script>
$(document).ready(function () {
    $('#example').DataTable();
});

</script>
@include('includes.footer')
