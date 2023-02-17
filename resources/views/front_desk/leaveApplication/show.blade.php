@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">            
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">                
                <h4 class="page-title">Leave Application</h4>            
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
            <form class="card" action="{{ route('leave_application.create') }}" enctype="multipart/form-data" method="post">
                {{ method_field("GET") }}
                {{csrf_field()}}
                <div class="row">
                    {{ App\Helpers\SearchChain('4','single','grade,std,div') }}
                    <div class="col-md-4">
                        <label>From Date</label>
                        <input type="text" name="from_date" class="form-control mydatepicker" autocomplete="off" >
                    </div>
                    <div class="col-md-4">
                        <label>To Date</label>
                        <input type="text" name="to_date" class="form-control mydatepicker" autocomplete="off">
                    </div>
                    <div class="col-md-12">
                        <label></label><br>
                        <center>
                            <input type="submit" name="submit" value="Search" class="btn btn-success" >
                        </center>
                    </div>
                </div>
            </form>
        </div>    
    </div>
</div>

@include('includes.footerJs')
@include('includes.footer')
