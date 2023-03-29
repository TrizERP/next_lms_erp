@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Class Teacher Report</h4>
            </div>            
        </div>
        @php
            $grade_id = $standard_id = $division_id = $teacher_id = '';

            if(isset($data['grade_id'])){
                $grade_id = $data['grade_id'];
                $standard_id = $data['standard_id'];
                $division_id = $data['division_id'];
            }

            
        @endphp
        <div class="card"> 
            @if ($sessionData = Session::get('data'))
             <div class="alert alert-block {{ $sessionData['class'] }}">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $sessionData['message'] }}</strong>
            </div>
            @endif
            <form action="{{ route('classteacherReport.create') }}" enctype="multipart/form-data">
                @csrf       
                <div class="row">                                                                     
                    {{ App\Helpers\SearchChain('4','single','grade,std,div',$grade_id,$standard_id,$division_id) }}                            
                    <div class="col-md-4 form-group"> 
                        <label>Select Teacher</label>                                                                         
                        <select class="selectpicker form-control" name="teacher_id" id="teacher_id">
                            <option value="">Select Teacher</option>                        
                            @if(isset($data['teacher_data']))
                            @foreach($data['teacher_data'] as $key =>$val)
                                @php 
                                $selected = '';
                                if( isset($data['teacher_id']) && $data['teacher_id'] == $val->id )
                                {
                                    $selected = 'selected';
                                }
                                @endphp                                                                                                                                                                            
                                <option {{$selected}} value="{{$val->id}}">{{$val->teacher_name}}</option>                            
                            @endforeach                       
                            @endif                                                                                                 
                        </select>                        
                    </div>                            
                    <div class="col-md-12 form-group">                                                        
                        <center>                        
                            <input type="submit" name="submit" value="Search" class="btn btn-success">
                        </center>
                    </div>
                </div>         
            </form> 
        </div> 
        @if(isset($data['data']))
        @php
            if(isset($data['data'])){
                $data_class = $data['data'];
            }

        @endphp 
        <div class="card">
            <div class="row">                
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">                        
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Sr. No.</th>
                                    <th>Class Teacher</th>
                                    <th>Academic Section</th>
                                    <th>Standard</th>
                                    <th>Division</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php 
                                $i=1; 
                                @endphp
                                 @if(isset($data['data']))
                                    @foreach($data_class as $key => $value) 
                                    <tr>    
                                        <td>{{$i++}}</td>
                                        <td>{{$value['teacher_name']}}</td>     
                                        <td>{{$value['academic_section_name']}}</td>                 
                                        <td>{{$value['standard_name']}}</td>                 
                                        <td>{{$value['division_name']}}</td>     
                                    </tr>
                                    @php
                                        $i++;
                                    @endphp
                                    @endforeach
                                 @endif   
                            </tbody>
                        </table>
                    </div>    
                </div>
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
                title: 'Class Teacher Report',
                orientation: 'landscape',
                pageSize: 'LEGAL',                
                pageSize: 'A0',
                exportOptions: {                   
                     columns: ':visible'                             
                },
            }, 
            { extend: 'csv', text: ' CSV', title: 'Class Teacher Report' }, 
            { extend: 'excel', text: ' EXCEL', title: 'Class Teacher Report'}, 
            { extend: 'print', text: ' PRINT', title: 'Class Teacher Report'}, 
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
