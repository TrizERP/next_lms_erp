<!DOCTYPE html>
<html>
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

