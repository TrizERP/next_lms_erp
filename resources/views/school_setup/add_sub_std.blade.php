@include('includes.headcss')
<link href="../../../plugins/bower_components/bootstrap-select/bootstrap-select.min.css" rel="stylesheet" />
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">                
                @if(!isset($data['mapped_data']))
                Add Standard Subject Mapping
                @else
                Edit Standard Subject Mapping
                @endif
                </h4>
            </div>            
        </div>        
        <div class="card">
            <div class="panel-body">
                @if ($message = Session::get('success'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                </div>
                @endif
                <div class="col-lg-12 col-sm-12 col-xs-12">                    
                    <form action="@if (isset($data['mapped_data']))
                          {{ route('sub_std_map.update', $data['mapped_data']['id']) }}
                          @else
                          {{ route('sub_std_map.store') }}
                          @endif" enctype="multipart/form-data" method="post">
                          @if(!isset($data['mapped_data']))
                        {{ method_field("POST") }}
                        @else
                        {{ method_field("PUT") }}
                        @endif                                                                        
                        @csrf
                        <div class="row">
                        <div class="col-md-4 form-group">
                            <label>Standard</label>                            
                            <select class="form-control standard_class" @if(!isset($data['mapped_data']['standard_id'])) multiple @endif  data-style="form-control" name="standard_id[]" id="standard_id[]" required>
                                <option value="">Select</option>
                                @foreach($data['std_data'] as $key =>$val)
                                @php 
                                    $selected = '';
                                    if( isset($data['mapped_data']['standard_id']) && $data['mapped_data']['standard_id'] == $val->id )
                                    {
                                        $selected = 'selected';
                                    }
                                @endphp
                                    <option {{$selected}} value="{{$val->id}}">{{$val->name}}</option>
                                @endforeach                                
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Subject</label>
                            <select class="form-control" data-style="form-control" id="subject_id" name="subject_id" required>                               
                                <option value="">Select</option>
                                @foreach($data['sub_data'] as $key =>$val)
                                @php 
                                    $selected = '';
                                    if( isset($data['mapped_data']['subject_id']) && $data['mapped_data']['subject_id'] == $val->id )
                                    {
                                        $selected = 'selected';
                                    }
                                @endphp
                                    <option {{$selected}} value="{{$val->id}}">{{$val->subject_name}}</option>
                                @endforeach 
                            </select>
                        </div>                        
                        <div class="col-md-4 form-group">
                            <label>Display Name</label>
                            <input type="text" id='display_name' name="display_name" value="@if(isset($data['mapped_data']['display_name'])){{$data['mapped_data']['display_name']}}@endif" required class="form-control">
                        </div>
                        <div class="col-md-2 form-group">
                            <div class="checkbox checkbox-info checkbox-circle">
                                <input @if(isset($data['mapped_data']['allow_grades']) && $data['mapped_data']['allow_grades'] == "Yes"){{'checked'}}@endif type="checkbox" id="allow_grades" name="allow_grades" value="Yes">
                                <label for="allow_grades">Allow Grades</label>
                            </div> 
                        </div>
                        <div class="col-md-2 form-group">
                            <div class="checkbox checkbox-info checkbox-circle">                                                                                                     
                                <input type="checkbox" id="elective_subject" name="elective_subject" value="Yes"  @if(isset($data['mapped_data']['elective_subject']) && $data['mapped_data']['elective_subject'] == "Yes"){{'checked'}}@endif @if(session()->get('sub_institute_id')==254) onclick="openOptionType();" @endif>
                                <label for="elective_subject">Optional Subject</label> 
                            </div>
                        </div>  
                        <div class="col-md-2 form-group optionalType" id="optionalType">
                            <label for="optional type">Select Optional Type</label>
                            <select name="optional_type" id="optional_type" class="form-control">
                                <option value="">Select Optional Type</option>
                                @foreach($data['optional_type'] as $k => $v)
                                <option value="{{$v}}" @if(!empty($data['subject_optional_mapped']) && $data['subject_optional_mapped']->optional_type == $v) selected @endif>{{$v}}</option>
                                @endforeach
                            </select>
                        </div>                        
                        <div class="col-md-2 form-group">
                            <div class="checkbox checkbox-info checkbox-circle">
                                <input @if(isset($data['mapped_data']['allow_content']) && $data['mapped_data']['allow_content'] == "Yes"){{'checked'}}@endif type="checkbox" id="allow_content" name="allow_content" value="Yes">
                                <label for="allow_content">Allow Content</label>
                            </div> 
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Subject Category</label>                            
                            <select class="form-control" data-style="form-control" name="subject_category" id="subject_category">
                                @foreach($data['content_category'] as $key =>$val)
                                @php 
                                    $selected = '';
                                    if( isset($data['mapped_data']['subject_category']) && $data['mapped_data']['subject_category'] == $val['category_name'] )
                                    {
                                        $selected = 'selected';
                                    }
                                @endphp
                                    <option {{$selected}} value="{{$val['category_name']}}">{{$val['category_name']}}</option>
                                @endforeach                                
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Sort Order</label>
                            <input type="number" id='sort_order' name="sort_order" value="@if(isset($data['mapped_data']['sort_order'])){{$data['mapped_data']['sort_order']}}@endif" class="form-control">
                        </div>
                        <!-- load  -->
                        <div class="col-md-3 form-group">
                            <label>Subject Load</label>
                            <input type="number" id='load' name="load" value="@if(isset($data['mapped_data']['load'])){{$data['mapped_data']['load']}}@endif" class="form-control">
                        </div>

                        <div class="col-md-3 form-group">
                            <label>Display Image</label>
                            <input type="file" name="display_image" id="display_image" class="form-control">
                            @if(isset($data['mapped_data']['display_image']))
                             <img src="../../../storage{{$data['mapped_data']['display_image']}}" height="50px" width="50px">
                            @endif
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Add Content</label>
                            <select class="form-control" data-style="form-control" id="add_content" name="add_content" readonly="readonly">                               
                                <option value="chapterwise" @php
                                    if (isset($data['mapped_data']['add_content']) && $data['mapped_data']['add_content'] == 'chapterwise' ) {
                                        echo 'selected="selected"';
                                    }
                                @endphp >Chapterwise</option>
                                <option value="topicwise" @php
                                if (isset($data['mapped_data']['add_content']) && $data['mapped_data']['add_content'] == 'topicwise' ) {
                                    echo 'selected="selected"';
                                }
                            @endphp>Topicwise</option>
                            </select>
                        </div>
                        <div class="col-md-12 form-group">
                            <center>
                                <input type="submit" name="submit" value="Save" class="btn btn-success" >
                            </center>
                        </div>
                        </div>
                    </form>

                </div>
            </div>
        </div>        
    </div>
</div>

@include('includes.footerJs')
<script src="../../../plugins/bower_components/bootstrap-select/bootstrap-select.min.js" type="text/javascript"></script>
<script type="text/javascript">
    
    if( $('input[name="_method"]').val() == "PUT" )
    {
        $('option', ".standard_class").not(':eq(0), :selected').remove(); 
        $(".standard_class").find('option[value=""]').remove();
        $(".standard_class").attr("readonly",true); 
    
        $('option', "#subject_id").not(':eq(0), :selected').remove(); 
        $("#subject_id").find('option[value=""]').remove();
        $("#subject_id").attr("readonly",true); 
    }   
    $(document).ready(function(){
        $('#optionalType').hide();
        $('#optional_type').prop('required',false);
        @if(isset($data['mapped_data']['elective_subject']) && $data['mapped_data']['elective_subject'] == "Yes" && session()->get('sub_institute_id')==254)
            $('#optionalType').show();
            // $('#optional_type').prop('required',true);
        @endif
    })
    function openOptionType(){
       $('#optionalType').hide();

       if ($('#elective_subject').is(':checked')) {
            $('#optionalType').show();
            // $('#optional_type').prop('required',true); // commented on 15-03-2025
        } else {
            $('#optionalType').hide();
            // $('#optional_type').prop('required',false); // commented on 15-03-2025
        }
    }

</script>
@include('includes.footer')
