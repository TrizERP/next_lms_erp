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
                                        <td>{{ $id+1 }}</td>
                                        <td>{{ $col_arr['name'] }}</td>
                                        <td>{{ $col_arr['stddiv'] }}</td>
                                        <td>{{ $col_arr['mobile'] }}</td>
                                        <td>{{ $col_arr['apply_date'] }}</td>
                                        <td>{{ $col_arr['from_date'] }}</td>
                                        <td>{{ $col_arr['to_date'] }}</td>
                                        <td>{{ $col_arr['message'] }}</td>
                                        <td>
                                        @if(!empty($col_arr['files']))
                                            <a target="blank" href="/storage/leave_application/{{$col_arr['files']}}">View</a>
                                        @else
                                            -</td>
                                        @endif
                                        </td>
                                        <td>
                                            @if(!empty($col_arr['reply']))
                                                {{ $col_arr['reply'] }}
                                            @else
                                                <textarea name="reply[<?php echo $col_arr['leave_app_id']; ?>]" >{{ $col_arr['reply'] }}</textarea>
                                            @endif
                                        </td>
                                        <td>
                                           {{ $col_arr['reply_by'] }} 
                                        </td>
                                        <td>
                                        @if(!empty($col_arr['status']))
                                            {{ $col_arr['status'] }}
                                        @else
                                            <select name="status[{{ $col_arr['leave_app_id'] }}]" class="form-control" style="width: 135px;">
                                                <option value="">Select Status</option>
                                                <option {{ $col_arr['status'] == 'Approved' ? 'selected' : '' }} value="Approved">Approved</option>
                                                <option {{ $col_arr['status'] == 'Rejected' ? 'selected' : '' }} value="Rejected">Rejected</option>
                                                <option {{ $col_arr['status'] == 'Meet To Administrators' ? 'selected' : '' }} value="Meet To Administrators">Meet To Administrators</option>
                                                <option {{ $col_arr['status'] == 'Meet To Principal' ? 'selected' : '' }} value="Meet To Principal">Meet To Principal</option>
                                            </select>
                                        @endif
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
