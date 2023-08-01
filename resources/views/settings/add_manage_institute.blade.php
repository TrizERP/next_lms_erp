@include('includes.headcss')
<link rel="stylesheet" href="../../../plugins/bower_components/dropify/dist/css/dropify.min.css">
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Add Institute</h4> </div>
        </div>        
        <div class="card">
            <div class="panel-body">
               @if ($message = Session::get('success'))
                    <div class="alert alert-success alert-block">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $message }}</strong>
                    </div>
                    @endif
                   
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="@if(isset($data->Id))
                          {{ route('manage_institute.update', $data->Id) }}
                          @else
                          {{ route('manage_institute.store') }}
                          @endif" enctype="multipart/form-data" method="post">
                          @if(!isset($data->Id))
                        {{ method_field("POST") }}
                        @else
                        {{ method_field("PUT") }}
                        @endif
                            @csrf
                      
                        <div class="row">
                        <div class="col-md-4 form-group">
                            <label>Select Institute Type<span style="color: red;font-size: large;">*</span></label>
                            <select class="form-control" name="institute_type" id="institute_type" required @if(isset($data->institute_type)) style="pointer-events:none" readonly @endif>
                                <option value="" >Select Institute</option>
                                <option value="school" @if(isset($data->institute_type) && $data->institute_type="school") selected @endif>School</option>
                                <option value="college" @if(isset($data->institute_type) && $data->institute_type="college") selected @endif>College</option>                                            
                            </select>
                        </div>
                            <div class="col-md-4 form-group">
                                <label>Institute Name<font color="red">*</font></label>
                                <input type="text" id='SchoolName' value="@if(isset($data->SchoolName)){{$data->SchoolName}}@endif" required name="SchoolName" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Institute Code<font color="red">*</font></label>
                                <input type="text" id='ShortCode' value="@if(isset($data->ShortCode)){{$data->ShortCode}}@endif" required name="ShortCode" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Contact Person<font color="red">*</font></label>
                                <input type="text" id='ContactPerson' value="@if(isset($data->ContactPerson)){{$data->ContactPerson}}@endif" required name="ContactPerson" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Mobile<font color="red">*</font></label>
                                <input type="text" id='Mobile' value="@if(isset($data->Mobile)){{$data->Mobile}}@endif" required name="Mobile" class="form-control" pattern="[789][0-9]{9}" maxlength="10">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Email<font color="red">*</font></label>
                                <input type="email" id='Email' value="@if(isset($data->Email)){{$data->Email}}@endif" required name="Email" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Receipt Header<font color="red">*</font></label>
                                <input type="text" id='ReceiptHeader' value="@if(isset($data->ReceiptHeader)){{$data->ReceiptHeader}}@endif" required name="ReceiptHeader" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Receipt Address<font color="red">*</font></label>
                                <input type="text" id='ReceiptAddress' value="@if(isset($data->ReceiptAddress)){{$data->ReceiptAddress}}@endif" required name="ReceiptAddress" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Fee Email<font color="red">*</font></label>
                                <input type="email" id='FeeEmail' value="@if(isset($data->FeeEmail)){{$data->FeeEmail}}@endif" required name="FeeEmail" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Receipt Contact<font color="red">*</font></label>
                                <input type="text" id='ReceiptContact' value="@if(isset($data->ReceiptContact)){{$data->ReceiptContact}}@endif" required name="ReceiptContact" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Sort Order<font color="red">*</font></label>
                                <input type="number" id='SortOrder' value="@if(isset($data->SortOrder)){{$data->SortOrder}}@endif" required name="SortOrder" class="form-control">
                            </div>                                                                                       
                            <div class="col-md-4 ol-sm-4 col-xs-12">                         
                                <label for="input-file-now">Institute Logo<font color="red">*</font></label>
                                @if(isset($data->Logo))
                                <div class="logo p-2">
                                <img src="/admin_dep/images/{{$data->Logo}}" style="height:50px" alt="logo">
                                </div>
                                @endif
                                <input type="file" accept="image/*" name="Logo" @if(isset($data->Logo)) @else required @endif id="input-file-now" class="dropify" /> 
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Cheque Return Charges</label>
                                <input type="number" id='cheque_return_charges' value="@if(isset($data->cheque_return_charges)){{$data->cheque_return_charges}}@endif" name="cheque_return_charges" class="form-control">
                            </div> 
                            <div class="col-md-12 form-group mt-3">
                                <center>
                                    <input type="submit" name="submit" value="Save" class="btn btn-success" >
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
