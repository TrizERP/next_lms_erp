@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Hostel Room Report1</h4> </div>
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
                    @if($sessionData['status_code'] == 1)
                    <div class="alert alert-success alert-block">
                    @else
                    <div class="alert alert-danger alert-block">
                    @endif
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $sessionData['message'] }}</strong>
                    </div>
                    @endif
                    <form action="{{ route('show_hostel_report') }}" enctype="multipart/form-data" method="post">
                        
                        <div class="row">
                            {{ App\Helpers\SearchChain('3','single','grade,std,div',$grade_id,$standard_id,$division_id) }}

                            <div class="col-md-3 form-group">
                                <label for="gender">Select Gender:</label>
    	                        <select name="gender" id="gender" class="form-control">
    	                            <option value=""> Select Gender </option>
    	                            <option value="M" @if(isset($data['gender'])){{ $data['gender'] == 'M' ? 'selected' : '' }}@endif> Male </option>
    	                            <option value="F" @if(isset($data['gender'])){{ $data['gender'] == 'F' ? 'selected' : '' }}@endif> Female </option>
    	                        </select>
                            </div>
                            @if(isset($data['profiles']))
                            <div class="col-md-3 form-group">
                                <label for="user">Select User:</label>
                                <select name="user" required id="user" class="form-control">
                                    <option value=""> Select User </option>
                                        @foreach($data['profiles'] as $key => $value)
                                            @php 
                                                $selected = '';
                                            @endphp
                                        @if(isset($data['userProfile']))
                                            @if($data['userProfile'][0]['id'] == $value['id'])
                                                @php 
                                                $selected = 'selected="selected"';
                                                @endphp
                                            @endif
                                        @endif
                                        <option value="{{$value['id']}}" {{$selected}}> {{$value['name']}} </option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            @if(isset($data['admissionCategoryList']))
                            <div class="col-md-3 form-group">
                                <label for="admissionCategory">Select Admission Category</label>
                                <select name="admissionCategory" id="admissionCategory" class="form-control">
                                    <option value=""> Select Admission Category </option>
                                        @foreach($data['admissionCategoryList'] as $key => $value)
                                            @php 
                                                $selected = '';
                                            @endphp
                                        @if(isset($data['admissionCategory']))
                                            @if($data['admissionCategory'] == $key)
                                                @php 
                                                $selected = 'selected="selected"';
                                                @endphp
                                            @endif
                                        @endif
                                        <option value="{{$key}}" {{$selected}}> {{$value}} </option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            @if(isset($data['hostelList']))
                            <div class="col-md-3 form-group">
                                <label for="hostel">Select Hostel</label>
                                <select name="hostel" id="hostel" onchange="getRooms(this.value)" class="form-control">
                                    <option value=""> Select Hostel  </option>
                                        @foreach($data['hostelList'] as $key => $value)
                                            @php 
                                                $selected = '';
                                            @endphp
                                        @if(isset($data['hostel']))
                                            @if($data['hostel'] == $key)
                                                @php 
                                                $selected = 'selected="selected"';
                                                @endphp
                                            @endif
                                        @endif
                                        <option value="{{$key}}" {{$selected}}> {{$value}} </option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif


                            @if(isset($data['hostelList']))
                            <div class="col-md-3 form-group">
                                <label for="room">Select Room</label>
                                <select name="room" id="room"  class="form-control">
                                    <option value=""> Select Room  </option>
                                </select>
                                </div>
                            @endif

                            <div class="col-md-3 form-group">
                                <input type="submit" name="submit" value="Search" class="btn btn-success" >    
                            </div>
                        </div>
                    </form>       
                </div>
            </div>
        
        @if(isset($data['data']))
        @php
            if(isset($data['data'])){
                $student_data = $data['data'];
            }
        @endphp        
                <div class="panel-body">        
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">
                            @if(isset($data['tableHeads']))
                            <thead>
                                <tr>
                                    @foreach($data['tableHeads'] as $key => $value)
                                        <th>{{ucfirst(str_replace("_"," ",$value))}}</th>
                                    @endforeach
                                    <th>Admission Category</th>
                                    <th>Hostel</th>
                                    <th>Room No</th>
                                    <th>Bed No</th>
                                    <th>Locker No</th>
                                    <th>Table No</th>
                                    <th>Bedsheet No</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach($data['data'] as $keyData => $valueData)
                                <tr>
                                        @foreach($data['tableHeads'] as $key => $value)

                                            <td>{{$valueData[$value]}}</td>
                                        @endforeach
                                        
                                        <td>{{$data['admissionCategoryList'][$valueData['admission_category_id']]}}</td>
                                        <td>{{$data['hostelList'][$valueData['hostel_id']]}}</td>
                                        <td>{{$data['roomList'][$valueData['room_id']]}}</td>
                                        <td>{{$valueData['bed_no']}}</td>
                                        <td>{{$valueData['locker_no']}}</td>
                                        <td>{{$valueData['table_no']}}</td>
                                        <td>{{$valueData['bedsheet_no']}}</td>
                                    </tr>
                                    @endforeach
                            </tbody>
                            @endif
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
                title: 'Hostel Room Report',
                orientation: 'landscape',
                pageSize: 'LEGAL',                
                pageSize: 'A0',
                exportOptions: {                   
                     columns: ':visible'                             
                },
            }, 
            { extend: 'csv', text: ' CSV', title: 'Hostel Room Report' }, 
            { extend: 'excel', text: ' EXCEL', title: 'Hostel Room Report' }, 
            { extend: 'print', text: ' PRINT', title: 'Hostel Room Report' },
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
<script>
    function getRooms(hostel_id,room_id = 0){
        var path = "{{ route('hostelWiseRoomList') }}";
        var roomId = "#room";
        $(roomId).find('option').remove().end().append('<option value=""> Select Room </option>').val('');
        $.ajax({
        type:'POST',
        url:path,
        data:{hostel_id:hostel_id},
            success:function(data){
                for(var i=0;i < data.length;i++){
                    
                    if(data[i]['id'] == room_id)
                    {
                        $(roomId).append($("<option value=''> Select Room </option>").val(data[i]['id']).html(data[i]['room_name']).attr("selected","selected"));  
                        
                    }else{
                        $(roomId).append($("<option value=''> Select Room </option>").val(data[i]['id']).html(data[i]['room_name']));  
                    }
                } 
            }
        });
    }

    $( document ).ready(function() {
        var hostel = @if(isset($data['hostel'])) {{$data['hostel']}} @else '0' @endif;
        var room_id = @if(isset($data['room'])) {{$data['room']}} @else '0' @endif;
        if(hostel != 0)
        {
            getRooms(hostel,room_id);
        }
    });
</script>
@include('includes.footer')
