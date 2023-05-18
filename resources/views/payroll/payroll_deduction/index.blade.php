@include('includes.headcss')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Payroll Deduction</h4>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                @if ($sessionData = Session::get('data'))
                    @if($sessionData['status_code'] == 1)
                        <div class="alert alert-success alert-block">
                            @else
                                <div class="alert alert-danger alert-block">
                                    @endif
                                    <button type="button" class="close" data-dismiss="alert">×</button>
                                    <strong>{{ $sessionData['message'] }}</strong>
                                </div>
                            @endif
                            <form action="" enctype="multipart/form-data"
                                  method="post">
                                @csrf
                                <div class="row">
                                    <div class="col-md-3 form-group">
                                        <label>Payroll Type</label>
                                            <select id="payroll_type" name="payroll_type" class="form-control">
                                                <option value="0">Select Type</option>
                                                <option value="1">Allowance</option>
                                                <option value="2">Deduction</option>
                                            </select>
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <label>Payroll Name</label>
                                        <select id='type' name="type" class="form-control">
                                            <option value="0">Select Name</option>
                                            @foreach($payrollTypes as $type)
                                                <option value="{{$type->payroll_type}}">{{$type->payroll_name}}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-md-2 form-group">
                                        <label>Select Month</label>
                                        <select id='month' name="month" class="form-control">
                                            <option>Select Month</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2 form-group">

                                        <label>Select Year</label>
                                        <select id='year' name="year" class="form-control">
                                            <option>Select Year</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 col-sm-offset-4 text-center form-group">
                                        <input type="submit" name="submit" value="Submit" class="btn btn-success">
                                    </div>
                                </div>
                                <!-- Modal -->
                                <div class="modal fade bd-example-modal-lg" id="exampleModal" tabindex="-1"
                                     role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="exampleModalLabel">Choose Field</h5>
                                                <button type="button" class="close" data-dismiss="modal"
                                                        aria-label="Close">
                                                    <span aria-hidden="true">x</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
            </div>
        </div>
    </div>

    @include('includes.footerJs')
    <script>
        var select = document.getElementById('payroll_type');
        select.addEventListener('change', function () {
            var type = document.getElementById('payroll_type').value;
            window.location.href = window.location.origin +'/payroll-deduction?type=' + type;
        }, false);

    </script>
@include('includes.footer')
