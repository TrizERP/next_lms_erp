@extends('layout')
@section('container')
<style>
    .control-bar a:hover, .control-bar input:hover, [contenteditable]:focus, [contenteditable]:hover{
        background : #fff !important;
    }
</style>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Add Triz Process</h4>
            </div>
        </div>
      
        <div class="card">
            <form action="{{route('requirements.store')}}" method="post" enctype="multipart/form-data">
                @csrf
                <div class="row">
                    <div class="col-md-4">
                        <label for="">Menu Name</label>
                        <select name="menuDetails" id="menuDetails" class="form-control" required>
                            <option value="">Select Menu</option>
                            @foreach($data['allMenu'] as $key=>$value)
                            <option value="{{$value['parent_menu_id'].'/'.$value['menu_title']}}">{{$value['menu_title']}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label for="topicType">Add Process</label>
                        <textarea name="requirements" id="requirementText" contenteditable="true" required> </textarea>
                    </div>
                    <input type="hidden" name="sub_institute_id" value="0">
                    <input type="hidden" name="process" value="1">
                    <div class="col-md-12">
                        <center>
                            <input type="submit" value="Submit" id="submit" class="btn btn-success col-md-2">
                        </center>
                    </div>
                </div>
            </form>
        </div>

    </div>
</div>

@include('includes.footerJs')
<script src="{{ asset("/ckeditor_wiris/ckeditor4/ckeditor.js") }}"></script>
<script>

    CKEDITOR.config.toolbar_Full =
        [
            {name: 'document', items: ['Source']},
            {name: 'clipboard', items: ['Cut', 'Copy', 'Paste', '-', 'Undo', 'Redo']},
            {name: 'editing', items: ['Find']},
            {name: 'basicstyles', items: ['Bold', 'Italic', 'Underline']},
            {name: 'paragraph', items: ['JustifyLeft', 'JustifyCenter', 'JustifyRight']}
        ];
    CKEDITOR.config.height = '40px';

    CKEDITOR.plugins.addExternal('divarea', '../examples/extraplugins/divarea/', 'plugin.js');
    CKEDITOR.plugins.addExternal('sharedspace', '../examples/extraplugins/sharedspace/', 'plugin.js');
    CKEDITOR.plugins.addExternal('filebrowser', '../examples/extraplugins/filebrowser/', 'plugin.js');
    CKEDITOR.plugins.addExternal('enterkey', '../examples/extraplugins/enterkey/', 'plugin.js');
    CKEDITOR.plugins.addExternal('FMathEditor', '../examples/extraplugins/FMathEditor/', 'plugin.js');
    CKEDITOR.config.removePlugins = 'maximize';
    CKEDITOR.config.removePlugins = 'resize';
    CKEDITOR.config.sharedSpaces = {top: 'toolbar1'};
    CKEDITOR.replace('requirementText', {
        extraPlugins: 'filebrowser,divarea,sharedspace,FMathEditor,enterkey',
        enterMode: '2',
        language: 'en',
        filebrowserUploadUrl: "{{route('uploadimage',['_token' => csrf_token() ])}}",
        filebrowserUploadMethod: 'form'
    });
    var editor = CKEDITOR.instances['requirementText'];

    editor.on('blur', function () {
        // Call the check_input function when the CKEditor loses focus
        check_input(editor.getData());
    });

    function check_input(inputElement) {

        var inputValue = inputElement.value;
        var editor = CKEDITOR.instances['requirementText'];
        console.log(editor.getData())
    }
</script>
@include('includes.footer')
@endsection
