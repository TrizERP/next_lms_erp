@include('includes.headcss')
<link rel="stylesheet" href="../../../plugins/bower_components/dropify/dist/css/dropify.min.css">
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Used Storage Data</h4>
            </div>
        </div>
        <div class="card">                           
            <div class="col-lg-12 col-sm-12 col-xs-12">
                <div class="table-responsive">
                    <table id="example" class="table table-striped">
                        <thead>
                            <tr>
                                <th>Module Name</th>
                                <th>Used Data Size</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $total_used = 0;
                            @endphp
                            @foreach($data['total_used_space_array'] as $key => $data)                            
                            <tr>    
                                <td>{{$key}}</td>
                                <td>{{$data}} KB</td>                                
                            </tr>
                            @php
                                $total_used = $total_used + $data;
                            @endphp                            
                            @endforeach
                            <tr>    
                                <td><b>Total Used Data</b></td>
                                <td><b>{{$total_used}} KB</b></td>                                
                            </tr>
                        </tbody>
                    </table>
                </div>     
            </div>                        
        </div>
    </div>
</div>
@include('includes.footerJs')
@include('includes.footer')