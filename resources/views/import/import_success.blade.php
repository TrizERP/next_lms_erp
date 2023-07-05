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
                            @if(count($failedFields)>0)
                                <p>Required fields:</p>
                            @else
                                <p>Total Record :</p>
                                <p>Failed Record:</p>
                                <p>Failed Record Row List :</p>
                                <p>Insert Record:</p>
                                <p>OverWrite Record:</p>
                                <p>OverWrite Row List :</p>
                                <p>Skip Record:</p>
                                <p>Skip Record Row List:</p>
                                <p>Success Record:</p>
                            @endif
                        </div>
                        <div class="col-md-3">
                            @if(count($failedFields)>0)
                                <p>
                                    @foreach($failedFields as $key => $fields)
                                        {{$fields}} {{count($failedFields) == $key + 1 ? '' :','}}
                                    @endforeach
                                </p>
                            @else
                                <p>{{$totalRecordCount}}</p>
                                <p>{{$totalFailedRecordCount}}  </p>
                                <p>[@foreach($totalFailedRecordArray as $key => $row)
                                        {{$row}} {{count($totalFailedRecordArray) == $key + 1 ? '' :','}}
                                    @endforeach]</p>
                                <p>{{$totalInsertRecordCount}}</p>
                                <p>{{$totalOverwiteRecordCount}}</p>
                                <p>[@foreach($totalOverwiteRecordArray as $key => $row)
                                        {{$row}} {{count($totalOverwiteRecordArray) == $key + 1 ? '' :','}}
                                    @endforeach]</p>
                                <p>{{$totalSkipRecordCount}}</p>
                                <p>[@foreach($totalSkipRecordArray as $key => $row)
                                        {{$row}} {{count($totalSkipRecordArray) == $key + 1 ? '' :','}}
                                    @endforeach]</p>
                                <p>{{($totalRecordCount) - $totalFailedRecordCount}}</p>
                            @endif

                        </div>
                    </div>

                    <div class="panel-body">
                        @if(count($failedFields) == 0)
                            Data imported successfully..
                        @endif
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



