@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Van Wise Students Details Report</h4>
            </div>
        </div>
        <div class="row" style=" margin-top: 25px;">
            <div class="panel-body white-box">
                @if ($message = Session::get('success'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                </div>
                @endif
                <br><br><br>
                <div class="col-lg-12 col-sm-12 col-xs-12" style="overflow:auto;">
                    {!! App\Helpers\get_school_details("","","") !!}
                    <table id="example" class="table table-striped table-bordered" style="width:100%">
                        <thead>
                            <tr>
                                <th>Van No.</th>
                                <th>Van Name & Shift</th>
                                <th style="text-align:left">Total No. of Student</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($data['student_datas'] as $key => $student_data)
                            <tr>
                                <td>{{$student_data->bus_name}}</td>
                                <td>{{$student_data->bus_name}} - {{$student_data->shift_title}}</td>
                                <td><a href="#" data-toggle="modal" id="add_data" onclick="javascript:add_data({{ $student_data->transport_vehicle_id}},{{$student_data->transport_school_shift_id}});">{{ $student_data->student_count }}</a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
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

<div class="modal fade right modal-scrolling" id="ChapterModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel"
    style="display: none;" aria-hidden="true">
    <div class="modal-dialog modal-side modal-bottom-right modal-notify modal-info" role="document" style="min-width: 85%;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="heading">Student Lists</h5>
                <button type="button" class="close" id="refresh_data" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">x</span>
                </button>
            </div>
            <div class="modal-body">
                 <!-- Add "Print" button here -->
                 <div class="row">
                    <button class="btn btn-primary" onclick="printData()">Print</button>
                </div>
                <div class="row">
                    <div class="card">
                        <div class="table-responsive">
                            <table id="" class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Sr No.</th>
                                        <th>Bus Name</th>
                                        <th>GR No.</th>
                                        <th>{{App\Helpers\get_string('StudentName')}}</th>
                                        <th>Std</th>
                                        <th>Div</th>
                                        <th>Mobile</th>
                                        <th style="text-align:left;">Address</th>
                                    </tr>
                                </thead>
                                <tbody id="table_data">
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
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
    $(document).ready(function() {
        var table = $('#example').DataTable( {
         select: true,          
         lengthMenu: [ 
                        [100, 500, 1000, -1], 
                        ['100', '500', '1000', 'Show All'] 
        ],
        dom: 'Bfrtip', 
        buttons: [ 
            { 
                extend: 'pdfHtml5',
                title: 'Vanwise Report',
                orientation: 'landscape',
                pageSize: 'LEGAL',                
                pageSize: 'A0',
                exportOptions: {                   
                     columns: ':visible'                             
                },
            }, 
            { extend: 'csv', text: ' CSV', title: 'Vanwise Report' }, 
            { extend: 'excel', text: ' EXCEL', title: 'Vanwise Report' }, 
            {
                extend: 'print',
                text: ' PRINT',
                title: 'Vanwise Report',
                customize: function (win) {
                    $(win.document.body).prepend(`{!! App\Helpers\get_school_details("", "", "") !!}`);
                    $(win.document.body).append(`<div style="text-align: right;margin-top:20px">Printed on: {{date('d-m-Y H:i:s')}}</div>`);
                }
            },
            'pageLength' 
        ], 
        }); 
        //table.buttons().container().appendTo('#example_wrapper .col-md-6:eq(0)');

        $('#example thead tr').clone(true).appendTo( '#example thead' );
        $('#example thead tr:eq(1) th').each( function (i) {
            var title = $(this).text();
            $(this).html( '<input type="text" placeholder="Search '+title+'" />' );

            $( 'input', this ).on( 'keyup change', function () {
                if ( table.column(i).search() !== this.value ) {
                    table
                        .column(i)
                        .search( this.value )
                        .draw();
                }
            } );
        } );
    } );
</script>
<script>
function add_data(transport_vehicle_id, transport_school_shift_id) {
    $("#table_data").empty();
    $(document).ready(function() {
        $.ajax({
            url: '/transportation/transportationLists/studentLists/' + transport_vehicle_id + "/" + transport_school_shift_id,
            type: 'GET',
            dataType: 'json',
            success: function(data) 
            {
                $.each(data, function(index, value) 
                {
                    index++;
                    
                    // console.log(value['student_name']);
                    $('#table_data').append("<tr><td>" + index + "</td><td>" + value['bus_name'] + "</td><td>" + value['enrollment_no'] + "</td><td>" + value[
                            'student_name'] + "</td><td>" + value['standard_name'] + "</td><td>" + value['division_name'] + "</td><td>" + value['mobile'] + "</td><td>" + value['address'] + "</td></tr>");
                });

                $('#ChapterModal').modal('show');
            }
        });
    });
}
</script>
<script>
function printData() {
    // Assuming the modal contains the content to be printed
    var printContents = document.getElementById("ChapterModal").innerHTML;
    var originalContents = document.body.innerHTML;
    document.body.innerHTML = printContents;
    window.print();
    document.body.innerHTML = originalContents;

    // Close the modal
    $('#ChapterModal').modal('hide');

    // Reload the page
    location.reload();
}
</script>
@include('includes.footer')
