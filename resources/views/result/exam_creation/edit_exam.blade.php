<link rel="stylesheet" href="../../../plugins/bower_components/dropify/dist/css/dropify.min.css">
{{-- @include('includes.headcss') @include('includes.header') @include('includes.sideNavigation') --}} 
@extends('layout')
@section('container')

<div id="page-wrapper">
    <div class="container-fluid">        
            <div class="card">
                @if ($message = Session::get('success'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                </div>
                @endif
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('exam_creation.update', $data['id']) }}" enctype="multipart/form-data" method="post">
                        {{ method_field("PUT") }}
                        {{csrf_field()}}
                        <div class="row">
                            <!-- Below function will get term name and id from helper.php  -->
                            {{ App\Helpers\TermDD($data['term_id']) }}
                        
                            <input type="hidden" value="{{$data['medium']}}" name="medium">

                            <div class="col-md-4 form-group">
                                <label>Exam Type : </label>
                                <select name="exam_id" class="form-control">
                                    <option value="">Select</option>
                                    @foreach ($data['exams'] as $key => $value)
                                    <option value="{{ $key }}"
                                            @if ($data['exam_id'] == $key)
                                            selected="selected"
                                            @endif
                                            >{{ $value }}</option>
                                    @endforeach

                                </select>
                            </div>
                           
                            <div class="col-md-12 form-group">
                            <!-- Below function will get grade,standard,division name and id from helper.php  -->
                                {{ App\Helpers\SearchChainSubject('3','single','grade,std,sub',$data['grade'],$data['standard_id'],$data['subject_id']) }}
                            </div>
                        
                            <input type="hidden" value="{{$data['con_point']}}" name="con_point">
                            <input type="hidden" value="{{$data['app_disp_status']}}" name="app_disp_status">

                            <div class="col-md-3 form-grou">
                                <label for="report_card_status">Report Card Status</label>
                                <select name="report_card_status" id="report_card_status" class="form-control">
                                    @foreach($data['report_card_status_arr'] as $key=>$value)
                                    <option value="{{$key}}" @if(isset($data['report_card_status']) && $data['report_card_status']==$key) Selected @endif>{{$value}}</option>
                                    @endforeach
                                </select>
                            </div>
                                <div class="col-md-12 form-group mt-2">
                                <table id="myTable" class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Marks</th>
                                        <th>Sort Order</th>
                                        <th>Exam Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>
                                            <input type="text" name="title" value="{{ $data['title'] }}" class="form-control" />
                                        </td>
                                        <td>
                                            <input type="text" name="points" value="{{ $data['points'] }}" class="form-control" />
                                        </td>
                                        
                                        {{-- <input type="hidden" value="{{$data['report_card_status']}}" name="report_card_status"> --}}
                                        <input type="hidden" value="{{$data['marks_type']}}" name="marks_type">
                                        
                                        <td>
                                            <input type="text" name="sort_order" value="{{ $data['sort_order'] }}" class="form-control" />
                                        </td>
                                        <td>
                                            <input type="text" name="exam_date" value="{{ $data['exam_date'] }}" class="form-control mydatepicker" autocomplete="off" />
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                <tr>
                                </tr>
                                <tr>
                                </tr>
                            </tfoot>
                        </table>
                        </div>

                        <div class="col-md-12 form-group">
                            <center>
                                <input type="submit" name="submit" value="Save" class="btn btn-success" >
                            </center>
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
                    </div>
                </div>


@include('includes.footerJs')
<script src="../../../plugins/bower_components/dropify/dist/js/dropify.min.js"></script>
<script>
$(document).ready(function () {
    // Basic
    $('.dropify').dropify();
    // Translated
    $('.dropify-fr').dropify({
        messages: {
            default: 'Glissez-déposez un fichier ici ou cliquez',
            replace: 'Glissez-déposez un fichier ou cliquez pour remplacer',
            remove: 'Supprimer',
            error: 'Désolé, le fichier trop volumineux'
        }
    });
    // Used events
    var drEvent = $('#input-file-events').dropify();
    drEvent.on('dropify.beforeClear', function (event, element) {
        return confirm("Do you really want to delete \"" + element.file.name + "\" ?");
    });
    drEvent.on('dropify.afterClear', function (event, element) {
        alert('File deleted');
    });
    drEvent.on('dropify.errors', function (event, element) {
        console.log('Has Errors');
    });
    var drDestroy = $('#input-file-to-destroy').dropify();
    drDestroy = drDestroy.data('dropify')
    $('#toggleDropify').on('click', function (e) {
        e.preventDefault();
        if (drDestroy.isDropified()) {
            drDestroy.destroy();
        } else {
            drDestroy.init();
        }
    })
});
</script>

@include('includes.footer')
@endsection