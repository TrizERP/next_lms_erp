@include('../includes.headcss')
<link href="/plugins/bower_components/clockpicker/dist/jquery-clockpicker.min.css" rel="stylesheet">
@include('../includes.header')
@include('../includes.sideNavigation')


<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">            
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">                
                <h4 class="page-title">Add Shift</h4>            
            </div>                    
        </div>     
            <div class="card">
                @if ($message = Session::get('success'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                </div>
                @endif
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('transport_shift.update', $data['id']) }}" enctype="multipart/form-data" method="post">
                        {{ method_field("PUT") }}
                        {{csrf_field()}}

                        <div class="row">
                        <div class="col-md-6 form-group ml-0 mr-auto">
                            <label>Shift Name</label>
                            <input type="text" id='shift_title' required name="shift_title" value="{{$data['shift_title']}}" class="form-control">
                        </div>

                        <div class="col-md-6 form-group ml-0 mr-auto">
                            <label>Shift Rate</label>
                            <input type="number" id='shift_rate' required name="shift_rate" value="{{$data['shift_rate']}}" class="form-control">
                        </div>
                        
                        <div class="col-md-6 form-group ml-0 mr-auto">
                            <label>Shift KM Amount</label>
                            <input type="number" id='km_amount' required name="km_amount" value="{{$data['km_amount']}}" class="form-control">
                        </div>
                        <div class="col-md-12 form-group">
                            <center>
                                <input type="submit" name="submit" value="Update" class="btn btn-success" >
                            </center>
                        </div>
                        </div>

                    </form>
                </div>
                @if (count($errors) > 0)
                <div class="alert alert-danger">
                    <strong>Whoops!</strong> There were some problems with your input.<br><br>
                    <ul>
                        @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif
            </div>        
    </div>
</div>


@include('includes.footerJs')
@include('includes.footer')
