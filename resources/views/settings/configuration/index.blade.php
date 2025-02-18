@extends('layout')
@section('container')
<style>
   .drag-row-container
   {
        display:flex;
        /* justify-content:center; */
        overflow-x: scroll;
        padding:36px 10px;
    }
    .menuHead
    {
        background-color: #EF5E29;
        color:#fff;
        text-align: center;
        padding: 12px 4px;
        height: 66px;
    }
    
    .drag-row:nth-child(2) .menuHead,.drag-row:nth-child(7) .menuHead {
        background-color: #3CB878; /*green*/
        color: #fff;
    }

    .drag-row:nth-child(3) .menuHead,.drag-row:nth-child(8) .menuHead {
        background-color:#6297C3; /* blue */
        color: #fff;
    }

    .drag-row:nth-child(4) .menuHead,.drag-row:nth-child(9) .menuHead {
        background-color: #F1C40F; /* Yellow */
        color: #fff;
    }

    .drag-row:nth-child(5) .menuHead,.drag-row:nth-child(10) .menuHead {
        background-color: #8E44AD; /* Purple */
        color: white;
    }

    .menuIcon>span
    {
        font-size: 24px;
    }
    .dragIcon>span,
    .subMenuIcon>span
    {
        font-size: 24px;
    }
    .subMenuList
    {
        cursor: grab;
        display:flex;
        background-color: #2C3B49;
        color: #fff;
        padding: 16px 4px;
        margin-bottom: 2px;
    }
 
    .subMenuList:active {
        cursor: grabbing;
    }
    .dragIcon,
    .subMenuIcon,
    .subMenuName
    {
        padding:0px;
    }

    .sortable-placeholder {
        height: 40px;
        margin: 5px 0;
        border: 1px dashed #aaa;
    }
    .infoDiv{
        margin : 0px 20px;
        padding : 4px 10px;
        border-left:5px solid #6297C3;  
        border-radius: 5px; 
        height: 80px;
        color: #6297C3;
    }

</style>
<div id="page-wrapper">
    <div class="container-fluid">
        <!-- Start Drag and Drop -->
        <div class="card" style="width:100%">

            <div class="row infoDiv">
                <h4 style="width:100%"><span class="fa fa-info-circle"></span> info</h4>
                <p style="width:100%">Drag and drop a Module to re-order it. On click add/edit fields.</p>
            </div>

            <div class="drag-row-container">
                @foreach($data['main_menu'] as $menuTitle => $value)
                <div class="col-md-2 drag-row">
                    <div class="menuHead">
                        <div class="menuIcon">
                            <span class="mdi mdi-{{$value['icon']}}"></span>
                        </div>
                        <div class="menuName">
                            <h6>{{$menuTitle}}</h6>
                        </div>
                    </div>

                    @if(isset($data['sub_menu'][$menuTitle]) && !empty($data['sub_menu'][$menuTitle]))
                    <div class="subMenuContainer">
                        @foreach($data['sub_menu'][$menuTitle] as $k => $val)
                        <div class="subMenuList col-md-12" data-id="{{ $val['id'] }}" onclick="addFieldPage({{$val['id']}})">
                            <div class="dragIcon col-md-2">
                                <span class="mdi mdi-drag"></span>
                            </div>
                            <div class="subMenuIcon col-md-2">
                                <span class="{{$val['icon']}}"></span>
                            </div>
                            <div class="subMenuName col-md-8">{{ (strlen($val['name'])>12) ? substr($val['name'], 0, 12).'...' : $val['name'] }}</div>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        <!-- End Drag and Drop -->

    </div>
</div>

@include('includes.footerJs')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
<script>
    $(document).ready(function () {
        $(".subMenuContainer").sortable({
            placeholder: "sortable-placeholder",
            update: function(event, ui) {
                let menuArr = $(this).sortable("toArray", { attribute: "data-id" });
                // console.log("Updated order:", menuArr); // Log updated order
                $.ajax({
                    url : "{{route('updateMenuSortOrder')}}",
                    data : {orderArr:menuArr, masterType: 'rights'},
                    type : 'POST',
                    success : function(response){
                        console.log("Updated order:", response); // Log updated order

                        if(response.status==0){
                            alert(response.message);
                        }
                    },
                    error : function(response){
                        alert('Opps ! Something went wrong');
                    }
                })
            }
        }).disableSelection();
    });
    // call fields page as per menu id
    function addFieldPage(menuId){
        window.location.href = "/settings/configurations/create?main_menu_id="+menuId;
    }
</script>

@include('includes.footer')

@endsection