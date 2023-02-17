@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')

<link rel="stylesheet" href="{{ URL::asset('css/result.css') }}" />
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row" style=" margin-top: 25px;">
            <div class="panel-body white-box">
                @if(!empty($data['message']))
                <div class="alert alert-{{ $data['class'] }} alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $data['message'] }}</strong>
                </div>
                @endif


                <!--<div class="col-lg-12 col-sm-12 col-xs-12">-->
                <div>

                    <table class="main-table" width="100%">
                        <tbody><tr>
                                <td>
                                    <table class="report-card" style="border-collapse:collapse;" width="100%" cellspacing="0" cellpadding="0">
                                        <tbody><tr class="b-b">
                                                <td align="left">
                                                    <img alt="" src="" height="50px">

                                                </td>
                                                <td style="white-space:nowrap;" align="center">
                                                    <h2 style="margin-top:5%"></h2>

                                                    <br>

                                                    <br>

                                                </td>
                                                <td align="right">
                                                    <img alt="" src="" height="50px">
                                                </td>
                                            </tr>
                                        </tbody></table>
                                    <table class="report-card" style="border-collapse:collapse;" width="100%" cellspacing="0" cellpadding="0">

                                        <tbody><tr>
                                                <td colspan="3" align="center">
                                                    <h3 style="font-size:14">MARKSHEET CUM CERTIFICATE OF PERFORMANCE</h3>
                                                    <h3 style="font-size:14">SESSION 2018 - 2019</h3>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td width="60%">Student's Name : <label>AARAV PRAMOD YADAV</label></td>
                                                <td width="20%"></td>
                                                <td width="20%">Roll No. : <label>1</label></td>
                                            </tr>
                                            <tr>
                                                <td>Mother's Name : <label>SUNITA PRAMODKUMAR YADAV</label></td>
                                                <td></td>
                                                <td>Class : <label>1</label></td>
                                            </tr>
                                            <tr>
                                                <td>Father's Name : <label>DR. PRAMODKUMAR CHANDRABHUSAN YADAV</label></td>
                                                <td></td>
                                                <td>Division : <label>A</label></td>
                                            </tr>
                                            <tr>
                                                <td>Date Of Birth : <label>20-09-2012</label></td>
                                                <td></td>
                                                <td>G.R. No. : <label>2979</label></td>
                                            </tr>
                                        </tbody></table>
                                    <table class="report-card" style="border-collapse:collapse;" width="100%" cellspacing="0" cellpadding="0">

                                        <tbody><tr>
                                                <td colspan="3" class="p-t-10"><table class="aca-year" style="border-collapse:collapse; border:1px solid #e68023;" width="100%" cellspacing="0" cellpadding="0" border="1"><tbody><tr>   <th class="main-th" align="left">Part 1-A-Scholastic Areas:</th>   <th colspan="5" class="main-th" align="center">TERM-1 (50 marks)</th>   <th colspan="5" class="main-th" align="center">TERM-2 (50 marks)</th>   <th class="main-th" colspan="2">Total</th></tr><tr>   <th align="left">Sub Name</th>   <th align="center">PA1<br>(10)</th>   <th align="center">PA2<br>(10)</th>   <th align="center">SA1<br>(25)</th>   <th align="center">Notebook<br>(5)</th>   <th align="center">Marks Obtained<br>(50)</th>   <th align="center">PA3<br>(10)</th>   <th align="center">PA4<br>(10)</th>   <th align="center">SA2<br>(25)</th>   <th align="center">Notebook<br>(5)</th>   <th align="center">Marks Obtained<br>(50)</th>   <th align="center">Total Marks Obtained<br>(100)</th>   <th align="center">Grade</th></tr><tr>   <td>ENGLISH</td>   <td align="center">9.50</td>   <td align="center">8.50</td>   <td align="center">18.13</td>   <td align="center">3.00</td>   <td align="center">39.13</td>   <td align="center">8.50</td>   <td align="center">7.75</td>   <td align="center">-</td>   <td align="center">-</td>   <td align="center">16.25</td>   <td align="center">55.38</td>   <td align="center">C</td></tr><tr></tr><tr>   <td>HINDI</td>   <td align="center">8.88</td>   <td align="center">7.63</td>   <td align="center">22.13</td>   <td align="center">4.50</td>   <td align="center">43.14</td>   <td align="center">8.38</td>   <td align="center">5.00</td>   <td align="center">-</td>   <td align="center">-</td>   <td align="center">13.38</td>   <td align="center">56.52</td>   <td align="center">B</td></tr><tr></tr><tr>   <td>MATHEMATICS</td>   <td align="center">9.00</td>   <td align="center">8.00</td>   <td align="center">22.75</td>   <td align="center">3.00</td>   <td align="center">42.75</td>   <td align="center">8.50</td>   <td align="center">4.38</td>   <td align="center">-</td>   <td align="center">-</td>   <td align="center">12.88</td>   <td align="center">55.63</td>   <td align="center">B</td></tr><tr></tr><tr>   <td>EVS</td>   <td align="center">7.25</td>   <td align="center">7.63</td>   <td align="center">20.75</td>   <td align="center">3.00</td>   <td align="center">38.63</td>   <td align="center">8.38</td>   <td align="center">8.25</td>   <td align="center">-</td>   <td align="center">3.50</td>   <td align="center">20.13</td>   <td align="center">58.76</td>   <td align="center">B</td></tr><tr></tr><tr><td><b>Percentage</b></td><td colspan="12"><b>56.57%</b></td></tr></tbody></table></td>
                                            </tr>
                                            <tr>
                                                <td class="p-t-10" width="45%" valign="top"><table class="aca-year" style="border-collapse:collapse; border:1px solid #e68023;" cellspacing="0" cellpadding="0" border="1"><tbody><tr><th colspan="3" width="15%" align="center"><b>Part 1-B-Scholastic Areas:</b></th></tr><tr><th width="15%" align="center"><b>Subject</b></th><th width="15%" align="center"><b>TERM-1</b></th><th width="15%" align="center"><b>TERM-2</b></th></tr><tr><td>COMPUTER</td><td align="center">B</td><td align="center">D</td></tr><tr><td>GENERAL KNOWLEDGE</td><td align="center">A</td><td align="center">D</td></tr><tr><td>VALUE EDUCATION</td><td align="center">B</td><td align="center">D</td></tr></tbody></table></td>
                                                <td width="10%"></td>
                                                <td style="padding-left: 15px !important;" class="p-t-10" width="45%" valign="top"><div class="co-scholastic-area"><table class="aca-year" style="border-collapse:collapse; border:1px solid #e68023;" width="100%" cellspacing="0" cellpadding="0" border="1"><tbody><tr>    <th><b>Co-Scholastic Areas:  [on a 3-point (A-C) Grading Scale]</b></th>    <th width="15%" align="center"><b>TERM-1</b></th>    <th width="15%" align="center"><b>TERM-2</b></th></tr><tr>    <td>Physical Education</td>    <td align="center">A</td>    <td align="center">-</td></tr><tr>    <td>Art &amp; Craft</td>    <td align="center">A</td>    <td align="center">-</td></tr><tr>    <td>Music &amp; Dance</td>    <td align="center">B</td>    <td align="center">-</td></tr></tbody></table><div class="p-t-10"></div><table class="aca-year" style="border-collapse:collapse; border:1px solid #e68023;" width="100%" cellspacing="0" cellpadding="0" border="1"><tbody><tr>    <th>&nbsp;</th>    <th width="15%" align="center"><b>TERM-1</b></th>    <th width="15%" align="center"><b>TERM-2</b></th></tr><tr>    <td><b>Discipline:  [on a 3-point (A-C) Grading Scale]</b></td>    <td align="center">B</td>    <td align="center">B</td></tr></tbody></table></div></td>						
                                            </tr>

                                            <tr>
                                                <td colspan="3" class="p-t-10">
                                                    <b>Attendance (Term I &amp; II): 75/85</b>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td colspan="3" class="p-t-10">
                                                    <b>Class Teacher's Remarks : Very Good</b>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td colspan="3" class="p-t-10">
                                                    <b><table width="100%" cellspacing="0" cellpadding="3" border="0"><tbody><tr>     <td><b>Result : Passed &amp; Promoted to Class 2 - A</b></td><td></td></tr></tbody></table></b>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td colspan="3" class="p-t-10">
                                                    <b>School Reopens on : 3<sup>rd</sup> April, 2019</b>
                                                </td>
                                            </tr>
                                            <tr class="p-t b-b">
                                                <td colspan="3" style="padding-top:30px;">
                                                    <table class="signature" width="100%" cellspacing="0" cellpadding="0">
                                                        <tbody><tr>
                                                                <td align="center"><b>Signature of Class Teacher</b></td>
                                                                <td align="center"><b>Signature of Principal</b></td>
                                                                <td align="center"><b>Signature of Parent</b></td>
                                                            </tr>
                                                        </tbody></table>
                                                </td>
                                            </tr>

                                            <tr>
                                                <td colspan="3" align="center">
                                                    <table class="aca-year" style="border-collapse:collapse; border:1px solid #e68023;" width="80%" cellspacing="0" cellpadding="0" border="1">            <tbody><tr>                <th align="center"><b>SCHOLASTIC MARKS RANGE</b></th><td align="center">90 - 100</td><td align="center">75 - 89</td><td align="center">56 - 74</td><td align="center">33 - 55</td><td align="center">32 &amp; Below</td>            </tr>            <tr>                <th align="center"><b>GRADE</b></th><td align="center">A+</td><td align="center">A</td><td align="center">B</td><td align="center">C</td><td align="center">D</td>            </tr>            <tr>        </tr></tbody></table>
                                                </td>
                                            </tr>

                                            <tr>
                                                <td colspan="3" align="center">
                                                    <table class="aca-year" style="border-collapse:collapse; border:1px solid #e68023;" width="80%" cellspacing="0" cellpadding="0" border="1">            <tbody><tr>                <th width="200px" align="center"><b>CO-SCHOLASTIC GRADE</b></th><td align="center">4.1 - 5.0</td><td align="center">3.1 - 4.0</td><td align="center">2.1 - 3.0</td>     </tr>     <tr>        <th width="200px" align="center"><b>GRADE POINTS</b></th><td align="center">A</td><td align="center">B</td><td align="center">C</td>       </tr>        <tr><th width="200px" align="center"><b>REMARKS</b></th><td align="center">Very Good</td><td align="center">Good</td><td align="center">Fair</td>       </tr>       </tbody></table>
                                                </td>
                                            </tr>
                                        </tbody></table>
                                </td>
                            </tr>
                        </tbody></table>

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
</div>

@include('includes.footerJs')
@include('includes.footer')
