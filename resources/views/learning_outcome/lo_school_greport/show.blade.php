@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<style>
    .table>thead>tr>th 
    {
        color: #fff;
        background: #3f98d3 !important;
        text-transform: uppercase;
        font-size: 12px;
        padding: 5px 10px 5px 10px;
        border-right: 1px solid #e6e6e6;
        border-bottom: 1px solid #dedede;
        white-space: nowrap;
        text-align: center;
        font-weight: 700;
        letter-spacing: 0.3px;
    }
</style>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">School Wise Graph</h4>
            </div>
        </div>
        <div class="card">
            @if(!empty($data['message']))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $data['message'] }}</strong>
            </div>
            @endif
            <form action="{{ route('lo_class_greport.create') }}" enctype="multipart/form-data" method="GET">
                {{ method_field("GET") }}
                {{csrf_field()}}
                <div class="row">    
                    <div class="col-lg-12 col-sm-12 col-xs-12">
                        <div class="table-responsive">  
                            <table id="example" class="table table-striped">
                                <thead>
                                <tr>
                                    <th>Medium</th>
                                    <th>Exam Type</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                                @foreach ($data['data']['medium'] as $med_item=>$med_val)
                                @foreach ($data['data']['exam_type_dd'] as $ex_item=>$ex_val)
                                <?php
                                    $arr = array(
                                        "medium" => $med_item,
                                        "exam_type" => $ex_item
                                    );
                                ?>
                                <tr>
                                    <td>{{$med_val}}</td>
                                    <td>{{$ex_val}}</td>
                                    <td><a target="_blank"
                                            href="{{ route('lo_school_greport.create', $arr) }}">VIEW</a></td>
                                </tr>
                                @endforeach
                                @endforeach
                            </table> 
                        </div>
                     </div>   
                </div>         
            </form>
        </div>
    </div>
</div>

@include('includes.footerJs')

@include('includes.footer')