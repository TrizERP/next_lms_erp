{{--@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')--}}

@extends('layout')
@section('container')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Syllabus</h4>
            </div>
        </div>
                <div class="card">
                    <form action="{{ route('syllabus.store') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("POST") }}
                        {{csrf_field()}}
                        <div class="row">

                            {{ App\Helpers\SearchChain('4','single','grade,std') }}
                            <!-- App\Helpers\SearchChain('4','multiple','grade,std,div') -->

                            <div class="col-md-4 form-group">
                                <label for="subject">Select Subject:</label>
                                <select name="subject" id="subject" class="form-control mb-0" required>
                                    <option value="">Select Subject</option>
                                    @if(isset($data['questionpaper_data']))
                                    @foreach($data['subjects'] as $key => $value)
                                    <option value="{{$value['subject_id']}}" @if(isset($data['questionpaper_data']['subject_id'])) @if($data['questionpaper_data']['subject_id']==$value['subject_id']) selected='selected' @endif @endif>{{$value['display_name']}}</option>
                                    @endforeach
                                @endif
                                </select>
                            </div>


                            <div class="col-md-4 form-group">
                                <label>Date</label>
                                <input type="text" name="date_" class="form-control mydatepicker" autocomplete="off">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Title</label>
                                <input type="text" name="title" class="form-control" >
                            </div>


                            <div class="col-md-4 form-group">
                                <label>Message</label>
                                <textarea name="message" class="form-control"></textarea>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>File</label>
                                <input type="file" name="attachment" class="form-control">
                            </div>
                        </div>


                       <div class="col-md-4 form-group">
                            <center>
                                <input type="submit" name="submit" value="Submit" class="btn btn-success" >
                            </center>
                        </div>

                    </form>
                </div>
                <div class="card">
                    <div class="col-lg-12 col-sm-12 col-xs-12">
                        <div class="table-responsive">
                            <table id="example" class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Sr No</th>
                                        <th>Syear</th>
                                        <th>Title</th>
                                        <th>Message</th>
                                        <th>Date</th>
                                        <th>Standard</th>
                                        <th>Subject</th>
                                        <th>File</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                    $j=1;
                                    @endphp
                                    @if(isset($data['data']))
                                    @foreach($data['data'] as $key => $data)
                                    <tr>
                                        <td>{{$j}}</td>
                                        <td>{{$data->syear}}</td>
                                        <td>{{$data->title}}</td>
                                        <td>{{$data->message}}</td>
                                        <td>{{$data->date_}}</td>
                                        <td>{{$data->std_name}}</td>
                                        <td>{{$data->display_name}}</td>
                                        <td><a href="../../storage/syllabus/{{$data->file_name}}" target="_blank">{{$data->file_name}}</a></td>
                                        <td>
                                            <form action="{{ route('syllabus.destroy', $data->id)}}" method="post">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" onclick="return confirmDelete();" class="btn btn-info btn-outline-danger"><i class="ti-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                    @php
                                    $j++;
                                    @endphp
                                    @endforeach
                                    @endif


                                </tbody>

                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>


@include('includes.footerJs')
<script>
    //$("#division").parent('.form-group').hide();
    $("#standard").change(function(){
        var std_id = $("#standard").val();
        var path = "{{ route('ajax_LMS_StandardwiseSubject') }}";
        $('#subject').find('option').remove().end().append('<option value="">Select Subject</option>').val('');
        $.ajax({url: path,data:'std_id='+std_id, success: function(result){
            for(var i=0;i < result.length;i++){
                $("#subject").append($("<option></option>").val(result[i]['subject_id']).html(result[i]['display_name']));
            }
        }
        });
    })
</script>
@include('includes.footer')
@endsection
