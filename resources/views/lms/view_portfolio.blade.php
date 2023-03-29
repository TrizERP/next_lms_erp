@include('includes.lmsheadcss')
               
<!-- Content main Section -->
<div class="content-main flex-fill" style="padding-left:20px !important;">

    <div class="row">
        
        <div class="col-md-12">
            <div class="card">
                <div class="card-body p-b-0">
                    <h4 class="card-title">All Portfolio</h4>
                    <!-- Nav tabs -->
                    <ul class="nav nav-tabs customtab2" role="tablist">

                        @php $i = 1; @endphp
                        @foreach($data['portfolio_data'] as $key => $val)
                        @php
                        $main_active = "";
                        if($i == 1)
                        {
                            $main_active = "active";
                        }   
                        @endphp
                        <li class="nav-item"> 
                            <a class="nav-link {{$main_active}}" data-toggle="tab" href="#home{{$i++}}" role="tab">
                            <span class="hidden-sm-up"><i class="ti-home"></i></span> 
                            <span class="hidden-xs-down">{{$val['title']}}</span></a> 
                        </li>
                        @endforeach                        
                    </ul>
                    <!-- Tab panes -->
                    <div class="tab-content">
                        @php $j = 1; @endphp
                        @foreach($data['portfolio_data'] as $key => $val)
                        @php
                        $sub_active = "";
                        if($j == 1)
                        {
                            $sub_active = "active";
                        }   
                        @endphp
                      
                        <div class="tab-pane {{$sub_active}}" id="home{{$j++}}" role="tabpanel">
                             <div class="row">
                                <div class="col-md-4"><img src="../../../storage/lms_portfolio/{{$val['file_name']}}" class="img-fluid thumbnail m-r-15"> </div>
                                <div class="col-md-8"> {!!$val['description']!!} </div>
                            </div>
                        </div>
                        @endforeach                        
                    </div>
                </div>
            </div>
        </div>
    </div>                        
</div>


<footer class="footer text-center"> {{date('Y')}} &copy; Triz Innovation PVT LTD. <a href="{{route('siteMap')}}"> Site Map </a> </footer>
<!-- jQuery -->
<script src="{{ asset("/admin_dep/js/jquery-3.5.1.min.js") }}"></script>
<script src="{{ asset("/admin_dep/js/popper.min.js") }}"></script>
<script src="{{ asset("/admin_dep/js/bootstrap.min.js") }}"></script>
<script src="{{ asset("/admin_dep/js/bootstrap-select.min.js") }}"></script>
<script src="{{ asset("/admin_dep/js/lms-custom.js") }}"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script src="{{ asset("/plugins/bower_components/chartist-js/dist/chartist.min.js") }}"></script>
<script src="{{ asset("/plugins/bower_components/chartist-plugin-tooltip-master/dist/chartist-plugin-tooltip.min.js") }}"></script>
<!-- Sparkline chart JavaScript -->
<script src="{{ asset("/plugins/bower_components/jquery-sparkline/jquery.sparkline.min.js") }}"></script>
<script src="{{ asset("/plugins/bower_components/jquery.easy-pie-chart/dist/jquery.easypiechart.min.js") }}"></script>
<script src="{{ asset("plugins/bower_components/jquery.easy-pie-chart/easy-pie-chart.init.js") }}"></script>
<script src="{{ asset("plugins/bower_components/bootstrap-datepicker/bootstrap-datepicker.min.js") }}"></script>

<!-- Clock Plugin JavaScript -->
<script src="{{ asset("plugins/bower_components/clockpicker/dist/jquery-clockpicker.min.js") }}"></script>
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
        
    
    
