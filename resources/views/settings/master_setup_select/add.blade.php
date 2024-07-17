@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Master Setup Selects</h4>
            </div>
        </div>
        <!-- card start  -->
        <div class="card">
            <form action="{{route('master_setup.store')}}" method="post">
            @csrf
            <div class="row">
                <div class="col-md-4 form-group">
                    <label for="field">Select Type</label>
                    <select name="seltype" id="type" class="form-control" required>
                        <option value="">--Select--</option>
                        @foreach($data['seltypes'] as $value)
                            <option value="{{$value}}">{{$value}}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 form-group">
                    <label for="field">Field Name</label>
                    <input type="text" name="fieldname" class="form-control" placeholder="Enter Field Name" autocomplete="off" required>
                </div>
                <div class="col-md-4 form-group">
                    <label for="field">Add Options <a class="btn btn-success" style="padding:4px 4px !important;margin-left:20px" onclick="addOption()"><span class="mdi mdi-plus" style="color:#fff"></span></a></label>
                    <input type="text" name="selOptions[]" id="selOptions1" class="form-control mb-2 selOption" placeholder="Enter options values"  autocomplete="off" required>
                     </div>
                <div class="col-md-12">
                    <center>
                        <input type="submit" name="add" value="Add" class="btn btn-success">
                    </center>
                </div>
            </div>
            </form>
        </div>
        <!-- card end  -->
    </div>
</div>

@include('includes.footerJs')
<script>
     function addOption() {
        var lastInput = $('.selOption').last();
        var newInput = lastInput.clone();
        newInput.val('');
        lastInput.after(newInput);
    }
</script>
@include('includes.footer')
@endsection