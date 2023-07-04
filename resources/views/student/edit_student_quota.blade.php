@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Edit Student Quota</h4> </div>
        </div>      
            <div class="card">
            <div class="panel-body">
				<!-- @TODO: Create a saperate tmplate for messages and include in all tempate -->
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
                
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('student_quota.update',$data['id']) }}" enctype="multipart/form-data" method="post">
    
                        {{ method_field("PUT") }}
                        
                            @csrf
                        
                        <div class="row">
                        <div class="col-md-6 form-group">
                            <label>{{App\Helpers\get_string('studentquota','request')}}</label>
                            <input type="text" id='title' value="@if(isset($data['title'])){{ $data['title'] }}@endif" required name='title' class="form-control" pattern="[a-zA-Z\s]+">
                        </div>

                        <div class="col-md-6 form-group">
                            <label>Sort Order </label>
                            <input type="number" id='sort_order' value="@if(isset($data['sort_order'])){{ $data['sort_order'] }}@endif" required name='sort_order' class="form-control">
                        </div>
                        
                        <div class="col-md-12 form-group">
                                <input type="submit" name="submit" value="Update" class="btn btn-success" >
                        </div>
                        </div>

                    </form>
            </div>
            </div>
        </div>
    </div>

@include('includes.footerJs')
@include('includes.footer')
