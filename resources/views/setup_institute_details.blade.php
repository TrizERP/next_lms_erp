<!doctype html>
<html lang="en">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="{{asset('/css/style2.css')}}">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-ENjdO4Dr2bkBIFxQpeoTz1HIcje39Wm4jDKdf19U8gI4ddQ3GYNS7NTKfAdVQSZe" crossorigin="anonymous">
    </script>
    <title>Institute Data | TRIZ INNOVATION PVT LTD</title>
    <style>
        .icon-right {
            float: right; /* Or use other positioning styles like margin or absolute positioning */
            margin-left: 5px; /* Adjust the margin as needed */
            color: #333; /* Set the default color */
            transition: color 0.3s; /* Add a smooth transition effect */
        }

        .icon-right:hover {
            color: #FF5733; /* Change the color when hovering */
        }
    </style>
</head>

<body>
@include('includes.headcss')
@include('includes.header')
    <!-- Setup Your Details -->
    <!-- Add jQuery library -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Setup Your Details -->
    <section class="content-main" style="width:100% !important;padding:100px 140px 0px 140px !important">
        <div class="container-fluid">
            <div class="page-title">
                <h1 class="text-center  mt-4">Menu plan</h1>
            </div>
          
            <div class="row">
                <!-- Hello -->
                @if (!empty($data['head']))
                @php
                $i = 1;
                $extra_menu = [
                                [
                                    'menu_title'=>'Library',
                                    'link'=>'',
                                    ],
                                [
                                    'menu_title'=>'LMS Preloaded',
                                    'link'=>"/lms/course_master?preload_lms=preload_lms",
                                    ]
                             ];
                @endphp
                @foreach ($data['head'] as $key => $value)
                @if ($value['menu_title'] != '')
                <div class="col-md-4">
                    <div class="card" style="margin:2px !important;padding:0px !important  ">
                        <a style="color:#black;font-size:1rem;width:100% !important" data-bs-toggle="collapse"
                            href="#collapseExample-{{str_replace(' ', '_', $value['menu_title'])}}"
                            aria-expanded="false" aria-controls="collapseExample"
                            class="btn btn-outline-dark collapse-btn">{{$value['menu_title']}}</a>
                        <!-- </thead> -->
                    </div>
                </div>
                @endif
                @endforeach
                @endif

                @foreach ($extra_menu as $key => $value)
                <div class="col-md-4">
                    <div class="card" style="margin:2px !important;padding:0px !important">
                        <a style="color:#black;font-size:1rem;;width:100% !important" 
                            href="{{$value['link']}}" target="_blank" class="btn btn-outline-dark">{{$value['menu_title']}}</a>
                        <!-- </thead> -->
                    </div>
                </div>
                @endforeach

            </div>
          <!--   <div class="row mt-4">
                 <div class="col-md-6">
                    
                </div>
                <div class="col-md-6 align-items-md-center d-flex">
                    <h6 class="text-primary mr-2">Welcome Admin! Do You Want To Continue ?</h6>
                    <button class="btn mr-2 purple-outline-btn" style="border:1px solid #5C4AC7;color:#5C4AC7">Continue</buttn>
                    <button class="btn btn-outline-success" >Order</buttn>
                </div>
            </div> -->

            @if (!empty($data['head']))
            @foreach ($data['head'] as $key => $value)

            <div class="m-4 collapse-main panel-collapse collapse" role="tabpanel" aria-labelledby="headingOne"
                id="collapseExample-{{str_replace(' ', '_', $value['menu_title'])}}"  >
                <div class="collapse-hide">
                    <h4 class="text-center fw-bolder" style="color:#5C4AC7;">{{$value['menu_title']}}</h4>
                    <div class="card card-body need-card mt-2" style="padding:0px !important;" style="border :1px solid #ddd !important;">
                        <a data-bs-toggle="collapse"
                            href="#collapseExample-Master-{{str_replace(' ', '_', $value['menu_title'])}}"
                            aria-expanded="false" aria-controls="collapseExample-Master">
                            <div class="main" style=" display:flex;">
                                <div class="number">1</div>
                                <div class="text">
                                    {{$value['menu_title']}} Master
                                </div>
                            </div>
                        </a>
                    </div>
                    <!-- master -->
                    @if(!empty($data['groupwisemenuMaster'][$value['menu_title']]))
                    <div class="collapse" id="collapseExample-Master-{{str_replace(' ', '_', $value['menu_title'])}}">
                        @php $i =1 ; @endphp
                        @foreach($data['groupwisemenuMaster'][$value['menu_title']] as $masterKey => $master)
                        @if ($master['link'] == 'javascript:void(0);' || $master['link'] == '')
                        @else
                        <div class="card card-body need-card border-0" style="padding:4px !important;margin:0px !important;border-radius:0px !important;box-shadow:0 0 0 rgb(0 0 0 / 0%) !important;">
                            <a href="@if(Route::has($master['link'])){{route($master['link'])}}@else # @endif" target="_blank">
                                <div class="main" style="display:flex;">
                                    <div class="text">{{$master['name']}}
                                        @if(isset($data['table_name']) && isset($data['table_name'][$master['database_table']]) && $data['table_name'][$master['database_table']] == 1)
                                             <img src="{{asset('/Images/square-check.svg')}}">
                                             @else
                                             <img src="{{asset('/Images/close-square-icon.svg')}}">
                                            @endif
                                            @if (!empty($master['text']))
                                                <i class="fas fa-info-circle icon-right" data-toggle="tooltip" data-placement="top" title="{{$master['text']}}"></i>
                                            @endif
                                    </div>
                                </div>

                            </a>
                        </div>
                        @endif
                        @php $i++; @endphp
                        @endforeach
                    </div>
                    @else
                    <div class="collapse" id="collapseExample-Master-{{str_replace(' ', '_', $value['menu_title'])}}" >
                        <div class="card card-body need-card border-0 "  style="padding:4px !important;margin:0px !important;border-radius:0px !important;box-shadow:0 0 0 rgb(0 0 0 / 0%) !important;">
                        <div class="text text-danger">
                        <img src="{{asset('/Images/close-square-icon.svg')}}"> There is no required for Master Setup
                        </div>
                        </div>
                    </div>
                    @endif

                    <div class="card card-body need-card mt-2" style="padding:0px !important;">
                        <a data-bs-toggle="collapse"
                            href="#collapseExample-Entry-{{str_replace(' ', '_', $value['menu_title'])}}"
                            aria-expanded="false" aria-controls="collapseExample-Entry">
                            <div class="main" style=" display:flex;">
                                <div class="number">2</div>
                                <div class="text">
                                    {{$value['menu_title']}} Entry
                                </div>
                            </div>
                        </a>
                    </div>

                    <!-- entry -->
                    @if(!empty($data['groupwisesubmenuMaster'][$value['menu_title']]))
                    <div class="collapse" id="collapseExample-Entry-{{str_replace(' ', '_', $value['menu_title'])}}">
                        @php $i =1 ; @endphp
                        @foreach($data['groupwisesubmenuMaster'][$value['menu_title']] as $enrtyKey => $entry)

                        @if ($entry['link'] == 'javascript:void(0);' || $entry['link'] == '')

                        @else
                        <div class="card card-body need-card border-0"  style="padding:4px !important;margin:0px !important;border-radius:0px !important;box-shadow:0 0 0 rgb(0 0 0 / 0%) !important;" >
                            <a href="{{route($entry['link'])}}" target="_blank">
                                <div class="main" style=" display:flex;">
                                    <div class="text">{{$entry['name']; }}
                                        @if(isset($data['table_name']) && isset($data['table_name'][$entry['database_table']]) && $data['table_name'][$entry['database_table']] == 1)
                                         <img src="{{asset('/Images/square-check.svg')}}">
                                         @else
                                         <img src="{{asset('/Images/close-square-icon.svg')}}">
                                        @endif
                                        @if (!empty($entry['text']))
                                            <i class="fas fa-info-circle icon-right" data-toggle="tooltip" data-placement="top" title="{{$entry['text']}}"></i>
                                        @endif
                                    </div>
                                </div>
                            </a>
                        </div>
                        @endif
                        @php $i++; @endphp
                        @endforeach
                    </div>
                    @else
                    <div class="collapse" id="collapseExample-Entry-{{str_replace(' ', '_', $value['menu_title'])}}">
                        <div class="card card-body need-card border-0"  style="padding:4px !important;margin:0px !important;border-radius:0px !important;box-shadow:0 0 0 rgb(0 0 0 / 0%) !important;">
                        <div class="text text-danger">
                        <img src="{{asset('/Images/close-square-icon.svg')}}"> There is no required for Entry
                        </div>
                        </div>
                    </div>

                    @endif

                    <div class="card card-body need-card mt-2" style="padding:0px !important;">
                        <a data-bs-toggle="collapse"
                            href="#collapseExample-Report-{{str_replace(' ', '_', $value['menu_title'])}}"
                            aria-expanded="false" aria-controls="collapseExample-Report">
                            <div class="main" style=" display:flex;">
                                <div class="number">3</div>
                                <div class="text">
                                    {{$value['menu_title']}} Report
                                </div>
                            </div>
                        </a>
                    </div>

                    <!-- report -->
                    @if(!empty($data['groupwiseSubsubmenuMaster'][$value['menu_title']]))
                    <div class="collapse" id="collapseExample-Report-{{str_replace(' ', '_', $value['menu_title'])}}">
                        @php $i =1 ; @endphp
                        @foreach($data['groupwiseSubsubmenuMaster'][$value['menu_title']] as $reportKey => $report)
                        @if ($report['link'] == 'javascript:void(0);' || $report['link'] == '')

                        @else
                        <div class="card card-body need-card border-0"  style="padding:4px !important;margin:0px !important;border-radius:0px !important;box-shadow:0 0 0 rgb(0 0 0 / 0%) !important;">
                            <a href="{{route($report['link'])}}" target="_blank">
                                <div class="main" style=" display:flex;">
                                    <div class="text">{{$report['name']; }}
                                    @if(isset($data['table_name']) && isset($data['table_name'][$report['database_table']]) && $data['table_name'][$report['database_table']] == 1)
                                 <img src="{{asset('/Images/square-check.svg')}}">
                                 @else
                                 <img src="{{asset('/Images/close-square-icon.svg')}}">
                                @endif
                                @if (!empty($report['text']))
                                    <i class="fas fa-info-circle icon-right" data-toggle="tooltip" data-placement="top" title="{{$report['text']}}"></i>
                                @endif
                            </div>
                                </div>
                            </a>
                        </div>
                        @endif

                        @php $i++; @endphp
                        @endforeach
                    </div>
                    @else
                    <div class="collapse" id="collapseExample-Report-{{str_replace(' ', '_', $value['menu_title'])}}">
                        <div class="card card-body need-card border-0"  style="padding:4px !important;margin:0px !important;border-radius:0px !important;box-shadow:0 0 0 rgb(0 0 0 / 0%) !important;">
                        <div class="text text-danger">
                        <img src="{{asset('/Images/close-square-icon.svg')}}"> There is no required for Report 
                        </div>
                        </div>
                    </div>

                    @endif
                </div>
            </div>
            @endforeach
            @endif

        </div>
        </div>
    </section>

    <script>
    $(document).ready(function() {
        // Collapse toggle
        $('.collapse-btn').click(function() {
            var target = $(this).attr('href');
            $('.collapse-main').not(target).collapse('hide'); // Collapse other sections
            $(target).collapse('toggle');
            // $(this).toggleClass('active');
        });
    });
    </script>

    <!-- Option 1: jQuery and Bootstrap Bundle (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery library -->

    <!-- Bootstrap JavaScript files (required for Bootstrap components and features) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-ENjdO4Dr2bkBIFxQpeoTz1HIcje39Wm4jDKdf19U8gI4ddQ3GYNS7NTKfAdVQSZe" crossorigin="anonymous">
</script>
<script>
    $(function () {
        $('[data-toggle="tooltip"]').tooltip();
    });
</script>
</body>
<style>
.img-width {
    width: 20px;
}

.number {
    background: #5C4AC7;
    /*  opacity: 0.2;*/
    border: 1px solid rgba(0, 0, 0, 0.2);
    border-radius: 8px;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 26px;
    line-height: 31px;
    text-align: center;
    color: #FFFFFF;
}

.text {
    padding: 10px 15px;
    font-weight: 300;
    font-size: 18px;
    line-height: 18px;
    color: black;
    /*  opacity: 0.4;*/
    width: 100%;
    column-gap: 10px;
}

.text:hover {
    color: #5C4AC7;
}

</style>

</html>