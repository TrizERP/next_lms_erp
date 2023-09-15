@include('includes.headcss')
<link rel="stylesheet" href="../../../plugins/bower_components/dropify/dist/css/dropify.min.css">
<link rel="stylesheet" href="../../../tooltip/enjoyhint/jquery.enjoyhint.css">
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Add Fees Receipt Book</h4> 
            </div>
        </div>
        <div class="card">
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
            <div class="row">                
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('fees_receipt_book_master.store') }}" enctype="multipart/form-data" method="post">
                    {{ method_field("POST") }}
                    @csrf
                        <div class="row">                        
                            <div class="col-md-4 form-group">
                                <label>Receipt Line 1 </label>
                                <input type="text" id='receipt_line_1' required name="receipt_line_1" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Receipt Line 2 </label>
                                <input type="text" id='receipt_line_2' required name="receipt_line_2" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Receipt Line 3 </label>
                                <input type="text" id='receipt_line_3'  name="receipt_line_3" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Receipt Line 4 </label>
                                <input type="text" id='receipt_line_4'  name="receipt_line_4" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Receipt Prefix </label>
                                <input type="text" id='receipt_prefix'  name="receipt_prefix" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Receipt Postfix </label>
                                <input type="text" id='receipt_postfix'  name="receipt_postfix" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Account Number </label>
                                <input type="text" id='account_number' name="account_number" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Sort Order </label>
                                <input type="text" id='sort_order' name="sort_order" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Last Receipt Number </label>
                                <input type="number" id='last_receipt_number'  name="last_receipt_number" class="form-control" value="0">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Pan </label>
                                <input type="text" id='pan'  name="pan" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Bank Branch </label>
                                <input type="text" id='branch'  name="branch" class="form-control">
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Bank Logo </label>
                                <input type="file" accept="image/*" name="fees_bank_logo" id="input-file-now" class="dropify" />
                            </div>
                            <div class="col-md-4 form-group" hidden="hidden">
                                <label>Receipt Id </label>
                                <input type="hidden" id='receipt_id' value="@if(isset($receipt_id)){{ $receipt_id }}@endif" name="receipt_id" class="form-control">
                            </div>
                            {{ App\Helpers\SearchChain('4','multiple','grade,std') }}
                            <div class="col-md-4 form-group">
                                <label>Fees Head</label>
                                <select name="fees_head_id[]" id="fees_head_id" class="form-control" required multiple>
                                    @foreach($feeHeadList as $key => $value)
                                        <option value="{{$value['id']}}">{{$value['display_name']}}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 form-group" id="imagelogo">
                                <label for="input-file-now">Receipt Logo</label>
                                <input type="file" accept="image/*" name="fees_receipt_logo" id="input-file-now" class="dropify" />
                            </div>
                            <div class="col-md-12 form-group">
                                <center>                                    
                                    <input type="submit" name="submit" value="Save" class="btn btn-success" >
                                </center>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>    
</div>

@include('includes.footerJs')
@if(Session::get('erpTour')['fees_receipt']==0)
        <script src="../../../tooltip/bower_components/todomvc-common/base.js"></script>
        <!-- <script src="../../../tooltip/bower_components/jquery/jquery.js"></script> -->
        <script src="../../../tooltip/bower_components/underscore/underscore.js"></script>
        <script src="../../../tooltip/bower_components/backbone/backbone.js"></script>
        <script src="../../../tooltip/bower_components/backbone.localStorage/backbone.localStorage.js"></script>
        <script src="../../../tooltip/js/models/todo.js"></script>
        <script src="../../../tooltip/js/collections/todos.js"></script>
        <script src="../../../tooltip/js/views/todo-view.js"></script>
        <script src="../../../tooltip/js/views/app-view.js"></script>
        <script src="../../../tooltip/js/routers/router.js"></script>
        <script src="../../../tooltip/js/app.js"></script>
        <script src="../../../tooltip/enjoyhint/enjoyhint.js"></script>
        <script src="../../../tooltip/enjoyhint/jquery.enjoyhint.js"></script>
        <script src="../../../tooltip/enjoyhint/kinetic.min.js"></script>

    <!-- <script>
      localStorage.clear();
      var enjoyhint_script_data = [
        {
            onBeforeStart: function(){
            $('#receipt_line_1').change(function(e){

                enjoyhint_instance.trigger('new_todo');

            });
          },
          selector:'#receipt_line_1',
          event:'new_todo',
          event_type:'custom',
          description:'Enter first line of receipt here.'
        },
        {
            onBeforeStart: function(){
            $('#receipt_line_2').change(function(e){

                enjoyhint_instance.trigger('new_todo');

            });
          },
          selector:'#receipt_line_2',
          event:'new_todo',
          event_type:'custom',
          description:'Enter second line of receipt here.'
        },
        {
          selector:'#grade',
          event:'click',
          description:'Please select grade.',
          timeout:100
        },
        {
          selector:'#standard',
          event:'click',
          description:'Please select standard.',
          timeout:100
        },
        {
          selector:'#fees_head_id',
          event:'click',
          description:'Please select fees head.',
          timeout:100
        },
        {
          selector:'#input-file-now',
          event:'click',
          description:'Please select Image.',
          timeout:100
        },
        {
          selector:'.btn-success',
          event:'click',
          description:'Please press save to add new Breackoff.',
          timeout:100
        }
      ];
      var enjoyhint_instance = null;
      $(document).ready(function(){
        enjoyhint_instance = new EnjoyHint({});
        enjoyhint_instance.setScript(enjoyhint_script_data);
        enjoyhint_instance.runScript();
      });
    </script> -->

    <script type="text/javascript">
        var url = "http://dev.triz.co.in/tourUpdate?module=fees_receipt";
        var xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function() {
            if (this.readyState == 4 && this.status == 200) {
              console.log("success");
            }
        };
        xhttp.open("GET", url, true);
        xhttp.send();
    </script>
@endif

<script src="../../../plugins/bower_components/dropify/dist/js/dropify.min.js"></script>
<script>
$(document).ready(function() {
    // Basic
    $('.dropify').dropify();
    // Translated
    $('.dropify-fr').dropify({
        messages: {
            default: 'Glissez-déposez un fichier ici ou cliquez',
            replace: 'Glissez-déposez un fichier ou cliquez pour remplacer',
            remove: 'Supprimer',
            error: 'Désolé, le fichier trop volumineux'
        }
    });
    // Used events
    var drEvent = $('#input-file-events').dropify();
    drEvent.on('dropify.beforeClear', function(event, element) {
        return confirm("Do you really want to delete \"" + element.file.name + "\" ?");
    });
    drEvent.on('dropify.afterClear', function(event, element) {
        alert('File deleted');
    });
    drEvent.on('dropify.errors', function(event, element) {
        console.log('Has Errors');
    });
    var drDestroy = $('#input-file-to-destroy').dropify();
    drDestroy = drDestroy.data('dropify')
    $('#toggleDropify').on('click', function(e) {
        e.preventDefault();
        if (drDestroy.isDropified()) {
            drDestroy.destroy();
        } else {
            drDestroy.init();
        }
    })
});
</script>
@if(app('request')->input('implementation') == 1)
<script type="text/javascript">
    document.body.className = document.body.className.replace("fix-header", "fix-header show-sidebar hide-sidebar");
    document.getElementById('main-header').style.display = 'none';
</script>
@endif
@include('includes.footer')
