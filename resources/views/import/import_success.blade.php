<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
</head>
<body>
<div id="app">
    <div class="container">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel panel-default">
                    <div class="panel-heading">CSV Import Report</div>
                    <div class="row">
                        <div class="col-md-3">
                            <p>Total Record :</p>
                            <p>Failed Record:</p>
                            <p>Insert Record:</p>
                            <p>OverWrite Record:</p>
                            <p>Success Record:</p>
                        </div>
                        <div class="col-md-3">
                            <p>{{$totalRecordCount}}</p>
                            <p>{{$totalFailedRecordCount}}</p>
                            <p>{{$totalInsertRecordCount}}</p>
                            <p>{{$totalOverwiteRecordCount}}</p>
                            <p>{{($totalRecordCount) - $totalFailedRecordCount}}</p>
                        </div>
                    </div>

                    <div class="panel-body">
                        Data imported successfully.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="{{ asset('js/app.js') }}"></script>
</body>
</html>



