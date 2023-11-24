@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')


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
                    @php
                    if(isset($data['stu_data'])){
                    @endphp
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
                                <th>Reply On</th>
                            </tr>
                            </thead>
                            @php

                            $arr = $data['stu_data'];
                            foreach ($arr as $id=>$col_arr){
                            @endphp
                            <tr>

                                <!--<td><input type="checkbox" name="@php echo 'sendsms['.$col_arr['mobile'].']'; @endphp" class="ckbox1">  </td>-->
                                <td>@php echo $id+1; @endphp</td>
                                <td>@php echo $col_arr['name']; @endphp</td>
                                <td>@php echo $col_arr['stddiv']; @endphp</td>
                                <td>@php echo $col_arr['mobile']; @endphp</td>
                                <td>@php echo $col_arr['date_']; @endphp</td>
                                <td>@php echo $col_arr['title']; @endphp</td>
                                <td style="white-space: break-spaces;">@php echo $col_arr['message']; @endphp</td>
                                @php if(!empty($col_arr['reply'])){ @endphp
                                <td>@php echo $col_arr['reply']; @endphp</td>    
                                @php }else{ @endphp
                                <td><textarea class="form-control" name="reply[<?php echo $col_arr['parent_communication_id']; ?>]" >@php echo $col_arr['reply']; @endphp</textarea></td>
                                @php } @endphp
                                <td>@php echo $col_arr['reply_by']; @endphp</td>
                                <td>@php echo $col_arr['reply_on']; @endphp</td>
                            </tr>
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
