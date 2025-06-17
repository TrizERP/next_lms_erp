@if($print_type!="member")
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title>Barcodes</title>
    <style>
       body {
            margin: 0px;
            padding: 0px;
            width:100% !important;
        }   
    </style> 
</head>
<body>
<table style="width:100%;border-collapse: collapse;">
    @php 
        $columns = 3;
        $padding = 16;
        $fontSize = '0.8rem';
        if($print_type=="member"){
            $columns = 5;
            $padding = 2;
            $fontSize = '0.6rem';
        }
    @endphp
    @for ($i = 0; $i < count($barcodes); $i+=$columns)
        <tr>
            @for ($j = $i; $j < $i + $columns && $j < count($barcodes); $j++)
                <td style="text-align: center; padding: {{$padding}}px;">
                    <img class="barcode-image" src="data:image/png;base64,{{ base64_encode($barcodes[$j]['image']) }}" alt="{{ $barcodes[$j]['code'] }}">   
                <p style="font-size:{{$fontSize}};text-align:center;margin:0px;"><b>@if(isset($barcodes[$j]['other']) && $barcodes[$j]['other']=="member" ) {{ substr($barcodes[$j]['title'],0,25) }} @else {{ substr($barcodes[$j]['title'],0,20) }} @if($barcodes[$j]['other']!='') - {{$barcodes[$j]['other']}} @endif @endif<b></p>  
                </td>
            @endfor
        </tr>
    @endfor
</table>
</body>
</html>
<!-- members barcode  -->
@else 
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN""http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, post-check=0, pre-check=0" />
    <meta http-equiv="Expires" content="Sat, 26 Jul 1997 05:00:00 GMT" />
    <title>{{ $print_type === 'student' ? 'Student ID Cards' : 'Book Barcodes' }}</title>
    <style>
        @page {
            size: A4;
            margin: 0;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            font-size: 9pt;
            background: #fff;
        }

        .tableDiv {
            width: 100%;
            text-align: center;
            margin: 0;
            padding: 0;
            margin-top: 60px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 0 auto;
            padding: 0;
        }

        tr {
            page-break-inside: avoid;
        }

        td {
            text-align: center;
            vertical-align: top;
            padding: 0;
            margin: 0;
        }

        .labelStyle {
            width: 38mm;
            height: 21mm;
            text-align: center;
            margin: 0;
            padding: 0;
            display: inline-block;
            box-sizing: border-box;
            vertical-align: top;
            overflow: hidden;
            font-size: 8pt;
            line-height: 1.1;
        }

        .barcode-image {
            display: block;
            margin: 0 auto 2px auto;
            width: 90%;
            height: 50%;
        }

        .barcode-title {
            font-size: 6.3pt;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="tableDiv">
    @php
        $items_per_row = 5;
        $rows_per_page = 13;
        $barcode_image_width = '90%';
        $title_length = 25;
        $total_items = count($barcodes);
        $items_per_page = $items_per_row * $rows_per_page;
        $page_count = ceil($total_items / $items_per_page);
    @endphp

    @for ($page = 0; $page < $page_count; $page++)
        <table>
            @for ($row = 0; $row < $rows_per_page; $row++)
                <tr>
                    @for ($col = 0; $col < $items_per_row; $col++)
                        @php
                            $current_index = ($page * $items_per_page) + ($row * $items_per_row) + $col;
                        @endphp

                        @if ($current_index < $total_items)
                            <td>
                                <div class="labelStyle barcode-container">
                                    <img class="barcode-image" src="data:image/png;base64,{{ base64_encode($barcodes[$current_index]['image']) }}" />
                                    <div class="barcode-title">
                                        {{ substr($barcodes[$current_index]['title'], 0, $title_length) }}
                                    </div>
                                </div>
                            </td>
                        @else
                            <td><div class="labelStyle"></div></td>
                        @endif
                    @endfor
                </tr>
            @endfor
        </table>
    @endfor
    </div>
</body>
</html>
@endif
