{{--@include('../includes.headcss')
    <!-- <link rel="stylesheet" href="../../../tooltip/enjoyhint/jquery.enjoyhint.css"> -->
@include('../includes.header')
@include('../includes.sideNavigation')--}}
@extends('layout')
@section('container')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Add Fees Structure</h4>
            </div>
        </div>
        <div class="card">
            <div class="row">
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('fees_breackoff.store') }}" enctype="multipart/form-data" method="post">
                    {{ method_field("POST") }}
                    @csrf
                      <div class="row">
                        <div class="col-md-12 form-group">
                            <div class="row">
                              {{ App\Helpers\SearchChain('4','multiple','grade,std,div') }}
                            </div>
                        </div>
                        @foreach ($data['data']['ddMonth'] as $id => $val)
                          <div class="col-md-3 form-group month-option">
                              <input class="monthclass" name="month[{{$id}}]" value="{{$id}}" type="checkbox">
                              <label>{{$val}}</label>
                          </div>
                        @endforeach
                        <div class="col-md-12 form-group">
                          <center>
                            <input type="submit" name="submit" value="Add Fees Structure" class="btn btn-success"><!--onclick="return check_validation();" -->
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
@if(Session::get('erpTour')['fees_structure']==0)
        <!-- <script src="../../../tooltip/bower_components/todomvc-common/base.js"></script> -->
        <!-- <script src="../../../tooltip/bower_components/jquery/jquery.js"></script> -->
        <!-- <script src="../../../tooltip/bower_components/underscore/underscore.js"></script>
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
        <script src="../../../tooltip/enjoyhint/kinetic.min.js"></script> -->

   <!--  <script>
      localStorage.clear();
      var enjoyhint_script_data = [
        {
            onBeforeStart: function(){
            $('#grade').change(function(e){

                enjoyhint_instance.trigger('new_todo');

            });
          },
          selector:'#grade',
          event:'new_todo',
          event_type:'custom',
          description:'Select grade here.'
        },
        {
            onBeforeStart: function(){
            $('#standard').change(function(e){

                enjoyhint_instance.trigger('new_todo');

            });
          },
          selector:'#standard',
          event:'new_todo',
          event_type:'custom',
          description:'Select standard here.'
        },
        {
          selector:'.col-md-3',
          event:'click',
          description:'Check if month fees is mandatory.',
          timeout:100
        },
        {
          selector:'.btn-success',
          event:'click',
          description:'Please press save to add new Breakoff.',
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
        var url = "http://dev.triz.co.in/tourUpdate?module=fees_structure";
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
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $("#division").parent('.form-group').hide();
    $("#grade").attr('required', true);
    $("#standard").attr('required', true);
</script>
@if(app('request')->input('implementation') == 1)
<script type="text/javascript">
    document.body.className = document.body.className.replace("fix-header", "fix-header show-sidebar hide-sidebar");
    document.getElementById('main-header').style.display = 'none';
</script>
@endif

<script type="text/javascript">
    // function check_validation()
    // {
    //     var grade = $("#grade").val();
    //     var standard = $("#standard").val();

    //     var month_values_arr = new Array();
    //     $('.monthclass:checkbox:checked').each(function() {
    //         month_values_arr.push($(this).val());
    //     });

    //     var month_values = month_values_arr.join(",");

    //     var path = "{{ route('ajax_checkFeesStructure') }}";

    //     var return_first = function () {
    //     var status = "0";
    //     $.ajax({
    //         'async': false,
    //         url:path,
    //         data:"grade="+grade+"&standard="+standard+"&month_values="+month_values,
    //         success:function(result)
    //         {
    //             var existing_months = "";
    //             for(var i=0;i < result.length;i++)
    //             {
    //                 existing_months = existing_months + result[i]['Month'];
    //             }

    //             existing_months = existing_months.slice(0, -1);
    //             status = "1";
    //             alert("Fees Structure already exist for month - " + existing_months);
    //         }
    //     });
    //     return status;
    //     }();

    //     if(return_first == "1")
    //     {
    //         return false;
    //     }
    //     else
    //     {
    //         return true;
    //     }
    // }
</script>
@include('includes.footer')
@endsection
