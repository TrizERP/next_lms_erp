{{--@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Additional Fees Report</h4> </div>
            </div>
        @php
            $grade_id = $standard_id = $division_id  = '';

            if(isset($data['grade_id'])){
                $grade_id = $data['grade_id'];
                $standard_id = $data['standard_id'];
                $division_id = $data['division_id'];
            }

        @endphp
        <div class="card">
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
                <form action="{{ route('other_fees_report.create') }}" enctype="multipart/form-data" class="row">
                    @csrf
                    {{ App\Helpers\SearchChain('4','multiple','grade,std,div',$grade_id,$standard_id,$division_id) }} //added by vivek 10-10-2025

                    <div class="col-md-12 form-group">
                        <center>
                            <input type="submit" name="submit" value="Search" class="btn btn-success">
                        </center>
                    </div>

                </form>
            </div>
        @if(isset($data['other_feesData']))
        @php
            if(isset($data['other_feesData'])){
                $other_feesData = $data['other_feesData'];
            }

        @endphp
        <div class="card">
            <div class="table-responsive">
                <table id="example" class="table table-striped">
                    <thead>
                        <tr>
                            <th>Sr No.</th>
                            <th>Receipt No</th>
                            <th>Receipt Date</th>
                            <th>{{ App\Helpers\get_string('grno','request')}}</th>
                            <th>{{ App\Helpers\get_string('studentname','request')}}</th>
                            <th>{{ App\Helpers\get_string('standard','request')}}</th>
                            <th>{{ App\Helpers\get_string('division','request')}}</th>
                            <th>Mobile No.</th>
                            <!-- <th>Uniquie Id</th> -->
                            @if(isset($data['other_fee_title']))
                                 @foreach($data['other_fee_title'] as $key => $otherhead)
                                    <th>{{$otherhead->display_name}}</th>
                                 @endforeach
                            @endif
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                    @php
                    $j=1;
                    $final_grand_total = 0;
                    if(isset($data['other_fee_title']))
                    {
                        foreach($data['other_fee_title'] as $key => $otherhead)
                        {
                            $grand_total[$otherhead->fees_title] = 0;
                        }
                    }
                    @endphp

                    @if(isset($data['other_feesData']))
                        @foreach($other_feesData as $key => $fees_value)
                        @php $student_total = 0; @endphp
                        <tr>
                            <td>{{$j}}</td>
                            <td>{{$fees_value['reciept_id']}}</td>
                            <td>{{date('d-m-Y', strtotime($fees_value['receiptdate']))}}</td>
                            <td>{{$fees_value['enrollment_no']}}</td>
                            <td>{{App\Helpers\sortStudentName($fees_value['student_name'])}}</td>
                            <td>{{$fees_value['standard_name']}}</td>
                            <td>{{$fees_value['division_name']}}</td>
                            <td>{{$fees_value['mobile']}}</td>
                            <!-- <td>{{$fees_value['uniqueid']}}</td>  -->
                            @if(isset($data['other_fee_title']))
                                 @foreach($data['other_fee_title'] as $key => $otherhead)
                                    @php
                                        $field_name = "sum_".$otherhead->fees_title;
                                        $student_total += $fees_value[$field_name];
                                        $grand_total[$otherhead->fees_title] += $fees_value[$field_name];
                                    if($fees_value[$field_name] != "")
                                    {
                                        echo "<td>$fees_value[$field_name]</td>";
                                    }
                                    else
                                    {
                                        echo "<td>0</td>";
                                    }

                                    @endphp
                                 @endforeach
                            @endif
                            <td>{{$student_total}}</td>
                        </tr>
                    @php
                    $j++;
                    @endphp
                        @endforeach
                        <tr class="font-weight-bold">
                            <td>{{$j++}}</td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td>Total</td>
                            @if(isset($data['other_fee_title']))
                                 @foreach($data['other_fee_title'] as $key => $otherhead)
                                    <td>{{$grand_total[$otherhead->fees_title]}}</td>
                                    @php $final_grand_total += $grand_total[$otherhead->fees_title]; @endphp
                                 @endforeach

                            @endif
                            <td>{{$final_grand_total}}</td>
                        </tr>

                    @endif
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
</div>

@include('includes.footerJs')
<script>
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
                title: 'Other Fees Report',
                orientation: 'landscape',
                pageSize: 'LEGAL',
                pageSize: 'A0',
                exportOptions: {
                     columns: ':visible'
                },
            },
            { extend: 'csv', text: ' CSV', title: 'Other Fees Report' },
            { extend: 'excel', text: ' EXCEL', title: 'Other Fees Report'},
            { extend: 'print', text: ' PRINT', title: 'Other Fees Report'},
            'pageLength'
        ],
        });

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
@include('includes.footer')
@endsection
