@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">
                    Document Standard Mapping
                </h4>
            </div>            
        </div>
       
        <!-- form start  -->
        <form action="{{route('document_standard_mapping.update',$data['editData']->id)}}" class="card" method="post">
            {{method_field('PUT')}}
        @csrf 
            <div class="row">
                <!-- doc type select  -->
                <div class="col-md-4 form-group">
                    <label for="document_type">Select Document Type</label>
                    <select class="form-control" name="document_type" id="document_type" required>
                        <option value="">select</option>
                        @foreach($data['documentTypeList'] as $id=>$value)
                            <option value="{{$id}}" {{($data['editData']->document_type_id == $id) ? 'Selected' : '' }}>{{$value}}</option>
                        @endforeach
                    </select>
                </div>
                <!-- grade standard select  -->
                {{ App\Helpers\SearchChain('4','single','grade,std',$data['editData']->grade_id,$data['editData']->standard_id) }}
                <!-- add button  -->
                <div class="col-md-12">
                    <center>
                        <input type="submit" value="Update" name="add" class="btn btn-success">
                    </center>
                </div>

            </div>
        </form>
        <!-- form end  -->
       
    </div>
</div>

@include('includes.footerJs')
<script>
    $(document).ready(function(){
        $('#grade').prop('required',true);
        $('#standard').prop('required',true);
    })
</script>
@include('includes.footer')
@endsection