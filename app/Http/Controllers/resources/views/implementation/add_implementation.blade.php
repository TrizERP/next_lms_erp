@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Add Implementation</h4> </div>
        </div>
        <div class="row" style=" margin-top: 25px;">
            <div class="white-box">
            <div class="panel-body">
				<!-- @TODO: Create a saperate tmplate for messages and include in all tempate -->
                @if ($message = Session::get('success'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                </div>
                @endif
                @php
                    if(isset($data['implementation_data']))
                    {
                        $implementation_data = $data['implementation_data'];
                    }
                    if(isset($data['standard_data']))
                    {
                     $standard_data = $data['standard_data'];
                    }
                @endphp
                <div class="col-lg-12 col-sm-12 col-xs-12">
                     <form name="myform" action="{{ route('add_implementation.store') }}" method="post">


                        {{ method_field("POST") }}

                        {{csrf_field()}}
                          @csrf

                        <div class="col-md-4 form-group">
                            <label>Total Boys</label>
                            <input type="text" id='total_boys' value="@if(isset($data['total_boys'])){{ $data['total_boys'] }}@endif" required name="total_boys" class="form-control" onkeyup="total_student()">
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Total Girls</label>
                            <input type="text" id='total_girls' value="@if(isset($data['total_girls'])){{ $data['total_girls'] }}@endif" required name="total_girls" class="form-control" onkeyup="total_student()">
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Total Strength</label>
                            <input type="text" id='total_strenght' value="@if(isset($data['total_strenght'])){{ $data['total_strenght'] }}@endif" name="total_strenght" class="form-control" readonly="readonly">
                        </div>

                        <div class="col-md-6 form-group">
                            <label>Total Male Staff</label>
                            <input type="text" id='total_male' value="@if(isset($data['total_male'])){{ $data['total_male'] }}@endif" required name="total_male" class="form-control">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Total Female Staff</label>
                            <input type="text" id='total_female' value="@if(isset($data['total_female'])){{ $data['total_female'] }}@endif" required name="total_female" class="form-control">
                        </div>

                            <div class="col-md-3 form-group">
                                <label>Standard wise Strength</label>
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Total Boys</label>
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Total Girls</label>
                            </div>
                             <div class="col-md-3 form-group">
                                <label>Total Strength</label>
                            </div>


                          @foreach($standard_data as $key => $value)
                            <div class="col-md-3">
                                <lable>{{ $value['name'] }}</lable>
                                <input type="hidden" id='standard_id' name="standard_id[]" value="{{ $value['id'] }}">
                            </div>
                            <div class="col-md-3 form-group">
                                <input type="text" id='std_wise_total_boys' value="@if(isset($implementation_data[$value['id']]['std_wise_total_boys'])){{ $implementation_data[$value['id']]['std_wise_total_boys'] }}@endif" required name="std_wise_total_boys[]" class="form-control std_wise_total_boys" onChange="total_stu_std_wise()">
                            </div>
                            <div class="col-md-3 form-group">
                                <input type="text" id='std_wise_total_girls' value="@if(isset($implementation_data[$value['id']]['std_wise_total_girls'])){{ $implementation_data[$value['id']]['std_wise_total_girls'] }}@endif" name="std_wise_total_girls[]" class="form-control std_wise_total_girls" onChange="total_stu_std_wise()">
                            </div>
                             <div class="col-md-3 form-group">
                                <input type="text" value="@if(isset($implementation_data[$value['id']]['std_wise_total'])){{ $implementation_data[$value['id']]['std_wise_total'] }}@endif" name="std_wise_total[]" class="form-control std_wise_total" >
                            </div>
                        @endforeach

                            <!-- <div class="col-md-3 form-group">
                                <label>Total</label>
                            </div>
                            <div class="col-md-3 form-group">
                                <input type="text" id='final_std_total_boys' value="@if(isset($data['final_std_total_boys'])){{ $data['final_std_total_boys'] }}@endif" name="final_std_total_boys" class="form-control" >
                            </div>
                            <div class="col-md-3 form-group">
                                <input type="text" id='final_std_total_girls' value="@if(isset($data['final_std_total_girls'])){{ $data['final_std_total_girls'] }}@endif" name="final_std_total_girls" class="form-control" >
                            </div>
                             <div class="col-md-3 form-group">
                                <input type="text" id='final_std_total' value="@if(isset($data['final_std_total'])){{ $data['final_std_total'] }}@endif" name="final_std_total" class="form-control" >
                            </div> -->

                        <div class="col-md-12 form-group">

                            @if(isset($data['isImplementation']))
                                <input type="hidden" name="isImplementation" value="{{ $data['isImplementation'] }}">
                            @endif
                            <input type="submit" name="submit" value="Save" class="btn btn-success" >
                        </div>
                    </form>
            </div>
            </div>
        </div>
    </div>
</div>

@include('includes.footerJs')
<script src="../../../admin_dep/js/cbpFWTabs.js"></script>
<script type="text/javascript">
function total_student()
  {
    var elm = document.forms["myform"];

    if (elm["total_boys"].value !== "" && elm["total_girls"].value !== "")
    {
        elm["total_strenght"].value = parseInt(elm["total_boys"].value) + parseInt(elm["total_girls"].value);
    }
  }

  function total_stu_std_wise()
  {
      //alert('hey');
      var result = 0;

       var txtStdWiseBoysValue = document.getElementsByClassName("std_wise_total_boys");
       var txtStdWiseGirlsValue = document.getElementsByClassName("std_wise_total_girls");

       for(var i=0;i<txtStdWiseBoysValue.length;i++)
        {
            if (txtStdWiseBoysValue[i].value !== "" && txtStdWiseGirlsValue[i].value === "")
            {
                result = parseInt(txtStdWiseBoysValue[i].value);
            }else if(txtStdWiseBoysValue[i].value === "" && txtStdWiseGirlsValue[i].value !== ""){
                result= parseInt(txtStdWiseGirlsValue[i].value);
            }else if (txtStdWiseGirlsValue[i].value !== "" && txtStdWiseBoysValue[i].value !== ""){
                result = parseInt(txtStdWiseBoysValue[i].value) + parseInt(txtStdWiseGirlsValue[i].value);
            }
            //alert(result);
            if (!isNaN(result)) {
                 var a = document.getElementsByClassName('std_wise_total');
                 a[i].value = result;
            }
        }

  }

</script>

<script>
    function getUsername(){
        var first_name = document.getElementById("first_name").value;
        var last_name = document.getElementById("last_name").value;
        var username = first_name.toLowerCase()+"_"+last_name.toLowerCase();
        document.getElementById("user_name").value = username;
    }
</script>
@include('includes.footer')
