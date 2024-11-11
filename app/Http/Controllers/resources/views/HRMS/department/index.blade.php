@extends('lmslayout')
@section('container')
<style>
    .main-card{
        background:transparent;
        border:none
    }
    .departmentCard{
        /* align-items:center; */
        text-align:center;
        border-radius:20px;
    }
    .departmentName{
        /* background: linear-gradient(88.8deg, rgb(239, 171, 245) 13.4%, rgb(196, 181, 249) 76.3%); */
        background:#5e5959;
        padding: 20px;
        border-radius: 20px;
    }
    .subDepDetails {
        border:1px solid #ddd;
        border-radius:20px;
        margin: 20px;
        padding: 0;
    }
    .departmentName h5, .total_emp h3{
        color:#fff;
    }
    
    .total_emp{
        background:#5e5959;
        border-top-left-radius:20px;
        border-top-right-radius:20px;
    }

    @media (min-width:1200px){
        .main-row{
            width:120%;
        }
    }
</style>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-8 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Department Details</h4>
            </div>
        
            <div class="col-lg-4 col-md-4 col-sm-4 col-xs-12">
                <a href="{{route('add_department.create')}}" class="btn btn-primary">
                    <span class="mdi mdi-plus"></span> Add Department
                </a>
            </div>
        </div>
    
        <div class="card main-card">
            <div class="row main-row">
            @if(!empty($data['departmentData']))
            @foreach($data['departmentData'] as $key => $value)
                <!-- parent name  -->
                <div class="col-md-3 card departmentCard" style="margin:20px 30px !important;padding:0px 10px !important">
                    <!-- child details  -->
                    <div class="row">
                        <div class="col-md-12 departmentName">
                            <h5><b>{{$value->department}}</b></h5>
                        </div>
                        <div class="col-md-12 SubDepartments" style="padding:16px">
                            <div class="row">
                            @if(isset($data['subDepartmentData'][$value->id]))
                                @foreach($data['subDepartmentData'][$value->id] as $subkey => $subvalue)
                                <div class="col-md-3 subDepDetails">
                                    <div class="total_emp">
                                        <h3 class="mr-2 p-2">{{$subvalue->total_emp}}</b></h3>
                                    </div>
                                    <div class="subData">
                                        <h4 class="p-2"><b>{{$subvalue->department}}</b></h4>
                                    </div>
                                </div>
                                @endforeach
                            @else
                            <div class="col-md-12">
                                <img src="{{asset('admin_dep/images/not-found-1.png')}}" alt="not-found">
                                <center><p>No Sub Departments Added</p></center>
                            </div>
                            @endif
                            </div>

                        </div>
                    </div>
                </div>
            @endforeach
            @endif
            </div>
        </div>
        <!-- container end  -->
    </div>
</div>
@include('includes.lmsfooterJs')
@include('includes.footer')
@endsection
