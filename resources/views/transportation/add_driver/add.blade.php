@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')


<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">            
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">                
                <h4 class="page-title">Add Driver Conductor</h4>            
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
                    <form action="{{ route('add_driver.store') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("POST") }}
                        {{csrf_field()}}

                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label>First Name</label>
                                <input type="text" id='first_name' required name="first_name" value="" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Last Name</label>
                                <input type="text" id='last_name' required name="last_name" value="" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Mobile</label>
                                <input type="text" id='mobile' pattern="[1-9]{1}[0-9]{9}" required name="mobile" value="" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">                         
                                <label>Upload I-card Icon</label>
                                <input type="file" name="icard_icon" id="icard_icon" class="form-control"/> 
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Type</label>
                                <select name ="type" class="form-control" required>
                                    <option value="">--Select--</option>
                                    <option value="Driver">Driver</option>
                                    <option value="Conductor">Conductor</option>
                                </select>
                            </div>
                            <div class="col-md-4 form-group mt-4">                            
                                <input type="submit" name="submit" value="Save" class="btn btn-success" >                            
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
