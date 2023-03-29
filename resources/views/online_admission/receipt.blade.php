<!DOCTYPE html>
<html>
<head>
   <title>Admission Receipt</title>
</head>
<body>
   <center>
<img src="https://www.muljibhaimehtainternationalschool.in/wp-content/uploads/2017/03/MMIS_Logo.png" style="height:80px;float:center;">
<h1 style="float:center;">Muljibhai Mehta International School, Virar</h1>
<p>&nbsp;Gokul Township, Off Agashi Road, Bolinj, Virar (W), Mumbai Metropolitan Region, Palghar &ndash; 401303 <br> Phone -7887885780/81/82/83</p>
<center>
   <p><a href="mailto:contact@muljibhaimehtainternationalschool.in">contact@muljibhaimehtainternationalschool.in</a></p>
   <p><a href="http://www.muljibhaimehtainternationalschool">www.muljibhaimehtainternationalschool.in</a></p>
</center>
<p><strong>R E C E I P T</strong></p>
<table style="border-color: black;" border="1" width="0">
   <tbody>
      <tr>
         <td width="148">
            <p><strong>Student Name</strong></p>
         </td>
         <td width="148">
            <p>{{$data['data']['first_name']}} {{$data['data']['middle_name']}} {{$data['data']['last_name']}}</p>
         </td>
         <td width="118">
            <p><strong> Registration No</strong></p>
         </td>
         <td width="199">
            <p>{{$data['data']['enquiry_id']}}</p>
         </td>
      </tr>
      <tr>
         <td width="148">
            <p><strong>Class</strong></p>
         </td>
         <td width="148">
            <p>XI</p>
         </td>
         <td width="118">
            <p><strong>Date of Birth</strong></p>
         </td>
         <td width="199">
            <p>{{date('d-m-Y', strtotime($data['data']['date_of_birth']))}}</p>
         </td>
      </tr>
      <tr>
         <td width="148">
            <p><strong>Aadhar Number</strong></p>
         </td>
         <td width="148">
            <p>{{$data['data']['aadhar_number']}}</p>
         </td>
         <td width="118">
            <p><strong>Reg. Date</strong></p>
         </td>
         <td width="199">
            <p>{{date('d-m-Y')}}</p>
         </td>
         
      </tr>
      <tr>
         <td width="148">
            <p><strong>Academic Session</strong></p>
         </td>
         <td width="148">
            <p>2020-2021</p>
         </td>
         <td width="118">
            <p><strong>Gender</strong></p>
         </td>
         <td width="199">
            <p>{{$data['data']['gender']}}</p>
         </td>
         
      </tr>
      <tr>
         <td width="148">
            <p><strong>Address</strong></p>
         </td>
         <td width="148">
            <p>{{$data['data']['address']}}</p>
         </td>
         <td width="118">
            <p><strong>Email-ID</strong></p>
         </td>
         <td width="199">
            <p>{{$data['data']['email']}}</p>
         </td>
         
      </tr>
      <tr>
         <td colspan="4" width="613">
            <p><strong>Father and Mother Details</strong></p>
         </td>
      </tr>
      <tr>
         <td width="148">
            <p><strong>Father&rsquo;s Name</strong></p>
         </td>
         <td width="148">
            <p>
               @if(isset($data['data']['father_name']))
                  {{$data['data']['father_name']}}</p>
               @else
                  {{$data['data']['middle_name']}}</p>
               @endif

         </td>
         <td width="118">
            <p><strong>Mother&rsquo;s Name </strong></p>
         </td>
         <td width="199">
            <p>{{$data['data']['mother_name']}}</p>
         </td>
         
      </tr>
      <tr>
         <td width="148">
            <p><strong>Occupation</strong></p>
         </td>
         <td width="148">
            <p>{{$data['data']['father_occupation']}}</p>
         </td>
         <td width="118">
            <p><strong>Occupation </strong></p>
         </td>
         <td width="199">
            <p>{{$data['data']['mother_occupation']}}</p>
         </td>
         
      </tr>
      <tr>
         <td width="148">
            <p><strong>Father Mob No</strong></p>
         </td>
         <td width="148">
            <p>{{$data['data']['mobile']}}</p>
         </td>
         <td width="118">
            <p><strong>Mother Mob No.</strong></p>
         </td>
         <td width="199">
            <p>{{$data['data']['mother_mobile_number']}}</p>
         </td>
         
      </tr>
      <tr>
         <td colspan="4" width="613">
            <p><strong>Previous School Details</strong></p>
         </td>
      </tr>
      <tr>
         <td width="148">
            <p><strong>School Name </strong></p>
         </td>
         <td width="148">
            <p>{{$data['data']['previous_school_name']}}</p>
         </td>
         <td width="118">
            <p><strong>Board</strong></p>
         </td>
         <td width="199">
            <p><p>{{$data['data']['board']}}</p></p>
         </td>
         
      </tr>
      <tr>
         <td colspan="4" width="613">
            <p><strong>Stream And Subject Preferences</strong></p>
         </td>
      </tr>
      <tr>
         <td width="148">
            <p><strong>
               @if($data['data']['stream'] == 1)
                  Science
               @else
                  Commerce
               @endif
            </strong></p>
         </td>
         <td colspan="3" width="485">
            <p>{{$data['data']['subjects']}}</p>
         </td>
      </tr>
      <tr>
         <td colspan="4" width="613">&nbsp;</td>
      </tr>
      <tr>
         <td colspan="4" width="613">
            <p><strong>Class X Marks</strong></p>
         </td>
      </tr>
      <tr>
         <td width="172">
            <p><strong>Subject</strong></p>
         </td>
         <td width="118">
            <p><strong>Marks</strong></p>
         </td>
         <td  width="219">
            <p><strong>Grade</strong></p>
         </td>
         <td rowspan="7"><img src="{{asset('storage/student/'.$data['data']['photo_upload'])}}" style="height: 300px; width: 200px;"></td>
      </tr>
      @php
         $copyText = '';
      @endphp
      @if(isset($data['data']['subject']))
      	@foreach($data['data']['subject'] as $key => $value)
         	@if($value != '')
               <tr>
                  <td width="172">
                     <p>{{$value}}</p>
                  </td>
                  <td width="118">
                     <p>{{$data['data']['marks'][$key]}}</p>
                  </td>
                  <td width="219">
                     <p>{{$data['data']['grade'][$key]}}</p>
                  </td>
               </tr>
               @php
                  $copyText .= $value." : ";
                  $copyText .= $data['data']['marks'][$key];
               @endphp
            @endif
      	@endforeach
      @endif
   </tbody>
</table>
<p><strong>Declaration: </strong></p>
<p><strong>I hereby declare that all the information that has been furnished in the application form is true. I agree to the terms of admission. In case admission is granted, I promise to abide by the fee structure and the rules and regulations of the school, which includes decisions regarding my child / ward&rsquo;s general welfare, academics and behaviour.</strong></p>
<p><strong>Student&rsquo;s Signature :_________________ Parent&rsquo;s Signature: ____________________</strong></p></center>
</body>
</html>
<input type="text" style="display: none;" value="{{$copyText}}" id="myInputHost">
<input type="button" value="Copy" onclick="copyLink()" id="myInputHost">
<script type="text/javascript">
    function copyLink() {
            var copyText = document.getElementById("myInputHost");
      copyText.select();
      copyText.setSelectionRange(0, 99999)
      document.execCommand("copy");
      alert("Copied the text: " + copyText.value);
    }
</script>