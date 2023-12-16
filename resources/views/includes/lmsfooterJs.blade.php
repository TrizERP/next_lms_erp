@include('includes.rightsideNavigation')
<footer class="footer text-center"> {{date('Y')}} &copy; Triz Innovation PVT LTD. <a href="{{route('siteMap')}}"> Site Map </a> </footer>
<!-- <footer class="footer text-center"> {{date('Y')}} &copy; Triz Innovation PVT LTD. <a href="{{route('siteMap')}}"> Site Map </a> </footer> -->
</div>
<div class="help-guide">
  <div class="help-head">
    <div class="guide-title">Help Guide</div>
    <div class="dropdown">
        <button class="dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown"
                aria-haspopup="true" aria-expanded="false">
            <!-- <i class="mdi mdi-dots-vertical"></i> -->
        </button>
        <!--  <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
           <a class="dropdown-item" href="#">Action</a>
           <a class="dropdown-item" href="#">Another action</a>
           <a class="dropdown-item" href="#">Something else here</a>
         </div> -->
    </div>
      <div class="help-arraw">
          <i class="mdi mdi-chevron-down"></i>
      </div>
  </div>
    <div class="help-body" style="display:none;">
        <div class="w-auto gutter-10 main-nav justify-content-center">
            <div class="row">
                <div class="col-md-6">
                    <div class="help-box">
                        <a id="pdf_link" target="_blank" class="nav-link pb-0">
                            <span class="menu-main-icon"><i class="mdi mdi-file-pdf md-36"></i></span> PDF
                        </a>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="help-box">
                        <a id="youtube_link" target="_blank" class="nav-link pb-0">
              <span class="menu-main-icon"><i class="mdi mdi-youtube md-36"></i></span> Youtube
            </a>
          </div>
        </div>
       <!--  <div class="col-md-4">
          <div class="help-box">
            <a href="#" class="nav-link pb-0">
              <span class="menu-main-icon"><i class="mdi mdi-information-variant md-36"></i></span> FAQs
            </a>
          </div>
        </div> -->
        <!-- <div class="col-md-4">
          <div class="help-box">
            <a href="#" class="nav-link pb-0" data-toggle="modal" data-target="#chatModal">
              <span class="menu-main-icon"><i class="mdi mdi-chat-outline md-36"></i></span> Quick Chat
            </a>
          </div>
        </div> -->
        <div class="col-6 col-md-6">
          <div class="help-box">
            <a href="#" class="nav-link pb-0" data-toggle="modal" data-target="#emailModal">
              <span class="menu-main-icon"><i class="mdi mdi-email-outline md-36"></i></span> Email
            </a>
          </div>
        </div>
                <div class="col-6 col-md-6">
                    <div class="help-box">
                        <a href="http://apps.triz.co.in/crm/" class="nav-link pb-0" target="_blank">
                            <span class="menu-main-icon"><i class="mdi mdi-clipboard-account md-36"></i></span> TTMS
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Email Modal -->
<div class="modal fade" id="emailModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Email</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">x</span>
                </button>
      </div>
      <form name="sendmail" id="sendmail" action="{{ route('ajax_sendmail') }}" method="post">
        {{ method_field('POST') }}
        @csrf
        <div class="modal-body">
          <div class="form-group">
            <label for="name">Name :</label>
            <input type="text" placeholder="Name" class="form-control" id="name" name="name">
          </div>
          <div class="form-group">
            <label for="email">Email :</label>
            <input type="email" placeholder="Email" class="form-control" id="email" name="email">
          </div>
          <div class="form-group">
            <label for="subject">Subject :</label>
            <input type="text" placeholder="Subject" class="form-control" id="subject" name="subject">
          </div>
          <div class="form-group">
            <label for="message">Message :</label>
            <textarea id="message" name="message" class="form-control"></textarea>
          </div>
          <div class="form-group text-center">
            <input type="submit" class="btn btn-primary" name="submit" value="Submit">
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Chat Modal -->
<div class="modal fade" id="chatModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
          <div class="modal-header">
              <h5 class="modal-title" id="exampleModalLabel">Chat</h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">x</span>
              </button>
          </div>
          <div class="modal-body">
              <div class="jumbotron m-0 p-0 bg-transparent">
                  <div class="row m-0 p-0">
                      <div class="col-12 p-0 m-0" style="right: 0px;">
                          <div class="card bg-sohbet border-0 m-0 p-0" style="height: 100vh;">
                              <div id="sohbet" class="card border-0 m-0 p-0 position-relative bg-transparent"
                                   style="overflow-y: auto; height: 100vh;">
                                  <div class="balon1 p-2 m-0 position-relative" data-is="You - 3:20 pm">
                                      <a class="float-right"> Hey there! What's up? </a>
                                  </div>

                                  <div class="balon2 p-2 m-0 position-relative" data-is="Yusuf - 3:22 pm">
                                      <a class="float-left sohbet2"> Checking out iOS7 you know.. </a>
                                  </div>
                                  <div class="balon1 p-2 m-0 position-relative" data-is="You - 3:23 pm">
                                      <a class="float-right"> Check out this bubble! </a>
                                  </div>
                                  <div class="balon2 p-2 m-0 position-relative" data-is="Yusuf - 3:26 pm">
                                      <a class="float-left sohbet2"> It's pretty cool! </a>
                                  </div>
                                  <div class="balon1 p-2 m-0 position-relative" data-is="You - 3:28 pm">
                                      <a class="float-right"> Yeah it's pure CSS & HTML </a>
                                  </div>
                                  <div class="balon2 p-2 m-0 position-relative" data-is="Yusuf - 3:33 pm">
                                      <a class="float-left sohbet2"> Wow that's impressive. But what's even more
                                          impressive is that this bubble is really high. </a>
                                  </div>
                              </div>
                          </div>

                          <div
                              class="w-100 card-footer p-0 bg-light border border-bottom-0 border-left-0 border-right-0">
                              <form class="m-0 p-0 pt-4" action="" method="POST" autocomplete="off">
                                  @csrf
                                  <div class="row m-0 p-0">
                                      <div class="col-9 m-0 p-1">
                                          <input id="text" class="mw-100 border rounded form-control mb-0" type="text"
                                                 name="text" title="Type a message..." placeholder="Type a message..."
                                                 required>
                                      </div>
                                      <div class="col-3 m-0 p-1">
                                          <button class="btn btn-outline-secondary rounded border w-100 mb-0 h-100"
                                                  title="Gönder!"><i class="fa fa-paper-plane" aria-hidden="true"></i>
                                          </button>
                                      </div>
                                  </div>
                              </form>
                          </div>
                      </div>
                  </div>

              </div>
          </div>
      </div>
  </div>
</div>
<div id="loading-overlay" style="display:none;">
<center>
  <img src="/admin_dep/images/loader-man.gif" id="loading-gif" alt="loading-gif" >
  </center>
    </div>
<script>
        window.addEventListener('beforeunload', function() {
            $('#loading-overlay').show();
            setTimeout(() => {
                $('#loading-overlay').hide();
            },3000)
        });
</script>
<!-- /#wrapper -->
<!-- jQuery -->
<script src="{{ asset("/admin_dep/js/jquery-3.5.1.min.js") }}"></script>
<script src="{{ asset("/admin_dep/js/popper.min.js") }}"></script>
<script src="{{ asset("/admin_dep/js/bootstrap.min.js") }}"></script>
<script src="{{ asset("/admin_dep/js/bootstrap-select.min.js") }}"></script>
<script src="{{ asset("/admin_dep/js/lms-custom.js") }}"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>


<!-- <script src="{{ asset("/plugins/bower_components/jquery/dist/jquery.min.js") }}"></script> -->
<!-- Bootstrap Core JavaScript -->
<!-- <script src="{{ asset("/admin_dep/bootstrap/dist/js/bootstrap.min.js") }}"></script> -->
<!-- Menu Plugin JavaScript -->
<!-- <script src="{{ asset("/plugins/bower_components/sidebar-nav/dist/sidebar-nav.min.js") }}"></script> -->
<!--slimscroll JavaScript -->
<!-- <script src="{{ asset("/admin_dep/js/jquery.slimscroll.js") }}"></script> -->
<!--Wave Effects -->
<!-- <script src="{{ asset("/admin_dep/js/waves.js") }}"></script> -->
<!-- chartist chart -->
<script src="{{ asset("/plugins/bower_components/chartist-js/dist/chartist.min.js") }}"></script>
<script src="{{ asset("/plugins/bower_components/chartist-plugin-tooltip-master/dist/chartist-plugin-tooltip.min.js") }}"></script>
<!-- Sparkline chart JavaScript -->
<script src="{{ asset("/plugins/bower_components/jquery-sparkline/jquery.sparkline.min.js") }}"></script>
<!-- Custom Theme JavaScript -->
<!-- <script src="{{ asset("/admin_dep/js/custom.min.js") }}"></script> -->
<!-- <script src="{{ asset("/admin_dep/js/dashboard1.js") }}"></script> -->

<!--Style Switcher -->
<!-- <script src="{{ asset("/plugins/bower_components/styleswitcher/jQuery.style.switcher.js") }}"></script> -->

<script src="{{ asset("/plugins/bower_components/jquery.easy-pie-chart/dist/jquery.easypiechart.min.js") }}"></script>
<script src="{{ asset("plugins/bower_components/jquery.easy-pie-chart/easy-pie-chart.init.js") }}"></script>
<!-- <script src="{{ asset("plugins/bower_components/toast-master/js/jquery.toast.js") }}"></script> -->
<script src="{{ asset("plugins/bower_components/bootstrap-datepicker/bootstrap-datepicker.min.js") }}"></script>

<!--<script src="{{ asset("/admin_dep/js/sweetalert.min.js") }}"></script>-->

<script>
  //Google Analytics
  setInterval(function() {
    // var path = "{{ route('google-analytics-summary') }}";
    // $.ajax({url: path, success: function(result){
    //     var  nresult = result+" Users online";
    //     $('#google_analytics').html(nresult);
    // }
    // });

    // var  nresult = result+" Users online";
    var nresult = "1 Users online";
    $('#google_analytics').html(nresult);
  }, 3000);

  // Date Picker
  jQuery('.mydatepicker, #datepicker').datepicker({
    autoclose: true,
    format: 'yyyy-mm-dd',
    orientation: 'bottom'
  });
  jQuery('#datepicker-autoclose').datepicker({
    autoclose: true,
    todayHighlight: true
  });
  jQuery('#date-range').datepicker({
    toggleActive: true
  });
  jQuery('#datepicker-inline').datepicker({
    todayHighlight: true
  });
</script>

<!-- Clock Plugin JavaScript -->
<script src="{{ asset("plugins/bower_components/clockpicker/dist/jquery-clockpicker.min.js") }}"></script>

<script>
  // Clock pickers
  $('#single-input').clockpicker({
    placement: 'bottom',
    align: 'left',
    autoclose: true,
    'default': 'now'
  });
  $('.clockpicker').clockpicker({
    donetext: 'Done',
  }).find('input').change(function() {
    console.log(this.value);
  });
  $('#check-minutes').click(function(e) {
    // Have to stop propagation here
    e.stopPropagation();
    input.clockpicker('show').clockpicker('toggleView', 'minutes');
  });

  function confirmDelete() {
    var txt;
    var r = confirm("Are you sure ?");
    if (r == true) {
      return true;
    } else {
      return false;
    }
    //    document.getElementById("demo").innerHTML = txt;
  }
</script>


<script language="javascript">
  function printdiv(printpage) {
    var headstr = "<html><head><title></title></head><body>";
      var footstr = "</body>";
      var newstr = document.getElementById(printpage).innerHTML;
      var oldstr = document.body.innerHTML;
      document.body.innerHTML = headstr + newstr + footstr;
      window.print();
      document.body.innerHTML = oldstr;
      return false;
  }

  function sessionMenu(x) {
      // if (typeof(Storage) !== "undefined") {
      //   // Store
      //   // alert(x);
      //   localStorage.setItem("right_menu_id", x);
      //   // alert(x);
      //   // Retrieve
      // }
      var xhttp = new XMLHttpRequest();
      xhttp.onreadystatechange = function () {
          if (this.readyState == 4 && this.status == 200) {
        // alert(x);
      }
    };
    xhttp.open("GET", "{{route('ajaxMenuSession')}}?type=API&menu_id="+x, true);
    xhttp.send();
    // $('.list-unstyled  > li').click(function(){
    //   // alert('s');
    //   // alert(this);
    //   var menu_main_id = $(this).parents('.tab-pane').attr("id");
    //   // alert(menu_main_id);
      //  $(this).parents('.tab-pane').addClass('active');
      //   // $(this).parent("[aria-controls='menu-1']").addClass('active');
      // // alert('.nav-link').attr('href','#'+menu_main_id);
      // $('.nav-link').attr('href','#'+menu_main_id).addClass('active');
      //   // $('.nav-link[href="#' + menu_main_id + '"]').addClass('active');

      // });
  }

  function redirect_pages_soni(x, menu_id, main_menu_id) {
      localStorage.setItem('menu_id', menu_id);
      localStorage.setItem('main_menu_id', main_menu_id);
      window.location.replace(x);
  }

  function load_rightside_menu(x, main_menu_id) {
      $('.right-sidebar').show();
      var path = "{{ route('ajax_load_rightSideMenu') }}";

      $.ajax({
          url: path,
          data: 'menu_id=' + x,
          dataType: 'html',
          async: false,
          success: function (result) {
              // console.log(result);
              res = result.split("####");
              $("#loadRightSideMenu").html(res[0]);
              $("#loadSubMenu").html(res[1]);
          }
      });

      var path1 = "{{ route('ajax_load_helpguide') }}";

      $.ajax({
          url: path1,
          data: 'menu_id=' + x,
          dataType: 'html',
          async: false,
          success: function (links) {
              // console.log(links);
              if (links != "0") {
                  link_arr = links.split("####");
                  $("#youtube_link").attr("href", link_arr[0]);
                  $("#pdf_link").attr("href", "../../../storage/help_guide/" + link_arr[1]);
              }
          }
      });

      $("[aria-controls='menu-" + main_menu_id + "']").addClass('active');
      $("#menu-" + main_menu_id).addClass('active');

      //var tab_pane_id = $('.main-menu-block').find('.active').attr("aria-controls");
  }

  // function hideRightsideMenu(){
  //   $('.right-sidebar').show();
  // }
  // function load_rightside_menu(x)
  // {
  //   var path = "{{ route('ajax_load_rightSideMenu') }}";
  //   $.ajax({
  //     url : path,
  //     data:'menu_id='+x,
  //     success:function(result){
  //         console.log(result);
  //         var main_arr = result['Main'];
  //         var child_arr = result['Child'];

  //         $("#loadRightSideMenu").html('');
  //         $("#loadSubMenu").html('');


  //         for(var i=0,j=1;i < main_arr.length;i++,j++){
  //             inner_arr = child_arr[main_arr[i].id];
  //             var childmenus = '';
  //             // alert("<?php echo "asdasda";?>");
  //             for(var k=0;k < inner_arr.length;k++){
  //               var php_route ="<?php echo "{{ route('";?>"+inner_arr[k].link+"<?php echo "') }}";?>";
  //               console.log(php_route);
  //               childmenus = childmenus + '<li class="d-flex align-items-center"><em class="fa fa-angle-right"></em><a href="'+php_route+'" onclick="sessionMenu('+inner_arr[k].tblmenu_master_id+');" >'+inner_arr[k].name+'</a></li>';
  //             }

  //             if(i == 0){
  //               $("#loadRightSideMenu").append('<li class="nav-item" role="presentation" data-toggle="tooltip" data-placement="top" title="'+main_arr[i].name+'"><a class="nav-link active" data-toggle="tab" href="#right-tab-'+j+'" role="tab" aria-controls="right-tab-'+j+'" aria-selected="false"><img class="icon-nrml" src="http://{{$_SERVER['HTTP_HOST']}}/admin_dep/images/side-'+main_arr[i].icon+'.png" alt=""><img class="icon-hvr" src="http://{{$_SERVER['HTTP_HOST']}}/admin_dep/images/side-'+main_arr[i].icon+'-white.png" alt=""></a></li>');
  //               $("#loadSubMenu").append('<div class="tab-pane show active" id="right-tab-'+j+'" role="tabpanel"><div class="acc-panel"><div class="acc-header d-flex align-items-center"><span><em class="fa fa-angle-down"></em></span><h4 class="m-0">'+main_arr[i].name+'</h4></div><div class="acc-body" style="display: block;"><ul class="list-unstyled activity-checks">'+childmenus+'</ul><div class="activity-accordian"></div></div></div></div>');
  //             }else{
  //               $("#loadRightSideMenu").append('<li class="nav-item" role="presentation" data-toggle="tooltip" data-placement="top" title="'+main_arr[i].name+'"><a class="nav-link" data-toggle="tab" href="#right-tab-'+j+'" role="tab" aria-controls="right-tab-'+j+'" aria-selected="false"><img class="icon-nrml" src="http://{{$_SERVER['HTTP_HOST']}}/admin_dep/images/side-'+main_arr[i].icon+'.png" alt=""><img class="icon-hvr" src="http://{{$_SERVER['HTTP_HOST']}}/admin_dep/images/side-'+main_arr[i].icon+'-white.png" alt=""></a></li>');
  //               $("#loadSubMenu").append('<div class="tab-pane show" id="right-tab-'+j+'" role="tabpanel"><div class="acc-panel"><div class="acc-header d-flex align-items-center"><span><em class="fa fa-angle-down"></em></span><h4 class="m-0">'+main_arr[i].name+'</h4></div><div class="acc-body" style="display: block;"><ul class="list-unstyled activity-checks">'+childmenus+'</ul><div class="activity-accordian"></div></div></div></div>');
  //             }
  //          }
  //     }
  //   });

  // }
</script>

<script type="text/javascript">
    // function updateTour(module) {

    // //   alert('asd');

  //   var url = {{ route('tourUpdate') }} + "?module="+module;
    //   var xhttp = new XMLHttpRequest();
    //   xhttp.onreadystatechange = function() {
    //     if (this.readyState == 4 && this.status == 200) {
    //       alert("success");
    //     }
    //   };
    //   xhttp.open("GET", url, true);
    //   xhttp.send();
    // }


</script>
<script type="text/javascript">
    var options = {
        series: [{
            name: 'PRODUCT A',
            data: dataSet[0]
        }, {
            name: 'PRODUCT B',
            data: dataSet[1]
        }, {
          name: 'PRODUCT C',
          data: dataSet[2]
        }],
          chart: {
          type: 'area',
          stacked: false,
          height: 350,
          zoom: {
            enabled: false
          },
        },
        dataLabels: {
          enabled: false
        },
        markers: {
          size: 0,
        },
        fill: {
          type: 'gradient',
          gradient: {
              shadeIntensity: 1,
              inverseColors: false,
              opacityFrom: 0.45,
              opacityTo: 0.05,
              stops: [20, 100, 100, 100]
            },
        },
        yaxis: {
          labels: {
              style: {
                  colors: '#8e8da4',
              },
              offsetX: 0,
              formatter: function(val) {
                return (val / 1000000).toFixed(2);
              },
          },
          axisBorder: {
              show: false,
          },
          axisTicks: {
              show: false
          }
        },
        xaxis: {
          type: 'datetime',
          tickAmount: 8,
          min: new Date("01/01/2014").getTime(),
          max: new Date("01/20/2014").getTime(),
          labels: {
              rotate: -15,
              rotateAlways: true,
              formatter: function(val, timestamp) {
                return moment(new Date(timestamp)).format("DD MMM YYYY")
            }
          }
        },
        title: {
          text: 'Irregular Data in Time Series',
          align: 'left',
          offsetX: 14
        },
        tooltip: {
          shared: true
        },
        legend: {
          position: 'top',
          horizontalAlign: 'right',
          offsetX: -10
        }
        };

        var chart = new ApexCharts(document.querySelector("#timeline-chart"), options);
        chart.render();
    </script>

    <script type="text/javascript">
    	var options = {
          series: [{
          name: 'series1',
          data: [31, 40, 28, 51, 42, 109, 100]
        }, {
          name: 'series2',
          data: [11, 32, 45, 32, 34, 52, 41]
        }],
          chart: {
          height: 350,
          type: 'area'
        },
        dataLabels: {
          enabled: false
        },
        stroke: {
          curve: 'smooth'
        },
        xaxis: {
          type: 'datetime',
          categories: ["2018-09-19T00:00:00.000Z", "2018-09-19T01:30:00.000Z", "2018-09-19T02:30:00.000Z", "2018-09-19T03:30:00.000Z", "2018-09-19T04:30:00.000Z", "2018-09-19T05:30:00.000Z", "2018-09-19T06:30:00.000Z"]
        },
        tooltip: {
          x: {
            format: 'dd/MM/yy HH:mm'
          },
        },
        };

        var chart = new ApexCharts(document.querySelector("#splineChart"), options);
        chart.render();
    </script>
<script src="{{ asset("/admin_dep/js/ajax.js") }}"></script>


<script src="{{ asset("/plugins/bower_components/datatables/datatables.min.js") }}"></script>
<!-- start - This is for export functionality only -->
<script src="https://cdn.datatables.net/buttons/1.2.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/1.2.2/js/buttons.flash.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/2.5.0/jszip.min.js"></script>
<script src="https://cdn.rawgit.com/bpampuch/pdfmake/0.1.18/build/pdfmake.min.js"></script>
<script src="https://cdn.rawgit.com/bpampuch/pdfmake/0.1.18/build/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/1.2.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/1.2.2/js/buttons.print.min.js"></script>


<script>
    $(document).ready(function () {

        $('[data-toggle="tooltip"]').tooltip();

        //Call function for right side menu
        load_rightside_menu(localStorage.getItem('menu_id'), localStorage.getItem('main_menu_id'));

        $.extend($.fn.dataTable.defaults, {
            // dom: 'ZBflrtip',
            language: {
                oPaginate: {
                    sNext: '<i class="fa fa-angle-right" title="Next"></i>',
                    sPrevious: '<i class="fa fa-angle-left" title="Privious"></i>',
                    sFirst: '<i class="fa fa-angle-double-left" title="First"></i>',
                    sLast: '<i class="fa fa-angle-double-right" title="Last"></i>'
                },
            },
        });

        // $('.sub-drop-panel a').click(function(){
        //   var tab_pane_id = $('.main-menu-block').find('.active').attr("aria-controls");
        //   alert(tab_pane_id);
        //   $("#"+tab_pane_id).addClass('active');
        // });

    });
    // for sidebar menu entirely but not cover treeview
</script>
<!--<script src="https://f005.backblazeb2.com/file/miraibot/embed@latest.js" id="687e1c179bbf486788f11fa77d33f82f"></script>-->