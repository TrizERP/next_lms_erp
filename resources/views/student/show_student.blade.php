@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Search Student</h4>
            </div>
        </div>
        @php
        $grade_id = $standard_id = $division_id = '';

        if(isset($data['grade_id'])){
        $grade_id = $data['grade_id'];
        $standard_id = $data['standard_id'];
        $division_id = $data['division_id'];
        }
        @endphp
        <div class="card">
            @if ($sessionData = Session::get('data'))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $sessionData['message'] }}</strong>
            </div>
            @endif
            <form action="{{ route('show_search_student') }}" enctype="multipart/form-data" method="post">
                <div class="row">
                    {{ App\Helpers\SearchChain('4','single','grade,std,div',$grade_id,$standard_id,$division_id) }}
                </div>
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label class="box-title after-none mb-0">Last Name</label>
                        <div class = "ui-widget">
                            <input type="text" name="last_name" id="last_name" value="@if(isset($data['last_name'])) {{$data['last_name']}} @endif" class="form-control" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-4 form-group">
                        <label class="box-title after-none mb-0">First Name</label>
                        <div class = "ui-widget">
                            <input type="text" name="first_name" id="first_name" value="@if(isset($data['first_name'])) {{$data['first_name']}} @endif" class="form-control" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-4 form-group">
                        <label class="box-title after-none mb-0">Mobile</label>
                        <input type="text" name="mobile" value="@if(isset($data['mobile'])) {{$data['mobile']}} @endif" class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label class="box-title after-none mb-0">Gr No.</label>
                        <input type="text" name="gr_no" value="@if(isset($data['gr_no'])) {{$data['gr_no']}} @endif" class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label class="box-title after-none mb-0">UniqueID/Adm.No</label>
                        <input type="text" name="unique_id" value="@if(isset($data['unique_id'])) {{$data['unique_id']}} @endif" class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <div class="d-inline">                                                        
                            <input type="checkbox" name="including_inactive" value="Yes" @if(isset($data['including_inactive'])) @if($data['including_inactive'] == 'Yes') checked @endif @endif>
                            <span>Including In-active Students</span>
                        </div>
                    </div>
                </div>

                        

                <div class="row">
                    <div class="col-md-12 form-group">
                        <center>
                            <input type="submit" name="submit" value="Search" class="btn btn-success">
                        </center>
                    </div>
                </div>    
                </div>
            </form>
        </div>
        @if(isset($data['data']))
        @php
        if(isset($data['data'])){
        $student_data = $data['data'];
        }

        @endphp
        <div class="card">
            <span class="d-inline-block mb-2" tabindex="0" data-toggle="tooltip" title="Pink colour indicates In-active students">
              <button class="btn btn-danger" style="pointer-events: none;" type="button" disabled>Note</button>
            </span>
          <!-- <h4 class="text-danger">Note : Pink colour indicates In-active students</h4> -->
            <div class="row">
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Id</th>
                                    <th>Student Name</th>
                                    <th>Gr No.</th>
                                    <th>UniqueID/Adm.No</th>
                                    <th>Academic Section</th>
                                    <th>Standard</th>
                                    <th>Division</th>
                                    <th>Gender</th>
                                    <th>Mobile</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                $j=1;
                                @endphp
                                @foreach($student_data as $key => $data)
                                <tr style="background-color:{{$data->inactive_colour}}">
                                    <td>{{$j}}</td>
                                    <td>{{$data->first_name}} {{$data->middle_name}} {{$data->last_name}}</td>
                                    <td>{{$data->enrollment_no}}</td>
                                    <td>{{$data->uniqueid}}</td>
                                    <td>{{$data->grade}}</td>
                                    <td>{{$data->standard}}</td>
                                    <td>{{$data->division}}</td>
                                    <td>{{$data->gender}}</td>
                                    <td>{{$data->mobile}}</td>                                    
                                    <td>
                                         <div class="d-flex align-items-center justify-content-end">
                                            <a href="{{ route('add_student.edit',$data->student_id)}}" class="btn btn-outline-success mr-1"><i class="ti-pencil-alt"></i></a>
                                        
                                            <!-- <form action="{{ route('add_student.destroy', $data->student_id)}}" method="post">
                                            @csrf
                                            @method('DELETE')
                                                <button type="submit" onclick="return confirmDelete();" class="btn btn-info btn-outline-danger"><i class="ti-trash"></i></button>
                                            </form> -->
                                        </div>
                                    </td>
                                </tr>
                                @php
                                $j++;
                                @endphp
                                @endforeach
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
<link href = "https://code.jquery.com/ui/1.10.4/themes/ui-lightness/jquery-ui.css" rel = "stylesheet">
<script src = "https://code.jquery.com/jquery-1.10.2.js"></script>
<script src = "https://code.jquery.com/ui/1.10.4/jquery-ui.js"></script>

<script>
    $(document).ready(function() {
        
        $("#first_name").autocomplete({          
          source: function( request, response ) 
          {        
            $.ajax({
                    url: "{{route('search_student_by_firstname')}}",
                    type: 'POST',
                    data: {
                        'value': request.term
                    },
                    success: function(data){
                       response( $.map( data, function( item ) {
                          return {
                              label: item.first_name,
                              value: item.first_name
                          }
                      }));
                    }
                });
          }
        });

        $("#last_name").autocomplete({          
          source: function( request, response ) 
          {        
            $.ajax({
                    url: "{{route('search_student_by_lastname')}}",
                    type: 'POST',
                    data: {
                        'value': request.term
                    },
                    success: function(data){
                       response( $.map( data, function( item ) {
                          return {
                              label: item.last_name,
                              value: item.last_name
                          }
                      }));
                    }
                });
          }
        });


        $('#example thead tr').clone(true).appendTo('#example thead');
        $('#example thead tr:eq(1) th').each(function(i) {
            var title = $(this).text();
            $(this).html('<input type="text" size="4" style="color:black !important;" placeholder="Search ' + title + '" />');

            $('input', this).on('keyup change', function() {
                if (table.column(i).search() !== this.value) {
                    table
                        .column(i)
                        .search(this.value)
                        .draw();
                }
            });
        });

        $('#example').DataTable({
            "pageLength": 100
        });
        /*var table = $('#visitor_list').DataTable({
		orderCellsTop: true,
        fixedHeader: true,
        dom: 'Bfrtip',
        buttons: [
            'copyHtml5',
            'excelHtml5',
            'csvHtml5',
            'pdfHtml5'
        ]
    } );*/

  
      
  
    });

  
  
</script>
@include('includes.footer')