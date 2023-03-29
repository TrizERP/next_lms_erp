@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')


<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Leave Application</h4> </div>
        </div>
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
                    <form action="{{ route('leave_application.store') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("POST") }}
                        {{csrf_field()}}
                        <div class="table-responsive">
                            <table id="example" class="table-bordered table" id="myTable" width="100%">
                                <thead>
                                    <tr>
                                        <!--<th><input type="checkbox" name="all" id="ckbCheckAll" class="ckbox">  </th>-->
                                        <th>No</th>
                                        <th>Student Name</th>
                                        <th>STD/DIV</th>
                                        <th>Mobile</th>
                                        <th>Apply Date</th>
                                        <th>From Date</th>
                                        <th>To Date</th>
                                        <th>Message</th>
                                        <th>File</th>
                                        <th>Reply</th>
                                        <th>Reply By</th>
                                        <th>Status</th>                                    
                                    </tr>
                                </thead>
                                @php
                                $arr = $data['stu_data'];
                                foreach ($arr as $id=>$col_arr){
                                @endphp
                                <tbody>                                
                                    <tr>
                                        <!--<td><input type="checkbox" name="@php echo 'sendsms['.$col_arr['mobile'].']'; @endphp" class="ckbox1">  </td>-->
                                        <td>@php echo $id+1; @endphp</td>
                                        <td>@php echo $col_arr['name']; @endphp</td>
                                        <td>@php echo $col_arr['stddiv']; @endphp</td>
                                        <td>@php echo $col_arr['mobile']; @endphp</td>
                                        <td>@php echo $col_arr['apply_date']; @endphp</td>
                                        <td>@php echo $col_arr['from_date']; @endphp</td>
                                        <td>@php echo $col_arr['to_date']; @endphp</td>
                                        <td>@php echo $col_arr['message']; @endphp</td>
                                        <td>
                                            <a href="<?php echo asset('storage/leave_application/' . $col_arr['files']); ?>" download>Download</a> 
                                        </td>
                                        <td>
                                            <textarea name="reply[<?php echo $col_arr['leave_app_id']; ?>]" >@php echo $col_arr['reply']; @endphp</textarea>
                                        </td>
                                        <td>
                                           @php echo $col_arr['reply_by']; @endphp
                                        </td>
                                        <td>
                                            <select name="status[<?php echo $col_arr['leave_app_id']; ?>]" class="form-control" style="width: 135px;">
                                                <?php
                                                $ap_select = "";
                                                $rj_select = "";
                                                $mta_select = "";
                                                $mtp_select = "";
                                                if ($col_arr['status'] == 'Approved') {
                                                    $ap_select = "selected=selcted";
                                                }
                                                if ($col_arr['status'] == 'Rejected') {
                                                    $rj_select = "selected=selcted";
                                                }
                                                if ($col_arr['status'] == 'Meet To Administrators') {
                                                    $mta_select = "selected=selcted";
                                                }
                                                if ($col_arr['status'] == 'Meet To Principal') {
                                                    $mtp_select = "selected=selcted";
                                                }
                                                ?>
                                                <option value="">Select Status</option>
                                                <option <?php echo $ap_select; ?> value="Approved">Approved</option>
                                                <option <?php echo $rj_select; ?> value="Rejected">Rejected</option>
                                                <option <?php echo $mta_select; ?> value="Meet To Administrators">Meet To Administrators</option>
                                                <option <?php echo $mtp_select; ?> value="Meet To Principal">Meet To Principal</option>
                                            </select>
                                        </td>
                                    </tr>
                                </tbody>
                                @php
                                }
                                @endphp
                            </table>
                        </div>
                        <div class="col-md-12 form-group">
                            <center>
                                <input type="submit" name="submit" value="Save" class="btn btn-success" >
                            </center>
                        </div>
                    </form>
                    @php
                    }else{
                    echo "No Student Found.";
                    }
                    @endphp
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
</div>

@include('includes.footerJs')
<script>
//    $(function () {
//        var $tblChkBox = $("input:checkbox");
//        $("#ckbCheckAll").on("click", function () {
//            $($tblChkBox).prop('checked', $(this).prop('checked'));
//        });
//    });
    $(document).ready(function() {
    // Setup - add a text input to each footer cell
    $('#example thead tr').clone(true).appendTo( '#example thead' );
    $('#example thead tr:eq(1) th').each( function (i) {
        var title = $(this).text();
        $(this).html( '<input type="text" size="4" style="color:black;" placeholder="Search '+title+'" />' );
 
        $( 'input', this ).on( 'keyup change', function () {
            if ( table.column(i).search() !== this.value ) {
                table
                    .column(i)
                    .search( this.value )
                    .draw();
            }
        } );
    } );
 
    var table = $('#example').DataTable( {
        orderCellsTop: true,
        fixedHeader: true,
        dom: 'Bfrtip',
        buttons: [
            'copyHtml5',
            'excelHtml5',
            'csvHtml5',
            'pdfHtml5'
        ]
    } );
} );

</script>
@include('includes.footer')
