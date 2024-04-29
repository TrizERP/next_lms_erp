@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')


<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Send Whatsapp Messages</h4>
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
                    @php
                        if(isset($data['stu_data'])){
                    @endphp
                    <form action="{{ route('send_whatsapp_message.store') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("POST") }}
                        {{csrf_field()}}
                        <input type="hidden" name="grade" value="<?php echo $data['grade']; ?>">
                        <input type="hidden" name="standard" value="<?php echo $data['standard']; ?>">
                        <input type="hidden" name="division" value="<?php echo $data['division']; ?>">
                        <div class="row align-items-center">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="topicType">Messages</label>
                                    <textarea name="message" id="question_title"
                                              contenteditable="true">

                                        </textarea>
                                    @error('message')
                                    <span style="color: red">{{$message}}</span>
                                    @enderror
                                </div>
                            </div>

                        </div>
                        <div class="table-responsive">
                            <table class="table-bordered table" id="myTable" width="100%">
                                <tr>
                                    <th><input type="checkbox" name="all" id="ckbCheckAll" class="ckbox">  </th>
                                    <th>No</th>
                                    <th>Student Name</th>
                                    <th>GR.No.</th>
                                    <th>Mobile</th>
                                </tr>
                                @php

                                    $arr = $data['stu_data'];
                                    foreach ($arr as $id=>$col_arr){
                                @endphp
                                <tr>

                                    <td><input type="checkbox" name="@php echo 'sendNotification['.$col_arr['student_id'].']'; @endphp" class="ckbox1">  </td>
                                    <td>@php echo $id+1; @endphp</td>
                                    <td>@php echo $col_arr['name']; @endphp</td>
                                    <td>@php echo $col_arr['enrollment_no']; @endphp</td>
                                    <td>@php echo $col_arr['mobile']; @endphp</td>

                                </tr>
                                @php
                                    }
                                @endphp
                            </table>
                        </div>
                        <div class="row">
                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Save" class="btn btn-success" >
                                </center>
                            </div>
                        </div>
                    </form>
                    @php
                        }else{
                    @endphp
                    <div class="row">
                        <div class="col-md-12 form-group">
                            <center>
                                <span>No Record Found</span>
                            </center>
                        </div>
                    </div>
                    @php
                        }
                    @endphp
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
    $(function () {
        var $tblChkBox = $("input:checkbox");
        $("#ckbCheckAll").on("click", function () {
            $($tblChkBox).prop('checked', $(this).prop('checked'));
        });
    });
</script>
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
    CKEDITOR.replace('question_title', {
        extraPlugins: 'filebrowser,divarea,sharedspace,FMathEditor,enterkey',
        enterMode: '2',
        language: 'en',
        filebrowserUploadUrl: "{{route('uploadimage',['_token' => csrf_token() ])}}",
        filebrowserUploadMethod: 'form'
    });
    var editor = CKEDITOR.instances['question_title'];

    editor.on('blur', function () {
        // Call the check_input function when the CKEditor loses focus
        check_input(editor.getData());
    });

    function check_input(inputElement) {

        var inputValue = inputElement.value;
        var editor = CKEDITOR.instances['question_title'];
        console.log(editor.getData())
    }
</script>
@if (!isset($data['stu_data']))
    @if(isset(Session::get('erpTour')['fees_collect']) && Session::get('erpTour')['fees_collect'] == 0)
        <link rel="stylesheet" href="../../../tooltip/enjoyhint/jquery.enjoyhint.css">

        <script src="../../../tooltip/bower_components/todomvc-common/base.js"></script>
        <!-- <script src="../../../tooltip/bower_components/jquery/jquery.js"></script> -->
        <script src="../../../tooltip/bower_components/underscore/underscore.js"></script>
        <script src="../../../tooltip/bower_components/backbone/backbone.js"></script>
        <script src="../../../tooltip/bower_components/backbone.localStorage/backbone.localStorage.js"></script>
        <script src="../../../tooltip/js/models/todo.js"></script>
        <script src="../../../tooltip/js/collections/todos.js"></script>
        <script src="../../../tooltip/js/views/todo-view.js"></script>
        <script src="../../../tooltip/js/views/app-view.js"></script>
        <script src="../../../tooltip/js/routers/router.js"></script>
        <script src="../../../tooltip/js/app.js"></script>
        <script src="../../../tooltip/enjoyhint/enjoyhint.js"></script>
        <script src="../../../tooltip/enjoyhint/jquery.enjoyhint.js"></script>
        <script src="../../../tooltip/enjoyhint/kinetic.min.js"></script>
        <script>
            localStorage.clear();
            var enjoyhint_script_data = [
                {
                    onBeforeStart: function () {
                        $('#grade').change(function (e) {

                            enjoyhint_instance.trigger('new_todo');

                        });
                    },
                    selector: '#grade',
                    event: 'new_todo',
                    event_type: 'custom',
                    description: 'Select Grade Here.'
                },
                {
                    onBeforeStart: function () {
                        $('#standard').change(function (e) {

                            enjoyhint_instance.trigger('new_todo');

                        });
                    },
                    selector: '#standard',
                    event: 'new_todo',
                    event_type: 'custom',
                    description: 'Select Standard Here.'
                },
                {
                    selector: '#division',
                    event: 'change',
                    description: 'Select Division Here.',
                    timeout: 100
                },
                {
                    selector: '.btn-success',
                    event: 'click',
                    description: 'Please press to search students.',
                    timeout: 100
                }
            ];
            var enjoyhint_instance = null;
            $(document).ready(function () {
                enjoyhint_instance = new EnjoyHint({});
                enjoyhint_instance.setScript(enjoyhint_script_data);
                enjoyhint_instance.runScript();
            });
        </script>

        <script type="text/javascript">
            var url = "http://dev.triz.co.in/tourUpdate?module=fees_collect";
            var xhttp = new XMLHttpRequest();
            xhttp.onreadystatechange = function () {
                if (this.readyState == 4 && this.status == 200) {
                    console.log("success");
                }
            };
            xhttp.open("GET", url, true);
            xhttp.send();
        </script>
    @endif
@endif

<script>
    $(document).ready(function () {
        var table = $('#example').DataTable({
            select: true,
            lengthMenu: [
                [100, 500, 1000, -1],
                ['100', '500', '1000', 'Show All']
            ],
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'pdfHtml5',
                    title: 'Fees Monthly Report',
                    orientation: 'landscape',
                    pageSize: 'LEGAL',
                    pageSize: 'A0',
                    exportOptions: {
                        columns: ':visible'
                    },
                },
                {extend: 'csv', text: ' CSV', title: 'Fees Monthly Report'},
                {extend: 'excel', text: ' EXCEL', title: 'Fees Monthly Report'},
                {extend: 'print', text: ' PRINT', title: 'Fees Monthly Report'},
                'pageLength'
            ],
        });

        $('#example thead tr').clone(true).appendTo('#example thead');
        $('#example thead tr:eq(1) th').each(function (i) {
            var title = $(this).text();
            $(this).html('<input type="text" placeholder="Search ' + title + '" />');

            $('input', this).on('keyup change', function () {
                if (table.column(i).search() !== this.value) {
                    table
                        .column(i)
                        .search(this.value)
                        .draw();
                }
            });
        });
    });
</script>


@if(app('request')->input('implementation') == 1)
    <script type="text/javascript">
        document.body.className = document.body.className.replace("fix-header", "fix-header show-sidebar hide-sidebar");
        document.getElementById('main-header').style.display = 'none';
    </script>
@endif
@include('includes.footer')
