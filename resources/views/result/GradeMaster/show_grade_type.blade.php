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
                    <ul class="nav nav-pills mt-4 mb-4" style="border-bottom: 2px solid #41b3f9;padding-bottom: 2;">
                        <li class=" nav-item"> <a href="{{ route('grade_master.create') }}" class="btn btn add-new"><i class="fa fa-plus"></i></a> </li>

                        @foreach($data['data']['grade'] as $key => $data1)
                        <li class="nav-item"> 
                            <a href="#navpills-{{$data1->sort_order}}" class="nav-link" data-toggle="tab" aria-expanded="false">{{$data1->grade_name}}</a> 
                        </li>
                        @endforeach
                    </ul>
                    <div class="tab-content br-n pn">
                        <!--                        <div id="navpills-1" class="tab-pane active">
                                                    <div class="row">
                                                        <div class="col-md-4"> <img src="../../../assets/images/big/img2.jpg" class="img-fluid thumbnail mr-3"> </div>
                                                        <div class="col-md-8"> Raw denim you probably haven't heard of them jean shorts Austin. Nesciunt tofu stumptown aliqua, retro synth master cleanse. Mustache cliche tempor, williamsburg carles vegan helvetica.
                                                            <p>
                                                                <br/> Reprehenderit butcher retro keffiyeh dreamcatcher synth. Cosby sweater eu banh mi, qui irure terry richardson ex squid.
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>-->
                        @foreach($data['data']['grade'] as $key => $GradeMasterData)
                        <div id="navpills-{{$GradeMasterData->sort_order}}" class="tab-pane">
                            <div class="row">
                                <div class="col-lg-12 col-sm-12 col-xs-12">
                                    <div class="table-responsive">                                        
                                        <table id="example1" class="table table-striped" >
                                            <thead>
                                                <tr>
                                                    <th>Title</th>
                                                    <th>BREAKOFF</th>
                                                    <th>GP VALUE</th>
                                                    <th>SORT ORDER</th>
                                                    <th>COMMENT</th>
                                                    <th>GRADE SCALE</th>
                                                    <th>ACTION</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($data['data']['grade_data'][$GradeMasterData->id] as $key => $GradeData)
                                                <tr>    
                                                    <td>{{$GradeData->title}}</td>
                                                    <td>{{$GradeData->breakoff}}</td>
                                                    <td>{{$GradeData->gp}}</td>
                                                    <td>{{$GradeData->sort_order}}</td>
                                                    <td>{{$GradeData->comment}}</td>
                                                    <td>{{$GradeMasterData->grade_name}}</td>
                                                    <td>
                                                        <form action="{{ route('grade_master.destroy', $GradeData->id)}}" method="post">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button class="btn btn-outline-danger" onclick="return confirmDelete();" type="submit"><i class="ti-trash"></i></button>
                                                        </form>
                                                    </td> 
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    <a href="{{ route('grade_master.createData',$GradeMasterData->id) }}" class="btn btn add-new"><i class="fa fa-plus"></i> Add Row</a>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    <div class="col-lg-12 col-sm-12 col-xs-12" style="overflow:auto;">

                    </div>
        </div>
    </div>
</div>

@include('includes.footerJs')
<script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.19/js/dataTables.bootstrap.min.js"></script>
<script>
$(document).ready(function () {
    $('#example').DataTable({
        
    });
});

</script>
@include('includes.footer')
