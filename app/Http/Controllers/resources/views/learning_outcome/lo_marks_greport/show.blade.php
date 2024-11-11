@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Lo Marks Entry</h4>
            </div>
        </div>
        <div class="card">
            @if(!empty($data['message']))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $data['message'] }}</strong>
            </div>
            @endif
            <form action="{{ route('lo_marks_greport.create') }}" enctype="multipart/form-data" method="GET">
                {{ method_field("GET") }}
                {{csrf_field()}}
                <div class="row">                    
                    <div class="col-lg-12 col-sm-12 col-xs-12">  
                        <div class="row">             
                            <div class="col-md-4 form-group">
                                <label>Medium</label>
                                <select class="form-control" onchange="getSubjects();" required name="medium" id="medium">
                                    <option value="">--Select Medium--</option>
                                    @foreach ($data['data']['medium'] as $item=>$val)
                                    <option value="{{ $item }}">{{ $val }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Standard</label>
                                <select class="form-control" onchange="getSubjects();" required name="std" id="std">
                                    <option value="">--Select Standard--</option>
                                    @foreach ($data['data']['std'] as $item=>$val)
                                    <option value="{{ $item }}">{{ $val }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Division</label>
                                <select class="form-control" name="div">
                                    <option value="">--Select Division--</option>
                                    @foreach ($data['data']['div'] as $item=>$val)
                                    <option value="{{ $val }}">{{ $val }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Exam Type</label>
                                <select class="form-control" name="exam_type">
                                    <option value="">--Select Type--</option>
                                    @foreach ($data['data']['exam_type_dd'] as $item=>$val)
                                    <option value="{{ $val }}">{{ $val }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Save" class="btn btn-success">
                                </center>
                            </div>
                        </div>   
                    </div>     
                </div>
            </form>
        </div>
    </div>
</div>
</div>

@include('includes.footerJs')

@include('includes.footer')