{{-- @include('includes.headcss') @include('includes.header') @include('includes.sideNavigation') --}} 
@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="card">
            @if(!empty($data['message']))
            <div class="alert alert-{{ $data['class'] }} alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $data['message'] }}</strong>
            </div>
            @endif
            <div class="row">                
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('student_attendance_master.create') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("GET") }}
                        {{csrf_field()}}
                        <div class="row">                            
                            {{ App\Helpers\SearchChain('4','single','grade,std,div') }}
                            <!-- <input type="text" name=""> -->
                            {{ App\Helpers\TermDD() }}
                            <div class="col-md-12 form-group mt-2">
                                <center>
                                    <input type="submit" name="submit" value="Save" class="btn btn-success" >
                                </center>
                            </div>
                        </div>

                    </form>
                </div>
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
<script>
    CKEDITOR.replace('line1');
    CKEDITOR.replace('line2');
    CKEDITOR.replace('line3');
    CKEDITOR.replace('line4');
</script>
@include('includes.footer')
@endsection