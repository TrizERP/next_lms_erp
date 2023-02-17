@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')


<div id="page-wrapper">
    <div class="container-fluid">
        <div class="card">
            @if(!empty($data['message']))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $data['message'] }}</strong>
            </div>
            @endif
            <div class="row">                
                <div class="col-lg-3 col-sm-3 col-xs-3">
                    {{-- <a href="{{ route('lo_master.create') }}" class="btn btn-info add-new"><i
                        class="fa fa-plus"></i> Add New</a> --}}
                </div>
                <form action="{{ route('lo_marks_entry.store') }}" enctype="multipart/form-data" method="post">
                    <div class="row">                    
                        <div class="col-lg-12 col-sm-12 col-xs-12">
                            <div class="table-responsive">                                
                                <table id="example" class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Sr No.</th>
                                            <th>Name</th>
                                            <th>Standard</th>
                                            <th>LO</th>
                                            <th>Result</th>
                                            <th>Perfomance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                        $i=1;
                                        @endphp
                                        @foreach($data['stud'] as $key => $datas)
                                        @php
                                        if($datas->per > 50){
                                        $style = "color:#00ff00;";
                                        }else{
                                        $style = "color:#ff0000;";
                                        }
                                        @endphp
                                        <tr>
                                            <td>{{$i}}</td>
                                            <td>{{$datas->stu_name}}</td>
                                            <td>{{$datas->name}}</td>
                                            <td>{{$datas->INDICATOR}}</td>
                                            <td>{{$datas->AR}}</td>
                                            <td style="{{$style}}">{{$datas->per}}%</td>
                                        </tr>
                                        @php
                                        $i++;
                                        @endphp
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </form>
            </div>                
        </div>
    </div>
</div>

@include('includes.footerJs')
<script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.19/js/dataTables.bootstrap.min.js"></script>
<script>
    $(document).ready(function () {
                                                    var table = $('#example').DataTable({
                                                    });
                                                });

</script>
@include('includes.footer')