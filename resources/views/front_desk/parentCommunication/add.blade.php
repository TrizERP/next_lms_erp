{{-- @include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation') --}}
@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
    <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Parent Communication</h4> </div>
            </div>       
            <div class="card">
                @if ($message = Session::get('success'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                </div>
                @endif
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    @if(isset($data['stu_data']))
                    <form action="{{ route('parent_communication.store') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("POST") }}
                        {{csrf_field()}}
                        <!--<center><textarea name="smsText" required></textarea></center><br><br>-->
                        <div class="table-responsive">
                        <table id="example" class="table" id="myTable">
                            <thead>
                            <tr>
                                <!--<th><input type="checkbox" name="all" id="ckbCheckAll" class="ckbox">  </th>-->
                                <th>No</th>
                                <th>Student Name</th>
                                <th>STD/DIV</th>
                                <th>Mobile</th>
                                <th>Date</th>
                                <th>Title</th>
                                <th>Message</th>
                                <th>Reply</th>
                                <th>Reply By</th>
                                <th class="text-left">Reply On</th>
                            </tr>
                            </thead>
                            @php
                            $arr = $data['stu_data'];
                            @endphp
                            @foreach ($arr as $id=>$col_arr)                            
                            <tr>
                                <td>{{ $id+1 }}</td>
                                <td>{{ $col_arr['name'] }}</td>
                                <td>{{ $col_arr['stddiv'] }}</td>
                                <td>{{ $col_arr['mobile'] }}</td>
                                <td>{{ $col_arr['date_'] }}</td>
                                <td>{{ $col_arr['title'] }}</td>
                                <td style="white-space: break-spaces;">{{ $col_arr['message'] }}</td>
                                @php if(!empty($col_arr['reply'])){ @endphp
                                <td>{{ $col_arr['reply'] }}</td>    
                                @php }else{ @endphp
                                <td><textarea class="form-control" name="reply[{{$col_arr['parent_communication_id']}}]" >{{ $col_arr['reply'] }}</textarea></td>
                                @php } @endphp
                                <td>{{ $col_arr['reply_by'] }}</td>
                                <td>{{ $col_arr['reply_on'] }}</td>
                            </tr>
                            @endforeach
                        </table>
                        </div>

                        <div class="col-md-12 form-group">
                            <center>
                                <input type="submit" name="submit" value="Save" class="btn btn-success" >
                            </center>
                        </div>

                    </form>
                    @else
                    No Student Found.
                    @endif
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
@endsection