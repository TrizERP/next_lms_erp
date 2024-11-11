@include('../includes.headcss')
<link href="/plugins/bower_components/clockpicker/dist/jquery-clockpicker.min.css" rel="stylesheet">
@include('../includes.header')
@include('../includes.sideNavigation')


<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">            
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">                
                <h4 class="page-title">Route Stop Mapping</h4>            
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
                    <form action="{{ route('map_route_stop.store') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("POST") }}
                        {{csrf_field()}}

                        <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Route Name</label>
                            <select name="route" class="form-control" required>
                                <option value="">--Select--</option>
                                <?php
                                foreach ($data['ddRoute'] as $id => $arr) {
                                    echo "<option value='$id'>$arr</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-md-12 form-group">
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Sr. No.</th>
                                            <th>Stop Name</th>
                                            <th>Pickup Time</th>
                                            <th>Drop Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($data['ddStop'] as $id => $arr)
                                        <tr>
                                            <td><input type="checkbox" name="stop_arr[]" id="stop_arr" value="{{$id}}" onclick="make_require(this);"></td>
                                            <td><input class="form-control" type="text" name="stop_id[{{$id}}]" id="stop_id" value="{{$arr}}" readonly></td>
                                           
                                            <td> 
                                                <div class="input-group clockpicker" data-placement="bottom" data-align="top" data-autoclose="true">
                                                    <input type="text" id='pickuptime' name="pickuptime[{{$id}}]" class="form-control" autocomplete="off"> 
                                                    <span class="input-group-addon"><span class="glyphicon glyphicon-time"></span></span>
                                                </div>
                                            </td>
                                            <td> 
                                                <div class="input-group clockpicker" data-placement="bottom" data-align="top" data-autoclose="true">
                                                    <input type="text" id='droptime' name="droptime[{{$id}}]" class="form-control" autocomplete="off"> 
                                                    <span class="input-group-addon"><span class="glyphicon glyphicon-time"></span></span>
                                                </div>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- <div class="col-md-6 form-group">
                            <label>Stop Name</label>
                            <select name="stop[]" required multiple class="form-control">
                                <option value="">--Select--</option>
                                <?php
                                // foreach ($data['ddStop'] as $id => $arr) {
                                //     echo "<option value='$id'>$arr</option>";
                                // }
                                ?>
                            </select>
                        </div> -->

                        <div class="col-md-12 form-group">
                            <center>
                                <input type="submit" name="submit" value="Save" onclick="return validateData();" class="btn btn-success" >
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

<script type="text/javascript">
function validateData()
{               
    var selected_stud = $("input[name='stop_arr[]']:checked").length;
    if(selected_stud == 0)
    {
        alert("Please Select Atleast One Student");
        return false;
    }
    else{
        return true;
    }               
}

function make_require(ele)
{   
    var val = $(ele).val();    
    if($(ele).prop("checked") == true)
    {
        $("input[name='pickuptime["+val+"]']").attr("required", true);
        $("input[name='droptime["+val+"]']").attr("required", true);
    }
    else
    {
        $("input[name='pickuptime["+val+"]']").attr("required", false);
        $("input[name='droptime["+val+"]']").attr("required", false);   
    }
}

</script>
@include('includes.footer')
