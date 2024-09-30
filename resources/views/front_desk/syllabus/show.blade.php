@extends('layout')
@section('container')
<style>
   .control-bar a:hover, .control-bar input:hover, [contenteditable]:focus, [contenteditable]:hover{
   background : #fff !important;
   }
</style>
<div id="page-wrapper">
<div class="container-fluid">
   <div class="row bg-title">
      <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
         <h4 class="page-title">Syllabus</h4>
      </div>
   </div>
   <!-- form card  -->
   <div class="card">
      <form action="{{ route('syllabus.store') }}" enctype="multipart/form-data" method="post">
         {{ method_field("POST") }}
         {{csrf_field()}}
         <div class="row">
            {{ App\Helpers\SearchChain('4','single','grade,std') }}
            <!-- App\Helpers\SearchChain('4','multiple','grade,std,div') -->
            <div class="col-md-4 form-group">
               <label for="subject">Select Subject:</label>
               <select name="subject" id="subject" class="form-control mb-0" required>
                  <option value="">Select Subject</option>
               </select>
            </div>
            <!-- <div class="col-md-4 form-group">
               <label>from Date</label>
               <input type="text" name="from_date" class="form-control mydatepicker from_date" autocomplete="off">
               </div>
               
               <div class="col-md-4 form-group">
               <label>To Date</label>
               <input type="text" name="to_date" class="form-control mydatepicker to_date" autocomplete="off">
               </div> -->
            <div class="col-md-2">
              <div class="form-group">
               <label>Syllabus Type</label>
               <select name="types" id="types" class="form-control" onchange="getMonthArr(this);">
                  <option value="Yearly">Yearly</option>
                  <option value="Monthly">Monthly</option>
                  <option value="Daily">Daily</option>
               </select>
              </div>
            </div>
            <div class="col-md-2" id="monthLists">
              <div class="form-group">
               <label>Select Month</label>
               @php $monthArr = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul','Aug', 'Sep','Oct', 'Nov', 'Dec']; @endphp
               <select name="month" id="month" class="form-control">
                  @foreach($monthArr as $k=>$v)
                  <option value="{{$v}}">{{$v}}</option>
                  @endforeach
               </select>
              </div>
            </div>
            <div class="col-md-2">
                <div id="no_of_days_div">
                    <label>No. of Days</label>
                    <input type="number" name="no_of_days" class="form-control" id="no_of_days">
                </div>
                <div class="date">
                <label>Date</label>
                <input type="text" name="date" class="form-control mydatepicker selDate" autocomplete="off">
                </div>
            </div>
            <div class="col-md-2" >
                <div id="no_of_periods_div">
                    <label>No. of Periods</label>
                    <input type="number" name="no_of_periods" class="form-control" id="no_of_days">
                </div>
            </div>
            <div class="col-md-4">
                <div id="assement_tool_div">
                    <label for="">Assement Tool</label>
                    <textarea name="assement_tool"  class="form-control" id="assement_tool"></textarea>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-4 form-group">
               <label>Title</label>
               <input type="text" name="title" class="form-control" id="title" placeholder="Example 'std - 6 syllabus'">
            </div>
            <div class="col-md-4 form-group">
               <label>Message</label>
               <textarea name="message" class="form-control" id="message" style="height:100px" placeholder="Example : chater no : 2,3,4 and 5. And want Lesson Objective, Activity, Materials lists etc"></textarea>
               <a class="btn btn-primary mt-2" onclick="getAIOutput();">AI Search</a>
            </div>
            <div class="col-md-4 form-group">
               <label>File</label>
               <input type="file" name="attachment" id="attachment" class="form-control" required>
            </div>
            <div class="col-md-12 form-group" id="AI-output">
               <textarea name="aiOutput" id="aiOutput" contenteditable="true">
               </textarea>
            </div>
            <div class="col-md-12 form-group">
               <center>
                  <input type="submit" name="submit" value="Save" class="btn btn-success" >
               </center>
            </div>
        </div>
      </form>
      <!-- form card end  -->
      <div class="card">
         <div class="col-lg-12 col-sm-12 col-xs-12">
            <div class="table-responsive">
               <table id="example" class="table table-striped">
                  <thead>
                     <tr>
                        <th>Sr No</th>
                        <th>Title</th>
                        <th>Message</th>
                        <th>Assessment Tools </th>
                        <th>Type</th>
                        <th>Month</th>
                        <th>Date</th>
                        <th>Standard</th>
                        <th>Subject</th>
                        <th>File</th>
                        <th>Created By</th>
                        <th class="text-left">Action</th>
                     </tr>
                  </thead>
                  <tbody>
                     @php
                     $j=1;
                     @endphp
                     @if(isset($data['data']))
                     @foreach($data['data'] as $key=>$data)
                     <tr>
                        <td>{{$j}}</td>
                        <td>{{$data->title}}</td>
                        <td>{{$data->message}}</td>
                        <td>{{$data->assesment_tool}}</td>
                        <td>{{$data->types}}</td>
                        <td>{{$data->months}}</td>
                        <td>{{ \carbon\carbon::parse($data->date_)->format('d-m-Y')}}</td>
                        <td>{{$data->std_name}}</td>
                        <td>{{$data->display_name}}</td>
                        <td>@if(isset($data->file_name))
                           <a href="{{ Storage::disk('digitalocean')->url('public/syllabus/'.$data->file_name)}}" target="_blank">{{$data->file_name}}</a>
                           @else 
                           -
                           @endif
                        </td>
                        <td>{{$data->createdBy}}</td>
                        <td>
                           <form action="{{ route('syllabus.destroy', $data->id)}}" method="post">
                              @csrf
                              @method('DELETE')
                              <button type="submit" onclick="return confirmDelete();" class="btn btn-info btn-outline-danger"><i class="ti-trash"></i></button>
                           </form>
                        </td>
                     </tr>
                     @php
                     $j++;
                     @endphp
                     @endforeach
                     @endif
                  </tbody>
               </table>
            </div>
         </div>
      </div>
   </div>
</div>
@include('includes.footerJs')
<script src="{{ asset("/ckeditor_wiris/ckeditor4/ckeditor.js") }}"></script>
<script>
   $(document).ready(function () {
        $('#AI-output').hide();
        $('#monthLists').hide();
        $('#no_of_days_div').hide();
        $('#no_of_periods_div').hide();
        $('#assement_tool_div').hide();
        $('.date').hide();
   
       var table = $('#example').DataTable({
           select: true,
           lengthMenu: [
               [100, 500, 1000, -1],
               ['100', '500', '1000', 'Show All']
           ],
           dom: 'Bfrtip',
           buttons: [
               {
                   extend: 'pdfHtml5',
                   title: 'Syllabus Report',
                   orientation: 'landscape',
                   pageSize: 'LEGAL',
                   pageSize: 'A0',
                   exportOptions: {
                       columns: ':visible'
                   },
               },
               {extend: 'csv', text: ' CSV', title: 'Syllabus Report'},
               {extend: 'excel', text: ' EXCEL', title: 'Syllabus Report'},
               {extend: 'print', text: ' PRINT', title: 'Syllabus Report'},
               'pageLength'
           ],
       });
   
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
   //$("#division").parent('.form-group').hide();
   $("#standard").change(function(){
       var std_id = $("#standard").val();
       var path = "{{ route('ajax_LMS_StandardwiseSubject') }}";
       $('#subject').find('option').remove().end().append('<option value="">Select Subject</option>').val('');
       $.ajax({url: path,data:'std_id='+std_id, success: function(result){
           for(var i=0;i < result.length;i++){
               $("#subject").append($("<option></option>").val(result[i]['subject_id']).html(result[i]['display_name']));
           }
       }
       });
   })
   
   function getAIOutput(){
       var standard = $('#standard  option:selected').text();
       var subject = $('#subject  option:selected').text();
       var std_id = $('#standard').val();
       var sub_id = $('#subject').val();
       var date = $('.selDate').val();
       var types  = $('#types').val();
       var title  = $('#title').val();
       var message  = $('#message').val();
       var no_of_days = $('#no_of_days').val();
       var no_of_periods = $('#no_of_periods').val();
       var assement_tool = $('#assement_tool').val();
       var month ='';
       $('#AI-output').hide();
       $('#attachment').prop("required",true);
       var editor = CKEDITOR.instances['aiOutput'];
       editor.setData('');
   
       if(types==="Monthly"){
           month  = $('#month').val();
       }
       if(date=='' && types==="Daily"){
           alert('Date Field required !!');
       }
       else if(standard=="Select" || subject=="Select Subject" || title=='' || message==''){
           alert('All Fields Reuired for Browse !!');
       }else{
           // console.log([standard,subject,fromdate,todate,types,month,title,message]);
           // create prompt
          $.ajax({
           url : "{{route('generateSyllabus')}}",
           data : {standard_id:std_id,standard:standard,subject_id:sub_id,subject:subject,date:date,types:types,month:month,title:title,message:message,no_of_days:no_of_days,no_of_periods:no_of_periods,assement_tool:assement_tool},
           type : 'GET',
           success : function(response){
               console.log('output');
               console.log(response);
               if(response.AI_response!==''){
                   $('#AI-output').show();
                   $('#attachment').prop("required",false);
                //    $('#attachment').hide();
                   // display value in textarea
                   var formattedResponse = response.AI_response.replace(/\n/g, '<br>');
                   var editor = CKEDITOR.instances['aiOutput'];
                   editor.setData(formattedResponse);
               }else{
                   alert('Failed to Generate Syllabus');
               }
           },
           error: function(xhr, status, error) {
               alert(error);
           }
          })
       }
   }
   function getMonthArr(selectedVal){
       var type = $(selectedVal).val();

        $('#monthLists').hide();
        $('#no_of_days_div').hide();
        $('#no_of_periods_div').hide();
        $('#assement_tool_div').hide();
        $('.date').hide();

       if(type==="Monthly"){
           $('#monthLists').show();
           $('#no_of_days_div').show();
           $('#no_of_periods_div').show();
           $('#assement_tool_div').show();
       }
       else if(type==="Daily"){
           $('.date').show();
       }
   }
</script>
<script>
   CKEDITOR.config.toolbar_Full =
   [
       {name: 'document', items: ['Source']},
       {name: 'clipboard', items: ['Cut', 'Copy', 'Paste', '-', 'Undo', 'Redo']},
       {name: 'editing', items: ['Find']},
       {name: 'basicstyles', items: ['Bold', 'Italic', 'Underline']},
       {name: 'paragraph', items: ['JustifyLeft', 'JustifyCenter', 'JustifyRight']}
   ];
   CKEDITOR.config.height = '40px';
   
   CKEDITOR.plugins.addExternal('divarea', '../examples/extraplugins/divarea/', 'plugin.js');
   CKEDITOR.plugins.addExternal('sharedspace', '../examples/extraplugins/sharedspace/', 'plugin.js');
   CKEDITOR.plugins.addExternal('filebrowser', '../examples/extraplugins/filebrowser/', 'plugin.js');
   CKEDITOR.plugins.addExternal('enterkey', '../examples/extraplugins/enterkey/', 'plugin.js');
   CKEDITOR.plugins.addExternal('FMathEditor', '../examples/extraplugins/FMathEditor/', 'plugin.js');
   CKEDITOR.config.removePlugins = 'maximize';
   CKEDITOR.config.removePlugins = 'resize';
   CKEDITOR.config.sharedSpaces = {top: 'toolbar1'};
   CKEDITOR.replace('aiOutput', {
   extraPlugins: 'filebrowser,divarea,sharedspace,FMathEditor,enterkey',
   enterMode: '2',
   language: 'en',
   filebrowserUploadUrl: "{{route('uploadimage',['_token'=>csrf_token() ])}}",
   filebrowserUploadMethod: 'form'
   });
   var editor = CKEDITOR.instances['aiOutput'];
   
   editor.on('blur', function () {
   // Call the check_input function when the CKEditor loses focus
   check_input(editor.getData());
   });
   
   function check_input(inputElement) {
   
   var inputValue = inputElement.value;
   var editor = CKEDITOR.instances['aiOutput'];
   console.log(editor.getData())
   }
   
</script>
@include('includes.footer')
@endsection