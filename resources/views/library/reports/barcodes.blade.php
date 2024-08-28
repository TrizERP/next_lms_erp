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
    @for ($i = 0; $i < count($barcodes); $i+=3)
        <tr>
            @for ($j = $i; $j < $i + 3 && $j < count($barcodes); $j++)
                <td style="text-align: center; padding: 16px;">
                    <img class="barcode-image" src="data:image/png;base64,{{ base64_encode($barcodes[$j]['image']) }}" alt="{{ $barcodes[$j]['code'] }}">   
                <p style="font-size: 0.6rem;text-align:center;">@if(isset($barcodes[$j]['other']) && $barcodes[$j]['other']=="member" ) {{ substr($barcodes[$j]['title'],0,25) }} @else {{ substr($barcodes[$j]['title'],0,20) }} @if($barcodes[$j]['other']!='') - {{$barcodes[$j]['other']}} @endif @endif</p>  
                </td>
            @endfor
        </tr>
    @endfor
</table>
</body>
</html>

