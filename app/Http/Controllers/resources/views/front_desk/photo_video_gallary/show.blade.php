{{--@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Photo Video Gallery</h4>
            </div>
        </div>
        <div class="card">
            <form action="{{ route('photo_video_gallary.store') }}" enctype="multipart/form-data" method="post">
                {{ method_field("POST") }}
                {{csrf_field()}}
                <div class="row">
                    {{ App\Helpers\SearchChain('4','multiple','grade,std,div') }}
                    <div class="col-md-4">
                        <label>Date</label>
                        <input type="text" name="date_" class="form-control mydatepicker" autocomplete="off" required="required">
                    </div>
                    <div class="col-md-4">
                        <label>Title</label>
                        <input type="text" name="title" class="form-control" required="required">
                    </div>
                    <div class="col-md-4">
                        <label>Album Title</label>
                        <input type="text" name="album_title" class="form-control" required="required">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Type</label>
                        <select name="type" class="form-control" onchange="changetype(this.value)" required="required">
                            <option value="">Select</option>
                            <option value="Photo">Photo</option>
                            <option value="Video">Video</option>
                        </select>
                    </div>
                    <div id="photo" class="col-md-4 form-group">
                        <label>Browse Files</label>
                        <input type="file" name="attachment[]" id="attachment[]" class="form-control" multiple="multiple" accept="image/*">
                        <span class="text-danger font-weight-bold">Note: Select multiple or single files from here.</span>
                    </div>
                    <div id="video" style="display: none;" class="col-md-4 form-group">
                        <label>Youtube Link</label>
                        <input type="text" name="attachment" id="attachment" class="form-control">
                    </div>
                    <div class="col-md-12">
                        <label></label><br>
                        <center>
                            <input type="submit" name="submit" value="Submit" class="btn btn-success" >
                        </center>
                    </div>
                </div>
            </form>
        </div>
        <div class="card">
            <div class="row">
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Sr No</th>
                                    <th>Syear</th>
                                    <th>Title</th>
                                    <th>Album Title</th>
                                    <th>Type</th>
                                    <th>Date</th>
                                    <th>Standard</th>
                                    <th>Division</th>
                                    <th>Show</th>
                                    <th>Active/InActive</th>
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
                                    <td>{{$data->album_title}}</td>
                                    <td>{{$data->type}}</td>
                                    <td>{{date('d-m-Y',strtotime($data->date_))}}</td>
                                    <td>{{$data->std_name}}</td>
                                    <td>{{$data->div_name}}</td>
                                    <?php if ($data->type == 'Video') { ?>
                                    <td><a href="<?php echo $data->file_name; ?>" target="_blank" class="text-primary">View</a> </td>
                                    <?php } else { ?>
                                    <td><a href="<?php echo asset('storage/photo_video_gallary/' . $data->file_name); ?>" target="_blank" class="text-primary">View</a> </td>
                                    <?php } ?>
                                    <td>{{$data->ai}}</td>
                                    <td>
                                        <div class="d-inline">
                                            <a href="{{ route('photo_video_gallary.edit',$data->id)}}" class="btn btn-info btn-outline">
                                               <i class="mdi mdi-swap-horizontal"></i>
                                            </a>
                                        </div>
                                        <form action="{{ route('photo_video_gallary.destroy', $data->id)}}" method="post" class="d-inline">
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
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('includes.footerJs')
<script>
$(document).ready(function () {
    $('#example').DataTable();
    $('#grade').attr('required',true);
    $('#standard').attr('required',true);
    $('#division').attr('required',true);
});
</script>
<script>
    // $("#division").parent('.form-group').hide();
    function changetype(values) {
//        alert("here");
        if (values == 'Photo') {
            $("#video").hide();
            $("#photo").show();
            $("#attachment[]").attr("required",true);
        } else {
            $("#photo").hide();
            $("#video").show();
        }
    }
</script>

@include('includes.footer')
@endsection
