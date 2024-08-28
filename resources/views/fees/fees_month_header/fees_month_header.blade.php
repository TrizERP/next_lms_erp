{{--@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')--}}
@extends('layout')
@section('container')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Fees Month Header</h4>
            </div>
        </div>
        <div class="card">
            @if ($sessionData = Session::get('data'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $sessionData['message'] }}</strong>
                </div>
            @endif
            <form action="{{ route('fees_month_header.store') }}" enctype="multipart/form-data" method="post">
                @csrf
                <input type="hidden" value="insert" name="action">
                <div class="row">
                    <div class="col-md-12 form-group">
                        <div class="table-responsive">
                            <table id="example" class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th style="text-align: left;">Header</th>
                                    </tr>
                                </thead>
                                @foreach($data['data']['ddMonth'] as $key => $value)
                                    @php
                                        $existingHeader = null;
                                        foreach($data['month_header'] as $row) {
                                            if ($row->month_id == $key) {
                                                $existingHeader = $row->header;
                                                break;
                                            }
                                        }
                                    @endphp
                                    <tr>
                                        <td>{{ $value }}</td>
                                        <td>
                                            <input type="text" name="month_value[{{$key}}]" value="{{ $existingHeader ?? null }}" placeholder="Enter data">
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </div>
                    </div>
                    <div class="col-md-12 form-group">
                        <center>
                            <button type="submit" class="btn btn-info">Submit</button>
                        </center>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>


@include('includes.footerJs')
<script>
    $(document).ready(function () {
        $("input").each(function () {
            var that = this; // fix a reference to the <input> element selected
            $(this).keyup(function () {
//                alert("asdsa");
                newSum.call(that); // pass in a context for newsum():
                // call() redefines what "this" means
                // so newSum() sees 'this' as the <input> element
            });
        });
    });
    function newSum() {
        var sum = 0;
        var thisRow = $(this).closest('tr');

        thisRow.find('td:not(.total) input:text').each(function () {
            if (this.value != '') {
                sum += parseFloat(this.value); // or parseInt(this.value,10) if appropriate
            }
        });

        thisRow.find('td.total input:text').val(sum); // It is an <input>, right?
    }
</script>
@include('includes.footer')
@endsection
