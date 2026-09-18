@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
                <h4 class="page-title">Edit Email Template</h4>
            </div>
        </div>
        <div class="card">
            @if ($sessionData = Session::get('data'))
            <div class="alert {{ ($sessionData['status_code'] ?? 1) == 1 ? 'alert-success' : 'alert-danger' }} alert-block">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <strong>{{ $sessionData['message'] }}</strong>
            </div>
            @endif
            <form action="{{ route('email_template.update', $data['data']['id']) }}" method="post" class="email-template-form">
                @csrf
                @method('PUT')
                @include('communication.email_template._form')
            </form>
            {{-- Kept outside the form: includes.footerJs carries an email modal
                 with its own name/email/subject inputs, which would otherwise be
                 submitted with this form and blank out these fields. --}}
            @include('communication.email_template._scripts')
        </div>
    </div>
</div>
@endsection
