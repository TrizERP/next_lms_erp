@include('../includes.headcss')
<link rel="stylesheet" href="../../../plugins/bower_components/dropify/dist/css/dropify.min.css">
@include('../includes.header')
@include('../includes.sideNavigation')


<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">            
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">                
                <h4 class="page-title">Edit Vehicle</h4>            
            </div>                    
        </div>       
            <div class="card">
                @if ($message = Session::get('success'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                </div>
                @endif
                <?php
//                echo "<pre>";
//                print_r($data);
//                exit;
                ?>
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('add_vehicle.update', $data['id']) }}" enctype="multipart/form-data" method="post">
                        {{ method_field("PUT") }}
                        {{csrf_field()}}


                        <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Title</label>
                            <input type="text" required name="title" value="{{ $data['title'] }}" class="form-control">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Vehicle Number</label>
                            <input type="text" required name="vehicle_number" value="{{ $data['vehicle_number'] }}" class="form-control">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Vehicle Type</label>
                            <select name="vehicle_type" class="form-control" required>
                                <option value="">--Select--</option>
                                <?php
                                foreach ($data['vehicle_type_data'] as $id => $arr) {
                                    $selected = "";
                                    if ($data['vehicle_type'] == $arr)
                                        $selected = "selected=selected";
                                    echo "<option $selected value='$arr'>$arr</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Sitting Capacity</label>
                            <input type="text" required name="sitting_capacity" value="{{ $data['sitting_capacity'] }}" class="form-control">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>School Shift</label>
                            <select name="school_shift" class="form-control" required>
                                <option value="">--Select--</option>
                                <?php
                                foreach ($data['ddValue'] as $id => $arr) {
                                    $selected = "";
                                    if ($data['school_shift'] == $id)
                                        $selected = "selected=selected";
                                    echo "<option $selected value='$id'>$arr</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Driver</label>
                            <select name="driver" class="form-control" required>
                                <option value="">--Select--</option>
                                <?php
                                foreach ($data['Driverdd'] as $id => $arr) {
                                    $selected = "";
                                    if ($data['driver'] == $id)
                                        $selected = "selected=selected";
                                    echo "<option $selected value='$id'>$arr</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Conductor</label>
                            <select name="conductor" class="form-control">
                                <option value="">--Select--</option>
                                <?php
                                foreach ($data['Conductordd'] as $id => $arr) {
                                    $selected = "";
                                    if ($data['conductor'] == $id)
                                        $selected = "selected=selected";
                                    echo "<option $selected value='$id'>$arr</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Vehicle Identity Number</label>
                            <input type="text" name="vehicle_identity_number" value="{{ $data['vehicle_identity_number'] }}" class="form-control">
                        </div>


                        <div class="col-md-12 form-group">
                            <center>
                                <input type="submit" name="submit" value="Save" class="btn btn-success" >
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
