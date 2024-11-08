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
                            @if(isset($data['data'][0]['values']) && $data['data'][0]['values']!= 0)
                                <th>Top Work Values</th>
                                <th>Job Zone</th>
                            @elseif(isset($data['data'][0]['importance'])  && $data['data'][0]['importance']!= 0)
                                <th>Job Zone</th>
                                <th>Importance</th>
                                <th>Level</th>
                            @endif
                            <th>Code</th>
                            <th>Occupation</th>
                            @if(isset($data['data'][0]['occupation_type']) && $data['data'][0]['occupation_type']!= '')
                                <th>Occupation Type</th>
                            @endif
                            @if(isset($data['data'][0]['projected_growth'])  && $data['data'][0]['projected_growth']!= '')
                                <th>Employed by this Industry</th>
                                <th>Project Growth (2022 - 2032)</th>
                                <th>Project Job Openings (2022 - 2032)</th>
                            @endif
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($data['data'] as $key => $value)
                            <tr>
                                @if(isset($data['data'][0]['projected_growth'])  && $data['data'][0]['projected_growth']!= '')
                                    <td>{{$value->code}}</td>
                                    <td>
                                        <a href="{{ route('o-net-data-table.show-list-details',['code' => $value->code, 'occupation' => $value->occupation ])}}">{{$value->occupation}}
                                        </a></td>
                                    <td>{{$value->employee_by_this_industry}}</td>
                                    <td>{{$value->projected_growth}}</td>
                                    <td>{{$value->projected_growth_openings}}</td>

                                @else
                                    @if($value->values != 0)
                                        <td>{{$value->values}}</td>
                                    @elseif($data['data'][0]['importance']!= 0)
                                        <td>
                                            <div class="progress-bar" role="progressbar"
                                                 style="width: {{$value->importance}}%"
                                                 aria-valuenow="{{$value->importance}}" aria-valuemin="0"
                                                 aria-valuemax="100">{{$value->importance}}</div>
                                        <td>
                                            <div class="progress-bar" role="progressbar"
                                                 style="width: {{$value->level}}%"
                                                 aria-valuenow="{{$value->level}}" aria-valuemin="0"
                                                 aria-valuemax="100">{{$value->level}}</div>
                                        </td>
                                    @endif
                                    <td>{{$value->job_zone}}</td>
                                    <td>{{$value->code}}</td>

                                    <td>
                                        <a href="{{ route('o-net-data-table.show-list-details',['code' => $value->code, 'occupation' => $value->occupation ])}}">{{$value->occupation}}
                                        </a></td>
                                    <td>{{$value->occupation_type}}</td>
                                @endif

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
