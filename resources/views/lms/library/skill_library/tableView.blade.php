<div class="card roundCard" style="padding:10px">
    <div class="row">
        <div class="col-md-10">&nbsp;</div>
        <div class="col-md-2">
            <a class="btn btn-success" href="{{route('skill_library.create')}}">
                <i class="fa fa-plus-circle" aria-hidden="true"></i> Add New Skill
            </a>
        </div>
    </div>
    <div class="table-responsive">
		<table class="table table-bordered" style="color:#000 !important;margin-top:10px;">
            <thead>
                <tr>
                    <th>Category Name</th>
                    <th>Sub category</th>
                    <th>Name</th>
                    <th class="text-left">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['tableData'] as $key=>$value)
                <tr>
                    <td>{{$value['category']}}</td>
                    <td>{{$value['sub_category']}}</td>
                    <td>{{$value['title']}}</td>
                    <td>
                        <div class="d-inline">
                            <a href="{{ route('skill_library.edit',$value['id'])}}" class="btn btn-info btn-outline"><i class="ti-pencil-alt"></i></a>
                        </div>
                        <form class="d-inline" action="{{ route('skill_library.destroy', $value['id'])}}" method="post">
                            @csrf
                            @method('DELETE')
                            <button type="submit" onclick="return confirmDelete();" class="btn btn-info btn-outline-danger"><i class="ti-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>