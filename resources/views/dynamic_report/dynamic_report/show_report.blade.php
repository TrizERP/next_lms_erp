{{--@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')--}}
@extends('layout')
@section('container')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="card">

            @if(!empty($data['message']))
            <div class="alert alert-success alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $data['message'] }}</strong>
            </div>
            @endif

            <div class="col-lg-3 col-sm-3 col-xs-3">
                <a href="{{ route('dynamic_report.index') }}" class="btn btn-info add-new">
                    Back To Reports
                </a>
            </div>

            <div class="col-lg-12 col-sm-12 col-xs-12">
                <div class="table-responsive">
                    <table id="example" class="table table-striped">
                        <thead>
                            <tr>
                                @foreach($data['tbl_heading'] as $val)
                                    <th class="text-left">{!! $val !!}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>

                        @foreach($data['result'] as $arr)
                            <tr>
                                @foreach($arr as $ind => $val)

                                    {{-- ===== siblings_details UI (UNCHANGED) ===== --}}
                                    @if($ind == "siblings_details")
                                        @php $siblingsArr = explode('####', $val); @endphp
                                        <td data-siblings='@json($siblingsArr)'>
                                            <table class="table table-bordered">
                                                @foreach($siblingsArr as $v)
                                                    @php $details = explode('-', $v); @endphp
                                                    <tr>
                                                        @foreach($details as $d)
                                                            <td>{{ $d }}</td>
                                                        @endforeach
                                                    </tr>
                                                @endforeach
                                            </table>
                                        </td>
                                    @else
                                    <td>
                                        @if($ind == "chapter_name")
                                            @php $explodedValues = explode(',', $val); @endphp
                                            @foreach($explodedValues as $explodedVal)
                                                {{ $explodedVal }}<br>
                                            @endforeach

                                        @elseif($ind == "sub_contents" || $ind == "content_type")
                                            @php $i = 1; $explodedValues = explode(',', $val); @endphp
                                            @foreach($explodedValues as $explodedVal)
                                                {{ $i . ") " . $explodedVal }}<br>
                                                @php $i++; @endphp
                                            @endforeach

                                        @elseif($ind == "icard_icon")
                                            @php $path = 'storage/app/public/driver/' . $val; @endphp
                                            <img src="{{ asset($path) }}" height="30%" width="30%">

                                        @else
                                            {{ $val }}
                                        @endif
                                    </td>
                                    @endif
                                    {{-- ========================================== --}}

                                @endforeach
                            </tr>
                        @endforeach

                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

@include('includes.footerJs')

{{-- XLSX library --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
$(document).ready(function () {

    // check if siblings_details column exists
    let hasSiblings = false;
    $('#example thead th').each(function () {
        if ($(this).text().toLowerCase().includes('siblings')) {
            hasSiblings = true;
        }
    });

    var buttons = [
        { extend: 'pdfHtml5', title: 'Report', orientation: 'landscape', pageSize: 'A0' },
        { extend: 'csv', title: 'Report' },
        { extend: 'print', title: 'Report' }
    ];

    // ✅ CONDITIONAL EXCEL BUTTON
    if (hasSiblings) {
        buttons.push({
            text: 'Excel',
            action: function () {

                let wb = XLSX.utils.book_new();
                let wsData = [];

                wsData.push(['Mobile','GR No','Name','Board','Std','Div']);

                $('#example tbody tr').each(function () {

                    let tds = $(this).find('td');
                    let mobile = $(tds[0]).text().trim();
                    let siblings = JSON.parse($(tds[1]).attr('data-siblings') || '[]');

                    siblings.forEach(function (s) {
                        let p = s.split('-');
                        wsData.push([
                            mobile,
                            p[0] || '',
                            p[1] || '',
                            p[2] || '',
                            p[3] || '',
                            p[4] || ''
                        ]);
                    });
                });

                let ws = XLSX.utils.aoa_to_sheet(wsData);
                XLSX.utils.book_append_sheet(wb, ws, 'Report');
                XLSX.writeFile(wb, 'Siblings_Report.xlsx');
            }
        });
    } else {
        // normal excel when siblings not present
        buttons.push({ extend: 'excel', title: 'Report' });
    }

    buttons.push('pageLength');

    $('#example').DataTable({
        select: true,
        lengthMenu: [
            [100, 500, 1000, -1],
            ['100', '500', '1000', 'Show All']
        ],
        dom: 'Bfrtip',
        buttons: buttons
    });

});
</script>

@include('includes.footer')
@endsection
