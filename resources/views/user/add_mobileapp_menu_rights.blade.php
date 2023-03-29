@include('includes.headcss')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/image-picker/0.3.1/image-picker.min.css">
@include('includes.header')
@include('includes.sideNavigation')
<style type="text/css">
    
ul.thumbnails.image_picker_selector li .thumbnail.selected {
  background: #cdcdcd !important;
}
.image_picker_image {
  width: 76px !important;
  height: 50px !important;
}
ul.thumbnails.image_picker_selector li .thumbnail{
    border: 1px solid #a5a5a5 !important;
}
tbody tr td {
  font-family: 'Poppins', sans-serif;
  color: #060606;
}
.modal-dialog {
  max-width: 100% !important;
}
</style>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Mobile App Menu Rights</h4>
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
                <form action="{{ route('add_mobileapp_menu_rights.create') }}">
                    {{ method_field("POST") }}
                    @csrf
                    <div class="row">
                        @if(isset($data['profiles']))                                
                            <div class="col-md-3 form-group ml-0 mr-0">
                                <label>User Profile</label>
                                <select name="profile" id="profile" required="required" class="form-control">
                                    <option value=""> Select User Profile </option>
                                    @foreach($data['profiles'] as $key => $value)
                                        @php
                                        $checked = '';
                                        if(isset($data['profile'])){
                                            if($data['profile'] == $key){
                                                $checked = "selected='selected'";
                                            }
                                        }
                                        @endphp
                                        <option value="{{$key}}" {{$checked}}>{{$value}}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div class="col-md-2 form-group ml-0 mr-0 mt-4">
                            <div class="d-inline">                                                        
                                <input type="checkbox" name="inactive" value="No" @if(isset($data['inactive'])) @if($data['inactive'] == 'No') checked @endif @endif>
                                <span>Include In-active Menu</span>
                            </div>
                        </div>
                        
                        <div class="col-md-2 form-group ml-0 mr-0 mt-4">
                            <input type="submit" name="submit" value="Search" class="btn btn-success">  
                        </div>
                          
                    </div>
                </form>
            </div>
        </div>
        @if(isset($data['mobileapp_menu_data']))
            @php
            if(isset($data['mobileapp_menu_data'])){
                $mobileapp_menu_data = $data['mobileapp_menu_data'];
            }

            @endphp
            <div class="card">         
                <div class="row">
                    <div class="col-lg-12 col-sm-12 col-xs-12 p-0">
                        <div class="table-responsive">
                            <table id="example" class="table">
                                <thead>
                                    <tr>
                                        <th>Sr.No.</th>
                                        <th>Main Title</th>
                                        <th>Main Title Color Code</th>
                                        <th>Main Title Background Image</th>
                                        <th>Main Sort Order</th>
                                        <th>Sub Title of Main</th>
                                        <th>Sub Title Icon</th>
                                        <th>Sub Title Sort Order</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                    $counted_rowspan_arr = array();
                                    $i = $j = 1;
                                    @endphp
                                    @if(isset($data['mobileapp_menu_data']))
                                    @foreach($mobileapp_menu_data as $key => $value)
                                    @php 
                                        if(!in_array($value['main_title'], $counted_rowspan_arr))
                                        {   
                                            $counted_rowspan_arr[] = $value['main_title'];

                                            $extra = " AND mh.status = 'Yes' ";
                                            if(isset($data['inactive']) && $data['inactive'] == 'No'){
                                                $extra = " ";
                                            }

                                            if(isset($data['profile']) && ($data['profile'] == 'Admin' || $data['profile'] == 'Teacher'))
                                            {
                                               
                                                $get_count = DB::select("SELECT count(main_title) as rowspan_tot,mh.main_title
                                                                    FROM teacher_mobile_homescreen mh
                                                                    WHERE mh.sub_institute_id = '".$value['sub_institute_id']."'  
                                                                    AND mh.main_title = '".$value['main_title']."' AND mh.user_profile_name = '".$data['profile']."' $extra
                                                                    GROUP BY mh.main_title
                                                                    ORDER BY mh.main_sort_order,mh.sub_title_sort_order "); 
                                            }

                                            if(isset($data['profile']) && $data['profile'] == 'Student')
                                            {
                                                $get_count = DB::select("SELECT count(main_title) as rowspan_tot,mh.main_title
                                                                    FROM mobile_homescreen mh
                                                                    WHERE mh.sub_institute_id = '".$value['sub_institute_id']."'  
                                                                    AND mh.main_title = '".$value['main_title']."' $extra
                                                                    GROUP BY mh.main_title
                                                                    ORDER BY mh.main_sort_order,mh.sub_title_sort_order "); 
                                            }
                                            $row_span = '';
                                            if(count($get_count) > 0)
                                            {
                                                $row_span = $get_count[0]->rowspan_tot;
                                            }                        

                                            if($row_span > 1){
                                                $row_span_new = $row_span + 1;
                                            }else{
                                                $row_span_new = $row_span;
                                            }                                                            
                                            $rowspan_text = " rowspan=".$row_span;
                                    @endphp    
                                        <tr style="background-color: {{$value['main_title_color_code']}};">
                                            <td {{$rowspan_text}}>{{$new_i = $i++}}</td>
                                            <td {{$rowspan_text}}>{{$value['main_title']}}</td>
                                            <td {{$rowspan_text}}>{{$value['main_title_color_code']}}</td>
                                            <td {{$rowspan_text}} style="background-image: url({{$value['main_title_background_image']}});background-repeat: no-repeat;background-size: auto;background-color:white;width:  25% !important;height: auto !important;">&nbsp;</td>
                                            <td {{$rowspan_text}}>{{$value['main_sort_order']}}</td>
                                            <td>{{$value['sub_title_of_main']}}</td>
                                            <td style="background-color:white;">
                                                <img style="height: 70px;width: 70px;" src="{{$value['sub_title_icon']}}">
                                            </td>
                                            <td>{{$value['sub_title_sort_order']}}</td>
                                            <td>{{$value['status']}}</td>                                            
                                            <td style="background-color: white;">
                                                <a href="javascript:edit_data('{{route('add_mobileapp_menu_rights.update',$value['id'])}}','{{$value['id']}}','{{$value['main_title']}}','{{$value['main_title_color_code']}}','{{$value['main_title_background_image']}}','{{$value['main_sort_order']}}','{{$value['sub_title_of_main']}}','{{$value['sub_title_icon']}}','{{$value['sub_title_sort_order']}}','{{$value['status']}}','{{$data['profile']}}',{{$new_i}});" class="btn btn-outline-success mr-1"><i class="mdi mdi-lead-pencil"></i></a>
                                                
                                            </td>                                            
                                        </tr>
                                    @php        
                                        }
                                        else
                                        {
                                    @endphp 
                                        <tr style="background-color: {{$value['main_title_color_code']}};">
                                            <td>{{$value['sub_title_of_main']}}</td>
                                            <td style="background-color:white;">
                                                <img style="height: 70px;width: 70px;" src="{{$value['sub_title_icon']}}">
                                            </td>
                                            <td>{{$value['sub_title_sort_order']}}</td>
                                            <td>{{$value['status']}}</td>                                            
                                            <td style="background-color: white;">
                                                <a href="javascript:edit_data('{{route('add_mobileapp_menu_rights.update',$value['id'])}}','{{$value['id']}}','{{$value['main_title']}}','{{$value['main_title_color_code']}}','{{$value['main_title_background_image']}}','{{$value['main_sort_order']}}','{{$value['sub_title_of_main']}}','{{$value['sub_title_icon']}}','{{$value['sub_title_sort_order']}}','{{$value['status']}}','{{$data['profile']}}',{{$new_i}});" class="btn btn-outline-success mr-1"><i class="mdi mdi-lead-pencil"></i></a>
                                                
                                            </td> 
                                        </tr> 
                                    @php       
                                        }
                                       
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

<!--Modal: Add ChapterModal-->
<div class="modal fade right modal-scrolling" id="MenuModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" style="display: none;" aria-hidden="true">
    <div class="modal-dialog modal-side modal-bottom-right modal-notify modal-info" role="document">
        <!--Content-->
        <div class="modal-content">
            <!--Header-->
            <div class="modal-header">
                <h5 class="modal-title" id="heading">Add Chapter</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">x</span>
                </button>
            </div>  

            <?php 
                $bg_directory = "storage/homescreen/bg_icon";
                $bg_images = glob($bg_directory . "/*.png");
                $icon_directory = "storage/homescreen/icon";
                $icon_images = glob($icon_directory . "/*.png");
                $server = "http://".$_SERVER['HTTP_HOST']."/";                
            ?>

            <!--Body-->
            <form action="" method="post" id="menu_form" enctype="multipart/form-data">                          
            <div id="change_method">
            {{ method_field("POST") }}
            </div>
            @csrf 
            <div class="modal-body">
                <div class="row">
                    <div class="white-box">
                        <div class="panel-body">
                            @if ($message = Session::get('success'))
                            <div class="alert alert-success alert-block">
                                <button type="button" class="close" data-dismiss="alert">×</button>
                                <strong>{{ $message }}</strong>
                            </div>
                            @endif
                            <div class="col-lg-12 col-sm-12 col-xs-12">
                                <div class="col-md-12 form-group">
                                    <label>Main Title</label>
                                    <input type="text" id='main_title' name="main_title" class="form-control">
                                    <input type="hidden" id='profile_hidden' name="profile_hidden">
                                </div>
                                <div class="col-md-12 form-group">
                                    <label>Main Title Color Code</label>
                                    <div class="row">
                                        <div class="col-md-6 pr-0">                                        
                                            <input type="color" id="main_title_color_code" name="main_title_color_code" pattern="^#+([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$" class="form-control">
                                        </div>
                                        <div class="col-md-6 pl-0">
                                            <input type="text" pattern="^#+([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$" id="hexcolor" class="form-control"></input>
                                        </div>
                                    </div>                                    
                                </div>                            
                                <div class="col-md-12 form-group">
                                    <label>Main Title Background Image</label>
                                    <input type="text" id='main_title_background_image' name="main_title_background_image" class="form-control">
                                </div>
                                <div class="col-md-12 form-group">
                                    <select class="image-picker show-html" name="main_title_background_image" >
                                    @foreach($bg_images as $key => $bg_image)
                                      @php $full_bg_image = $server.$bg_image; @endphp
                                      <option id="main_title_background_image_{{$key}}" data-img-src="{{$full_bg_image}}" data-img-alt="Image-1" value="{{$full_bg_image}}"></option> 
                                    @endforeach
                                    </select>
                                </div>
                                <div class="col-md-12 form-group">
                                    <label>Main Sort Order</label>
                                    <select id='main_sort_order' name="main_sort_order" class="form-control">
                                        <option value="1">1</option>
                                        <option value="2">2</option>
                                        <option value="3">3</option>
                                        <option value="4">4</option>
                                        <option value="5">5</option>
                                        <option value="6">6</option>
                                        <option value="7">7</option>
                                        <option value="8">8</option>
                                        <option value="9">9</option>
                                        <option value="10">10</option>
                                        <option value="11">11</option>
                                        <option value="12">12</option>
                                    </select>
                                </div>
                                <div class="col-md-12 form-group">
                                    <label>Sub Title of Main</label>
                                    <input type="text" id='sub_title_of_main' name="sub_title_of_main" class="form-control">
                                </div>
                                <div class="col-md-12 form-group">
                                    <label>Sub Title Icon</label>
                                    <input type="text" id='sub_title_icon' name="sub_title_icon" class="form-control">
                                </div>
                                 <div class="col-md-12 form-group">
                                    <select class="image-picker show-html" name="sub_title_icon" >
                                      @foreach($icon_images as $k1 => $icon_image)
                                          @php $full_icon_image = $server.$icon_image; @endphp
                                          <option id="sub_title_icon_{{$k1}}" data-img-src="{{$full_icon_image}}" value="{{$full_icon_image}}"></option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-12 form-group">
                                    <label>Sub Title Sort Order</label>
                                    <select id='sub_title_sort_order' name="sub_title_sort_order" class="form-control">
                                        <option value="1">1</option>
                                        <option value="2">2</option>
                                        <option value="3">3</option>
                                        <option value="4">4</option>
                                        <option value="5">5</option>
                                        <option value="6">6</option>
                                        <option value="7">7</option>
                                        <option value="8">8</option>
                                        <option value="9">9</option>
                                        <option value="10">10</option>
                                        <option value="11">11</option>
                                        <option value="12">12</option>
                                    </select>
                                </div>
                                <div class="col-md-12 form-group">
                                    <label>Status</label>
                                    <select id='status' name="status" class="form-control">
                                        <option value="Yes">Yes</option>
                                        <option value="No">No</option>
                                    </select>
                                </div>                                
                            </div>
                        </div>
                    </div>                                
                </div>
            </div>

            <!--Footer-->
            <div class="modal-footer flex-center" style="display: block !important;text-align: center;">
                <input type="submit" id="submit" name="submit" value="Save" class="btn btn-success" >
            </div>
            </form>
        </div>
        <!--/.Content-->
    </div>
</div>
<!--Modal: Add ChapterModal-->

@include('includes.footerJs')
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/image-picker/0.3.1/image-picker.min.js"></script>
<script>
    $(".image-picker").imagepicker();
</script>
<script>
    $('#main_title_color_code').on('input', function() {
        $('#hexcolor').val(this.value);
    });
    $('#hexcolor').on('input', function() {
      $('#main_title_color_code').val(this.value);
    });
    function edit_data(url,main_id,main_title,main_title_color_code,main_title_background_image,main_sort_order,sub_title_of_main,sub_title_icon,sub_title_sort_order,status,user_profile,sr_no)
    {                      
        $("#main_title").val(main_title);
        $("#main_title_color_code").val(main_title_color_code);
        $("#hexcolor").val(main_title_color_code);
        $('#main_title_background_image'+sr_no).data('img-src',main_title_background_image);
        $("#main_title_background_image").val(main_title_background_image);
        $("#main_sort_order").val(main_sort_order);
        $("#sub_title_of_main").val(sub_title_of_main);
        $("#sub_title_icon").val(sub_title_icon);
        $("#sub_title_sort_order").val(sub_title_sort_order);
        $("#status").val(status);
        $("#profile_hidden").val(user_profile);
        $('#submit').val('Update'); 
        $('#heading').html('Update Menu Sub-menu');         
        $('#menu_form').attr('action',url);
        $('#change_method').html('{{ method_field("PUT") }}');
        $('#MenuModal').modal('show');
    }
    function add_data(fees_collect_id,student_id,receipt_no)
    {
        var css =  "";
        var recepit_css = "<style>" + css + "</style>";
        var fees_content = $('#fees_html_'+fees_collect_id).val();
        // alert(fees_content);
        $('#reprint_receipt_html').html(recepit_css+fees_content);
        $('#student_id').val(student_id);
        $('#receipt_id_html').val(receipt_no);
        $('#ChapterModal').modal('show');
       
    }
    $(document).ready(function() {
        
        $('#example').DataTable({
            "order": [
                [1, 'asc']
            ],
            "columnDefs": [{
                "orderable": false,
                "targets": 0
            }]
        });
    });
</script>
@include('includes.footer')
<style type="text/css">
    @media screen {
      #printSection {
          display: none;
      }
    }
</style>