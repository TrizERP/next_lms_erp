@include('includes.rightsideNavigation')
@php 
$link = url('/');
$all_segments = request()->segments();
$url = $all_segments[0] ?? $all_segments[1];
$route = ['dashboard'];
@endphp
<footer class="footer text-center"> {{date('Y')}} &copy; Triz Innovation PVT LTD. <a href="{{route('siteMap')}}" style="color:blue;"> Site Map </a> |  <a href="{{route('privacyPolicy')}}" style="color:blue;"> Privacy Policy </a> |  <a href="{{ route('termAndCondition')}}" style="color:blue;"> Term & Condition </a> |  <a href="{{ route('otherPolicy') }}" style="color:blue;"> Other Policy </a> </footer>

</div>
<div class="help-guide">
  <div class="help-head">
    <div class="guide-title">Help Guide</div>
    <div class="dropdown">
        <button class="dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown"
                aria-haspopup="true" aria-expanded="false">
        </button>
       
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


<script src="{{ asset("/admin_dep/js/popper.min.js") }}" defer></script>
<script src="{{ asset("/admin_dep/js/custom.js") }}" ></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts" defer></script>


<script src="{{ asset("/plugins/bower_components/chartist-js/dist/chartist.min.js") }}" defer></script>
<script src="{{ asset("/plugins/bower_components/chartist-plugin-tooltip-master/dist/chartist-plugin-tooltip.min.js") }}" defer></script>
<!-- Sparkline chart JavaScript -->
<script src="{{ asset("/plugins/bower_components/jquery-sparkline/jquery.sparkline.min.js") }}" defer></script>

<script src="{{ asset("/plugins/bower_components/jquery.easy-pie-chart/dist/jquery.easypiechart.min.js") }}" defer></script>
<script src="{{ asset("plugins/bower_components/jquery.easy-pie-chart/easy-pie-chart.init.js") }}" defer></script>
<script src="{{ asset("plugins/bower_components/bootstrap-datepicker/bootstrap-datepicker.min.js") }}"></script>


<script src="https://code.jquery.com/jquery-1.10.2.js"></script>
<script src="{{ asset("/admin_dep/js/jquery-ui.js") }}" defer></script>

<script src="{{ asset("/admin_dep/js/bootstrap.min.js") }}" defer></script>
<script src="{{ asset("/admin_dep/js/bootstrap-select.min.js") }}" defer></script>

<script>
    $(document).ready(function () {
        $.ajaxSetup({
            headers:
                {'X-CSRF-TOKEN': "{{ csrf_token() }}"}
        });

        $('.mydatepicker').each(function () {
            // alert("inside onload");
            $(this).attr("placeholder", "dd-mm-yyyy");
            var selected_date = $(this).val();
            // alert(selected_date);
            if (selected_date != "" && selected_date != "0000-00-00") {
                // alert(selected_date);
                var soni = new Date(selected_date);
                // alert(soni);
                formatted_date = ("0" + (soni.getDate())).slice(-2) + "-" + ("0" + (soni.getMonth() + 1)).slice(-2) + "-" + soni.getFullYear();
                // alert(formatted_date);
                $(this).val(formatted_date);
            }
        });

        //Google Analytics
        setInterval(function () {

    // var  nresult = result+" Users online";
    var nresult = "1 Users online";
    $('#google_analytics').html(nresult);
  }, 3000);

  // Date Picker
  jQuery('.mydatepicker, #datepicker').datepicker({
    changeMonth: true,
    changeYear: true,
    yearRange: "-40:+10",
    inline: true,
    autoclose: true,
    format: 'dd-mm-yyyy',
    orientation: 'bottom',
    forceParse: false
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
    });
</script>


<script src="{{ asset("plugins/bower_components/clockpicker/dist/jquery-clockpicker.min.js") }}" defer></script>

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
   
      var xhttp = new XMLHttpRequest();
      xhttp.onreadystatechange = function () {
          if (this.readyState == 4 && this.status == 200) {
        // alert(x);
      }
    };
    xhttp.open("GET", "{{route('ajaxMenuSession')}}?type=API&menu_id="+x, true);
    xhttp.send();
   
  }
  window.addEventListener("beforeunload", function () {
  // This code will be executed just before the page is unloaded (refreshed or navigated away)
  var current_id = 1; // Replace this with the appropriate value for 'menu_id'
  var xhttp = new XMLHttpRequest();
  xhttp.open("GET", "{{ route('check_access') }}?type=API&menu_id=" + current_id, true);
  xhttp.send();
});

  function redirect_pages_soni(x, menu_id, main_menu_id,current_id) {
      
      localStorage.setItem('menu_id', menu_id);
      localStorage.setItem('main_menu_id', main_menu_id);
      localStorage.setItem('current_id', current_id);   
    
      window.location.replace(x);
   
  }

  function load_rightside_menu(menu_id, main_menu_id) {
      $('.right-sidebar').show();
      var path = "{{ route('ajax_load_rightSideMenu') }}";

      $.ajax({
          url: path,
          data: 'menu_id=' + menu_id + '&main_menu_id=' + main_menu_id,
          dataType: 'html',
          defer: false,
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
          data: 'menu_id=' + menu_id,
          dataType: 'html',
          defer: false,
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
<script src="{{ asset("/admin_dep/js/ajax.js") }}" defer></script>


<script src="{{ asset("/plugins/bower_components/datatables/datatables.min.js") }}" defer></script>
<!-- start - This is for export functionality only -->

@if(!in_array($url,$route))
<script src="https://cdn.datatables.net/buttons/1.2.2/js/dataTables.buttons.min.js" defer></script>
<script src="https://cdn.datatables.net/buttons/1.2.2/js/buttons.flash.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/2.5.0/jszip.min.js" defer></script>
<script src="https://cdn.rawgit.com/bpampuch/pdfmake/0.1.18/build/pdfmake.min.js" defer></script>
<script src="https://cdn.rawgit.com/bpampuch/pdfmake/0.1.18/build/vfs_fonts.js" defer></script>
<script src="https://cdn.datatables.net/buttons/1.2.2/js/buttons.html5.min.js" defer></script>
<script src="https://cdn.datatables.net/buttons/1.2.2/js/buttons.print.min.js" defer></script>
@endif
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

    });

</script>