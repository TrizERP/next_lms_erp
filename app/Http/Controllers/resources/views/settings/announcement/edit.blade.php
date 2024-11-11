

@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Edit Announcement</h4> 
            </div>
        </div>
        <!-- table card start -->
        <div class="card">  
            <form class="row" action="{{route('announcements.update',$data['data']->id)}}" method="post" enctype="multipart/form-data">
            {{ method_field("PUT") }}
                @csrf
                 <!-- Profile Id  -->
                 <div class="col-md-4 form-group">
                    <label for="">User Profiles</label>
                   <input type="text"  class="form-control" name="user_profiles" id="user_profiles"  readonly value="{{$data['data']->name}}">
                   </div>

                <!-- title  -->
                <div class="col-md-4 form-group">
                    <label for="">Title</label>
                    <input type="text" class="form-control" name="title" id="title"  value="{{$data['data']->title}}" required>
                </div>
                <!-- description  -->
                <div class="col-md-4 form-group">
                    <label for="">Description</label>
                    <textarea class="form-control" name="description" id="description" required>{{$data['data']->description}}</textarea>
                </div>
                <!-- attachment  -->
                <div class="col-md-4 form-group">
                    <label for="">Attachment</label>
                    <input type="file" class="form-control" name="attachment" id="attachment">
                    <input type="hidden" class="form-control" name="oldFile" id="oldFile" value="{{$data['data']->attachment}}">
                </div>
                <!-- from_date  -->
                <div class="col-md-4 form-group">
                    <label>From Date</label>
                    <input type="text" id="from_date" name="from_date" class="form-control mydatepicker" autocomplete="off" value="{{ ($data['data']->from_date!="0000-00-00") ? $data['data']->from_date : ''}}">
                </div>
                <!-- to_date  -->
                <div class="col-md-4 form-group">
                    <label>To Date</label>
                    <input type="text" id="to_date" name="to_date" class="form-control mydatepicker" autocomplete="off" value="{{ ($data['data']->from_date!="0000-00-00") ? $data['data']->to_date : ''}}">
                </div>

            <div class="col-md-12">
                <center>
                    <input type="submit" value="Update" class="btn btn-primary">
                </center>
            </div>

            </form>
        </div>

        <!-- table card end -->
    </div>
    <!-- container fluid end  -->
</div>
<!-- page-wrapper div end  -->
@include('includes.footerJs')
@include('includes.footer')