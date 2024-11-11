@include('includes.headcss')
    <style type="text/css">
      .get-data,.save-template{
        visibility: hidden !important;
      }
      .clear-all{
       visibility: hidden ; 
      }
      .setbtn{
          bottom: 0 !important;
          position: absolute !important;
          right: 0 !important;
          margin: 13px !important; 
      }
    </style>

@include('includes.header')
@include('includes.sideNavigation')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Add Form Build</h4>
            </div>
        </div>
        <div class="card">
          <div class="form-group">
            <label for="exampleInputEmail1">Form Name</label>
            <input type="text" class="form-control" id="form_name" placeholder="Enter Name" required="">
            <p style="color: red;display: none;" id="error">Field Is required</p>
          </div>
  <div id="build-wrap">
    <!-- <p style="position: absolute;bottom:0px;color: red" class="error1">Field Is required</p> -->
   <div class="form-actions btn-group setbtn" style=" bottom: 0;position: absolute;right: 0;margin: 13px;z-index: 99999;">
    <button type="button" id="" class=" btn btn-danger" onclick="clearData()" style="visibility:visible;">Clear</button>
    <button type="button" id="" class="btn btn-primary " onclick="savedata()">Save</button>
    <a href="{{ route('formbuild.list') }}" class="btn btn-info ">Cancel</a>
  </div>
  </div>
  </div>
    </div>
</div>
 
@include('includes.footerJs')
    <!-- <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script> -->
    <!-- <script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.11.4/jquery-ui.min.js"></script> -->
    <script src="https://formbuilder.online/assets/js/form-builder.min.js"></script>
    <script>
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });
    
    var fbEditor = document.getElementById('build-wrap');
    var formBuilder = $(fbEditor).formBuilder();

     function savedata(){
        var formName = document.getElementById('form_name').value;
        var formName_error = document.getElementById('error');
        if(formName === ''){          
          formName_error.style.display = 'block';
          return;
        }else{
          formName_error.style.display = 'none';
        }

        var formjosndata = formBuilder.actions.getData('json');
        var formxmldata = formBuilder.actions.getData('xml');
        $.ajax({
            type:'post',
             url:'{{ route("saveformbuild") }}',
             data:{formname:formName,datajson:formjosndata, dataxml:formxmldata},
             success:function(data) {
                window.location.href = '{{ route("formbuild.list") }}';
             }
            });
     }

    function clearData(){
      $('.clear-all').click();
    }


    </script>
@include('includes.footer')