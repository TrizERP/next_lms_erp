@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')


<div id="page-wrapper">
    <div class="container-fluid">
        <div class="card">
            @if ($message = Session::get('success'))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $message }}</strong>
            </div>
            @endif
            <div class="row">
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    @php
                    if(isset($data['stu_data'])){
                    @endphp
                    <form action="{{ route('send_sms_staff.store') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("POST") }}
                        {{csrf_field()}}
                        <input type="hidden" name="group_id" value="<?php echo $data['group_id']; ?>">
                        <div class="row">                            
                            <div class="col-md-4 form-group">
                                <label>SMS Text</label>
                                <textarea  required name="smsText" class="form-control"></textarea>
                            </div>
                        </div>
                        <div class="table-responsive">                      
                            <table class="table-bordered table" id="myTable" width="100%">
                                <tr>
                                    <th><input type="checkbox" name="all" id="ckbCheckAll" class="ckbox">  </th>
                                    <th>No</th>
                                    <th>Student Name</th>
                                    <th>Mobile</th>
                                </tr>
                                @php
                                $arr = $data['stu_data'];
                                foreach ($arr as $id=>$col_arr){
                                @endphp
                                <tr>
                                    <td>
                                        <input type="checkbox" name="@php echo 'sendsms['.$col_arr['mobile'].']'; @endphp" class="ckbox1">  
                                    </td>
                                    <td>@php echo $id+1; @endphp</td>
                                    <td>@php echo $col_arr['name']; @endphp</td>
                                    <td>@php echo $col_arr['mobile']; @endphp</td>

                                </tr>
                                @php
                                }
                                @endphp
                            </table>
                        </div>
                        <div class="row">    
                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Save" class="btn btn-success" >
                                </center>
                            </div>
                        </div>    
                    </form>
                    @php
                    }else{
                    @endphp
                        <div class="row">                            
                            <div class="col-md-12 form-group">
                                <center>
                                    <span>No Record Found</span>
                                </center>
                            </div>
                        </div>
                    @php
                    }
                    @endphp
                </div>
            </div>    
            @if (count($errors) > 0)
            <div class="alert alert-danger">
                <strong>Whoops!</strong> There were some problems with your input.<br><br>
                <ul>
                    @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif
        </div>
    </div>
</div>


@include('includes.footerJs')
<script>
    $(function () {
        var $tblChkBox = $("input:checkbox");
        $("#ckbCheckAll").on("click", function () {
            $($tblChkBox).prop('checked', $(this).prop('checked'));
        });
    });
</script>
@include('includes.footer')
