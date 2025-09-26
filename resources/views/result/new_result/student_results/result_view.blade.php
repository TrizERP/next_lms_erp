@include('includes.headcss') @include('includes.header') @include('includes.sideNavigation')

@if (in_array(session()->get('sub_institute_id'), [201, 202, 203, 204, 324, 326, 327]) && $data['result_type'] == 'HPC')
    <link rel="stylesheet" href="/css/hpc_result.css" />
@else
    <link rel="stylesheet" href="/css/result.css" />
@endif
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.8.0/html2pdf.bundle.min.js"></script>

<div id="page-wrapper">
    <div class="container-fluid">
        @php
            $other_sub = [61];
            $sub_institute_id = session()->get('sub_institute_id');

        @endphp
        @if (session()->get('user_profile_name') == 'Admin' ||
                session()->get('user_profile_name') == 'School Admin' ||
                ($sub_institute_id == 254 && session()->get('user_profile_name') == 'Teacher'))
            <div class="row">
                <div class="col-sm-5 text-right">
                    <input class="btn btn-warning mb-4" type="button" onclick="printDiv('printableArea');"
                        value="Print Paper" />
                </div>
                <div class="col-sm-2 center"></div>
                <div class="col-sm-5 text-left">
                    <input class="btn btn-danger mb-4" type="button" onclick="printMob('printableArea');"
                        value="Print Mobile" />
                </div>
            </div>
        @endif
        <div class="card">
            <div class="row">
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div id="printableArea">
                        <!-- <div> -->
                        <style>
                            table td,
                            table th {
                                /*border: 1px solid #ddd;*/
                                padding: 8px;
                            }
                        </style>
                        {!! $data['html'] !!}
                        <!-- </div> -->
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<form name="savehtml" id="savehtml" action="{{ route('save_result_html_new') }}" method="POST">
    {{ method_field('POST') }}
    @csrf
    @php

        $student_id_arr = implode(',', array_values($data['students_ids']));
    @endphp
    <input type="hidden" id="grade_id" name="grade_id" value="{{ $data['grade_id'] }}">
    <input type="hidden" id="standard_id" name="standard_id" value="{{ $data['standard_id'] }}">
    <input type="hidden" id="division_id" name="division_id" value="{{ $data['division_id'] }}">
    <input type="hidden" id="term_id" name="term_id" value="{{ $data['term_id'] }}">
    <input type="hidden" id="syear" name="syear" value="{{ $data['syear'] }}">
    <input type="hidden" id="result_type" name="result_type"
        value="{{ isset($data['result_type']) ? $data['result_type'] : 'Regular' }}">
    <input type="hidden" id="student_arr" name="student_arr" value="{{ $student_id_arr }}">
</form>

@include('includes.footerJs')
<script>
    function printMob(divName) {
        var studentData = @json($data['all_stud_html']);
        for (var studentId in studentData) {
            if (studentData.hasOwnProperty(studentId)) {
                var html = studentData[studentId];
                var stu_id = studentId;
                var ele_id = html;
                var result_html = document.getElementById(stu_id).outerHTML;
                result_html = result_html.replaceAll("'", "\"");
                $("#savehtml").append("<input type='hidden' name='html_" + stu_id + "' id='" + stu_id + "' value='" +
                    result_html + "'>");
            }
        }

        var form = $("#savehtml");
        var url = form.attr('action');
        $.ajax({
            type: "POST",
            url: url,
            data: form.serialize(),
            success: function(data) {
                console.log('saved');
                alert('Save Successfully');
            }
        });
    }
</script>

<script type="text/javascript">
    $(document).ready(function() {
        // Remove the 'table-hover' class from all tables
        $('table').removeClass('table-hover');

        // Example of applying again if new tables are added dynamically
        $(document).on('DOMNodeInserted', function() {
            $('table').removeClass('table-hover');
        });
    });

    // function printDiv(divName) {
    //     var studentData = @json($data['all_stud_html']);

    //     var divToPrint = document.getElementById(divName);
    //     var popupWin = window.open('', '_blank', 'width=300,height=300');
    //     popupWin.document.open();
    //     popupWin.document.write('<html>');
    //     popupWin.document.write('<head><link rel="stylesheet" href="/css/result.css" /></head>');
    //      popupWin.document.write('<body onload="setTimeout(function() { window.print(); }, 2000);">');
    //     Object.keys(studentData).forEach(function(studentId) {
    //         var html = studentData[studentId];
    //         // console.log(html);
    //         popupWin.document.write(html + '<div style="page-break-after: always !important;"></div>');
    //     });
    //     popupWin.document.write('</body></html>');

    //     popupWin.document.close();
    // }
    function printDiv(divName) {
        var studentData = @json($data['all_stud_html']);
        var acaYear = "{{ session()->get('syear') }}" + '-' + "{{ session()->get('syear') + 1 }}";

        @if (!in_array(session()->get('sub_institute_id'), [201, 202, 203, 204, 324, 326, 327]))

            var divToPrint = document.getElementById(divName);
            var popupWin = window.open('', '_blank', 'width=300,height=300');
            popupWin.document.open();
            popupWin.document.write('<html>');

            popupWin.document.write('<head><link rel="stylesheet" href="/css/result.css" /></head>');

            popupWin.document.write(
                '<body onload="setTimeout(function() { window.print(); }, 2000);"><style>body{margin:0;padding:0}</style>'
            );
            @if (in_array($data['template'], [12, 51, 52, 57])) // added for mmis grandmarksheet
                popupWin.document.write(`
                    <div style="text-align: center; break-inside: avoid; page-break-inside: avoid;">
                        <h1 style="margin: 0px !important; break-after: avoid; page-break-after: avoid;">MULJIBHAI MEHTA INTERNATIONAL SCHOOL</h1>
                        <span>GOKUL TOWNSHIP, OFF. AGASHI ROAD, BOLINJ, VIRAR (W), MUMBAI METROPOLITAN REGION,</span><br>
                        <span>PALGHAR, PIN – 401 303</span>
                        <h3 style="margin: 0px !important; break-before: avoid; break-after: avoid; page-break-before: avoid; page-break-after: avoid;">GRAND MARKSHEET (` +
                                    acaYear + `)</h3>
                    </div>
                `);
            @endif

            Object.keys(studentData).forEach(function(studentId) {
                var html = studentData[studentId];
                @if (in_array($data['template'], [12, 51, 52, 57])) // added for mmis grandmarksheet
                    popupWin.document.write('<div style="break-inside: avoid; page-break-inside: avoid;">' +
                        html + '</div>');
                @elseif (in_array(session()->get('sub_institute_id'), [76])) // added for mmis grandmarksheet
                    popupWin.document.write('<div style="break-inside: avoid; page-break-inside: avoid;">' +
                        html + '</div>');
                @else
                    popupWin.document.write('<div style="page-break-before: always !important;">' + html +
                        '</div>');
                @endif
            });

            popupWin.document.write('</body></html>');
            popupWin.document.close();
        @else
            $.ajax({
                url: "{{ route('getHillsHPCPDF') }}",
                data: {
                    studentHtml: studentData
                },
                type: 'POST',
                success: function(result) {
                    if (result.status_code) {
                        alert(result.message);
                    }

                    let newTab = window.open(result, '_blank');

                }
            });
        @endif
    }
</script>
@include('includes.footer')
