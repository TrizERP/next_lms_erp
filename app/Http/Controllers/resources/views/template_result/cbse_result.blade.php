@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')

<link rel="stylesheet" href="{{ URL::asset('css/temp_result.css') }}" />


<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row" style=" margin-top: 25px;">
            <div class="panel-body white-box">  
                <div>
                    <center> 
                        <input class="btn btn-warning mb-4" type="button" onclick="printDivResult();" value="Print & Generate Result" /></center>
                </div>
                <div id="printResult" style="padding: 0px 63px 0px 132px;page-break-before: always;"> 
                    @foreach ($data as $resultView)
                        {!! $resultView !!}   
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">  
    function printDivResult(){
       
        var divContents = document.getElementById("printResult").innerHTML;
            var a = window.open('', '', 'height=500, width=500');
            a.document.write('<html>');
            a.document.write('<header><link rel="stylesheet" href="http://202.47.117.61/css/temp_result.css" /></header>');
            a.document.write('<body>');
            a.document.write(divContents);
            a.document.write('</body></html>');
            a.document.close();
            a.print();
    }
</script>
@include('includes.footer')
