@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<style>
    .title {
        font-weight: 200;
    }
</style>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">View Classwise Timetable</h4>
            </div>
        </div>

        <div class="card">
            @if ($message = Session::get('data'))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $message['message'] }}</strong>
            </div>
            @endif
            <div class="row">
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('ajax_getClasswiseTimetable') }}" enctype="multipart/form-data"
                          method="post">
                        @csrf
                        <div class="row ">
                            <div class="col-md-12 form-group">
                                <div class="row ">
                                    {{ App\Helpers\SearchChain('4','required','grade,std,div',$data['academic_section_id'],$data['standard_id'],$data['division_id']) }}
                                </div>
                            </div>
                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Submit" class="btn btn-success">
                                </center>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @if( isset($data['HTML']) )

        <div class="card">
            <div class="row">
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="row">
                        <div class="col-md-12 form-group" id="printPage">
                            <div class="table-responsive triz-table ">
                                @if( isset($data['HTML']) )
                                    @php echo $data['HTML'] @endphp
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                    <div class="col-md-12 form-group">
                        <center>
                            <button class="btn btn-success" onclick="PrintDiv('printPage');">Print</button>
                        </center>
                    </div>
                @endif

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
<script>
    function PrintDiv(divName) {
        var divToPrint = document.getElementById(divName);
        var popupWin = window.open('', '_blank', 'width=300,height=300');
        popupWin.document.open();
        popupWin.document.write('<html>');
        popupWin.document.write('<body onload="window.print()">' + divToPrint.innerHTML + '</html>');
        popupWin.document.close();
    }
</script>
@include('includes.footer')
