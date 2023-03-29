@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')


<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Other Fees Mapping</h4>
            </div>
        </div>  
        <div class="card">
            @if ($message = Session::get('success'))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">Ã—</button>
                <strong>{{ $message }}</strong>
            </div>
            @endif
            <div class="row">                
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    @php
                    if(isset($data['stu_data'])){
                    @endphp
                    <form action="{{ route('other_fee_map.store') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("POST") }}
                        {{csrf_field()}}
                        <input type="hidden" name="grade" value="<?php echo $data['grade']; ?>">
                        <input type="hidden" name="standard" value="<?php echo $data['standard']; ?>">
                        <input type="hidden" name="division" value="<?php echo $data['division']; ?>">
                        <div class="table-responsive">                        
                            <table class="table table-striped" id="myTable">
                                <thead>                                    
                                    <tr>
                                        <th><input type="checkbox" name="all" id="ckbCheckAll" class="ckbox">  </th>
                                        <th>Sr. No.</th>
                                        <th>Student Name</th>
                                        <th>Std/Div</th>
                                        <th>Mobile</th>
                                        @php
                                        $arr_title = $data['fees_title'];
                                        foreach ($arr_title as $id=>$tit_arr){
                                        echo '<th>'. $tit_arr['display_name']. '</th>';
                                        }
                                        @endphp
                                    </tr>
                                </thead>
                                <tbody>
                                    
                                    @php
                                    $arr = $data['stu_data'];
                                    foreach ($arr as $id=>$col_arr){
                                    @endphp
                                    <tr>
                                        <td><input type="checkbox" name="@php echo 'student_id['.$col_arr['student_id'].']'; @endphp" class="ckbox1">  </td>
                                        <td>@php echo $id+1; @endphp</td>
                                        <td>@php echo $col_arr['name']; @endphp</td>
                                        <td>@php echo $col_arr['std'].' / '.$col_arr['div']; @endphp</td>
                                        <td>@php echo $col_arr['mobile']; @endphp</td>
                                        @php
                                        $arr_title = $data['fees_title'];
                                        foreach ($arr_title as $id=>$tit_arr){
                                        @endphp
                                        <th><input type="text" value="@php echo $col_arr[$tit_arr['fees_title']]; @endphp" name="values[@php echo $col_arr['student_id']; @endphp][@php echo $tit_arr['fees_title']; @endphp]"></th>
                                        @php
                                        }
                                        @endphp
                                    </tr>
                                    @php
                                    }
                                    @endphp
                                </tbody>
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
                                <center>No Data Found !</center>
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
<script>
$(document).ready(function () {
    $('#myTable').DataTable();
});

</script>
@include('includes.footer')
