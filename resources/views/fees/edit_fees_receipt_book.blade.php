@include('includes.headcss')
<link rel="stylesheet" href="../../../plugins/bower_components/dropify/dist/css/dropify.min.css">
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Edit Fees Receipt Book</h4>
            </div>
        </div>
        <div class="card">
		  <!-- @TODO: Create a saperate tmplate for messages and include in all tempate -->
            @if ($sessionData = Session::get('data'))
                @if($sessionData['status_code'] == 1)
                <div class="alert alert-success alert-block">
                @else
                <div class="alert alert-danger alert-block">
                @endif
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $sessionData['message'] }}</strong>
                </div>
            @endif
            <div class="row">                
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('fees_receipt_book_master.store') }}" enctype="multipart/form-data" method="post">
                    {{ method_field("POST") }}
                    @csrf
                        <div class="row">                            
                            <div class="col-md-4 form-group">
                                <label>Receipt Line 1 </label>
                                <input type="text" id='receipt_line_1' value="@if(isset($data['receipt_line_1'])){{ $data['receipt_line_1'] }}@endif" required name="receipt_line_1" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Receipt Line 2 </label>
                                <input type="text" id='receipt_line_2' value="@if(isset($data['receipt_line_2'])){{ $data['receipt_line_2'] }}@endif" required name="receipt_line_2" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Receipt Line 3 </label>
                                <input type="text" id='receipt_line_3' value="@if(isset($data['receipt_line_3'])){{ $data['receipt_line_3'] }}@endif" required name="receipt_line_3" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Receipt Line 4 </label>
                                <input type="text" id='receipt_line_4' value="@if(isset($data['receipt_line_4'])){{ $data['receipt_line_4'] }}@endif" required name="receipt_line_4" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Receipt Prefix </label>
                                <input type="text" id='receipt_prefix' value="@if(isset($data['receipt_prefix'])){{ $data['receipt_prefix'] }}@endif"  name="receipt_prefix" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Receipt Postfix </label>
                                <input type="text" id='receipt_postfix' value="@if(isset($data['receipt_postfix'])){{ $data['receipt_postfix'] }}@endif"  name="receipt_postfix" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Account Number </label>
                                <input type="text" id='account_number' value="@if(isset($data['account_number'])){{ $data['account_number'] }}@endif"  name="account_number" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Sort Order </label>
                                <input type="text" id='sort_order' value="@if(isset($data['sort_order'])){{ $data['sort_order'] }}@endif" required name="sort_order" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Last Receipt Number </label>
                                <input type="number" id='last_receipt_number' value="@if(isset($data['last_receipt_number'])){{ $data['last_receipt_number'] }}@endif"  name="last_receipt_number" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Pan </label>
                                <input type="text" id='pan'  name="pan" value="@if(isset($data['pan'])){{ $data['pan'] }}@endif" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Bank Branch </label>
                                <input type="text" id='branch' value="@if(isset($data['branch'])){{ $data['branch'] }}@endif"  name="branch" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Bank Logo </label>
                                <input type="file" accept="image/*" @if(isset($data))data-default-file="/storage/fees/{{ $data['bank_logo'] ?? '' }}" @endif name="bank_logo" id="input-file-now" class="dropify" />
                            </div>
                            <div class="col-md-4 form-group" hidden="hidden">
                                <label>Receipt Id </label>
                                <input type="hidden" id='receipt_id' value="@if(isset($data['receipt_id'])){{ $data['receipt_id'] }}@endif" required name="receipt_id" class="form-control">
                            </div>

                            @php
                                $grade_id = array();
                                if(isset($data['grade_id']))
                                {
                                    $grade_id = explode(",",$data['grade_id']);
                                }
                                $standard_id = array();
                                if(isset($data['standard_id']))
                                {
                                    $standard_id = explode(",",$data['standard_id']);
                                }
                                $fees_head_id = array();
                                if(isset($data['fees_head_id']))
                                {
                                    $fees_head_id = explode(",",$data['fees_head_id']);
                                }
                            @endphp
                            {{ App\Helpers\SearchChain('4','multiple','grade,std',$grade_id,$standard_id) }}

                            <div class="col-md-4 form-group">
                                <label>Fees Head</label>
                                <select name="fees_head_id[]" id="fees_head_id" class="form-control" required multiple>
                                    @foreach($feeHeadList as $key => $value)
                                        <option value="{{$value['id']}}" @if(in_array($value['id'],$fees_head_id)) selected @endif >{{$value['display_name']}}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="input-file-now">Receipt Logo</label>
                                <input type="file" accept="image/*" @if(isset($data))data-default-file="/storage/fees/{{ $data['receipt_logo'] ?? '' }}" @endif name="fees_receipt_logo" id="input-file-now" class="dropify" />
                            </div>
                            <div class="col-md-4 form-group" hidden="hidden">
                                <label for="input-file-now">Receipt Logo</label>
                                <input type="hidden" value="@if(isset($data['receipt_logo'])){{ $data['receipt_logo'] }}@endif" required name="receipt_logo" id="receipt_logo" class="form-control"/>
                            </div>
                            <div class="col-md-12 form-group">
                                <center>                                    
                                    <input type="submit" name="submit" value="Update" class="btn btn-success" >
                                </center>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>    
</div>

@include('includes.footerJs')
<script src="../../../plugins/bower_components/dropify/dist/js/dropify.min.js"></script>
<script>
$(document).ready(function() {
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
    drEvent.on('dropify.beforeClear', function(event, element) {
        return confirm("Do you really want to delete \"" + element.file.name + "\" ?");
    });
    drEvent.on('dropify.afterClear', function(event, element) {
        alert('File deleted');
    });
    drEvent.on('dropify.errors', function(event, element) {
        console.log('Has Errors');
    });
    var drDestroy = $('#input-file-to-destroy').dropify();
    drDestroy = drDestroy.data('dropify')
    $('#toggleDropify').on('click', function(e) {
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
