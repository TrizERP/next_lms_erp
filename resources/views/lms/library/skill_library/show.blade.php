@extends('layout')
@section('container')
<style>
    .ActiveTab{
        color : #2f99de;
        border-bottom : 2px solid #2f99de;
    }
</style>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">View Skill</h4>
            </div>
        </div>

       <!-- header card starts  -->
       <div class="card shadow-sm my-3 tab-bar-card">
            <div class="card-body p-0">
                <div class="row">
                    <div class="tab-bar-accessory-left col-auto ml-2 mr-2 mt-2">
                        <i class="mdi mdi-star-outline pr-2" style="font-size:45px"></i>
                    </div>
                    <div class="col-auto">
                        <div class="row tab-bar-entity-name">
                        Skill
                        ( {{$data['editData']['category']}} @if($data['editData']['sub_category'])> {{$data['editData']['sub_category']}} @endif
                        )
                        </div>
                        <div class="row">
                            {{$data['editData']['title']}}
                        </div>
                    </div>
                    <div class="tab-bar-accessory-right col-auto ml-auto ml-xl-0 order-xl-1">
                        <div class="dropdown">
                        <button type="button" class="btn btn-sm btn-outline-dark dropdown-toggle" data-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false" style="padding-right:30px !important">
                        Actions
                        </button>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="{{route('skill_library.edit',[$data['editData']['id']])}}">
                                <i class="fa fa-edit"></i> Edit skill
                            </a><a class="dropdown-item no-target-icon" onclick="printDiv('DetailsDiv')">
                                <i class="fa fa-download"></i> Export PDF
                            </a><a class="dropdown-item" href="{{route('skill_library.destroy',[$data['editData']['id']])}}">
                                <i class="fa fa-trash"></i> Delete skill
                            </a>
                        </div>
                        </div>
                    </div>
                    <div class="tab-bar-main col-12 col-xl mt-2 mt-xl-0">
                        <div id="dashboard_tabs" class="tab-bar mdc-tab-bar" data-container-id="dashboard_tab_content" role="tablist">
                            <div class="mdc-tab-scroller">
                                    <div class="mdc-tab-scroller__scroll-area mdc-tab-scroller__scroll-area--scroll" style="margin-bottom: 0px;">
                                    <!-- <a class="mdc-tab mdc-ripple-upgraded ActiveTab" role="tab" id="mdc-tab-3"><span class="mdc-tab__content"><span class="mdc-tab__text-label">About</span></span><span class="mdc-tab-indicator"><span class="mdc-tab-indicator__content mdc-tab-indicator__content--underline"></span></span></a> -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </div>
        <!-- header card end  -->
        
        <div class="card" id="DetailsDiv">
           <div class="abuotDiv">
                <table class="table table-bordered"  cellspacing="0"  border="1">
                    <tr>
                        <th><strong>Name</strong></th>
                        <th>{{$data['editData']['title']}}</th>
                    </tr>
                    <tr>
                        <th><strong>Category</strong></th>
                        <th>{{$data['editData']['category']}}</th>
                    </tr>
                    <tr>
                        <th><strong>Sub Category</strong></th>
                        <th>{{$data['editData']['sub_category']}}</th>
                    </tr>
                    <tr>
                        <th><strong>Description</strong></th>
                        <th>{{$data['editData']['description']}}</th>
                    </tr>
                </table>
           </div>
        </div>

    </div>
</div>

@include('includes.footerJs')
<script>
    function printDiv(divName) {
        var divToPrint = document.getElementById(divName);
        var popupWin = window.open('', '_blank', 'width=300,height=300');
        popupWin.document.open();
        popupWin.document.write('<html>');
        
        popupWin.document.write('<body onload="window.print()">' + divToPrint.innerHTML + '</html>');
        popupWin.document.close();
    }
</script>

@include('includes.footer')
@endsection
