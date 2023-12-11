<!doctype html>
<html lang="en">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-ENjdO4Dr2bkBIFxQpeoTz1HIcje39Wm4jDKdf19U8gI4ddQ3GYNS7NTKfAdVQSZe" crossorigin="anonymous">
    </script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>    
    <title>Institute Data | TRIZ INNOVATION PVT LTD</title>
    <style>
        .icon-right {
            float: right;
            margin-left: 5px; 
            color: #333; 
            transition: color 0.3s;
        }

        .icon-right:hover {
            color: #FF5733;
        }
   
.collapse{
    margin-bottom:10px;
}
.number {
    background: #5C4AC7;
    border: 1px solid rgba(0, 0, 0, 0.2);
    border-radius: 8px;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 26px;
    line-height: 10px;
    text-align: center;
    color: #FFFFFF;
    margin:10px;
}

.text {
    padding: 14px 15px;
    font-weight: 300;
    font-size: 16px;
    font-weight: 500;
    line-height: 40px;
    color: black;
    width: 100%;
    column-gap: 10px;
}

.text:hover {
    color: #5C4AC7;
}
.fcolor{
    color:#5C4AC7
}
.collapse, .main-head{
    box-shadow:5px 5px 5px 5px #8888 !important;
    font-size:1rem;
}
    </style>
</head>

<body>
@include('includes.headcss')
@include('includes.header')
    <!-- Setup Your Details -->
    <section class="content-main" style="width:100% !important;padding:100px 140px 0px 140px !important">
        <div class="container-fluid">
            <div class="page-title">
                <h1 class="text-center  mt-4 fcolor">Menu plan</h1>
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
                <div class="col-md-4 col-sm-12">
                    <div class="card" style="margin:2px !important;padding:0px !important  ">
                        <a style="color:#black;font-size:1rem;width:100% !important" data-bs-toggle="collapse"
                            href="#collapseExample-{{str_replace(' ', '_', $value['menu_title'])}}"
                            aria-expanded="false" aria-controls="collapseExample"
                            class="btn btn-outline-dark collapse-btn" data-id="{{$value['parent_menu_id']}}" data-title="{{$value['menu_title']}}">{{$value['menu_title']}}</a>
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


            @if (!empty($data['head']))
            @foreach ($data['head'] as $key => $value)

            <div class="m-4 collapse-main panel-collapse collapse" role="tabpanel" aria-labelledby="headingOne"
                id="collapseExample-{{str_replace(' ', '_', $value['menu_title'])}}"  >
                <div class="collapse-hide">
                    <h4 class="text-center fw-bolder" style="color:#5C4AC7;font-size:1.5rem">{{$value['menu_title']}}</h4>
                    
                    <div class="card card-body need-card mt-2" style="padding:0px !important;" style="border :1px solid #ddd !important;">
                        <a data-bs-toggle="collapse"
                            href="#collapseExample-Master-{{str_replace(' ', '_', $value['menu_title'])}}"
                            aria-expanded="false" aria-controls="collapseExample-Master">
                            <div class="main main-head" style="display:flex;">
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
                            <div class="main main-head" style=" display:flex;">
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

                        @elseif (Route::has($entry['link']))
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
                            <div class="main main-head" style=" display:flex;">
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
    @include('onboarding_model')

@include('includes.footer')
@include('includes.footerJs')

    <!-- Option 1: jQuery and Bootstrap Bundle (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
   <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>

<script>
 $(document).ready(function() {
        // Collapse toggle
        $('.collapse-btn').click(function() {
            var target = $(this).attr('href');
            var menu_id = $(this).attr('data-id'); 
            var menu_title = $(this).attr('data-title'); 
            var table_name = @json($data['table_name']);
            // alert(menu_id);
            $('.collapse-main').not(target).collapse('hide'); // Collapse other sections
            $(".collapse-main").addClass('card');

            $(target).collapse('toggle');
            if(menu_id==6){
                // alert('clicked');
                $('#exampleModal_fees').modal('show');  
            }
            else
            {
                $('#all_model').empty(); 
                $('#allTitle').empty(); 
		        $('#allTitle').append('<b>'+menu_title+'</b>');
                var master = @json($data['groupwisemenuMaster']) ;
                // console.log(master);
                var menu_data = '';
                var exists = 0;
                if (typeof master[menu_title] === 'object' && master[menu_title] !== null) {
                    exists = 1;
                    for (let key in master[menu_title]) {
                        if (master[menu_title].hasOwnProperty(key)) {
                // console.log(table_name[master[menu_title][key]['database_table']]);
                var prefix1 = {"Transportation": "transportation/","Hostel": "hostel_management/","Inventory": "inventory/","Transport": "","LMS": "lms/","Communication": "easy_com/","Result": "result/","Petty Cash": "frontdesk/","Inward-Outward": "inward_outward/","PTM": "ptm/","School": "school_setup/","Student": "student/","Utility": "student/"
                        };

                        if (master[menu_title][key]['title'] == "HRMS") {
                            menu_link1 = master[menu_title][key]['link'];
                        } else {
                            var linkValue1 = master[menu_title][key]['link'];
                            var newLinkValue1 = linkValue1.replace('.index', '/create');
                            var menu_link1 = prefix1[menu_title]  + newLinkValue1;
                        }
                menu_data += `<div class="col-md-4">
                        <label>
                            <a class="text-primary" onclick="window.open('/${menu_link1}', 'scrollbars=yes,resizable=no,status=no,location=no,toolbar=no,menubar=no', 'width=1200,height=1200,left=100,top=100');" href="javascript:void(0);">
                                <b>${master[menu_title][key]['name']}</b>
                            </a>
                        </label>
                    </div>
                    <div class="col-md-4">`;

                    if (table_name[master[menu_title][key]['database_table']] && table_name[master[menu_title][key]['database_table']] == 1) 
                    {
                        var prefix = {"Transportation": "transportation/","Hostel": "hostel_management/","Inventory": "inventory/","Transport": "","LMS": "lms/","Communication": "easy_com/","Result": "result/","Petty Cash": "frontdesk/","Inward-Outward": "inward_outward/","PTM": "ptm/","School": "school_setup/","Student": "student/","Utility": "student/"
                        };

                        if (master[menu_title][key]['title'] == "HRMS") {
                            menu_data += `<a class="text-primary" onclick="window.open('/${master[menu_title][key]['link']}', 'scrollbars=yes,resizable=no,status=no,location=no,toolbar=no,menubar=no', 'width=1200,height=1200,left=100,top=100');" href="javascript:void(0);">View Details</a>`;
                        } else {
                            var linkValue = master[menu_title][key]['link'];
                            var newLinkValue = linkValue.replace('.index', '');
                            var menu_link = prefix[menu_title]  + newLinkValue;
                            menu_data += `<a class="text-primary" onclick="window.open('/${menu_link}', 'scrollbars=yes,resizable=no,status=no,location=no,toolbar=no,menubar=no', 'width=1200,height=1200,left=100,top=100');" href="javascript:void(0);">View Details</a>`;
                        }

                    } 
                    else 
                    {
                        menu_data += `<label class="text-danger">Incomplete Setup</label>`;
                    }

                    menu_data += `</div>
                    <div class="col-md-4">`;

                    if (table_name[master[menu_title][key]['database_table']] && table_name[master[menu_title][key]['database_table']] == 1) {
                        menu_data += `<img src="{{asset('/Images/square-check.svg')}}" title="complete">`;
                    } else {
                        menu_data += `<img src="{{asset('/Images/close-square-icon.svg')}}" title="complete">`;
                    }

                    menu_data += `</div>`;
                        }
                    }
                } else {
                    console.error("master[menu_title] is not an object");
                }
                console.log(table_name);
                // $('#all_model').append(menu_data);
                // Set modal body content
                $('#all_model').html(menu_data);

                // Show the modal
                if(exists == 1)
                {
                $('#exampleModal_all').modal('show');
                }
                
            }
            // $(this).toggleClass('active');
        });
    });
    $(function () {
        $('[data-toggle="tooltip"]').tooltip();
    });
</script>
</body>
</html>