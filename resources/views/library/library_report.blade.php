@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Library Report</h4>
            </div>
        </div>
        <div class="card">
            @if(!empty($data['message']))
                <div class="alert alert-{{ $data['class'] }} alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $data['message'] }}</strong>
                </div>
            @endif
            <div class="col-lg-12 col-sm-12 col-xs-12">
                <form action="{{ route('show_library_report') }}" method="post">
                    {{ method_field("POST") }}
                    {{csrf_field()}}

                    <div class="row">

                        <div class="col-md-4 form-group">
                            <label for="report_of">Select Report</label>
                            <select name="report_of" id="report_of" class="form-control" required
                                    onchange="check_report(this.value);">
                                <option value="">Select Report</option>
                                <option value="material_resource">Material Resource</option>
                                <option value="author">Author</option>
                                <option value="publisher_name">Publisher Name</option>
                                <option value="publishing_place">Publishing Place</option>
                                <option value="language">Language</option>
                                <option value="subject">Subject</option>
                            </select>
                        </div>

                        <div class="col-md-4 form-group" style="display: none;" id="for_material_resource">
                            <label>Material Resource</label>
                            <input type="text" id="material_resource" name="material_resource" class="form-control"
                                   autocomplete="off">
                        </div>

                        <div class="col-md-4 form-group" style="display: none;" id="for_author">
                            <label>Author</label>
                            <input type="text" id="author" name="author" class="form-control"
                                   autocomplete="off">
                        </div>

                        <div class="col-md-4 form-group" style="display: none;" id="for_publisher_name">
                            <label>Publisher Name</label>
                            <input type="text" id="publisher_name" name="publisher_name" class="form-control"
                                   autocomplete="off">
                        </div>

                        <div class="col-md-4 form-group" style="display: none;" id="for_publishing_place">
                            <label>Publishing Place</label>
                            <input type="text" id="publishing_place" name="publishing_place" class="form-control"
                                   autocomplete="off">
                        </div>

                        <div class="col-md-4 form-group" style="display: none;" id="for_language">
                            <label>Language</label>
                            <input type="text" id="language" name="language" class="form-control"
                                   autocomplete="off">
                        </div>

                        <div class="col-md-4 form-group" style="display: none;" id="for_subject">
                            <label>Subject</label>
                            <input type="text" id="subject" name="subject" class="form-control"
                                   autocomplete="off">
                        </div>

                        <div class="col-md-12 form-group">
                            <center>
                                <input type="submit" name="submit" value="Search" class="btn btn-success">
                            </center>
                        </div>

                    </div>
                </form>
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
<script type="text/javascript">
    function check_report (report_val) {
        if (report_val == 'material_resource') {
            document.getElementById('for_material_resource').style.display = 'block';
            document.getElementById('for_author').style.display = 'none';
            document.getElementById('for_publisher_name').style.display = 'none';
            document.getElementById('for_publishing_place').style.display = 'none';
            document.getElementById('for_language').style.display = 'none';
            document.getElementById('for_subject').style.display = 'none';
        }

        if (report_val == 'author') {
            document.getElementById('for_material_resource').style.display = 'none';
            document.getElementById('for_author').style.display = 'block';
            document.getElementById('for_publisher_name').style.display = 'none';
            document.getElementById('for_publishing_place').style.display = 'none';
            document.getElementById('for_language').style.display = 'none';
            document.getElementById('for_subject').style.display = 'none';
        }

        if (report_val == 'publisher_name') {
            document.getElementById('for_material_resource').style.display = 'none';
            document.getElementById('for_author').style.display = 'none';
            document.getElementById('for_publisher_name').style.display = 'block';
            document.getElementById('for_publishing_place').style.display = 'none';
            document.getElementById('for_language').style.display = 'none';
            document.getElementById('for_subject').style.display = 'none';
        }

        if (report_val == 'publishing_place') {
            document.getElementById('for_material_resource').style.display = 'none';
            document.getElementById('for_author').style.display = 'none';
            document.getElementById('for_publisher_name').style.display = 'none';
            document.getElementById('for_publishing_place').style.display = 'block';
            document.getElementById('for_language').style.display = 'none';
            document.getElementById('for_subject').style.display = 'none';
        }

        if (report_val == 'language') {
            document.getElementById('for_material_resource').style.display = 'none';
            document.getElementById('for_author').style.display = 'none';
            document.getElementById('for_publisher_name').style.display = 'none';
            document.getElementById('for_publishing_place').style.display = 'none';
            document.getElementById('for_language').style.display = 'block';
            document.getElementById('for_subject').style.display = 'none';
        }

        if (report_val == 'subject') {
            document.getElementById('for_material_resource').style.display = 'none';
            document.getElementById('for_author').style.display = 'none';
            document.getElementById('for_publisher_name').style.display = 'none';
            document.getElementById('for_publishing_place').style.display = 'none';
            document.getElementById('for_language').style.display = 'none';
            document.getElementById('for_subject').style.display = 'block';
        }
    }

  
</script>
@include('includes.footer')
