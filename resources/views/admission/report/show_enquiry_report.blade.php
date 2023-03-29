@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Admission Enquiry Report</h4>
            </div>
        </div>
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
            <div class="row">                
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('admission_enquiry_report') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("POST") }}
                        @csrf
                        <div class="row">
                            <div class="col-md-3 form-group">
                                <label>From Date </label>
                                <input type="text" id='from_date' required name="from_date" @if(isset($data['from_date'])) value="{{$data['from_date']}}" @endif class="form-control mydatepicker" autocomplete="off">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>To Date </label>
                                <input type="text" id='to_date' required name="to_date" @if(isset($data['to_date'])) value="{{$data['to_date']}}" @endif class="form-control mydatepicker" autocomplete="off">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Standard </label>
                                <select id='standard' name="standard" class="form-control">
                                    <option value=""> Select Standard </option>
                                    <option value="NURSERY" @if(isset($data['standard'])) @if($data['standard'] == "NURSERY") selected="selected" @endif @endif> Nursery </option>
                                    <option value="JRKG" @if(isset($data['standard'])) @if($data['standard'] == "JRKG") selected="selected" @endif @endif> Jrkg </option>
                                    <option value="SRKG" @if(isset($data['standard'])) @if($data['standard'] == "SRKG") selected="selected" @endif @endif> Srkg </option>
                                    <option value="1" @if(isset($data['standard'])) @if($data['standard'] == "1") selected="selected" @endif @endif> 1 </option>
                                    <option value="2" @if(isset($data['standard'])) @if($data['standard'] == "2") selected="selected" @endif @endif> 2 </option>
                                    <option value="3" @if(isset($data['standard'])) @if($data['standard'] == "3") selected="selected" @endif @endif> 3 </option>
                                    <option value="4" @if(isset($data['standard'])) @if($data['standard'] == "4") selected="selected" @endif @endif> 4 </option>
                                    <option value="5" @if(isset($data['standard'])) @if($data['standard'] == "5") selected="selected" @endif @endif> 5 </option>
                                    <option value="6" @if(isset($data['standard'])) @if($data['standard'] == "6") selected="selected" @endif @endif> 6 </option>
                                    <option value="7" @if(isset($data['standard'])) @if($data['standard'] == "7") selected="selected" @endif @endif> 7 </option>
                                    <option value="8" @if(isset($data['standard'])) @if($data['standard'] == "8") selected="selected" @endif @endif> 8 </option>
                                    <option value="9" @if(isset($data['standard'])) @if($data['standard'] == "9") selected="selected" @endif @endif> 9 </option>
                                    <option value="10" @if(isset($data['standard'])) @if($data['standard'] == "10") selected="selected" @endif @endif> 10 </option>
                                    <option value="11COM" @if(isset($data['standard'])) @if($data['standard'] == "11COM") selected="selected" @endif @endif> 11 COM </option>
                                    <option value="11ART" @if(isset($data['standard'])) @if($data['standard'] == "11ART") selected="selected" @endif @endif> 11 ART </option>
                                    <option value="11SCI" @if(isset($data['standard'])) @if($data['standard'] == "11SCI") selected="selected" @endif @endif> 11 SCI </option>
                                    <option value="12COM" @if(isset($data['standard'])) @if($data['standard'] == "12COM") selected="selected" @endif @endif> 12 COM </option>
                                    <option value="12ART" @if(isset($data['standard'])) @if($data['standard'] == "12ART") selected="selected" @endif @endif> 12 ART </option>
                                    <option value="12SCI" @if(isset($data['standard'])) @if($data['standard'] == "12SCI") selected="selected" @endif @endif> 12 SCI </option>
                                </select>
                            </div>

                            <div class="col-md-3 form-group">
                                <label>User </label>
                                <select id='user' name="user" class="form-control">
                                    <option value=""> Select User </option>
                                    @if ( isset( $data['users'] ) )        
                                        @foreach ( $data['users'] as $user )
                                            <option value="{{ $user->id }}" @php echo ( isset($data['ser_user']) && $data['ser_user'] == $user->id ) ? 'selected' : '' @endphp >{{ $user->name_suffix.' '.$user->first_name.' '.$user->last_name }}</option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                            
                            <div class="col-md-12 form-group">                            
                                <center>
                                    <input type="submit" name="report" value="Search" class="btn btn-success" >
                                </center>
                            </div>
                        </div>    
                    </form>    
                </div>
            </div>
        </div>
        
        <div class="card">            
            @if(isset($data['data']))
            <div class="row">                
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    @foreach($data['headers'] as $hkey => $header)
                                        <th> {{ucfirst(str_replace("_", " ", $header))}} </th>
                                    @endforeach
                                    
                                </tr>
                            </thead>
                            <tbody>

                            	@php 
                            		$grand_total_admission_fees = $grand_total_fees_amount = 0; 
                            	@endphp		
                            	@foreach($data['data'] as $key => $value)
                            	@php
                                    if(isset($value['admission_fees'])){
    									$grand_total_admission_fees = $grand_total_admission_fees + $value['admission_fees'];
    									$grand_total_fees_amount = $grand_total_fees_amount + $value['fees_amount'];
                                        }
                            	@endphp
                                    <tr>    
                                        @foreach($data['headers'] as $hkey => $header)
                                            @if($header == 'address')
                                                <td data-toggle="popover" data-content="{{$value[$header]}}"> 
                                                    {{substr($value[$header],0,50)}} 
                                                    <!-- <span style="font-size: 28px;color: black;font-weight: bolder;">...</span> -->
                                                </td>
                                            @elseif($header == 'fees_circular')
                                                <td>
                                                    <button type="button" class="btn btn-info float-right" data-toggle="modal" onclick="javascript:add_data({{$value['id']}});">Circular</button>
                                                    <input type="hidden" name="fees_html_{{$value['id']}}" id="fees_html_{{$value['id']}}" value="{{$value[$header]}}">
                                                </td>
                                            @elseif($header == 'followup_date' || $header == 'created_on' || $header == 'date_of_birth')
                                                <td> {{date('d-m-Y H:i:s', strtotime($value[$header]))}} </td>    
                                            @else
                                                <td> {{$value[$header]}} </td>
                                            @endif

                                        @endforeach 
                                        
                                    </tr>
                                    
                                @endforeach 
                                
                                @if(isset($value['admission_fees']))
                                    <tr>                               
                                        <th>Total</th>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <th>{{$grand_total_admission_fees}}</th>
                                            <th>{{$grand_total_fees_amount}}</th>
                                            <td></td>
                                            <td></td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

<!--Modal: Add ChapterModal-->
    <div id="printThis">
        <div class="modal fade right modal-scrolling" id="ChapterModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" style="display: none;" aria-hidden="true">
            <div class="modal-dialog modal-side modal-bottom-right modal-notify modal-info" role="document" style="max-width: 75%;">
                <!--Content-->
                <div class="modal-content">
                    <!--Header-->
                    <div class="modal-header" style="display: block !important;text-align: center !important;">
                        <h5 class="modal-title" id="heading"><b>Re-Print Circular</b></h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">x</span>
                        </button>
                    </div>
                    <!--Body-->
                    <div class="modal-body">
                        <div class="row">
                            <div class="panel-body">
                                <div class="col-lg-12 col-sm-12 col-xs-12">
                                    <div id="reprint_receipt_html">
                                    </div>
                                </div>
                            </div>                        
                        </div>
                    </div>
                    <!--Footer-->
                    <div class="modal-footer flex-center" style="display: block !important;text-align: center !important;">
                        <!-- <button class="btn" data-dismiss="modal" aria-hidden="true">Close</button> -->
                        <button id="btnPrint" type="button" class="btn btn-primary">Print</button>                            
                    </div>
                </div>
                <!--/.Content-->
            </div>
        </div>
    </div>    
<!--Modal: Add ChapterModal-->

@include('includes.footerJs')
<script>
        document.getElementById("btnPrint").onclick = function () 
        {
            PrintDiv("reprint_receipt_html");
        }

        function PrintDiv(divName) {
            var divToPrint = document.getElementById(divName);
            var popupWin = window.open('', '_blank', 'width=300,height=300');
            popupWin.document.open();
            popupWin.document.write('<html>');
            popupWin.document.write('<body onload="window.print()">' + divToPrint.innerHTML + '</html>');
            popupWin.document.close();
        }

        function add_data(s)
        {
            var fees_content = $('#fees_html_'+s).val();
            // alert(fees_content);
            $('#reprint_receipt_html').html(fees_content);
            $('#ChapterModal').modal('show');
           
        }
</script>
<script>
$(document).ready(function(){  
  $('[data-toggle="popover"]').popover({title: "",html: true});
  $('[data-toggle="popover"]').on('click', function (e) {
    $('[data-toggle="popover"]').not(this).popover('hide');
    });
});
</script>    
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
                title: 'Admission Enquiry Report',
                orientation: 'landscape',
                pageSize: 'LEGAL',                
                pageSize: 'A0',
                exportOptions: {                   
                     columns: ':visible'                             
                },
            }, 
            { extend: 'csv', text: ' CSV', title: 'Admission Enquiry Report' }, 
            { extend: 'excel', text: ' EXCEL', title: 'Admission Enquiry Report'}, 
            { extend: 'print', text: ' PRINT', title: 'Admission Enquiry Report'}, 
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
