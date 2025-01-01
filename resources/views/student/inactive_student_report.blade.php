@extends('layout')
@section('container')
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
                                 @php 
                                    $i=0;
                                 @endphp
                                    @foreach($data['data'] as $header => $headerValue)

                                    @if(isset($data['data'][$header]) && !empty($data['data'][$header]))
                                          <div class="col-md-12 form-group py-8">
                                             <div class="row mb-4"  style="border-bottom:1px solid black">
                                                <div class="col-md-4"><h4><b>{{$header}}</b></h5></div>
                                                <div class="col-md-2">Check All <input type="checkbox" onclick="checkAll('chkClass{{$i}}')" class="chkClass{{$i}}"></div>
                                             </div>
                                             <div class="row">
                                             @foreach($data['data'][$header] as $key => $value)
                                             @php $val = $value->field_name.'/'.$value->id;
                                                $joinVal = str_replace(' ','',$value->field_label);
                                                $lowerCase = strtolower($joinVal);
                                                // norm clature
                                                $checkNorm = DB::table('app_language')->where('sub_institute_id',session()->get('sub_institute_id'))->where('string',$joinVal)->first(); 

                                                   if(!empty($checkNorm)){
                                                      $value->field_label = $checkNorm->value;
                                                   }
                                             @endphp
                                             <div class="col-md-2 pb-2"><input type="checkbox" name="dynamicFields[]" class="chkClass{{$i}}" value="{{$val}}" @if(isset($data['dynamicFields']) && in_array($val,$data['dynamicFields'])) checked @endif> {{$value->field_label}}</div>
                                             @endforeach
                                             </div>
                                          </div>
                                    @endif
                                    @php 
                                          $i++;
                                    @endphp
                                    @endforeach
                              </div>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
         </div>
         </form>
      </div>
      @if(isset($data['student_data']))
      @php
      if(isset($data['student_data'])){
      $student_data = $data['student_data'];
      }
      $j =1;
      @endphp
      <div class="card">
         <div class="table-responsive mt-20 tz-report-table">
            {!! App\Helpers\get_school_details("$grade_id","$standard_id","$division_id") !!}
            <table id="example" class="table table-striped">
               <thead>
                  <tr>
                     @foreach($data['headers'] as $hkey => $header)
                     @php 
                        $joinVal = str_replace(' ','',$header);
                        $lowerCase = strtolower($joinVal);
                        // norm clature
                        $checkNorm = DB::table('app_language')->where('sub_institute_id',session()->get('sub_institute_id'))->where('string',$joinVal)->first(); 

                           if(!empty($checkNorm)){
                              $header = $checkNorm->value;
                           }
                     @endphp
                     <th class="text-left"> {{$header}} </th>
                     @endforeach
                  </tr>
               </thead>
               <tbody>
                  @foreach($student_data as $key => $value)
                  <tr>
                     @foreach($data['headers'] as $hkey => $header)
                     @if($hkey == 'image')
                     <td><img height="60" width="60" src="../storage/student/{{$value->$hkey}}"/>
                     </td>
                     @elseif($hkey == 'admission_date' || $hkey == 'dob' || $hkey == 'date' || $hkey == 'birthdate' || $hkey == 'created_on' || $hkey == 'birthday' || $hkey == 'created_at')
                     <td> {{ (isset($value->$hkey)) ? date('d-m-Y', strtotime($value->$hkey)) : '-'}} </td>
                     @elseif($hkey == 'dise_uid')
                     <td>{{$value->$hkey}}</td>
                     @elseif($hkey=='student_name')
                     <td>{{App\Helpers\sortStudentName($value->$hkey)}}</td>
                     @else
                     <td> {{$value->$hkey ?? '-'}} </td>
                     @endif
                     @endforeach
                  </tr>
                  @endforeach
               </tbody>
            </table>
         </div>
      </div>
   </div>
   @endif
   </div>
</div>
@include('includes.footerJs')
<script>
 function checkAll(chkName){
    $('.'+chkName).each(function() {
            $(this).prop('checked', !$(this).prop('checked'));
        });
   }
</script>
<script>
   $(document).ready(function () {
       var table = $('#example').DataTable({
           ordering: false,
           select: true,
           lengthMenu: [
               [100, 500, 1000, -1],
               ['100', '500', '1000', 'Show All']
           ],
           dom: 'Bfrtip',
           buttons: [
               {
                   extend: 'pdfHtml5',
                   title: 'Student Report',
                   orientation: 'landscape',
                   pageSize: 'LEGAL',
                   pageSize: 'A0',
                   exportOptions: {
                       columns: ':visible'
                   },
               },
               {extend: 'csv', text: ' CSV', title: 'Student Report'},
               {extend: 'excel', text: ' EXCEL', title: 'Student Report'},
               {
                   extend: 'print',
                   text: ' PRINT',
                   title: 'Student Report',
                   customize: function (win) {
                       $(win.document.body).prepend(`{!! App\Helpers\get_school_details("$grade_id", "$standard_id", "$division_id") !!}`);
                       $(win.document.body).append(`<div style="text-align: right;margin-top:20px">Printed on: {{date('d-m-Y H:i:s')}}</div>`);
                   }
               },
               'pageLength'
           ],
       });
       //table.buttons().container().appendTo('#example_wrapper .col-md-6:eq(0)');
   
       $('#example thead tr').clone(true).appendTo('#example thead');
       $('#example thead tr:eq(1) th').each(function (i) {
           var title = $(this).text();
           $(this).html('<input type="text" placeholder="Search ' + title + '" />');
   
           $('input', this).on('keyup change', function () {
               if (table.column(i).search() !== this.value) {
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