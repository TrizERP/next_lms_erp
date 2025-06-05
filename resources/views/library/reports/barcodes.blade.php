<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
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
            margin: 0; /* Ensures full use of page */
        }

        body {
            padding: 0;
            margin: 0px;
            font-family: "Arial, Verdana, Helvetica, 'Trebuchet MS'";
            font-size: 9pt;
            background: #fff;
        }

        .header {
            width: 100%;
            display: flex;
            text-align: center;
            position: fixed; /* Keep header visible on top during print */
            top: 0;
            left: 0;
            padding: 10px 0;
            font-size: 10pt;
        }

        .header-left {
            width:20%;
            float: left;
            margin-left: 20px; /* Adjust as needed */
        }

        .header-center {
            width:80%;
            white-space: nowrap;
        }

        table {
            width: 88%;
            border-collapse: collapse;
            margin: 0px 34px;
            padding: 0px;
            align-items: center;
            text-align: center;
            /* Adjust margin-top to leave space for the fixed header */
            margin-top: 88px; /* Increased to accommodate header */
        }

        tr {
            page-break-inside: avoid;
        }

        td {
            text-align: center;
            vertical-align: top;
            overflow: hidden;
            padding: 0; /* Remove default cell padding */
        }

        .barcode-image {
            display: block;
            margin: 0 auto;
        }

        .labelStyle {
            width: 52mm;
            height: 32mm;
            text-align: center;
            margin: 0px;
            border: 0px solid #000000;
            display: inline-block;
            box-sizing: border-box;
            vertical-align: top;
            line-height: 1.2;
        }

        .barcode-number {
            font-size: 10pt; /* Adjust font size for barcode number */
            font-weight: bold;
            /* margin-top: 2px; Space between image and number */
            /* margin-bottom: 2px; Space between number and title */
        }

        .barcode-title {
            font-size: 9pt; /* Adjust font size for title */
            line-height: 1.1;
            font-weight: bold;
        }
        .tableDiv{
            width:100%;
            text-align: center;
            display: flex;
            justify-content: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <span class="header-left">{{ \Carbon\Carbon::now()->format('n/j/y, g:i A') }}</span>
        <span class="header-center">Item Barcode Label Print Result</span>
    </div>
    <div class="tableDiv">
    @php
        // Configuration based on print type
        // To get 24 labels per page, a 4x6 grid is optimal for A4.
        $items_per_row = 3;
        $rows_per_page = 8;

        // Cell dimensions will be calculated based on the number of items per row/page
        $cell_width_percent = (100 / $items_per_row) . '%';
        $cell_height_percent = (100 / $rows_per_page) . '%';

        $barcode_image_width = '90%';
        $title_length = 25; // Adjusted title length

        // Override settings for student cards if they need different sizes
        if ($print_type === 'student') {
            // Student specific settings if different from default 24/page
            // (e.g., if you want more student cards per page than book barcodes)
        }

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
                            <td >
                                <div class="labelStyle barcode-container">
                                    <div id="bio">
                                        <img height="50px" src="data:image/png;base64,{{ base64_encode($barcodes[$current_index]['image']) }}" style="width: {{ $barcode_image_width }};" border="0" />
                                        <div class="barcode-title">{{ substr($barcodes[$current_index]['title'], 0, $title_length) }} - 030</div>
                                    </div>
                                </div>
                            </td>
                        @else
                            {{-- Fill remaining cells in the row with empty ones to maintain layout --}}
                            <td style="width: {{ $cell_width_percent }}; height: {{ $cell_height_percent }};"></td>
                        @endif
                    @endfor
                </tr>
            @endfor
        </table>
        @if ($page < $page_count - 1)
            {{-- Optional: Force a page break if you have distinct tables for each page --}}
            {{-- <div style="page-break-after: always;"></div> --}}
        @endif
    @endfor
    </div>
</body>
</html>