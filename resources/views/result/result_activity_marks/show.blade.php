@extends('layout')
@section('container')
<style>
    .table-wrapper {
        /* overflow-x: auto; */
        white-space: nowrap;
        max-width: 100%;
    }

    th:first-child, td:first-child {
        position: sticky;
        left: 0;
        background-color: #fff;
        border-bottom:1px solid #e7e7e7 !important;
        border-right: 1px solid #ddd !important;
    }

    th:first-child, td:first-child {
        min-width: 250px;
        max-width: 250px;
        white-space: normal; /* Enable text wrap */
        word-wrap: break-word;
        border-right: 1px solid #ddd !important;
    }
    .wrapper1, .wrapper2 { width: 100%; overflow-x: scroll; overflow-y: hidden; }
    .wrapper1 { height: 20px; }
    .wrapper2 {}
    .div1 { height: 20px; }
    .table-wrapper { overflow: none; }
</style>
<div id="page-wrapper">
    <div class="container-fluid">       
            <div class="card">
            @if ($sessionData = Session::get('data'))
                @if($sessionData['status_code'] == 1)
                <div class="alert alert-success alert-block">
                @else
                <div class="alert alert-danger alert-block">
                @endif
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $sessionData['message'] }}</strong>
                </div>
            @endif
            <div class="col-lg-12 col-sm-12 col-xs-12">
                <form action="{{ route('result_activity_marks_V1.create') }}" enctype="multipart/form-data" method="post">
                    {{ method_field("GET") }}
                    {{csrf_field()}}
                    <div class="row">
                    @php
                    $grade_id = $standard_id = $division_id = '';
                        if(isset($data['grade_id'])){
                            $grade_id = $data['grade_id'];
                            $standard_id = $data['standard_id'];
                            $division_id = $data['division_id'];
                        }
                    @endphp
                        {{ App\Helpers\SearchChain('4','single','grade,std,div',$grade_id,$standard_id,$division_id) }}
                        <div class="col-md-12 form-group">
                            <center>
                                <input type="submit" name="submit" value="Search" class="btn btn-success" >
                            </center>
                        </div>
                    </div>
                </form>
            </div>
            @if (count($errors) > 0)
            <div class="alert alert-danger">
                <strong>Whoops!</strong> There were some problems with your input.<br><br>
                <ul>
                    @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif
        </div>  
        <div class="card">
        @if(isset($data['studentsList']) && !empty($data['skillData']))
        <form action="{{route('result_activity_marks_V1.store')}}" method="POST">
                @csrf
            <div class="row">
            <div class="wrapper1">
                <div class="div1"></div>
            </div>
            <div class="wrapper2">
                <div class="table-wrapper">
                    <table class="table table-stripped">
                        <thead>
                            <tr>
                                <th>Activities</th>
                                @foreach($data['studentsList'] as $stuKey=>$stuvalue)
                                <th style="max-width:fit-content !important">{{$stuvalue['first_name'].' '.$stuvalue['last_name'].'/'.$stuvalue['enrollment_no']}}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @php $i = 1; @endphp
                            @if(isset($data['skillData']))
                                @foreach($data['skillData'] as $skillKey=>$skillValue)
                                    <tr>
                                        {{-- <td colspan="{{count($data['studentsList'])}}"><h4><b>{{$skillKey}}<b></h4></td> --}}
                                        <td>{{$i++}}.<b>{{$skillKey}}<b></td>
                                        @foreach($data['studentsList'] as $stukey=>$stuvalue)
                                        <td>
                                            &nbsp;
                                        </td>
                                        @endforeach
                                    </tr>

                                    @foreach($skillValue as $key=>$value)
                                    <!-- sub Title  -->
                                    <tr>
                                        <td>{{$i++}}.<b>{{$value->title}}<b></td>
                                        @foreach($data['studentsList'] as $stukey=>$stuvalue)
                                        <td>
                                            &nbsp;
                                        </td>
                                        @endforeach
                                    </tr>
                                    <!-- grouped Data  -->
                                    @if(isset($data['activityGroup'][$value->id]))
                                    @foreach($data['activityGroup'][$value->id] as $key2=>$value2)
                                        <tr>
                                            <td>{{$i++}}.
                                                @if(isset($data['subActivityGroup'][$value2->id]))
                                                    <b>{{$value2->title}}</b>
                                                @else 
                                                    {{$value2->title}}
                                                @endif
                                            </td>

                                            @foreach($data['studentsList'] as $stukey=>$stuvalue)
                                            <td>
                                                @if(isset($data['subActivityGroup'][$value2->id]))
                                                    &nbsp;
                                                @else 
                                                    <select name="marksArr[{{$stuvalue['id']}}][activity_id][{{$value2->id}}]" id="groupedData" class="form-control">
                                                        <option value="">--Select--</option>
                                                        @foreach($data['marksType'] as $mkey => $mvalue)
                                                            @php 
                                                            $selected = ""; 
                                                            if(isset($data['studentMarks']['activity'][$stuvalue['id']][$value2->id]) && $data['studentMarks']['activity'][$stuvalue['id']][$value2->id]==$mvalue->id)
                                                            {
                                                                $selected = "Selected"; 
                                                            }
                                                            @endphp
                                                        <option value="{{$mvalue->id}}" {{$selected}}>{{$mvalue->title}}</option>
                                                        @endforeach
                                                    </select>
                                                @endif
                                            </td>
                                            @endforeach
                                        </tr>
                                        <!-- sub grouped activity  -->
                                        @if(isset($data['subActivityGroup'][$value2->id]))
                                            @foreach($data['subActivityGroup'][$value2->id] as $key3=>$value3)
                                            <tr>
                                                <td>{{$i++}}.{{$value3->title}}</td>
                                                @foreach($data['studentsList'] as $stukey=>$stuvalue)
                                                <td>
                                                    <select name="marksArr[{{$stuvalue['id']}}][sub_activity_id][{{$value2->id}}][{{$value3->id}}]" id="groupedData" class="form-control">
                                                        <option value="">--Select--</option>
                                                        @foreach($data['marksType'] as $mkey => $mvalue)
                                                        @php 
                                                            $selected1 = ""; 
                                                            if(isset($data['studentMarks']['sub_activity_id'][$stuvalue['id']][$value3->id]) && $data['studentMarks']['sub_activity_id'][$stuvalue['id']][$value3->id]==$mvalue->id)
                                                            {
                                                                $selected1 = "Selected"; 
                                                            }
                                                        @endphp
                                                        <option value="{{$mvalue->id}}" {{$selected1}}>{{$mvalue->title}}</option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                @endforeach
                                            </tr>
                                            @endforeach
                                        @endif
                                        <!-- last data -->
                                    @endforeach
                                    @endif
                                <!-- grouped Data end  --> 
                                    @endforeach
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                    </div>
                </div>
                <div class="col-md-12">
                    <center>
                        <input type="submit" value="submit" name="submit" class="btn btn-primary">
                    </center>
                </div>
            </div>
            </form>
            @endif 
        </div>  
    </div>
</div>

@include('includes.footerJs')
<script>
  $(document).ready(function(){
    $('#standard').prop('required',true);
  })
  $(function () {
    $('.wrapper1').on('scroll', function (e) {
        $('.wrapper2').scrollLeft($('.wrapper1').scrollLeft());
    }); 
    $('.wrapper2').on('scroll', function (e) {
        $('.wrapper1').scrollLeft($('.wrapper2').scrollLeft());
    });
});
$(window).on('load', function (e) {
    $('.div1').width($('table').width());
    $('.table-wrapper').width($('table').width());
});
</script>
@include('includes.footer')
@endsection