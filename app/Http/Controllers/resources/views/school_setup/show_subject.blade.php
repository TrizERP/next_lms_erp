@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<!-- division = L -->
<!-- <div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Create Subject</h4>
            </div>            
        </div>        
        <div class="card">    
            @if ($sessionData = Session::get('data'))
            <div class="@if($sessionData['status_code']==1) alert alert-success alert-block @else alert alert-danger alert-block @endif ">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $sessionData['message'] }}</strong>
            </div>
            @endif
            <div class="row">
                <div class="col-lg-3 col-sm-3 col-xs-3">
                    <a href="{{ route('subject_master.create') }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add New</a>
                </div>                
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="subject_list" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Subject Name</th>
                                    <th>Subject Code</th>
                                    <th>Subject Type</th>
                                    <th>Short Name</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($data['data'] as $key => $data)
                                <tr>    
                                    <td>{{$data->subject_name}}</td>
                                    <td>{{$data->subject_code}}</td> 
                                    <td>@if($data->subject_type != "")
                                        {{$data->subject_type}}
                                        @else
                                        {{'-'}}
                                        @endif
                                    </td>                                                                 
                                    <td>{{$data->short_name}}</td>     
                                    <td>
                                        <div class="d-inline">
                                            <a href="{{ route('subject_master.edit',$data->id)}}" class="btn btn-info btn-outline"><i class="ti-pencil-alt"></i></a>                                                                        
                                        </div>                                        
                                        <form class="d-inline" action="{{ route('subject_master.destroy', $data->id)}}" method="post">
                                        @csrf
                                        @method('DELETE')
                                            <button onclick="return confirmDelete();" type="submit" class="btn btn-info btn-outline-danger"><i class="ti-trash"></i></button>
                                        </form>                                    
                                    </td> 
                                </tr>
                                @endforeach

                            </tbody>
                        </table>
                    </div>    
                </div>
            </div>                
        </div>
    </div>
</div>
 -->

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Add Masters</h4>
            </div>            
        </div>        
        <div class="card">    

             @if ($sessionData = Session::get('success'))
            <div class="alert alert-success alert-block alert">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $sessionData }}</strong>
            </div>
            @endif

             @if ($sessionData = Session::get('failed'))
            <div class="alert alert-danger alert-block alert">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $sessionData }}</strong>
            </div>
            @endif

          <div class="row">                
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form name="master" action="{{url('/school_setup/insert_data')}}" method="post" enctype="multipart/form-data">
                    @csrf
                    select master section
                     <div class="col-md-3 form-group">
                            <label>Select Master</label>
                            <select name="Slsection" id="Slsection" class="form-control">
                                <option value="">select section</option>
                                <option value="1">Academy</option>
                                <option value="2">Standard</option>
                                <option value="3">Division</option>
                                <option value="4">Subject</option>
                            </select>
                        </div>
                    <!-- pop ups for acadmic,standard,division,subject -->
                        <div class="row">                                                    
                            <!-- academic section 1 -->
                            <div class="col-md-12 form-group sectionacd d--none" style="display: none;">
                                <div class="row">
                                    <div class="col-md-3">
                                        <label>Title</label>
                                        <input type="text" name="ac_title" class="form-control" placeholder="Enter Title">
                                    </div>

                                    <div class="col-md-3">
                                        <label>Short Name</label>
                                        <input type="text" name="ac_short_name" class="form-control" placeholder="Enter Short Name">
                                    </div>

                                    <div class="col-md-3">
                                        <label>Sort Order</label>
                                        <input type="number" name="ac_sort_order" class="form-control" placeholder="Enter Sort Order">
                                    </div>

                                    <div class="col-md-3">
                                        <label>Shift</label>
                                        <input type="text" name="ac_shift" class="form-control" placeholder="Enter Shift">
                                    </div>

                                    <div class="col-md-3">
                                        <label>Medium</label>
                                        <input type="text" name="ac_medium" class="form-control" placeholder="Enter Medium">
                                    </div>

                                </div>
                            </div>
                        <!-- academic section end -->

                        <!-- standard 2 -->
                        <div class="col-md-12 form-group sectionstd d--none" style="display: none;">
                            <div class="row">                        
                                <div class="col-md-3">
                                        <label>Select Grade</label>
                                        <select name="st_grade" id="st_grade" class="form-control">
                                        <option>select</option>
                                        @foreach($grade as $grade1)
                                        <option value="{{$grade1->id}}">{{$grade1->short_name}}</option>
                                        @endforeach
                                        </select>
                                </div>

                                <div class="col-md-3">
                                    <label>Name</label>
                                    <input type="text" name="st_name" class="form-control" placeholder="Enter Standard Name">
                                </div>

                                <div class="col-md-3">
                                    <label>Short Name</label>
                                    <input type="text" name="st_short_name" class="form-control" placeholder="Enter Short Name">
                                </div>

                                <div class="col-md-3">
                                    <label>Sort Order</label>
                                    <input type="number" name="st_sort_order" class="form-control" placeholder="Enter Sort Order">
                                </div>

                                <div class="col-md-3">
                                    <label>Medium</label>
                                    <input type="text" name="st_medium" class="form-control" placeholder="Enter Medium">
                                </div>

                                <div class="col-md-3">
                                    <label>Course Duration</label>
                                    <input type="text" name="st_course_duration" class="form-control" placeholder="Enter Course Duration">
                                </div>

                                 <div class="col-md-3">
                                        <label>Select Next Grade</label>
                                        <select name="st_next_grade" id="st_next_grade" class="form-control">
                                        <option>select</option>
                                        @foreach($grade as $grade1)
                                        <option value="{{$grade1->id}}">{{$grade1->short_name}}</option>
                                        @endforeach
                                        </select>
                                    </div>

                                <div class="col-md-3">
                                    <label>Select Next Standard</label>
                                    <select name="st_next_standard" id="st_next_standard" class="form-control"></select>
                                </div>    
                                   
                            </div>
                        </div>
                    <!-- standar end -->

                   <!-- division -->
                   <div class="col-md-12 form-group sectiondvs d--none" style="display: none;">
                        <div class="row">                            
                            <div class="col-md-3 form-group">
                                <label>Enter Division</label>
                                <!-- <input type="text" name="institute" value="{{Session::get('sub_institute_id')}}"> -->
                                <input type="text" name="div_name" class="form-control" placeholder="Enter Division">
                            </div>
                        </div>
                    </div>
                    <!-- end division -->

                    <!-- subject  -->
                    <div class="col-md-12 form-group sectionsub d--none" style="display: none;">
                        <div class="row">                            
                            <div class="col-md-3 form-group">
                                <label>Subject Name</label>
                                <input type="text" id='subject_name' name="subject_name" class="form-control" placeholder="Enter Subject Name">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Subject Code</label>
                                <input type="text" id='subject_code' name="subject_code" class="form-control" placeholder="Enter Subject Code">
                            </div>                                                
                            <div class="col-md-3 form-group">
                                <label>Short Name</label>
                                <input type="text" id='short_name' name="short_name" class="form-control" placeholder="Enter Short Name">
                            </div>
                            <div class="col-md-2 form-group checkbox checkbox-info checkbox-circle mt-4">      
                                <input type="checkbox" id="subject_type" name="subject_type" value="Major">
                                <label for="subject_type">Major</label> 
                            </div>
                        </div>
                    </div>
                <!-- subject end -->

            </div>
            <!-- pop up divs end -->
                           
                    <div class="col-md-12 form-group">
                    <center>
                        <input type="submit" name="submit" value="Save" class="btn btn-success" >
                    </center>
                </div>
               </form>
           </div>
       </div>
              
                    </div>    
                </div>
            </div>                
        </div>
    </div>
</div>

@include('includes.footerJs')
<script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.19/js/dataTables.bootstrap.min.js"></script>

<script>
    $('body').on('change', '#Slsection', function(){
        var sec = $(this).val();

        $.ajax({
            url:'{{route("collectsct")}}',
            data:{"_token":"{{csrf_token()}}", "sectionId": $(this).val()},
            type:'post',
            datatype:'json',
            success:function(data){
                $('.sectionacd').hide(); $('.sectionstd').hide(); $('.sectiondvs').hide();$('.sectionsub').hide();
                if(sec ==1){ $('.sectionacd').show(); $('#Academy').html(data); }
                if(sec ==2){ $('.sectionstd').show(); $('#Standard').html(data); }
                if(sec ==3){ $('.sectiondvs').show(); $('#Division').html(data); }
                if(sec ==4){ $('.sectionsub').show(); $('#Subject').html(data); }
            }
        });
    });

    $(document).on('change','#st_next_grade' ,function(){
  var val = $('#st_next_grade option:selected').val();
 // console.log(val);
  $.ajax({
            url:'{{route("collectsct")}}',
            data:{"_token":"{{csrf_token()}}", "sectionId":5, "grade": val},
            type:'post',
            datatype:'json',
            success:function(data){
             $('#st_next_standard').html(data);
            }
        });
});

</script>
@include('includes.footer')
