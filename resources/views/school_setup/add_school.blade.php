@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row" style=" margin-top: 25px;">
            <div class="panel-body">
                @if ($message = Session::get('success'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                </div>
                @endif
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="
                          @if (isset($data))
                          {{ route('add_school.update', $data->Id) }}
                          @else
                          {{ route('add_school.store') }}
                          @endif

                          " enctype="multipart/form-data" method="post">

                        @if(!isset($data))
                        {{ method_field("POST") }}
                        @else
                        {{ method_field("PUT") }}
                        @endif


                        {{csrf_field()}}
                        <div class="col-md-6 form-group">
                            <label>School Name </label>
                            <input type="text" id='SchoolName' required name="SchoolName" value="@if(isset($data->SchoolName)) {{ $data->SchoolName }} @endif" class="form-control">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Short Code </label>
                            <input type="text" id='ShortCode' required name="ShortCode" value="@if(isset($data->ShortCode)) {{ $data->ShortCode }} @endif" class="form-control">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Contact Person</label>
                            <input type="text" id='ContactPerson' required name="ContactPerson" value="@if(isset($data->ContactPerson)) {{ $data->ContactPerson }} @endif" class="form-control">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Mobile</label>
                            <input type="text" id='Mobile' required name="Mobile" value="@if(isset($data->Mobile)) {{ $data->Mobile }} @endif" class="form-control">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Email</label>
                            <input type="email" id='Email' required name="Email" value="@if(isset($data->Email)) {{ $data->Email }} @endif" class="form-control">
                        </div>

                        <div class="col-md-6 form-group">
                            <label>Receipt Header</label>
                            <input type="text" id='ReceiptHeader' required name="ReceiptHeader" value="@if(isset($data->ReceiptHeader)) {{ $data->ReceiptHeader }} @endif" class="form-control">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Receipt Address</label>
                            <input type="text" id='ReceiptAddress' required name="ReceiptAddress" value="@if(isset($data->ReceiptAddress)) {{ $data->ReceiptAddress }} @endif" class="form-control">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Fee Email</label>
                            <input type="email" id='FeeEmail' required name="FeeEmail" value="@if(isset($data->FeeEmail)) {{ $data->FeeEmail }} @endif" class="form-control">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Receipt Contact</label>
                            <input type="text" id='ReceiptContact' required name="ReceiptContact" value="@if(isset($data->ReceiptContact)) {{ $data->ReceiptContact }} @endif" class="form-control">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Sort Order</label>
                            <input type="text" id='SortOrder' name="SortOrder" value="@if(isset($data->SortOrder)) {{ $data->SortOrder }} @endif" class="form-control">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Logo</label>
                            <input type="file" name="Logo" id="Logo"  accept="image/*">
                        </div>
                        <div class="col-md-12 form-group">
                            <center>
                                <input type="submit" name="submit" value="Save" class="btn btn-success" >
                            </center>
                        </div>

                    </form>

                </div>
                @if (count($errors) > 0)
                <div class="alert alert-danger">
                    <strong>Whoops!</strong> There were some problems with your input.<br><br>
                    <ul>
                        @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

@include('includes.footerJs')
@include('includes.footer')
