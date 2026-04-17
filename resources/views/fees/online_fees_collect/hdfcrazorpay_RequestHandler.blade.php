<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pay Online Fees</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" crossorigin="anonymous">
</head>
<body>
    <div class="container py-5">
        <div class="row">
            <div class="col-md-6 offset-md-3">
                <div class="card shadow-lg">
                    <div class="card-header text-center bg-primary text-white">
                        <h5>Online Fees Payment</h5>
                    </div>
                    <div class="card-body text-center">
                        <h6>{{ $data['student_name'] }}</h6>
                        <p><strong>Medium:</strong> {{ $data['medium'] }}</p>
                        <p><strong>Amount:</strong> ₹{{ number_format($data['amount']/100, 2) }}</p>

                        <form method="POST" action="https://api.razorpay.com/v1/checkout/embedded">
                            <input type="hidden" name="key_id" value="{{ $data['key'] }}">
                            <input type="hidden" name="amount" value="{{ $data['amount'] }}">
                            <input type="hidden" name="order_id" value="{{ $data['order_id'] }}">
                            <input type="hidden" name="name" value="{{ $data['student_name'] }}">
                            <input type="hidden" name="description" value="{{ $data['medium'] }}">

                            {{-- Include fallback query parameters for callback --}}
                            <input type="hidden" name="callback_url"
                                   value="{{ route('hdfcrazorpay_response_handler') }}?redirect=1&student_id={{ $data['student_id'] }}&inserted_id={{ $data['inserted_id'] }}">
                            
                            <input type="hidden" name="notes[student_id]" value="{{ $data['student_id'] }}">
                            <input type="hidden" name="notes[inserted_id]" value="{{ $data['inserted_id'] }}">

                            <button type="submit" class="btn btn-success btn-block mt-3">Pay Now</button>
                        </form>

                        <p class="text-muted small mt-3">You will be redirected to Razorpay for secure payment.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
