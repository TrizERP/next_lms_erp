@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Online Fees Setting</h4>
            </div>
        </div>
        <div class="card">
            <div class="row">
                <div class="col-lg-3 col-sm-3 col-xs-3 m-30">
                    @if(isset($data['data']))
                        @if (count(($data['data']))==0)
                            <a href="{{ route('online_fees.create') }}" class="btn btn-info">
                                <i class="fa fa-plus"></i> Add New
                            </a>
                        @endif
                    @endif
                </div>
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Sr No</th>
                                    <th>Syear</th>
                                    <th>Bank Name</th>
                                    <th>Link</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                $j=1;
                                @endphp
                                @if(isset($data['data']))
                                @foreach($data['data'] as $key => $data)
                                <tr>
                                    <td>{{$j}}</td>
                                    <td>{{$data['syear']}}</td>
                                    <td>{{$data['bank_name']}}</td>
                                    <td>
                                        <a href="https://erp.triz.co.in/fees/online_fees_collect" target="_blank">
                                            Link
                                        </a>
                                    </td>
                                    <td>
                                        <form action="{{ route('online_fees.destroy', $data['id'])}}" method="post" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                            <button type="submit" onclick="return confirmDelete();" class="btn btn-info btn-outline-danger">
                                                <i class="ti-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                @php
                                $j++;
                                @endphp
                                @endforeach
                                @endif


                            </tbody>

                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('includes.footerJs')
<script>
$(document).ready(function () {
    $('#example').DataTable({
    });
});

</script>
@include('includes.footer')