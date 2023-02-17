@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">            
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">                
                <h4 class="page-title">Book List</h4>            
            </div>                    
        </div>                  
                <div class="card">
                    <form action="{{ route('book_list.store') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("POST") }}
                        {{csrf_field()}}
                        <div class="row">
                        
                            {{ App\Helpers\SearchChain('4','single','grade,std') }}

                            <div class="col-md-4 form-group">
                                <label for="subject">Select Subject:</label>
                                <select name="subject" id="subject" class="form-control mb-0" required>
                                    <option value="">Select Subject</option>       
                                </select>
                            </div> 
                            <div class="col-md-4 form-group">
                                <label for="chapter">Select Chapter</label>
                                <select id="chapter" name="chapter"  class="cust-select form-control">
                                    <option value="">Select Chapter</option>
                                </select>
                            </div> 
                            <div class="col-md-4 form-group">
                                <label for="topic">Select Topic</label>
                                <select id="topic" name="topic" class="cust-select form-control">
                                    <option value="">Select Topic</option>
                                </select>
                            </div>      
                            <div class="col-md-4 form-group">
                                <label>Date</label>
                                <input type="text" name="date_" class="form-control mydatepicker" autocomplete="off">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Title</label>
                                <select id="title" name="title" class="form-control" required>
                                    <option value="">Select Title</option>
                                    <option value="NCERT/CBSE Textbook">NCERT/CBSE Textbook</option>
                                    <option value="Other Book">Other Book</option>
                                    <option value="Practice Worksheet">Practice Worksheet</option>
                                </select>
                            </div>
                        
                            <div class="col-md-4 form-group">
                                <label>Message</label>
                                <textarea name="message" class="form-control"></textarea>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>File</label>
                                <input type="file" name="attechment" class="form-control">
                            </div>
                            <div class="col-md-4 ml-0 form-group">
                                <label>Link</label>
                                <input type="text" name="link" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Submit" class="btn btn-success" >
                                </center>
                            </div>

                        </div>
                        
                    </form>
                
                    <div class="col-lg-12 col-sm-12 col-xs-12">
                        <div class="table-responsive">
                            <table id="example" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Sr No</th>
                                        <!-- <th>Syear</th> -->
                                        <th>Standard</th>
                                        <th>Subject</th>
                                        <th>Chapter</th>
                                        <th>Topic</th>
                                        <th>Title</th>
                                        <th>Message</th>
                                        <th>File Name</th>
                                        <th>Link</th>
                                        <th>Date</th>
                                        
                                        
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                    $j=1;
                                    @endphp
                                    @if(isset($data['data']))
                                    @foreach($data['data'] as $key => $data)
                                    <tr>    
                                        <td>{{$j}}</td>
                                        <!-- <td>{{$data->syear}}</td>   -->

                                        <td>{{$data->std_name}}</td> 
                                        <td>{{$data->subject_name}}</td> 
                                        <td>{{$data->chapter_name}}</td> 
                                        <td>{{$data->topic_name}}</td>  
                                        <td>{{$data->title}}</td>  
                                        <td>{{$data->message}}</td>  
                                        <td><a target="_blank" href="{{$data->file_name_path}}">{{$data->file_name}}</a></td>  
                                        <td><a target="_blank" href="{{$data->link}}">{{$data->link}}<a/></td>  
                                        <td>{{$data->date_}}</td>  
                                       
                                        <td>
                                            <form action="{{ route('book_list.destroy', $data->id)}}" method="post">
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
<script>
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
    $("#subject").change(function(){
        var std_id = $("#standard").val();
        var sub_id = $("#subject").val();
        var path = "{{ route('ajax_LMS_SubjectwiseChapterForBooklist') }}";
        $('#chapter').find('option').remove().end().append('<option value="">Select Chapter</option>').val('');
        $.ajax({url: path,data:'sub_id='+sub_id+'&std_id='+std_id, success: function(result){
            for(var i=0;i < result.length;i++){
                $("#chapter").append($("<option></option>").val(result[i]['id']).html(result[i]['chapter_name']));
            }
        }
        });
        $("#title").val($("#subject option:selected").text());
    })
    $("#chapter").change(function(){
        var chapter_id = $("#chapter").val();
        var path = "{{ route('ajax_LMS_ChapterwiseTopic') }}";
        $('#topic').find('option').remove().end().append('<option value="">Select Topic</option>').val('');
        $.ajax({url: path,data:'chapter_id='+chapter_id, success: function(result){
            for(var i=0;i < result.length;i++){
                $("#topic").append($("<option></option>").val(result[i]['id']).html(result[i]['name']));
            }
        }
        });
        title_val = $("#title").val() + ' / ' + $("#chapter option:selected").text();
        $("#title").val(title_val);
    })  

    $("#topic").change(function(){
        title_val = $("#title").val() + ' / ' + $("#topic option:selected").text();
        $("#title").val(title_val);
    })  

</script>


@include('includes.footer')
