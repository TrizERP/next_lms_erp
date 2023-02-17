@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')

<style>
    .customers {
        font-family: "Trebuchet MS", Arial, Helvetica, sans-serif;
        border-collapse: collapse;
        width: 100%;
    }

    .customers td, .customers th {
        border: 1px solid #ddd;
        padding: 8px;
    }

    .customers tr:nth-child(even){background-color: #f2f2f2;}

    .customers tr:hover {background-color: #ddd;}

    .customers th {
        padding-top: 12px;
        padding-bottom: 12px;
        text-align: left;
        background-color: #4CAF50;
        color: white;
    }
</style>
<div id="page-wrapper">
    <div class="container-fluid">        
            <div class="card">
                @if ($message = Session::get('success'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                </div>
                @endif
                <div class="row">
                    <div class="col-lg-12 col-sm-12 col-xs-12">
                        <?php echo $data['stu_data']; ?>
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
    document.body.className = document.body.className.replace("fix-header", "fix-header show-sidebar hide-sidebar");
//    $(function () {
//        var $tblChkBox = $("input:checkbox");
//        $("#ckbCheckAll").on("click", function () {
//            $($tblChkBox).prop('checked', $(this).prop('checked'));
//        });
//    });
</script>
@include('includes.footer')
