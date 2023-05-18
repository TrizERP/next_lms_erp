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


</head>

<body>
    <!-- Header -->
    <div class="header-log">
        <div class="headr-logo">
            <a href="#">
                <img src="{{asset('Images/logo.png')}}">
            </a>
        </div>
    </div>
    <!-- Setup Your Details -->
    <section class="setup-table" style="width:auto !important;margin-left: 0px !important">
        <div class="container" style="margin-left:20px !important;margin-right:20px !important">
            <div class="page-title">
                <h1 class="text-center  mt-4">Menu plan</h1>
            </div>
            <div class="row">
                <!-- Hello -->
                @if (!empty($data['head']))
                @php
                $i = 1;
                @endphp
                @foreach ($data['head'] as $key => $value)
                @if ($value['menu_title'] != '')
                <div class="col-md-4">
                    <div class="card">

                        <a style="color:#black;font-size:1rem;" data-bs-toggle="collapse"
                            href="#collapseExample-{{$value['menu_title']}}" aria-expanded="false"
                            aria-controls="collapseExample"
                            class="btn btn-outline-dark collapse-btn">{{$value['menu_title']}}</a>
                        <!-- </thead> -->
                    </div>
                </div>
                @endif
                @endforeach
                @endif
            </div>

            @if (!empty($data['head']))
            @foreach ($data['head'] as $key => $value)
            <div class="collapse accordion-button " id="collapseExample-{{$value['menu_title']}}">
                <div class="card card-body need-card mt-2" style="padding:0px !important;">
                    <a data-bs-toggle="collapse" href="#collapseExample-Master-{{$value['menu_title']}}"
                        aria-expanded="false" aria-controls="collapseExample">
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
                <div class="collapse" id="collapseExample-Master-{{$value['menu_title']}}"  style="border :1px solid #ddd !important;">
                    @php $i =1 ; @endphp
                    @foreach($data['groupwisemenuMaster'][$value['menu_title']] as $masterKey => $master)

                    @if ($master['link'] == 'javascript:void(0);' || $master['link'] == '')

                    @else
                    <div class="card card-body need-card border-0" style="padding:4px !important">
                        <a href="{{route($master['link'])}}">
                            <div class="main" style=" display:flex;">

                                <div class="text">{{$master['name']; }}</div>
                            </div>
                        </a>
                    </div>
                    @endif

                    @php $i++; @endphp
                    @endforeach
                </div>
                @endif


                <div class="card card-body need-card mt-2" style="padding:0px !important;">
                    <a data-bs-toggle="collapse" href="#collapseExample-Entry-{{$value['menu_title']}}"
                        aria-expanded="false" aria-controls="collapseExample">
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
                <div class="collapse" id="collapseExample-Entry-{{$value['menu_title']}}" style="border:1px solid #ddd !important;">
                    @php $i =1 ; @endphp
                    @foreach($data['groupwisesubmenuMaster'][$value['menu_title']] as $enrtyKey => $entry)

                    @if ($entry['link'] == 'javascript:void(0);' || $entry['link'] == '')

                    @else
                    <div class="card card-body need-card border-0">
                        <a href="{{route($entry['link'])}}">
                            <div class="main" style=" display:flex;">
                                <div class="text">{{$entry['name']; }}</div>
                            </div>
                        </a>
                    </div>
                    @endif
                    @php $i++; @endphp
                    @endforeach
                </div>
                @endif

                <div class="card card-body need-card mt-2" style="padding:0px !important;">
                    <a data-bs-toggle="collapse" href="#collapseExample-Report-{{$value['menu_title']}}"
                        aria-expanded="false" aria-controls="collapseExample">
                        <div class="main" style=" display:flex;">
                            <div class="number">3</div>
                            <div class="text">
                                {{$value['menu_title']}} Report
                            </div>
                        </div>
                    </a>
                </div>

            </div>

            <!-- report -->
            @if(!empty($data['groupwiseSubsubmenuMaster'][$value['menu_title']]))
            <div class="collapse" id="collapseExample-Report-{{$value['menu_title']}}" style="border:1px solid #ddd !important;">
                @php $i =1 ; @endphp
                @foreach($data['groupwiseSubsubmenuMaster'][$value['menu_title']] as $reportKey => $report)
                @if ($report['link'] == 'javascript:void(0);' || $report['link'] == '')

                @else
                <div class="card card-body need-card border-0">
                    <a href="{{route($report['link'])}}">
                        <div class="main" style=" display:flex;">
                            <div class="text">{{$report['name']; }}</div>
                        </div>
                    </a>
                </div>
                @endif

                @php $i++; @endphp
                @endforeach
            </div>
            @endif
            @endforeach


        </div>

        @endif
        </div>
    </section>

    <!-- Option 1: jQuery and Bootstrap Bundle (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery library -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Bootstrap JavaScript files (required for Bootstrap components and features) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>

    <script type="text/javascript">
    // jQuery code
    // $(document).ready(function() {
    //   $('.collapse-btn').click(function() {
    //     var target = $(this).data('target');
    //     var value = $(this).val(); // Get the value of the clicked element

    //     $('.collapse').not('#' + target).slideUp(); // Hide other collapses
    //     $('#' + target).slideToggle(); // Toggle the target collapse

    //     // Use the value in your desired logic
    //     console.log('Clicked value:', value);
    //     // Perform further actions with the value
    //   });
    // });
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
    font-weight: 500;
    font-size: 18px;
    line-height: 18px;
    /*  color: #000000;*/
    /*  opacity: 0.4;*/
    width: 100%;
    column-gap: 10px;
}
</style>

</html>