{{--@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('layout')
@section('container')

<style>
 th{
     background:#2e55a9;
     color:#fff !important;
 }
</style>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Student House Automation</h4>
            </div>
        </div>
        <div class="card">
        @php
        $options = ['No','Yes'];
        @endphp
            @if ($sessionData = Session::get('data'))
                <div class="alert alert-block {{ $sessionData['class'] }}">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $sessionData['message'] }}</strong>
                </div>
            @endif
            <form action="{{ route('house_automation.create') }}" method="get">    
                @csrf
                <div class="row">
                    <div class="col-md-4 form-group ml-0 mr-0">
                        <label>Standard</label>
                        <select class="form-control" name="standard_id" id="standard_id" required>
                            <option value="">Select {{App\Helpers\get_string('standard','request')}}</option>
                            @if(isset($data['standard_data']))
                                @foreach($data['standard_data'] as $key =>$val)
                                    @php
                                        $selected = '';
                                        if( isset($data['standard_id']) && $data['standard_id'] == $val->id )
                                        {
                                            $selected = 'selected';
                                        }
                                    @endphp
                                    <option {{$selected}} value="{{$val->id}}">{{$val->name}}</option>
                                @endforeach
                            @endif
                        </select>
                    </div>

                    <div class="col-md-4 form-group ml-0 mr-0">
                        <label>Optional subject</label>
                        <select class="form-control" name="opt_sub" id="opt_sub" required>
                        @foreach($options as $key =>$value)
                            <option value="{{$value}}" @if(isset($data['opt_sub']) && $data['opt_sub'] == $value) selected @endif>{{$value}}</option>
                        @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 form-group mt-4 ml-0 mr-0">
                        <input type="submit" name="submit" value="Submit" class="btn btn-success">
                    </div>
                </div>
            </form>
        </div>

         <!-- table card  -->
         @if(!empty($data['student_house']))
         <div class="card">
            <div class="col-lg-12 col-sm-12 col-xs-12 table-responsive mb-4">
                <table class="table  table-border">
                    <tbody>
                    <tr>
                        <td><b>Standard</b></td>
                        <td>{{ $data['std_name'] }}</td>
                        <td></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td><b>Total Boys</b></td>
                        <td>{{ $data['total_boys'] }}</td>
                        <td><b>Total Girls</b></td>
                        <td>{{ $data['total_girls'] }}</td>
                    </tr>
                    <tr>
                        <td><b>Total Division</b></td>
                        <td>{{ $data['total_div'] }}</td>
                        <td><b>Total House</b></td>
                        <td>{{ $data['total_house'] }}</td>
                    </tr>
                    </tbody>
                </table>
            </div>
            @if($data['total_boys']!=0 && $data['total_girls']!=0)
          <div class="col-lg-12 col-sm-12 col-xs-12">
                <div class="table-responsive">
                    <table id="example" class="table table-border">
                        <thead>
                            <tr>
                            <th>Division</b></th>
                            @foreach($data['house_data'] as $key=>$value)
                                <th class="text-left" colspan="2">{{$value->house_name}}</b></th>
                            @endforeach
                            <th class="text-left">Total Student</b></th>
                            </tr>
                            <tr>
                                <th></th>
                            @foreach($data['house_data'] as $key=>$value)
                                <th class="text-left">Boys</b></th>
                                <th class="text-left">Girls</b></th>                                
                            @endforeach
                            <th class="text-left">Total</b></th>
                            </tr>
                        </thead>
                        <tbody>
                        @php 
                            $mainTot=0;
                            $totBoy=$totGirl=[];
                        @endphp
                        @foreach($data['student_house'] as $key=>$value)
                        <tr>
                            <td>{{$value['division']}}</td>
                            @php 
                                $all_stud=0;
                            @endphp
                            @foreach($data['house_data'] as $key1=>$value1) 
                                @if(isset($value1->house_name))
                                    @php 
                                        $boy =$value1->house_name.'_b' ?? 0;
                                        $girl = $value1->house_name.'_g' ?? 0;
                                    @endphp                           
                                    <td>{{$value[$boy]}}</td>
                                    <td>{{$value[$girl]}}</td>
                                    @php 
                                        $all_stud +=$value[$boy]+$value[$girl];
                                        $totBoy[$boy][] =$value[$boy];
                                        $totGirl[$girl][]=$value[$girl];                                
                                    @endphp
                                @endif
                            @endforeach  
                            <td>{{$all_stud}}</td>   
                            @php 
                                $mainTot +=$all_stud;
                            @endphp                                                   
                        <tr>
                        @endforeach
                        <tr>
                            <td>Total</td>
                            @foreach($data['house_data'] as $key=>$value)
                            <td>{{ array_sum($totBoy[$value->house_name.'_b']) }}</td>
                            <td>{{ array_sum($totGirl[$value->house_name.'_g']) }}</td>
                            @endforeach
                            <td>{{$mainTot}}</td>                            
                        <tr>
                        </tbody>
                    <table>
                </div>
            </div>
            @else
                <div class="alert alert-block alert-danger">
                    <strong>No Optional Subject Found</strong>
                </div>
         </div>
        @endif
       
        @endif
    </div>
</div>
@include('includes.footerJs')
@include('includes.footer')
@endsection
