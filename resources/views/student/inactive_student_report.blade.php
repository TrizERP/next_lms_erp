@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Inactive Student Report</h4>
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

            @php
            $grade_id = $standard_id = $division_id = '';
            
                if(isset($data['grade_id'])){
                    $grade_id = $data['grade_id'];
                    $standard_id = $data['standard_id'];
                    $division_id = $data['division_id'];
                }
            @endphp   
            <form action="{{ route('inactive_student_report.create') }}" enctype="multipart/form-data">                
                @csrf  
                <div class="row">                    
                    {{ App\Helpers\SearchChain('4','single','grade,std,div',$grade_id,$standard_id,$division_id) }}
                    <div class="col-md-4 form-group mt-3">
                        <input type="submit" name="submit" value="Search" class="btn btn-success" >                     
                     <button type="button" class="btn btn-info" data-toggle="modal"
                                                data-target="#exampleModal"><i class="mdi mdi-tune"></i></button>
                                    </div>
                                </div>
                                <!-- Modal -->
                                <div class="modal fade bd-example-modal-lg" id="exampleModal" tabindex="-1"
                                     role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="exampleModalLabel">Choose Field</h5>
                                                <button type="button" class="close" data-dismiss="modal"
                                                        aria-label="Close">
                                                    <span aria-hidden="true">x</span>
                            </button>
                          </div>
                          <div class="modal-body">
                                <div class="slimscrollright">
                                    <div class="rpanel-title"><span><i class="ti-close right-side-toggle"></i></span> </div>
                                    <div class="row">
                                        <div class="col-md-12 form-group mb-2">
                                            <div class="checkbox checkbox-info">
                                                <input id="checkall" onclick="checkedAll();" name="checkall" type="checkbox">
                                                <label for="checkall"> Check All </label>
                                                <input type="hidden" name="page" value="bulk">
                                            </div>
                                        </div>

                                    @if(isset($data['data']))
                                            @php
                                        //$checkedArray = array('enrollment_no','first_name','middle_name','last_name','mobile');
                                        $checkedArray = array();
                                        @endphp
                                        @foreach($data['data'] as $key => $value)
                                        <div class="col-md-4 form-group mt-1">
                                            <div class="custom-control custom-checkbox">
                                                @php
                                                $checked = '';
                                                if(in_array($key,$checkedArray)){
                                                    $checked = 'checked="checked"';
                                                }
                                                if(isset($data['headers'])){
                                                    if(count($data['headers']) > 0){
                                                        $headersChecked = array_keys($data['headers']);
                                                    }
                                                    $checked = '';
                                                    if(in_array($key,$headersChecked)){
                                                        $checked = 'checked="checked"';
                                                    }
                                                }
                                                @endphp
                                                <input id="{{$key}}" {{$checked}} value="{{$key}}" class="custom-control-input" name="dynamicFields[]" type="checkbox">
                                                <label for="{{$key}}" class="custom-control-label"> {{$value}} </label>
                                            </div>
                                        </div>
                                        @endforeach
                                    @endif
                                    </div>
                                </div>
                          </div>
                                        </div>
                                    </div>
                                </div>
                </div>              
            </form>
        </div>
      

        @if(isset($data['result_report']))
        @php
        $j = 1;
            if(isset($data['result_report'])){
                $result_report = $data['result_report'];
            }
        @endphp
        <div class="card">            
            <div class="table-responsive">
                <table id="example" class="table table-striped">
                    <thead>
                        <tr>
                            <th>Sr No</th>
                            <!-- <th>Enrollment No</th>
                            <th>Student Name</th>
                            <th>Standard</th>
                            <th>Division</th> -->
                            @foreach($result_report[0] as $heads=>$value)
                                <th>{{$heads}}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($result_report as $stud_key => $stud_data)
                            <tr>
                                <td>{{$j++}}</td>
                                @foreach($stud_data as $value)
                                <td>{{$value}}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>    
        @endif
    </div>
</div>

<script>
    function checkAll(ele,name) {
         var checkboxes = document.getElementsByClassName(name);
         if (ele.checked) {
             for (var i = 0; i < checkboxes.length; i++) {
                 if (checkboxes[i].type == 'checkbox') {
                     checkboxes[i].checked = true;
                 }
             }
         } else {
             for (var i = 0; i < checkboxes.length; i++) {
                 console.log(i)
                 if (checkboxes[i].type == 'checkbox') {
                     checkboxes[i].checked = false;
                 }
             }
         }
    }
</script>

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
                title: 'Inactive Student Report',
                orientation: 'landscape',
                pageSize: 'LEGAL',                
                pageSize: 'A0',
                exportOptions: {                   
                     columns: ':visible'                             
                },
            }, 
            { extend: 'csv', text: ' CSV', title: 'Inactive Student Report' }, 
            { extend: 'excel', text: ' EXCEL', title: 'Inactive Student Report' }, 
            { extend: 'print', text: ' PRINT', title: 'Inactive Student Report' }, 
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

