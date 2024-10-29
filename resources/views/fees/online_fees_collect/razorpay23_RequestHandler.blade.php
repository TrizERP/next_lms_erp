<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- CSRF Token -->
    {{-- <meta name="csrf-token" content="{{ csrf_token() }}"> --}}
    <title>Pay Online Fees</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
</head>
<body>
    <div id="app">
        <main class="py-4">
            <div class="container">
                <div class="row">
                    <div class="col-md-6 offset-3 col-md-offset-6">
                        
                        <div class="card card-default">
                            <div class="card-header">
                                Online Fees
                            </div>
                            <div class="card-body text-center">
                                <div>{{$data['student_name']}}</div>
                                <div>{{$data['medium']}}</div>
                                <form action="https://erp.triz.co.in/fees/razorpay/online_fees_razorpayResponseHandler" method="POST" >
                                    @csrf
                                    <input type="hidden" value ="{{$data['student_id']}}" name="student_id">
                                    <input type="hidden" value ="{{$data['inserted_id']}}" name="inserted_id">
                                    <script src="https://checkout.razorpay.com/v1/checkout.js"
                                            data-key="{{$data['key']}}"
                                            data-amount="{{$data['amount']}}"
                                            data-buttontext="Pay Now"
                                            data-name="{{$data['student_name']}}"
                                            data-description="{{$data['medium']}}"
                                            data-order_id="{{$data['order_id']}}"
                                            data-theme.color="#ff7529">
                                    </script>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
