<div class="card">
    {{-- Add Buttons Section --}}
    <div class="row">
        <div class="col-md-12 text-right">
            <a data-toggle="modal" data-target="#exampleModal" class="btn btn-primary">Add Title</a>
        </div>
    </div>

    {{-- Modal Section --}}
    <div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document" style="max-width: 1200px;">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Add Title</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    {{-- Add Title Form --}}
                    <form id="titleForm" action="{{ route('dicipline-Master.store') }}" enctype="multipart/form-data" method="post">
                        @csrf
                        <input type="hidden" name="formType" value="title">
                        <div class="row">
                            <div class="col-md-4">
                                <label for="title">Title</label>
                                <input type="text" class="form-control" name="title" id="editTitle" placeholder="Enter Title" required>
                            </div>
                            <div class="col-md-4">
                                <input type="submit" value="Submit" class="btn btn-success mt-4 titleBtn">
                            </div>
                        </div>
                    </form>

                    {{-- Titles Table --}}
                    @if(isset($data['diciplineDD']) && count($data['diciplineDD']) > 0)
                        <div class="table-responsive mt-2">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Sr No</th>
                                        <th>Title</th>
                                        <th class="text-left">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($data['diciplineDD'] as $key => $diciplineDD)
                                        <tr>
                                            <td>{{ $key + 1 }}</td>
                                            <td>{{ $diciplineDD['message'] }}</td>
                                            <td>
                                                <div class="d-flex align-items-center justify-content-end">
                                                    <a class="btn btn-outline-success mr-1 editBtn" 
                                                       data-id="{{ $diciplineDD['id'] }}" 
                                                       data-message="{{ $diciplineDD['message'] }}">
                                                        <i class="ti-pencil-alt"></i>
                                                    </a>
                                                    <form action="{{ route('dicipline-Master.destroy', $diciplineDD['id']) }}" class="d-inline" method="post">
                                                        @csrf
                                                        @method('DELETE')
                                                        <input type="hidden" name="formType" value="title">
                                                        <button onclick="return confirmDelete();" type="submit" class="btn btn-outline-danger"><i class="ti-trash"></i></button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                </div>
            </div>
        </div>
    </div>

    {{-- Main Form Section --}}
    <form id="editMasterForm" action="{{ route('dicipline-Master.store') }}" enctype="multipart/form-data" method="post">
        @csrf
        <input type="hidden" name="formType" value="master">
        <div class="row">
            <div class="col-md-4">
                <label for="dicipline_id">Title</label>
                <select name="dicipline_id" id="dicipline_id" class="form-control">
                    @if(isset($data['diciplineDD']) && count($data['diciplineDD']) > 0)
                        @foreach($data['diciplineDD'] as $diciplineDD)
                            <option value="{{ $diciplineDD['id'] }}">{{ $diciplineDD['message'] }}</option>
                        @endforeach
                    @else
                        <option value="">Please Add Title First</option>
                    @endif
                </select>
            </div>
            <div class="col-md-4">
                <label for="message">Message</label>
                <input type="text" class="form-control" name="message" id="editMessage" placeholder="Enter Message" required>
            </div>
            <div class="col-md-12">
                <center>
                    <input type="submit" value="Submit" class="btn btn-success mt-4 masterBtn">
                </center>
            </div>
        </div>
    </form>
</div>
