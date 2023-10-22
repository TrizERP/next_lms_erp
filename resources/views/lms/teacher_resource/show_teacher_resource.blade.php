{{--@include('includes.lmsheadcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('lmslayout')
@section('container')
<!-- Content main Section -->
<div class="content-main flex-fill">
    <div class="row">
        <div class="col-md-6">
            <h1 class="h4 mb-3">
            Add Teacher Resource
            </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb bg-transparent p-0">
                    <li class="breadcrumb-item"><a href="{{route('course_master.index')}}">LMS</a></li>
                    <li class="breadcrumb-item">Teacher Resource</li>
                    <li class="breadcrumb-item active" aria-current="page">Add Teacher Resource</li>
                </ol>
            </nav>
        </div>
    </div>
            @php
            if(isset($_REQUEST['preload_lms'])){
                $preload_lms = "preload_lms=preload_lms";
                $readonly="pointer-events: none";
            }
            @endphp
    <div class="card" style="@php echo $readonly ?? '' @endphp">
        <div class="card-body">
            @if ($message = Session::get('success'))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $message }}</strong>
            </div>
            @endif
            <form action="{{ route('lms_teacherResource.store') }}" method="post" enctype='multipart/form-data' onsubmit="return check_validation();">
                {{ method_field("POST") }}
                @csrf
                <div class="row">
                    <div class="col-md-3 form-group">
                        <label>Title</label>
                        <input type="text" class="form-control" name="title" id="title" required>
                    </div>

                    <div class="col-md-3 form-group">
                        <label>Resource</label>
                        <input type="file" name="teacher_file" id="teacher_file" class="form-control" required>
                    </div>

                    @if(isset($data['data']['custom_fields']))
                        @foreach($data['data']['custom_fields'] as $key => $value)

                        <div class="col-md-4 form-group text-left">

                            <label>{{ $value['field_label'] }}</label>

                            @if($value['field_type'] == 'file')
                            <input type="{{ $value['field_type'] }}" accept="image/*" id="input-file-now" @if($value['required'] == 1) required @endif name="{{ $value['field_name'] }}" class="dropify">
                            @elseif($value['field_type'] == 'date')
                            <div class="input-daterange input-group" >
                                <input type="date" class="form-control" placeholder="dd/mm/yyyy" autocomplete="off" id="{{ $value['field_name'] }}"
                                @if($value['required'] == 1) required @endif name="{{ $value['field_name'] }}" class="form-control"><span class="input-group-addon"><i class="icon-calender"></i></span>
                            </div>

                            @elseif($value['field_type'] == 'checkbox')
                            <div class="checkbox-list">
                                @if(isset($data['data']['data_fields'][$value['id']]))
                                @foreach($data['data']['data_fields'][$value['id']] as $keyData => $valueData )
                                    <label class="checkbox-inline">
                                        <div class="checkbox checkbox-success">
                                            <input type="checkbox" name="{{ $value['field_name'] }}[]" value="{{ $valueData['display_value'] }}" id="{{ $valueData['display_value'] }}" @if($value['required'] == 1) required @endif>
                                            <label for="{{ $valueData['display_value'] }}">{{ $valueData['display_text'] }}</label>
                                        </div>
                                    </label>
                                    @endforeach
                                @endif
                            </div>

                            @elseif($value['field_type'] == 'dropdown')
                                <select name="{{ $value['field_name'] }}" class="form-control" @if($value['required'] == 1) required @endif id="{{ $value['field_name'] }}">
                                    <option value=""> SELECT {{ strtoupper($value['field_label']) }} </option>
                                @if(isset($data['data']['data_fields'][$value['id']]))
                                    @foreach($data['data']['data_fields'][$value['id']] as $keyData => $valueData)
                                    <option value="{{ $valueData['display_value'] }}"> {{ $valueData['display_text'] }} </option>
                                    @endforeach
                                @endif
                                </select>

                            @elseif($value['field_type'] == 'textarea')
                                <textarea id="{{ $value['field_name'] }}" class="form-control" @if($value['required'] == 1) required @endif name="{{ $value['field_name'] }}" placeholder="{{ $value['field_message'] }}">
                                </textarea>

                            @else
                                <input type="{{ $value['field_type'] }}" id="{{ $value['field_name'] }}" placeholder="{{ $value['field_message'] }}" @if($value['required'] == 1) required @endif name="{{ $value['field_name'] }}" class="form-control">
                            @endif
                        </div>
                        @endforeach
                    @endif

                    <input type="hidden" class="form-control" name="hid_standard_id" id="hid_standard_id" value="@if(isset($data['data']['standard_id'])){{$data['data']['standard_id']}}@endif">
                    <input type="hidden" class="form-control" name="hid_subject_id" id="hid_subject_id" value="@if(isset($data['data']['subject_id'])){{$data['data']['subject_id']}}@endif">
                    <input type="hidden" class="form-control" name="hid_chapter_id" id="hid_chapter_id" value="@if(isset($data['data']['chapter_id'])){{$data['data']['chapter_id']}}@endif">
                    <input type="hidden" class="form-control" name="hid_topic_id" id="hid_topic_id" value="@if(isset($data['data']['topic_id'])){{$data['data']['topic_id']}}@endif">

                    <div class="col-md-3 form-group mt-4">
                        <input type="submit" name="submit" value="Save" class="btn btn-success" >
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card mt-2">
        <div class="card-body">
            <div class="table-responsive">
                <table id="example" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Sr No</th>
                            <th>Chapter Name</th>
                            <th>Topic Name</th>
                            <th>Title</th>
                            <th>Resource</th>
                            <th>Activity</th>
                            <th>File</th>

                            @if(isset($data['data']['custom_fields']))
                                @foreach($data['data']['custom_fields'] as $key => $value)
                                    <th>{{ $value['field_label'] }}</th>
                                @endforeach
                            @endif

                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                        $j=1;
                        @endphp
                        @if(count($data['data']['TR_data']) > 0)
                            @foreach($data['data']['TR_data'] as $tkey => $tdata)
                            <tr>
                                <td>{{$j}}</td>
                                <td>{{$tdata['chapter_name']}}</td>
                                <td>@if(isset($tdata['topic_name'])){{$tdata['topic_name']}} @else- @endif</td>
                                <td>{{$tdata['title']}}</td>
                                <td>{{$tdata['description']}}</td>
                                <td>{{$tdata['activity']}}</td>
                                <td>
                                    <a href="<?php echo asset("storage".$tdata['file_folder']."/".$tdata['file_name']); ?>" target="_blank">View</a>
                                </td>

                                @if(isset($data['data']['custom_fields']))
                                    @foreach($data['data']['custom_fields'] as $key => $value)
                                        <td>{{ $tdata[$value['field_label']] }}</td>
                                    @endforeach
                                @endif

                                <td  style="@php echo $readonly ?? '' @endphp">
                                    <form action="{{ route('lms_teacherResource.destroy', $tdata['id'])}}" method="post">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" onclick="return confirmDelete();" class="btn btn-outline-danger"><i class="ti-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            @php
                            $j++;
                            @endphp
                            @endforeach
                        @else
                            <tr><td colspan="10" class="font-weight-bold"><center>No Records</center></td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@include('includes.lmsfooterJs')
@include('includes.footer')
@endsection
