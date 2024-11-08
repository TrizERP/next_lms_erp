@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">School</h4></div>
            <!-- /.col-lg-12 -->
        </div>
        <!-- /.row -->
        <!-- ============================================================== -->
        <!-- Different data widgets @if(!empty($data['message'])){{ $data['message'] }} @endif -->
        <!-- ============================================================== -->
        <!-- .row -->
        <div class="row">

                    <div class="col-lg-12 col-sm-12">
                        <div class="white-box">

                            <div class="button-box">
                                <button class="fcbtn btn btn-primary btn-outline btn-1b">Admission</button>
                                <button class="fcbtn btn btn-info btn-outline btn-1b">Fees</button>
                                <button class="fcbtn btn btn-warning btn-outline btn-1b">Inword - Outword</button>
                                <button class="fcbtn btn btn-danger btn-outline btn-1b">Inventory</button>
                                <button class="fcbtn btn btn-success btn-outline btn-1b">Easy Com</button>
                                <button class="fcbtn btn btn-primary btn-outline btn-1b">Mobile Apps</button>
                                <button class="fcbtn btn btn-info btn-outline btn-1b">Hostel Management</button>
                            </div>
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
