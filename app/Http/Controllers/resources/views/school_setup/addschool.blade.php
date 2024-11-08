@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Add School</h4> </div>
            <!-- /.col-lg-12 -->
        </div>
        <!-- /.row -->
        <!-- ============================================================== -->
        <!-- Different data widgets -->
        <!-- ============================================================== -->
        <!-- .row -->
        <div class="row">
                    <div class="col-sm-12">
                        <div class="white-box">
                            <h3 class="box-title m-b-0">Default Basic Forms</h3>
                            <p class="text-muted m-b-30 font-13"> All bootstrap element classies </p>
                            <form class="form-horizontal">
                                @csrf
                                <div class="form-group">
                                    <label class="col-md-12">Default Text <span class="help"> e.g. "George deo"</span></label>
                                    <div class="col-md-12">
                                        <input type="text" class="form-control" value="George deo..."> </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-md-12" for="example-email">Email <span class="help"> e.g. "example@gmail.com"</span></label>
                                    <div class="col-md-12">
                                        <input type="email" id="example-email" name="example-email" class="form-control" placeholder="Email"> </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-md-12">Password</label>
                                    <div class="col-md-12">
                                        <input type="password" class="form-control" value="password"> </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-md-12">Placeholder</label>
                                    <div class="col-md-12">
                                        <input type="text" class="form-control" placeholder="placeholder"> </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-md-12">Text area</label>
                                    <div class="col-md-12">
                                        <textarea class="form-control" rows="5"></textarea>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-sm-12">Input Select</label>
                                    <div class="col-sm-12">
                                        <select class="form-control">
                                            <option>1</option>
                                            <option>2</option>
                                            <option>3</option>
                                            <option>4</option>
                                            <option>5</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-sm-12">File upload</label>
                                    <div class="col-sm-12">
                                        <div class="fileinput fileinput-new input-group" data-provides="fileinput">
                                            <div class="form-control" data-trigger="fileinput"> <i class="glyphicon glyphicon-file fileinput-exists"></i> <span class="fileinput-filename"></span></div> <span class="input-group-addon btn btn-default btn-file"> <span class="fileinput-new">Select file</span> <span class="fileinput-exists">Change</span>
                                            <input type="file" name="..."> </span> <a href="#" class="input-group-addon btn btn-default fileinput-exists" data-dismiss="fileinput">Remove</a> </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-sm-12">Helping text</label>
                                    <div class="col-sm-12">
                                        <input type="text" class="form-control" placeholder="Helping text"> <span class="help-block"><small>A block of help text that breaks onto a new line and may extend beyond one line.</small></span> </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
    <!-- /.container-fluid -->
<!-- ============================================================== -->
<!-- End Page Content -->
<!-- ============================================================== -->
</div>

@include('includes.footerJs')
@include('includes.footer')
