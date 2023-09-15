@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<style type="text/css">
    .label{
        color:black;
        font-size: 0.8rem;
        font-weight: bold;
    }
</style>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Student Strength Report</h4>
            </div>
        </div>        
        <div class="card">
            @if ($sessionData = Session::get('data'))
                @if($sessionData['status_code'] == 1)
                    <div class="alert <!-- alert -->-success alert-block">
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
            <form action="{{ route('student_strength_report.create') }}" enctype="multipart/form-data">                
                @csrf  
                <div class="row">  
                    <!-- Admisiion and Equiry Date                   -->
                    <div class="col-md-4 mt-3">
                      <label class="label" for="">Select Any One </label>
                      <div class=" form-group d-flex">
                      <div class="form-check mt-3">
                        <input class="form-check-input" type="radio" name="one_date" value="add" id="flexRadioDefault1" @if(!isset($data['one_date']) || $data['one_date'] == "add") checked @endif>
                        <label class="form-check-label label" for="flexRadioDefault1">Admission Date</label>
                    </div>

                    <div class="form-check mt-3 ml-2">
                        <input class="form-check-input" type="radio" name="one_date" value="start" id="flexRadioDefault2" @if(isset($data['one_date']) && $data['one_date'] !== "add") checked @endif>
                        <label class="form-check-label label" for="flexRadioDefault2">Entry Date</label>
                    </div>
                    </div>
                </div>
                    <!-- standard or division wise -->
                    <div class="col-md-4 form-group mt-3">
                      <label  class="label"for="">{{ App\Helpers\get_string('standard','request')}}</label>
                        <select class="form-select form-control" multiple aria-label="multiple select" name="standard_wise[]" required>
                          <option value="standard" @if(isset($data['standard']) && in_array("standard",$data['standard']) ) selected @endif>{{ App\Helpers\get_string('standard','request')}} Wise</option>
                          <option value="division" @if(isset($data['standard']) && in_array("division",$data['standard']) ) selected @endif>{{ App\Helpers\get_string('division','request')}} Wise</option>
                        </select>
                    </div>
                    <!-- From Date  -->
                    <div class="col-md-4 form-group mt-3">
                        <label class="label">From Date</label>
                        <input type="text" id="from_date_input" name="from_date" class="form-control mydatepicker" value="{{ isset($data['from_date']) ? $data['from_date'] : '' }}" autocomplete="off" required>
                    </div>
                     <!-- To Date  -->
                     <div class="col-md-4 form-group mt-3">
                        <label class="label">To Date</label>
                        <input type="text" id="to_date_input" name="to_date" class="form-control mydatepicker" value="{{ isset($data['to_date']) ? $data['to_date'] : '' }}" autocomplete="off" required>
                    </div>
                    <!-- General New / LC  -->
                    <div class="col-md-4 form-group mt-3">
                      <label class="label" for="">General</label>
                        <select class="form-select form-control" multiple aria-label="multiple select" name="general[]">
                          <option value="new_add" @if(isset($data['general']) && in_array("new_add",$data['general']) ) selected @endif>New Admission</option>
                          <option value="take_lc" @if(isset($data['general']) && in_array("take_lc",$data['general']) ) selected @endif>Take LC</option>
                        </select>
                    </div>
                      <!-- Strength Boy/Girl -->
                    <div class="col-md-4 form-group mt-3">
                      <label class="label" for="">Strength</label>
                        <select class="form-select form-control" multiple aria-label="multiple select" name="strength[]">
                          <option value="M" @if(isset($data['strength']) && in_array("M",$data['strength']) ) selected @endif>Boy</option>
                          <option value="F" @if(isset($data['strength']) && in_array("F",$data['strength']) ) selected @endif>Girl</option>
                        </select>
                    </div>
                      <!-- Religion Hindu / Muslim  -->
                    <div class="col-md-4 form-group mt-3">
                      <label class="label" for="">Religion</label>
                        <select class="form-select form-control" multiple aria-label="multiple select" name="religion[]">
                          {{$religions = DB::table('religion')->get(); }}
                          @foreach($religions as $key => $religion)
                          <option value="{{$religion->id}}" @if(isset($data['religion']) && in_array($religion->id,$data['religion']) ) selected @endif>{{$religion->religion_name}}</option>
                          @endforeach
                        </select>
                    </div>
                       <!-- caste Hindu / Muslim  -->
                    <div class="col-md-4 form-group mt-3">
                      <label class="label" for="">Cast</label>
                        <select class="form-select form-control" multiple aria-label="multiple select" name="cast[]">
                          {{$casts = DB::table('caste')->get(); }}
                          @foreach($casts as $key => $cast)
                          <option value="{{$cast->id}}" @if(isset($data['cast']) && in_array($cast->id,$data['cast']) ) selected @endif>{{$cast->caste_name}}</option>
                          @endforeach
                        </select>
                    </div>

                       <!-- Quota Hindu / Muslim  -->
                    <div class="col-md-4 form-group mt-3">
                      <label class="label" for="">Quota</label>
                        <select class="form-select form-control" multiple aria-label="multiple select" name="quota[]">
                          {{$quotas = DB::table('student_quota')->where('sub_institute_id',session()->get('sub_institute_id'))->get(); }}
                          @foreach($quotas as $key => $quota)
                          <option value="{{$quota->id}}" @if(isset($data['quota']) && in_array($quota->id,$data['quota']) ) selected @endif>{{$quota->title}}</option>
                          @endforeach
                        </select>
                    </div>
                  
                </div>  
                 <!-- search button  -->
                    <div class="col-md-4 form-group mt-3">
                        <center>
                        <input type="submit" name="submit" value="Search" class="btn btn-success" >       
                        </center>              
                    </div>            
            </form>
        </div>
      
    @php 
    if(isset($data['religion'])){
    $rel = DB::table('religion')->whereIn('id',$data['religion'])->get();
    }
    if(isset($data['quota'])){
    $quot = DB::table('student_quota')->whereIn('id',$data['quota'])->get();
    }
    if(isset($data['cast'])){
    $cas =  DB::table('caste')->whereIn('id',$data['cast'])->get();
    }
    @endphp

        @if(isset($data['result']))
        <div class="card">            
            <div class="table-responsive">
                @php
                    echo App\Helpers\get_school_details("","","");
                    echo '<br><center><span style=" font-size: 14px;font-weight: 600;font-family: Arial, Helvetica, sans-serif !important">From Date : '.date('d-m-Y',strtotime($data['from_date'])) .' - </span><span style=" font-size: 14px;font-weight: 600;font-family: Arial, Helvetica, sans-serif !important">To Date : '.date('d-m-Y',strtotime($data['to_date'])) .'</span></center><br>';
                @endphp
                <table id="example" class="table table-striped text-center">
                @if(count($data['result'])>0)
                    <thead>
                        <tr>
                            <th rowspan="3">Date</th>
                            <th rowspan="3">{{ App\Helpers\get_string('standard','request')}}</th>
                            <th rowspan="3">Total</th>
                            <!-- general -->
                            @if(isset($data['general']))
                                <th colspan="{{ count($data['general'])+2 }}"  class="text-center">General</th>
                            @endif

                            <!-- religion -->
                            @if(isset($data['religion']))
                                <th colspan="{{ count($data['religion'])+2 }}"  class="text-center">Religion</th>
                            @endif
                            <!-- strength -->
                            @if(isset($data['strength']))
                                <th colspan="{{ count($data['strength'])+2 }}" class="text-center">Strength</th>
                            @endif
                            <!-- cast -->
                            @if(isset($data['cast']))
                                <th colspan="{{ count($data['cast'])+2 }}" class="text-center">Cast</th>
                            @endif
                            <!-- quota -->
                            @if(isset($data['quota']))
                                <th colspan="{{ count($data['quota'])+2 }}" class="text-center">Quota</th>
                            @endif
                        </tr>
                        <tr>
                            <!-- general -->
                                @if(isset($data['general']))
                                    @if(in_array("new_add",$data['general']) ) <th> New Addmission </th> @endif
                                    @if(in_array("take_lc",$data['general']) ) <th> Take LC</th> @endif
                                    <th>Total</th>
                                @endif
                                <!-- religion -->
                                @if(isset($data['religion']))
                                    @foreach($data['religion'] as $key => $religionId)
                                        @if($religionId == $rel[$key]->id)
                                            <th colspan="2">{{ $rel[$key]->religion_name }}</th>
                                        @endif
                                    @endforeach
                                    <th rowspan="2">Total</th>
                                @endif
                                <!-- strength -->
                                @if(isset($data['strength']))
                                    @foreach($data['strength'] as $strength)
                                        <th colspan="2">{{ $strength }}</th>
                                    @endforeach
                                    <th rowspan="2">Total</th>
                                @endif
                                <!-- cast -->
                                @if(isset($data['cast']))
                                    @foreach($data['cast'] as $key => $castId)
                                        @if($castId == $cas[$key]->id)
                                            <th colspan="2">{{ $cas[$key]->caste_name }}</th>
                                        @endif
                                    @endforeach
                                    <th rowspan="2">Total</th>
                                @endif
                                <!-- quota -->
                                @if(isset($data['quota']))
                                    @foreach($data['quota'] as $key => $quotaId)
                                        @if($quotaId == $quot[$key]->id)
                                            <th colspan="2">{{ $quot[$key]->title }}</th>
                                        @endif
                                    @endforeach
                                    <th rowspan="2">Total</th>
                                @endif
                        </tr>
                        <tr>
                            @if(isset($data['religion']))
                                @foreach($data['religion'] as $key => $religionId)
                                    @if($religionId == $rel[$key]->id)
                                        <th>M</th>
                                        <th>F</th>
                                    @endif
                                @endforeach
                            @endif

                            @if(isset($data['cast']))
                                @foreach($data['cast'] as $key => $castId)
                                    @if($castId == $cas[$key]->id)
                                        <th>M</th>
                                        <th>F</th>
                                    @endif
                                @endforeach
                            @endif

                            @if(isset($data['quota']))
                                @foreach($data['quota'] as $key => $quotaId)
                                    @if($quotaId == $quot[$key]->id)
                                        <th>M</th>
                                        <th>F</th>
                                    @endif
                                @endforeach
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                    @php
                        $generalTotal = 0;
                        if(isset($data['religion'])){  $mreligionTotals =  array_fill(0, count($data['religion']), 0); }
                        if(isset($data['religion'])){  $freligionTotals =  array_fill(0, count($data['religion']), 0); }
                        if(isset($data['strength'])){  $strengthTotals = array_fill(0, count($data['strength']), 0); }
                        if(isset($data['cast'])){ $mcastTotals = array_fill(0, count($data['cast']), 0); }
                        if(isset($data['cast'])){ $fcastTotals = array_fill(0, count($data['cast']), 0); }
                        if(isset($data['quota'])){ $mquotaTotals = array_fill(0, count($data['quota']), 0); }
                        if(isset($data['quota'])){ $fquotaTotals = array_fill(0, count($data['quota']), 0); }
                        $totalStudents = 0;
                        $mainTotal=0;
                    @endphp

                    @foreach($data['result'] as $key => $value)
                        <tr>
                            <td>{{$data['from_date'] .'  /  '. $data['to_date']}}</td>
                            <td>{{$value->standard_name}}@if(in_array("division", $data['standard'])) / {{$value->division_name}} @endif</td>
                            <td>{{$value->total_students}}</td>
                            @php $mainTotal += $value->total_students; @endphp
                            <!-- general -->
                            @if(isset($data['general']))
                                @foreach ($data['general'] as $general)
                                    @php
                                        $generalTotal += $value->$general;
                                    @endphp
                                    <td>{{$value->$general}}</td>
                                @endforeach
                                <td>{{$value->total_students}}</td>
                            @endif

                            <!-- religion  -->
                            @if(isset($data['religion']))
                                @foreach($data['religion'] as $religionId)
                                    @php
                                        $mreligionTotals[$religionId-1] += $value->{'m_religion_'.$religionId};
                                        $freligionTotals[$religionId-1] += $value->{'f_religion_'.$religionId};
                                    @endphp
                                    <td>{{ $value->{'m_religion_'.$religionId} }}</td>
                                    <td>{{ $value->{'f_religion_'.$religionId} }}</td>
                                @endforeach
                                <td>{{$value->total_students}}</td>
                                
                            @endif

                            <!-- strength -->
                            @if(isset($data['strength']))
                                @foreach ($data['strength'] as $gender)
                                    @php
                                        $genderTotal = $value->$gender ?? 0;
                                        $genderIndex = ($gender == 'M') ? 0 : 1;
                                        $strengthTotals[$genderIndex] += $genderTotal;
                                    @endphp
                                    <td>{{$genderTotal}}</td>
                                @endforeach
                                <td>{{$value->total_students}}</td>
                            @endif


                            <!-- cast -->
                            @if(isset($data['cast']))
                                @foreach($data['cast'] as $kee=>$castId)
                                    @php
                                        $mcastTotals[$kee] += $value->{'m_cast_'.$castId};
                                        $fcastTotals[$kee] += $value->{'f_cast_'.$castId};
                                    @endphp
                                    <td>{{ $value->{'m_cast_'.$castId} }}</td>
                                    <td>{{ $value->{'f_cast_'.$castId} }}</td>
                                @endforeach
                                <td>{{$value->total_students}}</td>
                            @endif

                            <!-- quota -->
                            @if(isset($data['quota']))
                                @foreach($data['quota'] as $kee=> $quotaId)
                                    @php
                                        $mquotaTotals[$kee] += $value->{'m_quota_'.$quotaId};
                                        $fquotaTotals[$kee] += $value->{'f_quota_'.$quotaId};
                                    @endphp
                                    <td>{{ $value->{'m_quota_'.$quotaId} }}</td>
                                    <td>{{ $value->{'f_quota_'.$quotaId} }}</td>
                                @endforeach
                                <td>{{$value->total_students}}</td>
                            @endif
                            
                            @php
                                $totalStudents += $value->total_students;
                            @endphp
                        </tr>
                    @endforeach

                    <!-- Last row with totals -->
                    <tr>
                        <td colspan="2">Total</td>
                        <td>{{$mainTotal}}</td>
                        <!-- general totals -->
                        @if (isset($data['general']))
                            @php
                                $totalStudents = 0; // Initialize total students count
                            @endphp
                            @foreach ($data['general'] as $general)
                                @php
                                    $generalTotal = 0; // Reset total for each general
                                    foreach ($data['result'] as $value) {
                                        $generalTotal += $value->$general; // Calculate total for the current general
                                    }
                                @endphp
                                <td>{{ $generalTotal }}</td>
                            @endforeach
                            @foreach ($data['result'] as $value)
                                @php
                                    $totalStudents += $value->total_students; // Calculate total students count
                                @endphp
                            @endforeach
                            <td>{{ $totalStudents }}</td>
                        @endif

                        <!-- religion totals -->
                        @if(isset($data['religion']))
                            @foreach($data['religion'] as $key => $religionId)
                                @if($religionId == $rel[$key]->id)
                                    <td>{{$mreligionTotals[$key]}}</td>
                                    <td>{{$freligionTotals[$key]}}</td>
                                @endif
                            @endforeach
                            <td>{{$totalStudents}}</td>
                        @endif

                        <!-- strength totals -->
                        @if(isset($data['strength']))
                            @foreach($strengthTotals as $strengthTotal)
                                <td>{{$strengthTotal}}</td>
                            @endforeach
                            <td>{{$totalStudents}}</td>
                        @endif

                        <!-- cast totals -->
                        @if(isset($data['cast']))
                            @foreach($data['cast'] as $key => $castId)
                                @if($castId == $cas[$key]->id)
                                    <td>{{$mcastTotals[$key]}}</td>
                                    <td>{{$fcastTotals[$key]}}</td>
                                @endif
                            @endforeach
                            <td>{{$totalStudents}}</td>
                        @endif

                        <!-- quota totals -->
                        @if(isset($data['quota']))
                            @foreach($data['quota'] as $key => $quotaId)
                                @if($quotaId == $quot[$key]->id)
                                    <td>{{$mquotaTotals[$key]}}</td>
                                    <td>{{$fquotaTotals[$key]}}</td>
                                @endif
                            @endforeach
                            <td>{{$totalStudents}}</td>
                        @endif
                    </tr>
                </tbody>
                @else
                <tbody>
                    <tr>
                        <th class="text-center">No Data Found</th>
                    </tr>
                </tbody>
                    @endif
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
            {
                extend: 'print',
                text: ' PRINT',
                title: 'Inactive Student Report',
                customize: function (win) {
                    $(win.document.body).prepend(`{!! App\Helpers\get_school_details("$grade_id", "$standard_id", "$division_id") !!}`);
                }
            },
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

