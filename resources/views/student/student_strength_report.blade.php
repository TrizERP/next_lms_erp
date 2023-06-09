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
                <h4 class="page-title">Student Strength Report1</h4>
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
            
                if(isset($data['one_date']) && $data['one_date']=="add"){
                    $defaultAdd = "Checked";
                }else{
                    $defaultStart = "Checked";
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
					  <input class="form-check-input" type="radio" name="one_date" value="add" id="flexRadioDefault1"  @if(isset($defaultAdd) ) {{ $defaultAdd }} @endif>
					  <label class="form-check-label label" for="flexRadioDefault1"> Admission Date</label>
					</div>

					  <div class="form-check mt-3 ml-2">
					  <input class="form-check-input" type="radio" name="one_date" value="start" id="flexRadioDefault2" @if(isset($defaultStart)) {{ $defaultStart }} @endif>
					  <label class="form-check-label label" for="flexRadioDefault2">
					    Entry Date
					  </label>
					</div>
                </div>
					</div>
                    <!-- standard or division wise -->
					<div class="col-md-4 form-group mt-3">
					  <label  class="label"for="">Standard</label>
						<select class="form-select form-control" multiple aria-label="multiple select" name="standard_wise[]" required>
						  <option value="">Select One Option</option>
						  <option value="standard" @if(isset($data['standard']) && in_array("standard",$data['standard']) ) selected @endif>Standard Wise</option>
						  <option value="division" @if(isset($data['standard']) && in_array("division",$data['standard']) ) selected @endif>Division Wise</option>
						</select>
					</div>
                    <!-- Date  -->
                    <div class="col-md-4 form-group mt-3">
                        <label class="label">Date</label>
                        <input type="text" name="get_date" class="form-control mydatepicker" value="@if(isset($data['date'])){{$data['date']}} @endif" autocomplete="off" required>
                    </div>
                    <!-- General New / LC  -->
                    <div class="col-md-4 form-group mt-3">
                      <label class="label" for="">General</label>
                        <select class="form-select form-control" multiple aria-label="multiple select" name="general[]">
                          <option value="">Select One Option</option>
                          <option value="new_add" @if(isset($data['general']) && in_array("new_add",$data['general']) ) selected @endif>New Admission</option>
                          <option value="take_lc" @if(isset($data['general']) && in_array("take_lc",$data['general']) ) selected @endif>Take LC</option>
                        </select>
                    </div>
                      <!-- Strength Boy/Girl -->
                    <div class="col-md-4 form-group mt-3">
                      <label class="label" for="">Strength</label>
                        <select class="form-select form-control" multiple aria-label="multiple select" name="strength[]">
                          <option value="">Select One Option</option>
                          <option value="M" @if(isset($data['strength']) && in_array("M",$data['strength']) ) selected @endif>Boy</option>
                          <option value="F" @if(isset($data['strength']) && in_array("F",$data['strength']) ) selected @endif>Girl</option>
                        </select>
                    </div>
                      <!-- Religion Hindu / Muslim  -->
                    <div class="col-md-4 form-group mt-3">
                      <label class="label" for="">Religion</label>
                        <select class="form-select form-control" multiple aria-label="multiple select" name="religion[]">
                          <option value="">Select One Option</option>
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
                          <option value="">Select One Option</option>
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
                          <option value="">Select One Option</option>
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
                <table id="example" class="table table-striped text-center">
                    @if(count($data['result'])>0)
                    <thead>
                    <tr>
                        <th rowspan="2">SR No</th>
                        <th rowspan="2">Date</th>
                        <th rowspan="2">Standard</th>
                        <th rowspan="2">Total</th>
                         <!-- general -->
                        @if(isset($data['general']))
                            <th colspan="{{ count($data['general'])+1 }}"  class="text-center">General</th>
                        @endif

                        <!-- religion -->
                        @if(isset($data['religion']))
                            <th colspan="{{ count($data['religion'])+1 }}"  class="text-center">Religion</th>
                        @endif

                        <!-- strength -->
                        @if(isset($data['strength']))
                            <th colspan="{{ count($data['strength'])+1 }}" class="text-center">Strength</th>
                        @endif
                        <!-- cast -->
                        @if(isset($data['cast']))
                            <th colspan="{{ count($data['cast'])+1 }}" class="text-center">Cast</th>
                        @endif
                        <!-- quota -->
                        @if(isset($data['quota']))
                            <th colspan="{{ count($data['quota'])+1 }}" class="text-center">Quota</th>
                        @endif
                    </tr>
    
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
                                    <th>{{ $rel[$key]->religion_name }}</th>
                                @endif
                            @endforeach
                            <th>Total</th>
                        @endif

                    <!-- strength -->
                        @if(isset($data['strength']))
                            @foreach($data['strength'] as $strength)
                                <th>{{ $strength }}</th>
                            @endforeach
                            <th>Total</th>
                        @endif

                    <!-- cast -->
                        @if(isset($data['cast']))
                          @foreach($data['cast'] as $key => $castId)
                                @if($castId == $cas[$key]->id)
                                    <th>{{ $cas[$key]->caste_name }}</th>
                                @endif
                            @endforeach
                            <th>Total</th>
                        @endif

                    <!-- quota -->
                        @if(isset($data['quota']))
                            @foreach($data['quota'] as $key => $quotaId)
                                @if($quotaId == $quot[$key]->id)
                                    <th>{{ $quot[$key]->title }}</th>
                                @endif
                            @endforeach
                            <th>Total</th>
                        @endif
                </tr>
            </thead>
            <tbody>
            @php
                $generalTotal = 0;
                if(isset($data['religion'])){  $religionTotals =  array_fill(0, count($data['religion']), 0); }
                if(isset($data['strength'])){  $strengthTotals = array_fill(0, count($data['strength']), 0); }
                if (isset($data['cast'])){ $castTotals = array_fill(0, count($data['cast']), 0); }
                if(isset($data['quota'])){ $quotaTotals = array_fill(0, count($data['quota']), 0); }
                $totalStudents = 0;
                $mainTotal=0;
                $j =1;
            @endphp

    @foreach($data['result'] as $key => $value)
        <tr>
            <td>{{$j++}}</td>
            <td>{{$data['date']}}</td>
            <td>{{$value->standard_name}}@if(in_array("division", $data['standard'])) - {{$value->division_name}} @endif</td>
            <td>{{$value->total_students}}</td>
            @php $mainTotal += $value->total_students; @endphp
            <!-- general -->
            @if(isset($data['general']))
                @foreach ($data['general'] as $gender)
                    @php
                        $generalTotal += $value->$gender;
                    @endphp
                    <td>{{$value->$gender}}</td>
                @endforeach
                <td>{{$value->total_students}}</td>
            @endif

            <!-- religion  -->
            @if(isset($data['religion']))
                @foreach($data['religion'] as $religionId)
                    @php
                        $religionTotals[$religionId-1] += $value->{'religion_'.$religionId};
                    @endphp
                    <td>{{ $value->{'religion_'.$religionId} }}</td>
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
                        $castTotals[$kee] += $value->{'cast_'.$castId};
                    @endphp
                    <td>{{ $value->{'cast_'.$castId} }}</td>
                @endforeach
                <td>{{$value->total_students}}</td>
            @endif

            <!-- quota -->
          @if(isset($data['quota']))
            @foreach($data['quota'] as $kee=> $quotaId)
                @php
                    $quotaTotals[$kee] += $value->{'quota_'.$quotaId};
                @endphp
                <td>{{ $value->{'quota_'.$quotaId} }}</td>
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
        <td colspan="3">Total</td>
        <td>{{$mainTotal}}</td>
        <!-- general totals -->
        @if(isset($data['general']))
            @foreach ($data['general'] as $gender)
                <td>{{$gender === 'new_add' ? $generalTotal : ''}}</td>
            @endforeach
            <td>{{$totalStudents}}</td>

        @endif

        <!-- religion totals -->
        @if(isset($data['religion']))
            @foreach($religionTotals as $religionTotal)
                <td>{{$religionTotal}}</td>
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
            @foreach($castTotals as $castTotal)
                <td>{{$castTotal}}</td>
            @endforeach
            <td>{{$totalStudents}}</td>
        @endif

        <!-- quota totals -->
        @if(isset($data['quota']))
            @foreach($quotaTotals as $quotaTotal)
                <td>{{$quotaTotal}}</td>
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

