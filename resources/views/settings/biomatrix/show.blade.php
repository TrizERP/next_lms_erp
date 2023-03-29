@include('includes.headcss')
<link rel="stylesheet" href="../../../tooltip/enjoyhint/jquery.enjoyhint.css">
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Biomatrix Setting</h4>
            </div>
        </div>

        <div class="white-box">
            <div class="panel-body p-0">
                <div class="row">
                    <div class="col-lg-3 col-sm-3 col-xs-3 m-30">
                        <a href="{{ route('biomatrix.create') }}" class="btn btn-info add-new"><i
                                class="fa fa-plus"></i> Add New</a>
                    </div>

                    <div class="col-lg-12 col-sm-12 col-xs-12">
                        <div class="table-responsive">
                            <table id="example" class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Sr No</th>
                                        <th>BiomatixId</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                    $j=1;
                                    @endphp
                                    @if(isset($data['data']))
                                    @foreach($data['data'] as $key => $datas)
                                    <tr>
                                        <td>{{$j}}</td>
                                        <td>{{$datas->biomatrix_id}}</td>
                                        <td><a href="{{ route('biomatrix.edit',$datas->id)}}"><button
                                                    style="float:left;" type="button"
                                                    class="btn btn-info btn-outline btn-circle btn m-r-5"><i
                                                        class="ti-pencil-alt"></i></button></a>
                                            <form action="{{ route('biomatrix.destroy', $datas->id)}}" method="post">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" onclick="return confirmDelete();"
                                                    class="btn btn-info btn-outline btn-circle btn m-r-5"><i
                                                        class="ti-trash"></i></button>
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