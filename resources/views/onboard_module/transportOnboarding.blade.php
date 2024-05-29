<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ENjdO4Dr2bkBIFxQpeoTz1HIcje39Wm4jDKdf19U8gI4ddQ3GYNS7NTKfAdVQSZe" crossorigin="anonymous"></script>
@include('includes.headcss') @include('includes.header')
<link rel="stylesheet" href="{{asset('/transport_onboard/CSS/Transportation.css')}}">
<!-- This section starts the step 1 -->
<div class="accordian-menu-container">
   <ul class="text accordian">
      <li>
         <input type="radio" name="accordian" id="first" checked />
         <label for="first">Step 1 - Driver/Conductor</label>
         <div id="first-step" class="content">
            <span style="display: flex; align-items: center; gap: 3vh;padding:16px">
               <img src="{{asset('transport_onboard/svg_to_png/step_1_1.png')}}" alt="step_1_1" style="width:30px">
               <h3 style="font-size: 2.5vh; font-weight: 500">
                  How much Driver/Conductor you have?
               </h3>
            </span>
            <div class="option-container">
               <div class="option driverOptions">1 to 10</div>
               <div class="option driverOptions">11 to 20</div>
               <div class="option driverOptions">21 to 30</div>
               <div class="option driverOptions">31 to 40</div>
               <div class="option driverOptions">41 to 50</div>
               <div class="option driverOptions">51 to 60</div>
            </div>
            <span style="display: flex; align-items: center; gap: 3vh;padding:16px">
               <img src="{{asset('transport_onboard/svg_to_png/step_1_2.png')}}" alt="step_1_1" style="width:30px">
               <h3 style="font-size: 2.5vh; font-weight: 500">
                  Wow, You have <span id="driverSpan">21 to 30</span> Drivers/Conductors.
               </h3>
            </span>
            <button class="selected-content-button" data-toggle="modal" data-target="#driverModal">
            + Add Driver/Conductor Details
            </button>
            <button class="save-continue">
            Continue
            <span style="font-size: 3vh" class="mdi mdi-arrow-right-bold-box-outline" ></span>
            </button>
         </div>
      </li>
      <!-- add driver modal  -->
      <div class="modal fade" id="driverModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
         <div class="modal-dialog" role="document" style="max-width:1000px">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title" id="exampleModalLabel">Add Driver/Conductor Details</h5>
                  <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">&times;</span>
                  </button>
               </div>
               <div class="modal-body">
                  <div class="row">
                     <div class="col-md-4 form-group">
                        <label>First Name</label>
                        <input type="text" id='first_name' required name="first_name" class="form-control">
                     </div>
                     <div class="col-md-4 form-group">
                        <label>Last Name</label>
                        <input type="text" id='last_name' required name="last_name" class="form-control">
                     </div>
                     <div class="col-md-4 form-group">
                        <label>Mobile</label>
                        <input type="text" id='mobile' pattern="[1-9]{1}[0-9]{9}" required name="mobile" class="form-control">
                     </div>
                     <div class="col-md-4 form-group">                         
                        <label>Upload I-card Icon</label>
                        <input type="file" name="icard_icon" id="icard_icon" class="form-control"/> 
                     </div>
                     <div class="col-md-4 form-group">
                        <label>Type</label>
                        <select name ="type" class="form-control" required id="driver_type">
                           <option value="">--Select--</option>
                           <option value="Driver">Driver</option>
                           <option value="Conductor">Conductor</option>
                        </select>
                     </div>
                     <div class="col-md-4 form-group">
                        <label>Status</label>
                        <select name ="status" class="form-control" required id="status">
                           <option value="">--Select--</option>
                           <option value="Active">Active</option>
                           <option value="Inactive">Inactive</option>
                        </select>
                     </div>
                     <div class="col-md-4 form-group mt-4">                            
                        <input type="submit" name="submit" value="Save" class="btn btn-success" onclick="addDriver()">                            
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </div>
      <!-- Step 2 -->
      <li>
         <input type="radio" name="accordian" id="second" />
         <label for="second">Step 2 - Vehicle</label>
         <div id="second-step" class="content">
            <span style="display: flex; align-items: center; gap: 3vh;padding:16px">
               <img src="{{asset('transport_onboard/svg_to_png/step_2_1.png')}}" alt="step_1_1" style="width:30px">
               <h3 style="font-size: 2.5vh; font-weight: 500">
                  How much Vehicle you have?
               </h3>
            </span>
            <div class="option-container">
               <div class="option vehicleOptions">1 to 10</div>
               <div class="option vehicleOptions">11 to 20</div>
               <div class="option vehicleOptions">21 to 30</div>
               <div class="option vehicleOptions">31 to 40</div>
               <div class="option vehicleOptions">41 to 50</div>
               <div class="option vehicleOptions">51 to 60</div>
            </div>
            <span style="display: flex; align-items: center; gap: 3vh;padding:16px">
               <img src="{{asset('transport_onboard/svg_to_png/step_1_2.png')}}" alt="step_1_1" style="width:30px">
               <h3 style="font-size: 2.5vh; font-weight: 500">
                  Wow, You have <span id="vehicleSpan">21 to 30</span> Vehicles, Please select
                  which types of vehicle you have?
               </h3>
            </span>
            <div class="vehicle-choice-container">
               <div class="vehicle-choice" data-val="Bus">
                  <img src="{{asset('/transport_onboard/images/bus.png')}}" class="vehicle-icon" alt="Bus" />
                  Bus
               </div>
               <div class="vehicle-choice" data-val="Van">
                  <img src="{{asset('/transport_onboard/images/van.png')}}" class="vehicle-icon" alt="Bus" />
                  Van
               </div>
               <div class="vehicle-choice" data-val="Rickshaw">
                  <img src="{{asset('/transport_onboard/images/rikshaw.png')}}" class="vehicle-icon" alt="Bus" />
                  Rikshaw
               </div>
            </div>
            <span style="display: flex; align-items: center; gap: 3vh;padding:16px">
               <img src="{{asset('transport_onboard/svg_to_png/step_3_1.png')}}" alt="step_3_1" style="width:30px">
               <h3 style="font-size: 2.5vh; font-weight: 500">
                  Great, Add your vehicle details
               </h3>
            </span>
            <button class="selected-content-button" data-toggle="modal" data-target="#vehicleModal">
            + Add Vehicle Details
            </button>
            <button class="save-continue">
            Continue
            <span style="font-size: 3vh" class="mdi mdi-arrow-right-bold-box-outline" ></span>
            </button>
         </div>
      </li>
      <!-- add vehicle modal  -->
      <div class="modal fade" id="vehicleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
         <div class="modal-dialog" role="document" style="max-width:1000px">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title" id="exampleModalLabel">Add Vehicle Details</h5>
                  <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">&times;</span>
                  </button>
               </div>
               <div class="modal-body">
                  <div class="row">
                     <div class="col-md-6 form-group">
                        <label>Title</label>
                        <input type="text" required name="title" id="vehicle_name" class="form-control">
                     </div>
                     <div class="col-md-6 form-group">
                        <label>Vehicle Number</label>
                        <input type="text" required name="vehicle_number" id="vehicle_number" class="form-control">
                     </div>
                     <input type="hidden" name="vehicle_type" id="vehicleType" class="form-control">
                     <div class="col-md-6 form-group">
                        <label>Sitting Capacity</label>
                        <input type="text" required name="sitting_capacity" id="sitting_capacity" class="form-control">
                     </div>
                     <div class="col-md-6 form-group">
                        <label>School Shift</label>
                        <select name="school_shift" class="form-control" required id="school_shift">
                           <option value="">--Select--</option>
                           @foreach ($data['vehicleData']->ddValue as $id => $arr) 
                           <option value='{{$id}}'>{{$arr}}</option>
                           @endforeach
                        </select>
                     </div>
                     <div class="col-md-6 form-group">
                        <label>Driver</label>
                        <select name="driver" class="form-control" required id="driver">
                           <option value="">--Select--</option>
                           @foreach ($data['vehicleData']->Driverdd as $id => $arr) 
                           <option value='{{$id}}'>{{$arr}}</option>
                           @endforeach
                        </select>
                     </div>
                     <div class="col-md-6 form-group">
                        <label>Conductor</label>
                        <select name="conductor" class="form-control" id="conductor">
                           <option value="">--Select--</option>
                           @foreach ($data['vehicleData']->Conductordd as $id => $arr) 
                           <option value='{{$id}}'>{{$arr}}</option>
                           @endforeach
                        </select>
                     </div>
                     <div class="col-md-6 form-group">
                        <label>Vehicle Identity Number</label>
                        <input type="text" required name="vehicle_identity_number" id="vehicle_identity_number" class="form-control">
                     </div>
                     <div class="col-md-12 form-group">
                        <center>
                           <input type="submit" name="submit" value="Save" class="btn btn-success" onclick="addVehicle()">
                        </center>
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </div>
      <!-- Step 3 -->
      <li>
         <input type="radio" name="accordian" id="third" />
         <label for="third">Step 3 - Route</label>
         <div class="content">
            <span style="display: flex; gap: 3vh; align-items: center">
               <img class="selected-content-image" src="{{asset('/transport_onboard/images/route.png')}}" alt="Route Icon"/>
               <span>
                  <h3 style="font-size: 2.5vh;font-weight: 500;margin-bottom: 6vh;">
                     We need your route details
                  </h3>
                  <button class="selected-content-button" data-toggle="modal" data-target="#vehicleModal">
                  + Add Route
                  </button>
               </span>
            </span>
            <button class="save-continue">
            Continue
            <span style="font-size: 3vh" class="mdi mdi-arrow-right-bold-box-outline" ></span>
            </button>
         </div>

    <!-- add Route modal  -->
      <div class="modal fade" id="vehicleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
         <div class="modal-dialog" role="document" style="max-width:1000px">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title" id="exampleModalLabel">Add Route Details</h5>
                  <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">&times;</span>
                  </button>
               </div>
               <div class="modal-body">
                  <div class="row">
                  <div class="col-md-4 form-group">
                            <label>Route Name</label>
                            <input type="text" id='route_name' required name="route_name" value="" class="form-control">
                        </div>
                        <div class="col-md-4 form-group">
                            <label>From Time</label>
                            <div class="input-group clockpicker " data-placement="bottom" data-align="top" data-autoclose="true">
                                <input type="text" id='from_time'  name="from_time" class="form-control" value=""> 
                                <span class="input-group-addon"><span class="glyphicon glyphicon-time"></span></span>
                            </div>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>To Time</label>
                            <div class="input-group clockpicker " data-placement="bottom" data-align="top" data-autoclose="true">
                                <input type="text" id='to_time'  name="to_time" class="form-control" value=""> 
                                <span class="input-group-addon"><span class="glyphicon glyphicon-time"></span></span>
                            </div>
                        </div>

                        <div class="col-md-12 form-group">
                            <center>
                                <input type="submit" name="submit" value="Save" class="btn btn-success" onclick="addRoute()">
                            </center>
                        </div>
                  </div>
               </div>

            </div>
         </div>
      </div>

      </li>
      <!-- Step 4 -->
      <li>
         <input type="radio" name="accordian" id="fourth" />
         <label for="fourth">Step 4 - Stop</label>
         <div class="content">
            <span style="display: flex; gap: 3vh; align-items: center">
               <img class="selected-content-image" src="{{asset('/transport_onboard/images/stop.png')}}" alt="Stop Icon"/>
               <span>
                  <h3 style="font-size: 2.5vh;font-weight: 500;margin-bottom: 3vh;">
                     We need your stop details
                  </h3>
                  <button class="selected-content-button" data-toggle="modal" data-target="#routeModal">
                  + Add Stop
                  </button>
               </span>
            </span>
            <button class="save-continue">
            Done
            <span style="font-size: 3vh" class="mdi mdi-arrow-right-bold-box-outline" ></span>
            </button>
         </div>

         <!-- add Route modal  -->
      <div class="modal fade" id="routeModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
         <div class="modal-dialog" role="document" style="max-width:1000px">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title" id="exampleModalLabel">Add Route Details</h5>
                  <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">&times;</span>
                  </button>
               </div>
               <div class="modal-body">
                  <div class="row">
                        <div class="col-md-6 form-group ml-0 mr-auto">
                            <label>Stop Name</label>
                            <input type="text" id='stop_name' required name="stop_name" value="" class="form-control">
                        </div>

                        <div class="col-md-12 form-group">
                            <center>
                                <input type="submit" name="submit" value="Save" class="btn btn-success" onclick="addStop()">
                            </center>
                        </div>
                  </div>
               </div>

            </div>
         </div>
      </div>

      </li>
   </ul>
</div>
<!-- Done Popup -->
<div class="popup-container">
   <div class="popup text">
      <img style="height: 40vh" src="{{asset('/transport_onboard/images/Done.png')}}" />
      <h2 style="font-weight: 400; margin-bottom: 2vh">
         Nice, Your transportation details succesfully saved.
      </h2>
      <a href="{{route('Onboarding')}}"><button style=" font-family: 'Inter'; border: none; background-color: #5c4ac7; color: white; padding: 2vh 5vw; border-radius: 1vh; font-size: 2vh; cursor: pointer; ">
      Done
      </button></a>
   </div>
</div>
</body>
<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.14.3/dist/umd/popper.min.js" integrity="sha384-ZMP7rVo3mIykV+2+9J3UJ46jBk0WLaUAdn689aCwoqbBJiSnjAK/l8WvCWPIPm49" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.1.3/dist/js/bootstrap.min.js" integrity="sha384-ChfqqxuZUCnJSK3+MXmPNIyE6ZbWh2IMqE241rYiqJxyMiZ6OW/JmZQ5stwEULTy" crossorigin="anonymous"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="{{asset('/transport_onboard/JavaScript/Transportation.js')}}"></script>
<script>
   $(document).ready(function(){
       // for add driver and conductor 
       $('.driverOptions').on('click',function(){
           var getNumber = $(this).text();
           $('#driverSpan').empty();
           $('#driverSpan').text(getNumber);
           $('.driverOptions').removeClass('selected-option');
           $(this).toggleClass('selected-option');
       })
        // for add vehicle
       $('.vehicleOptions').on('click',function(){
           var getNumber = $(this).text();
           $('#vehicleSpan').empty();
           $('#vehicleSpan').text(getNumber);
           $('.vehicleOptions').removeClass('selected-option');
           $(this).toggleClass('selected-option');
       })
       // select vehicle type
       
       $('.vehicle-choice').on('click',function(){
           var getVehicle = $(this).attr('data-val');
           $('#vehicleType').val(getVehicle);
           $('.vehicle-choice').removeClass('selected-vehicle-choice');
           $(this).toggleClass('selected-vehicle-choice');
       })
   });
   
   function addDriver(){
       var first_name = $('#first_name').val();
       var last_name = $('#last_name').val();
       var mobile = $('#mobile').val();
       var icard_icon = $('#icard_icon')[0].files[0];
       var driver_type = $('#driver_type').val();
       var status = $('#status').val();
       var formData = new FormData();
        formData.append('type', 'API');
        formData.append('first_name', first_name);
        formData.append('last_name', last_name);
        formData.append('mobile', mobile);
        formData.append('icard_icon', icard_icon); // Append the file
        formData.append('driver_type', driver_type);
        formData.append('status', status);
   
       $.ajax({
           url:"{{route('add_driver.store')}}",
           data:formData,
           type:"POST",
           contentType: false,
            processData: false, 
           success: function(response){
               if (typeof response === 'string') {
                   response = JSON.parse(response);
               }
               alert(response.message);
           },
           error: function(xhr, status, error) {
               console.error(xhr.responseText);
               alert('Error adding Driver. Please check the console for details.');
           }
       })
   }
   
   function addVehicle(){
       var title=$('#vehicle_name').val();
       var vehicle_number=$('#vehicle_number').val();
       var sitting_capacity=$('#sitting_capacity').val();
       var school_shift=$('#school_shift').val();
       var driver=$('#driver').val();
       var conductor=$('#conductor').val();
       var vehicle_type=$('#vehicleType').val();
       var vehicle_identity_number=$('#vehicle_identity_number').val();
       var sub_institute_id = "{{$data['sub_institute_id']}}";
   
       if(vehicle_type===''){
           alert('Please one vehicle from above image');
       }else{
   
       var formData = {type:"API",title:title,vehicle_number:vehicle_number,sitting_capacity:sitting_capacity,school_shift:school_shift,driver:driver,conductor:conductor,vehicle_type:vehicle_type,vehicle_identity_number:vehicle_identity_number,sub_institute_id:sub_institute_id};
       
           $.ajax({
               url:"{{route('add_vehicle.store')}}",
               data:formData,
               type:"POST",
               success: function(response){
                   if (typeof response === 'string') {
                       response = JSON.parse(response);
                   }
                   alert(response.message);
               },
               error: function(xhr, status, error) {
                   console.error(xhr.responseText);
                   alert('Error adding Driver. Please check the console for details.');
               }
           })
       }
   }
   function addRoute(){
    var route_name=$('#route_name').val();
    var from_time=$('#from_time').val();
    var to_time=$('#to_time').val();

    var formData = {type:"API",route_name:route_name,from_time:from_time,to_time:to_time};

        $.ajax({
            url:"{{route('add_route.store')}}",
            data:formData,
            type:"POST",
            success: function(response){
                if (typeof response === 'string') {
                    response = JSON.parse(response);
                }
                alert(response.message);
            },
            error: function(xhr, status, error) {
                console.error(xhr.responseText);
                alert('Error adding Driver. Please check the console for details.');
            }
        })
   }

   function addStop(){
    var stop_name=$('#stop_name').val();
    $.ajax({
            url:"{{route('add_stop.store')}}",
            data:{type:"API",stop_name:stop_name},
            type:"POST",
            success: function(response){
                if (typeof response === 'string') {
                    response = JSON.parse(response);
                }
                alert(response.message);
            },
            error: function(xhr, status, error) {
                console.error(xhr.responseText);
                alert('Error adding Driver. Please check the console for details.');
            }
        })
   }
</script>
</html>