@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Other Fees Mapping</h4>
            </div>
        </div>    
        <div class="card">
            @if(!empty($data['message']))
            @if($data['status_code']==1)
            <div class="alert alert-success alert-block">
            @else
            <div class="alert alert-danger alert-block">            
            @endif
                <button type="button" class="close" data-dismiss="alert">x</button>
                <strong>{{ $data['message'] }}</strong>
            </div>
            @endif
            <div class="row">
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('other_fee_map.create') }}" enctype="multipart/form-data" method="post">
                    {{ method_field("GET") }}
                    {{csrf_field()}}
                        <div class="row">
                            {{ App\Helpers\SearchChain('4','single','grade,std,div') }}
                        </div>
                        
                        {{-- <style>
                            .custom-select select {
                                display: inline !important;
                            }
                        </style> --}}
                        <div class="row">
                        <div class="col-md-4 form-group">
                        <label>{{App\Helpers\get_string('studentname','request')}}</label>
                        <input type="text" id="stu_name" placeholder="Name" name="stu_name" class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>{{App\Helpers\get_string('uniqueid','request')}}</label>
                        <input type="text" id="uniqueid" placeholder="UniqueID/Adm.No" name="uniqueid" class="form-control" >
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Mobile</label>
                        <input type="text" id="mobile" placeholder="Mobile" name="mobile" class="form-control">
                    </div>                        
                    <div class="col-md-4 form-group">
                        <label>{{App\Helpers\get_string('grno','request')}}</label>
                        <input type="text" id="grno" placeholder="Gr No." name="grno" class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                                <label>Fees Heads</label>
    							{{-- <div class="custom-select"> --}}
                                <select name="fees_heads[]" class="form-control" required multiple>
                                    <?php
                                    foreach ($data['data']['heads'] as $id => $val) {
                                        ?>
                                        <option value="<?php echo $val->id ; ?>"><?php echo $val->display_name; ?></option>
                                        <?php
                                    }
                                    ?>
                                </select>
    							{{-- </div> --}}
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Month</label>
    							{{-- <div class="custom-select"> --}}
                                <select name="month_id[]" class="form-control" required multiple>
                                    <?php
                                    foreach ($data['data']['ddMonth'] as $id => $val) {
                                        ?>
                                        <option value="<?php echo $id ; ?>"><?php echo $val; ?></option>
                                        <?php
                                    }
                                    ?>
                                </select>
    							{{-- </div> --}}
                            </div>
                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Search" class="btn btn-success">
                                </center>
                            </div>
                        </div>    
                    </form>
                </div>
            </div>
        </div>    
    </div>
</div>

@include('includes.footerJs')
<script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.19/js/dataTables.bootstrap.min.js"></script>
<script>
$(document).ready(function () {
    $('#example').DataTable({

    });
});
function validateData()
{       
    var c = $('#grade').val();
    // alert(c);
    if(c == '')
    {
        alert("Please Select Atleast One Academic Section");
        return false;
    }
    else{
        return true;
    }
    
}
</script>
@include('includes.footer')
