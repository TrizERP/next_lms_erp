@include('includes.headcss')
<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
<link rel="stylesheet" href="/resources/demos/style.css">
<style>
#sortable { list-style-type: none; margin: 0; padding: 0; width: 60%; }
#sortable li { margin: 0 3px 3px 3px; padding: 20px; padding-left: 1.5em; font-size: 1.4em; height: 46px; }
#sortable li span {margin-left: -1.3em; }

.multiselect {
  width: 350px;
}

.selectBox {
  position: relative;
}

.selectBox select {
  width: 100%;
  font-weight: bold;
}

.overSelect {
  position: absolute;
  left: 0;
  right: 0;
  top: 0;
  bottom: 0;
}

#checkboxes { 
  border: 1px #dadada solid;
}

#checkboxes label {
  display: block;
}

#checkboxes label:hover {
  background-color: #1e90ff;
}
</style>
  
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Dashboard Setting</h4>
            </div>
        </div>
        <div class="card">                                                                                                              
            <!-- <ul id="sortable">
              <li class="ui-state-default" id="item-1"><span class="ui-icon ui-icon-arrowthick-2-n-s"></span>Item 1</li>
              <li class="ui-state-default" id="item-2"><span class="ui-icon ui-icon-arrowthick-2-n-s"></span>Item 2</li>
              <li class="ui-state-default" id="item-3"><span class="ui-icon ui-icon-arrowthick-2-n-s"></span>Item 3</li>
              <li class="ui-state-default" id="item-4"><span class="ui-icon ui-icon-arrowthick-2-n-s"></span>Item 4</li>
              <li class="ui-state-default" id="item-5"><span class="ui-icon ui-icon-arrowthick-2-n-s"></span>Item 5</li>
              <li class="ui-state-default" id="item-6"><span class="ui-icon ui-icon-arrowthick-2-n-s"></span>Item 6</li>
              <li class="ui-state-default" id="item-7"><span class="ui-icon ui-icon-arrowthick-2-n-s"></span>Item 7</li>
            </ul>    -->                                       
       
            <div class="row">
              <div class="col-md-3">
                  <div class="multiselect">
                      <div class="selectBox form-group" onclick="showCheckboxes()">
                        <select class="form-control" style="background-color: #25bdea !important;color: white;">
                          <option>Select Dashboard Boxes</option>
                        </select>
                        <div class="overSelect"></div>
                      </div>
                      
                        @php
                        $total_usermenu = (count($data['final_userMenu']) + 2);                        
                        @endphp
                        @if(isset($data['final_dynamic_dashboard']) && count($data['final_dynamic_dashboard']) > 0)
                            <div id="checkboxes" class="p-3 rounded">
                            @foreach($data['final_dynamic_dashboard'] as $menukey => $menuval)
                                @php                        
                                $checked = "";
                                if(in_array($menukey,$data['final_userMenu']))
                                {
                                    $checked = "checked";
                                }                    
                                @endphp
                              <div class="custom-control custom-checkbox mb-2">
                                <input {{$checked}} type="checkbox" value="{{$menukey}}" data-title="{{$menuval}}" id="{{$menuval}}" class="custom-control-input" onclick="save_menu(this);" /> 
                                <label for="{{$menuval}}" class="custom-control-label pt-1">{{$menuval}}</label>
                              </div>
                            @endforeach
                            </div>
                        @else
                          <span class="text-danger font-weight-bold ml-4">Please Contact Administrator For ERP Rights</span>
                        @endif                        
                  </div>
                  <input type="hidden" id="hid_total_usermenu" name="hid_total_usermenu" value="{{$total_usermenu}}">
              </div>
        </div> 
      </div>           
    </div>
</div>

@include('includes.footerJs')
  <script src="https://code.jquery.com/jquery-1.12.4.js"></script>
  <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
  <script>
  // $( function() {
  //   $( "#sortable" ).sortable({
  //      axis: 'y',
  //       stop: function (event, ui) {
  //         var data1 = $(this).sortable('serialize');   
          
  //         var path = "{{ route('ajax_SaveDynamicDashboardMenu') }}";          
  //         $.ajax({url: path,data:'data1='+data1});          
  //       }
  //   });

  //   //$( "#sortable" ).disableSelection();  
  
  // });
  </script>

  <script type="text/javascript">
//START Dynamic Dashboard
    var expanded = false;

    function showCheckboxes() {
      var checkboxes = document.getElementById("checkboxes");
      if (!expanded) {
        checkboxes.style.display = "block";
        expanded = true;
      } else {
        checkboxes.style.display = "none";
        expanded = false;
      }
    }
    function save_menu(x)
    {
        var ch = $( x ).prop( "checked" );                
        var total_usermenu = $("#hid_total_usermenu").val();
        
        if(total_usermenu == 10 && ch == true)
        {
            alert("Maximum 8 Menus are allowed");
            $( x ).prop( "checked", false );
        }
        else
        {
            var menu_id = x.value;
            
            var title = $( x ).attr( "data-title" );                       
            var path = "{{ route('ajax_SaveDynamicDashboardMenu') }}";
            
            $.ajax({
              url: path,
              data:'menu_id='+menu_id+"&checked="+ch+"&title="+title,
              success:function(result){   
                alert("Dashboard Setting Saved.");
                $("#hid_total_usermenu").val(result);
              }
            });
        }
    }
    //END Dynamic Dashboard
</script>


@include('includes.footer')