<?php
session_start();

if (isset($_REQUEST['sub_institute_iderp']) && $_REQUEST['sub_institute_iderp'] != '') {
    $_SESSION['SUB_INSTITUTE_ID'] = $_REQUEST['sub_institute_iderp'];
}

?>
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="">
        <meta name="author" content="">
        <link rel="icon" type="image/png" sizes="16x16" href="images/favicon.png">
        <title>TRIZ ERP</title>

        <!-- Bootstrap Core CSS -->
        <link href="https://erp.triz.co.in/admin_dep/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
        <!-- Menu CSS -->
        <link href="https://erp.triz.co.in/plugins/bower_components/sidebar-nav/dist/sidebar-nav.min.css"
              rel="stylesheet">
        <link href="https://erp.triz.co.in/plugins/bower_components/css-chart/css-chart.css" rel="stylesheet">

        <!-- chartist CSS -->
        <link href="https://erp.triz.co.in/plugins/bower_components/chartist-js/dist/chartist.min.css" rel="stylesheet">
        <link
            href="https://erp.triz.co.in/plugins/bower_components/chartist-plugin-tooltip-master/dist/chartist-plugin-tooltip.css"
            rel="stylesheet">
        <!-- Calendar CSS -->
        <link href="https://erp.triz.co.in/plugins/bower_components/calendar/dist/fullcalendar.css" rel="stylesheet">
        <!-- animation CSS -->
        <link href="https://erp.triz.co.in/admin_dep/css/animate.css" rel="stylesheet">
        <!-- Custom CSS -->
        <link href="https://erp.triz.co.in/plugins/bower_components/morrisjs/morris.css" rel="stylesheet">
        <link href="https://erp.triz.co.in/admin_dep/css/style.css" rel="stylesheet">
        <link href="https://erp.triz.co.in/admin_dep/css/triz-style.css" rel="stylesheet">
        <!-- color CSS -->
        <link href="https://erp.triz.co.in/admin_dep/css/colors/default.css" id="theme" rel="stylesheet">
        <!-- Morris CSS -->

        <!-- <link href="https://erp.triz.co.in/plugins/bower_components/toast-master/css/jquery.toast.css" rel="stylesheet"> -->

        <link href="https://erp.triz.co.in/plugins/bower_components/bootstrap-datepicker/bootstrap-datepicker.min.css"
              rel="stylesheet" type="text/css"/>


        <link href="https://erp.triz.co.in/plugins/bower_components/datatables/media/css/dataTables.bootstrap.css"
              rel="stylesheet"
              type="text/css"/>


        <link href="https://cdn.datatables.net/buttons/1.5.6/css/buttons.dataTables.min.css" rel="stylesheet"
              type="text/css"/>
        <style type="text/css">
            @media print {
                .pagebreak {
                    page-break-before: always;
                }

                /* page-break-after works, as well */
            }
        </style>

        <!-- Global site tag (gtag.js) - Google Analytics -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=UA-153077517-1"></script>
        <script>
            window.dataLayer = window.dataLayer || [];

            function gtag() {
                dataLayer.push(arguments);
            }

            gtag('js', new Date());
            gtag('config', 'UA-153077517-1');
        </script>

    </head>
    <link rel="stylesheet" href="../../../tooltip/enjoyhint/jquery.enjoyhint.css">
    <body class="fix-header">
    <!-- ============================================================== -->
    <!-- Preloader -->
    <!-- ============================================================== -->
    <!--<div class="preloader">-->
    <!--    <svg class="circular" viewBox="25 25 50 50">-->
    <!--        <circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10" />-->
    <!--    </svg>-->
    <!--</div>-->
    <!-- ============================================================== -->
    <!-- Wrapper -->
    <!-- ============================================================== -->
    <div id="wrapper">
        <!-- ============================================================== -->
        <div id="page-wrapper">
            <div class="container-fluid">
                <div class="mt-30">
                    <div class="white-box">
                        <div class="row">
                            <div class="col-lg-6 col-md-8 col-sm-8 col-xs-12">
                                <h4 class="page-title">Welcome to TRIZ ERP</h4>
                            </div>
                            <div class="col-lg-6 col-sm-8 col-md-8 col-xs-12">
                                <button
                                    class="right-side-toggle waves-effect waves-light btn-info btn-circle pull-right m-l-20">
                                    <i class="ti-settings text-white"></i>
                                </button>
                            </div>
                            <!-- /.col-lg-12 -->
                        </div>
                    </div>
                </div>
                <!-- /.row -->
                <!-- ============================================================== -->
                <!-- Different data widgets Success  -->
                <!-- ============================================================== -->
                <!-- .row -->
                <div class="row">
                    <div class="white-box">
                        <div class="panel-body">

                            <?php
                            include 'db.php';

                            $getTables = mysqli_query($cn, "SELECT * FROM import_table_fields group by table_name order by id");

                            echo "<form name=subexam class=form-inline id=subexam method='GET'>";

                            ?>
                            <div class="form-group">
                                <input type="hidden" name="sub_institute_iderp"
                                       value="<?php $_REQUEST['sub_institute_iderp'] ?? '' ?>">
                                <label for="email">Module:</label>
                                <select name="fileName" id="table">
                                    <option value=""> Select Module Name</option>
                                    <?php
                                    while ($value = mysqli_fetch_assoc($getTables)) {
                                        ?>
                                        <option
                                            value="<?php echo "UPLOAD_" . $value['table_name'] . ".xlsx" ?>"><?php echo $value['display_table_name'] ?></option>
                                        <?php
                                    }
                                    ?>
                                </select>
                            </div>
                            <?php

                            echo '          <input type="submit" name="sbtsubmit" value="Submit" class="btn btn-default"/>';
                            echo "</form>";

                            if (isset($_REQUEST['sbtsubmit']) && $_REQUEST['sbtsubmit'] == 'Submit') {
//                                $coexamSql = '';
//                                $titleArr = array();
//                                $stuSqlRet = array();
//                                $coexamSql .= "SELECT * FROM import_table_fields WHERE table_name = '" . $_REQUEST['table'] . "' AND display_status = 1";
//                                //echo $coexamSql;die();
//                                $coexamSqlRET = mysqli_query($cn, $coexamSql);
//                                while ($dbRows = mysqli_fetch_assoc($coexamSqlRET)) {
//                                    $titleArr[$dbRows['field']] = $dbRows['field'];
//                                }
//
////    $titleArr['Pass & Promoted to'] = 'Pass & Promoted to';
//                                /*
//                                     *  Export Code Start
//                            */
//                                // $sheetPaasWord = 'Tr!z~!NnOv@t!0N';
//                                $sheetPaasWord = '';
//                                $excelVersion = '2007';
//
//                                if ($excelVersion == '2003') {
//                                    $exportfileName = "UPLOAD_" . $_REQUEST['table'] . ".xls";
//                                } else if ($excelVersion == '2007') {
//                                    $exportfileName = "UPLOAD_" . $_REQUEST['table'] . ".xlsx";
//                                }
//                                //echo "<pre>";
//                                //print_r($titleArr);
//                                $fileName = exportExcel($titleArr, $stuSqlRet, $exportfileName, $excelVersion, $sheetPaasWord);

//                                $valid_str = "";
//                                $valid_str .= "<script>";
//                                $valid_str .= "console.log(324)";
//                                $valid_str .= 'window.location.href = "export_xlsx.php?fileName=' . $fileName . '"';
//                                $valid_str .= "</script>";
//                                echo $valid_str;
                            }
                            if (isset($_GET['fileName']) && $_GET['fileName'] != '') {
                                $fileName = $_REQUEST['fileName'];
                                echo "<div>";
                                echo "<form enctype=\"multipart/form-data\" name=downloadForm id=downloadForm METHOD='POST'>";
                                if (file_exists('assets/' . $fileName)) {
                                    echo "<table border=0>";
                                    echo "<p>Please download file and click on upload button to upload data.</p>";
                                    echo '<tr><td align=center><a href="assets/' . $fileName . '" class="btn_medium downloadBtn">Download File</a></td><td align=center><a href="Import_xlsx_data.php" class="btn_medium downloadBtn">Upload Excel File</a></td></tr>';
                                    echo "</table>";
                                }
                                echo "</form>";
                                echo "</div>";
                            }

                            ?>

                        </div>

                    </div>
                </div>

            </div>
            <!-- /.container-fluid -->
            <!-- ============================================================== -->
            <!-- End Page Content -->
            <!-- ============================================================== -->
        </div>

        <footer class="footer text-center"> 2020 &copy; Triz Innovation PVT LTD.</footer>
    </div>

    <!-- /#wrapper -->
    <!-- jQuery -->
    <script src="https://erp.triz.co.in/plugins/bower_components/jquery/dist/jquery.min.js"></script>
    <!-- Bootstrap Core JavaScript -->
    <script src="https://erp.triz.co.in/admin_dep/bootstrap/dist/js/bootstrap.min.js"></script>
    <!-- Menu Plugin JavaScript -->
    <script src="https://erp.triz.co.in/plugins/bower_components/sidebar-nav/dist/sidebar-nav.min.js"></script>
    <!--slimscroll JavaScript -->
    <script src="https://erp.triz.co.in/admin_dep/js/jquery.slimscroll.js"></script>
    <!--Wave Effects -->
    <script src="https://erp.triz.co.in/admin_dep/js/waves.js"></script>
    <!-- chartist chart -->
    <script src="https://erp.triz.co.in/plugins/bower_components/chartist-js/dist/chartist.min.js"></script>
    <script
        src="https://erp.triz.co.in/plugins/bower_components/chartist-plugin-tooltip-master/dist/chartist-plugin-tooltip.min.js"></script>
    <!-- Sparkline chart JavaScript -->
    <script src="https://erp.triz.co.in/plugins/bower_components/jquery-sparkline/jquery.sparkline.min.js"></script>
    <!-- Custom Theme JavaScript -->
    <script src="https://erp.triz.co.in/admin_dep/js/custom.min.js"></script>
    <script src="https://erp.triz.co.in/admin_dep/js/dashboard1.js"></script>

    <!--Style Switcher -->
    <script src="https://erp.triz.co.in/plugins/bower_components/styleswitcher/jQuery.style.switcher.js"></script>

    <script
        src="https://erp.triz.co.in/plugins/bower_components/jquery.easy-pie-chart/dist/jquery.easypiechart.min.js"></script>
    <script src="https://erp.triz.co.in/plugins/bower_components/jquery.easy-pie-chart/easy-pie-chart.init.js"></script>
    <!-- <script src="https://erp.triz.co.in/plugins/bower_components/toast-master/js/jquery.toast.js"></script> -->
    <script
        src="https://erp.triz.co.in/plugins/bower_components/bootstrap-datepicker/bootstrap-datepicker.min.js"></script>

    <!--<script src="https://erp.triz.co.in/admin_dep/js/sweetalert.min.js"></script>-->

    <script>
        //Google Analytics
        setInterval(function () {
            var path = "https://erp.triz.co.in/school_setup/google-analytics-summary";
            $.ajax({
                url: path, success: function (result) {
                    var nresult = result + " Users online";
                    $('#google_analytics').html(nresult);
                }
            });

        }, 3000);

        // Date Picker
        jQuery('.mydatepicker, #datepicker').datepicker({
            autoclose: true,
            format: 'yyyy-mm-dd'
        });
        jQuery('#datepicker-autoclose').datepicker({
            autoclose: true,
            todayHighlight: true
        });
        jQuery('#date-range').datepicker({
            toggleActive: true
        });
        jQuery('#datepicker-inline').datepicker({
            todayHighlight: true
        });
    </script>

    <!-- Clock Plugin JavaScript -->
    <script src="https://erp.triz.co.in/plugins/bower_components/clockpicker/dist/jquery-clockpicker.min.js"></script>

    <script>
        // Clock pickers
        $('#single-input').clockpicker({
            placement: 'bottom',
            align: 'left',
            autoclose: true,
            'default': 'now'
        });
        $('.clockpicker').clockpicker({
            donetext: 'Done',
        }).find('input').change(function () {
            console.log(this.value);
        });
        $('#check-minutes').click(function (e) {
            // Have to stop propagation here
            e.stopPropagation();
            input.clockpicker('show').clockpicker('toggleView', 'minutes');
        });

        function confirmDelete() {
            var txt;
            var r = confirm("Are you sure ?");
            if (r == true) {
                return true;
            } else {
                return false;
            }
//    document.getElementById("demo").innerHTML = txt;
        }

    </script>


    <script language="javascript">
        function printdiv(printpage) {
            var headstr = "<html><head><title></title></head><body>";
            var footstr = "</body>";
            var newstr = document.getElementById(printpage).innerHTML;
            var oldstr = document.body.innerHTML;
            document.body.innerHTML = headstr + newstr + footstr;
            window.print();
            document.body.innerHTML = oldstr;
            return false;
        }
    </script>

    <script type="text/javascript">
        function updateTour(module) {
            var url = 'https://erp.triz.co.in/tourUpdate' + "?module=" + module;
            var xhttp = new XMLHttpRequest();
            xhttp.onreadystatechange = function () {
                if (this.readyState == 4 && this.status == 200) {
                    alert("success");
                }
            };
            xhttp.open("GET", url, true);
            xhttp.send();
        }
    </script>

    <script src="https://erp.triz.co.in/admin_dep/js/ajax.js"></script>


    <script src="https://erp.triz.co.in/plugins/bower_components/datatables/datatables.min.js"></script>
    <!-- start - This is for export functionality only -->
    <script src="https://cdn.datatables.net/buttons/1.2.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.2.2/js/buttons.flash.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/2.5.0/jszip.min.js"></script>
    <script src="https://cdn.rawgit.com/bpampuch/pdfmake/0.1.18/build/pdfmake.min.js"></script>
    <script src="https://cdn.rawgit.com/bpampuch/pdfmake/0.1.18/build/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.2.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.2.2/js/buttons.print.min.js"></script>

    </body>

<?php

function exportExcel($titleArr, $valueArr, $fileName, $excelVersion, $sheetPaasWord = '')
{
    global $sheetPaasWord;
    require_once 'PHPExcel.php';

    $objPHPExcel = new PHPExcel();
    $column = 'A';
    $dispTitle = $titleArr;
    $dispTitle = array_values($dispTitle);
    $objPHPExcel->getActiveSheet()
        ->getProtection()->setSheet(true);
    for ($i = 1; $i <= count($titleArr); $i++) {
        $objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue($column . '1', $dispTitle[$i - 1]);
        $objPHPExcel->getActiveSheet()
            ->getColumnDimension($column)
            ->setAutoSize(true);
        $objPHPExcel->getActiveSheet()->getStyle($column . '1')->getFont()->setBold(true);
        $column++;
    }

    $objPHPExcel->setActiveSheetIndex(0);
    $callStartTime = microtime(true);

    if ($excelVersion == '2003') {
        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $Filename = 'assets/' . $fileName;
        $objWriter->save($Filename);
    } else if ($excelVersion == '2007') {
        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $Filename = 'assets/' . $fileName;
        $objWriter->save($Filename);
    } else {
        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        if (is_array($fileName)) {
            foreach ($fileName as $fName) {
                $Filename = 'assets/' . $fName;
                $objWriter->save($Filename);
            }
        }
    }
    return $fileName;
}
