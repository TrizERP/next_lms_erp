@include('includes.headcss')

<div id="page-wrapper" style="min-height:100vh; display:flex; align-items:center; justify-content:center; background:linear-gradient(to bottom right, #dbeafe, #ffffff, #eff6ff);">
    <div class="container" style="padding:20px; margin-top:20px; width:100%; max-width:600px;">
        
        {{-- Cash Payment Success --}}
        @if(isset($data['payment_type']) && $data['payment_type'] == 'cash')
            <div style="background:#ffffff; box-shadow:0 4px 12px rgba(0,0,0,0.1); border-radius:16px; padding:40px 20px; text-align:center;">
                <div style="width:100%; background:#22c55e; color:#fff; font-weight:600; padding:12px 20px; border-radius:8px; margin-bottom:20px; font-size:16px; line-height:1.5;">
                    ✅ Thank you for your enquiry.<br>
                    Please save the enquiry token below for your future reference.
                </div>
                <p style="color:#374151; font-size:18px; font-weight:500; margin-top:24px;">
                    <strong style="display:block; margin-bottom:8px;">Enquiry Token:</strong> 
                    <span style="background:#fef08a; color:#111827; padding:12px 20px; border-radius:8px; font-size:22px; font-weight:700; display:inline-block; box-shadow:0 2px 6px rgba(0,0,0,0.1);">
                        {{ $data['enquiry_no'] ?? 'N/A' }}
                    </span>
                </p>
            </div>

        {{-- Online Payment - Upload Form --}}
        @elseif(isset($data['payment_type']) && $data['payment_type'] == 'online')
            <div style="background:#ffffff; box-shadow:0 4px 12px rgba(0,0,0,0.1); border-radius:16px; padding:40px 20px;">
                <h2 style="font-size:22px; font-weight:700; color:#1f2937; margin-bottom:24px; text-align:center; border-bottom:1px solid #e5e7eb; padding-bottom:20px;">
                    💳 Upload Payment Attachment
                </h2>
                
                <form action="{{route('admission_enquiry.payment_proof')}}" method="POST" enctype="multipart/form-data" style="display:flex; flex-wrap:wrap; gap:20px;">
                    @csrf
                    <input type="hidden" name="enquiry_no" value="{{ $data['enquiry_no'] ?? '' }}">
                    <input type="hidden" name="enquiry_id" value="{{ $data['enquiry_id'] ?? '' }}">
                    <input type="hidden" name="sub_institute_id" value="{{ $data['sub_institute_id'] ?? '' }}">
                    <input type="hidden" name="syear" value="{{ $data['syear'] ?? '' }}">
                    
                    <div style="flex:1; min-width:200px;">
                        <label for="attachment" style="display:block; color:#374151; font-weight:500; margin-bottom:8px;">Choose File:</label>
                        <input type="file" name="attachment" id="attachment" accept="image/*"
                               style="width:100%; border:1px solid #d1d5db; border-radius:8px; padding:10px 12px; outline:none;">
                    </div>
                    
                    <div style="flex:1; min-width:200px; display:flex; align-items:flex-end;">
                        <button type="submit" 
                                style="width:100%; background:#22c55e; color:white; font-weight:600; padding:12px; border:none; border-radius:8px; cursor:pointer;">
                            Submit
                        </button>
                    </div>
                </form>
            </div>
        @endif

    </div>
</div>

@include('includes.footer')
