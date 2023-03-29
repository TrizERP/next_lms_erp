@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid"> 
        <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">User Log Repot</h4> </div>
            </div>     
            <div class="card">
                @if(!empty($data['message']))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $data['message'] }}</strong>
                </div>
                @endif
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('user_log.store') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("POST") }}
                        {{csrf_field()}}

                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label>From Date</label>
                                <input type="text" required id="from_date" name="from_date"  class="form-control mydatepicker" autocomplete="off">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>To Date</label>
                                <input type="text" required id="to_date" name="to_date"  class="form-control mydatepicker" autocomplete="off">
                            </div>
                            
                            <div class="col-md-4 form-group">
                                <label>User</label>
                                <select name="user" class="form-control" >
                                    <option value="">Select</option>
                                    @foreach($data['data']['user'] as $key => $value)
                                        <option value="{{$key}}">{{$value}}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Search" class="btn btn-success" >
                                </center>
                            </div>
                        </div>
                    </form>
                </div>
            </div>      
    </div>
</div>

@include('includes.footerJs')
@include('includes.footer')
