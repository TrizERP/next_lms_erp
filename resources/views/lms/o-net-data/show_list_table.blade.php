@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Browse by {{$data['category_name']}}</h4>

            </div>
        </div>
        <div class="card">
            <div class="card">
                <div class="table-responsive">
                    <h4 class="page-title">{{$data['sub_category_name']}}</h4>
                    <table id="example" class="table display" style="border:none !important">
                        <thead>
                        <tr id="head-table" style="border:none !important"></tr>
                        <tr id="heads">
                            @if(isset($data['data'][0]['values']) && $data['data'][0]['values']!= '')
                                <th>Top Work Values</th>
                            @else
                                <th>Importance</th>
                                <th>Level</th>
                            @endif

                            <th>Job Zone</th>
                            <th>Code</th>
                            <th>Occupation</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($data['data'] as $key => $value)
                            <tr>
                                @if($value->values != '')
                                    <td>{{$value->values}}</td>
                                @else
                                <td>
                                    <div class="progress-bar" role="progressbar" style="width: {{$value->importance}}%"
                                         aria-valuenow="{{$value->importance}}" aria-valuemin="0"
                                         aria-valuemax="100">{{$value->importance}}</div>
                                <td>
                                    <div class="progress-bar" role="progressbar" style="width: {{$value->level}}%"
                                         aria-valuenow="{{$value->level}}" aria-valuemin="0"
                                         aria-valuemax="100">{{$value->level}}</div>
                                </td>
                                @endif
                                <td>{{$value->job_zone}}</td>
                                <td>{{$value->code}}</td>
                                <td>
                                    <a href="{{ route('o-net-data-table.show-list-details',['code' => $value->code, 'occupation' => $value->occupation ])}}">{{$value->occupation}}
                                    </a></td>


                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

@include('includes.footerJs')
@include('includes.footer')
