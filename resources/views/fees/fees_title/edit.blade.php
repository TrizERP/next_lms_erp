@include('../includes.headcss')
<link rel="stylesheet" href="../../../plugins/bower_components/dropify/dist/css/dropify.min.css">
@include('../includes.header')
@include('../includes.sideNavigation')


<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">            
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">                
                <h4 class="page-title">Edit Fees Tilte</h4>            
            </div>                    
        </div>
        <div class="card">
            @if ($message = Session::get('success'))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $message }}</strong>
            </div>
            @endif
            <div class="row">                
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('fees_title.update', $data['id']) }}" enctype="multipart/form-data" method="post">
                    {{ method_field("PUT") }}
                    {{csrf_field()}}
                        <div class="row">                            
                            <div class="col-md-3 form-group">
                                <label>Fees Title</label>
                                <select name="fees_title_id" id="title" class="form-control van">
                                    <option value="">--Select--</option>
                                    <?php
                                    foreach ($data['data']['ddTtitle'] as $id => $arr) {
                                        if ($data['fees_title_id'] == $id) {
                                            $selected = "selected=selected";
                                        } else {
                                            $selected = "";
                                        }
                                        echo "<option $selected value='$id'>$arr</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Display Name</label>
                                <input type="text" id='dispaly_name' value="{{ $data['display_name'] }}" required name="display_name" class="form-control">
                            </div>
                            <div class="col-md-3 form-group">
                              <label>Sort Order</label>
                              <input type="number" id='sort_order' name="sort_order" class="form-control">
                          </div>
                            <div class="col-md-3 form-group">
                                <label>Cumulative Name</label>
                                <input type="text" id='cumulative_name' value="{{ $data['cumulative_name'] }}" name="cumulative_name" class="form-control">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Append Name</label>
                                <input type="text" id='append_name' value="{{ $data['append_name'] }}" name="append_name" class="form-control">
                            </div>
                            <div class="col-md-2 form-group">
                                <label>Mandetory </label>
                                <div class="checkbox checkbox-info">
                                    <?php if ($data['mandatory'] == 1) { ?>
                                    <input id="mandatory" name="mandatory" value="1" type="checkbox" checked="checked">
                                    <?php } else { ?>
                                        <input id="mandatory" name="mandatory" value="1" type="checkbox">
                                    <?php } ?>
                                    <label for="mandatory"> Mandatory </label>
                                </div>
                            </div>
                            <div class="col-md-12 form-group">
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


@include('includes.footer')
