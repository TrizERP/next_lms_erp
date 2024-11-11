@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Announcement</h4> 
            </div>
        </div>
        <!-- table card start -->
        <div class="card">  
        @if(session()->get('user_profile_name')=="Admin")
            <div class="col-lg-3 col-sm-3 col-xs-3">
                <a href="{{ route('announcements.create') }}" class="btn btn-info add-new mb-3">
                    <i class="fa fa-plus"></i> Add Announcement
                </a>
            </div>
        @endif

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

            @if(!empty($data['announcementDetails']))
            <div class="row">                
                <div class="col-md-12">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Sr.No.</th>
                                    <th>Title</th>
                                    <th>Description</th>
                                    <th>Attachment</th>
                                    <th>From Date</th>
                                    <th class="text-left">To date</th>
                                    @if(session()->get('user_profile_name')=="Admin" || session()->get('user_profile_name')=="Super Admin")
                                    <th>User Profile</th>
                                    <th class="text-left">Action</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($data['announcementDetails'] as $key=>$value)
                                <tr>
                                    <td>{{$key+1}}</td>
                                    <td>{{$value->title}}</td>
                                    <td>{{$value->description}}</td>
                                    <td>@if(isset($value->attachment))<a href="{{$value->attachment_url}}" target="_blank">View</a>  @else N/A @endif </td>
                                    <td>{{ ($value->from_date!="0000-00-00") ? $value->from_date : '-'}}</td>
                                    <td>{{ ($value->to_date!="0000-00-00") ? $value->to_date : '-'}}</td>
                                    @if(session()->get('user_profile_name')=="Admin" || session()->get('user_profile_name')=="Super Admin")
                                    <td>{{$value->name}}</td>
                                    <td><a href="{{ route('announcements.edit',$value->id)}}"><button style="float:left;" type="button" class="btn btn-info btn-outline btn-circle btn m-r-5"><i class="ti-pencil-alt"></i></button></a>
                                        <form action="{{ route('announcements.destroy', $value->id)}}" method="post">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"  onclick="return confirmDelete();" class="btn btn-danger btn-outline btn-circle btn m-r-5"><i class="ti-trash"></i></button>
                                        </form></td>
                                    @endif
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div> 
            @endif
            <!-- row end  -->
        </div>
        <!-- table end  -->

    </div>
    <!-- container fluid end  -->
</div>
<!-- page-wrapper div end  -->
@include('includes.footerJs')
@include('includes.footer')