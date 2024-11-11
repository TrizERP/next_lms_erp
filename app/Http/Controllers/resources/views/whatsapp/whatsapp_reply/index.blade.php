@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<style>
    .chat-container {
        max-height: 400px; /* Set your desired height */
        overflow-y: auto; /* Enable vertical scrolling */
        padding: 10px;
        border: 1px solid #ccc;
        background-color: #f9f9f9;
    }

    .chat-message {
        padding: 10px;
        border-radius: 20px;
        margin: 5px 0;
        display: inline-block;
        max-width: 75%;
    }

    .chat-message.incoming {
        background-color: #e1ffc7;
        text-align: left;
        float: left;
        clear: both;
    }

    .chat-message.outgoing {
        background-color: #cce5ff;
        text-align: right;
        float: right;
        clear: both;
    }

    .message-date {
        display: block;
        font-size: 0.8em;
        color: #777;
        margin-top: 5px;
    }

    textarea#message {
        width: 100%;
        padding: 10px;
        border-radius: 5px;
        border: 1px solid #ccc;
        resize: none;
    }

    .btn-primary {
        background-color: #007bff;
        border-color: #007bff;
        color: #fff;
        padding: 10px 20px;
        border-radius: 5px;
        font-size: 1rem;
        cursor: pointer;
    }

    .btn-primary:hover {
        background-color: #0056b3;
        border-color: #004085;
    }
    
    .control-bar a:hover, .control-bar input:hover, [contenteditable]:focus, [contenteditable]:hover{
        background : #fff !important;
    }
</style>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Whatsapp Chats {{$data['wid']}}</h4>
            </div>
        </div>
        <div class="card">
            @if ($sessionData = Session::get('data'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $sessionData['message'] }}</strong>
                </div>
            @endif
            <div class="row">
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive chat-container">
                        @foreach($data['chats'] as $chat)
                            @if($chat['type'] == 'incoming')
                                <p class="chat-message incoming"> {{$chat['message']}} <span
                                        class="message-date">{{$chat['message_date']}}</span></p>
                            @else
                                <p class="chat-message outgoing"> {{$chat['message']}} <span
                                        class="message-date">{{$chat['message_date']}}</span></p>
                            @endif
                        @endforeach
                    </div>
                    <form action="{{ route('send_whatsapp_reply_message.store') }}" enctype="multipart/form-data"
                          method="post">
                        @csrf
                        <div class="row align-items-center">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="topicType">Messages</label>
                                    <textarea name="message" id="question_title" class="form-control" rows="3"
                                              placeholder="Type your message here..." contenteditable="true"></textarea>
                                    @error('message')
                                    <span style="color: red">{{$message}}</span>
                                    @enderror
                                </div>
                                <input type="hidden" name="wid" value="{{$data['wid']}}">
                            </div>

                            <div class="col-md-12 text-right">
                                <button type="submit" class="btn btn-primary">Send</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @include('includes.footerJs')

    <script src="{{ asset("/plugins/bower_components/datatables/datatables.min.js") }}"></script>
    <script>
        $(document).ready(function () {
            $('#example').DataTable();
        });

    </script>
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
@include('includes.footer')