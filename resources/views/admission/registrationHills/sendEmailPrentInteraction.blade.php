<!DOCTYPE html>
<html>
<head>
    <title>Admission confirmation</title>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
</head>

<body style="padding: 10px; margin: 0; font-family: Arial, Helvetica, sans-serif; background: #f4f6f9;">

<!--[if mso]>
<style type="text/css">
body, table, td, div, p {font-family: Arial, Helvetica, sans-serif !important;}
</style>
<![endif]-->

@if($page_type=="parent")
@if (isset($pint) && $pint == 'I')
    <div style="color:#000000;">
        <b style="color:#000000;">Dear Parent,</b>
        <p style="color:#000000; font-size: 15px; line-height: 1.6;">
            We are glad to inform you that you have proceeded to stage 3 of admission for your child at Hills High
            School, Vesu, Surat. You are requested to be present at Hills High School campus on <span
                style="background:#ffff00;">{{ \Carbon\Carbon::parse($parent_date)->format('d/m/Y') }} at
                {{ \Carbon\Carbon::parse($parent_time)->format('h:i a') }}</span> sharp, for a brief interaction
            (approx.45mins) with the Admission Committee. This is an opportunity for all of us to get familiar with
            each other. It is compulsory for BOTH the parents to be present and also to strictly follow the time.
            This will be an exclusive interaction with the parents, kindly avoid bringing your child along with you.
            Parents who cannot be present for the interaction please mail on <a href="mailto:admission@hillshigh.com" style="color: #1a73e8;">admission@hillshigh.com</a> for an
            alternative date.
        </p>
        <p style="margin: 15px 0;">&nbsp;</p>
        <p style="color:#000000; font-size: 15px;">->Please carry a xerox copy of Child's previous year Report card for Academic Year 2025-26 (Any one) & 2024-25 (Final exam Report card).</p>
        
        <p style="color:#000000; font-size: 15px;"><b>Please revert your confirmation for same.</b></p>
        <p style="color:#000000; font-size: 15px;"><b>THIS IS NOT A CONFIRMATION OF ADMISSION</b></p>
        <p style="margin: 15px 0;">&nbsp;</p>
        <p style="color:#000000; font-size: 15px;"><b>Mr.P.P.Jose</b></p>
        <p style="color:#000000; font-size: 15px;"><b>(Principal)</b></p>
    </div>
@elseif(isset($pint) && $pint == 'W/L')
    <div style="color:#000000;">
        <h3 style="color:#000000; margin: 10px 0;">HILLS HIGH SCHOOL</h3>
        <h3 style="color:#000000; text-align:center; margin: 15px 0;"><b>Waitlisted</b></h3>
        <p style="color:#000000; font-size: 15px; line-height: 1.6;">
            Your child has been placed on the waitlist. Please re-check your e-mail id by end of this month.
            Your co-operation is really appreciated.
        </p>
        <p style="color:#000000; font-size: 15px;">Thanks</p>
    </div>
@elseif(isset($pint) && $pint == 'NO')
    <div style="color:#000000;">
        <h3 style="color:#000000; text-align:center; margin: 15px 0;"><b>Regret mail</b></h3>
        <p style="color:#000000; font-size: 15px;">Dear Parent,</p>
        <p style="color:#000000; font-size: 15px; line-height: 1.6;">
            We regret to inform you that we are unable to grant admission for your child at
            Hill's' High School.
            Any inconvenience caused is deeply regretted.
        </p>
        <p style="color:#000000; font-size: 15px;">Regards ,</p>
        <p style="color:#000000; font-size: 15px;">Hills High School</p>
    </div>
@else
    <div style="border:3px solid #000000; padding:1px; color:#000000;">
        <div style="border:1px solid #000000; padding:6px; color:#000000;">
            <div style="text-align: right;">
                <img src="https://erp.triz.co.in/admin_dep/images/hills_logo1.png" width="150" height="50" style="display: inline-block;" />
            </div>
            <div style="color:#000000;">
                <h2 style="text-align: center; text-decoration: underline; color:#000000; margin: 15px 0;">
                    Standard-{{ $admission_std }} ADMISSION PROCEDURE AT HILLS HIGH SCHOOL, A.Y- {{ $aca_year }}</h2>
                <p style="font-size:16px; color:#000000;">Dear Parents,</p>
                <p style="text-decoration:underline; font-size:16px; color:#000000;">
                    Please note that the issue of Admission Forms DOES NOT GUARANTEE ADMISSION.
                </p>

                <h3 style="text-decoration:underline; color:#000000; margin: 20px 0 10px 0;">CONTACT WITH HILLS HIGH SCHOOL</h3>
                <ul style="padding-left: 20px;">
                    <li style="font-size:16px; color:#000000; margin-bottom: 8px;">
                        Please note that all communication with the Parents will be done via e-mail ONLY.
                        The school mail id is <a href="mailto:admission@hillshigh.com" style="color: #1a73e8;">admission@hillshigh.com</a>.
                    </li>
                    <li style="font-size:16px; color:#000000;">
                        The STATUS of admission of your child's admission will be communicated via email.
                    </li>
                </ul>

                <h3 style="text-decoration:underline; color:#000000; margin: 20px 0 10px 0;">ADMISSION PROCEDURE</h3>
                <p style="font-size:16px; color:#000000;">
                    The Procedure for admissions at Hills High School has 4 stages
                </p>

                <h3 style="text-decoration:underline; color:#000000; margin: 20px 0 10px 0;">STAGE-1 COLLECTION OF FORM</h3>
                <ul style="padding-left: 20px;">
                    <li style="font-size:16px; color:#000000;">
                        Collection of Admission Form from Hills High School, Vesu at the date & time given.
                    </li>
                </ul>

                <h3 style="text-decoration:underline; color:#000000; margin: 20px 0 10px 0;">STAGE 2 Activity session :</h3>
                <ul style="padding-left: 20px;">
                    <li style="font-size:16px; color:#000000; margin-bottom: 8px;">
                        A fun filled activity session will be conducted at Hills High School, Vesu, on 
                        <b>{{ \Carbon\Carbon::parse($parent_date)->format('d-m-Y') }} at {{ \Carbon\Carbon::parse($parent_time)->format('h:i a') }}</b>
                    </li>
                    <li style="font-size:16px; color:#000000; margin-bottom: 8px;">
                        Child should be present with <span style="background-color: #ffff00;">current School I-card.</span>
                    </li>
                    <li style="font-size:16px; color:#000000;">
                        The session would be for approx 45mins.
                    </li>
                </ul>

                <h3 style="text-decoration:underline; color:#000000; margin: 20px 0 10px 0;">STAGE 3- Parents Interaction (If the students Activity session is cleared): </h3>
                <ul style="padding-left: 20px;">
                    <li style="font-size:16px; color:#000000;">
                        Parents interaction with the Admission committee will be held at a specific date and time. For
                        the interaction, it is COMPULSORY FOR BOTH MOTHER & FATHER TO BE PRESENT. Please do not bring
                        the child for the parents interaction.
                    </li>
                </ul>

                <h3 style="text-decoration:underline; color:#000000; margin: 20px 0 10px 0;">STAGE 4- Submission of form and required documents:</h3>
                <p style="font-size:16px; color:#000000; line-height: 1.6;">
                    Candidates who are selected after the interaction will need to submit all the necessary documents along with the admission form & fees to Hills High School office, within the stipulated time, to complete the Provisional Admission procedure.
                </p>
                <ul style="padding-left: 20px; margin: 15px 0;">
                    <li style="font-size:16px; color:#000000;">
                        <b>PLEASE NOTE :-</b> In case parents or child are unable to attend any of the sessions on the dates / time allotted by us, kindly send a mail to <a href="mailto:admission@hillshigh.com" style="color: #1a73e8;">admission@hillshigh.com</a> so that we can arrange an alternative date / time.
                    </li>
                </ul>
                <p style="font-size:16px; color:#000000; line-height: 1.6;">
                    <b>Selection of Shift - </b>Morning or Noon will be on the basis of first come first serve, Once the total number of students are enrolled in either morning or afternoon session admission will be granted only in the other session.
                </p>

                <div style="text-align:center; color:#000000; margin-top: 25px;">
                    <h3 style="font-size:16px; color:#000000; margin: 15px 0;">Wishing you all the Best!!!</h3>
                    <h3 style="color:#000000; margin: 15px 0;">THANK YOU!! <br>Hills High Family</h3>
                    <p style="font-size:16px; color:#000000; line-height: 1.6;">
                        <b>Address:</b> 65-A RCC Canal Road Opp.Nandavan Apt,Vesu, Surat, Gujarat 395007.<br>
                        Contact Number-9033095477 / 3477 <span style="color:#ff0000;">Connect us on Instagram</span> :- @hillshighschool <br>
                        School website - <a href="http://www.hillshigh.com" style="color: #1a73e8;">www.hillshigh.com</a><br>
                        Email Id: <a href="mailto:admission@hillshigh.com" style="color: #1a73e8;">admission@hillshigh.com</a> (For any admission query)
                    </p>
                </div>
            </div>
        </div>
    </div>
@endif
@elseif($page_type=="confirm")
<div style="border: 3px solid #000000; padding: 2px; font-family: Arial, sans-serif;">
    <div style="border: 1px solid #000000; padding: 18px;">
  
      <!-- HEADER -->
      <div style="width: 100%; margin-bottom:10px;">
        <div style="float:left; width:50%;">
          <p style="color:#943634; font-size:18px; font-weight:bold; margin:0;">HHS/EC/2/2026-27</p>
        </div>
  
        <div style="float:right; width:50%; text-align:right;">
          <img src="https://erp.triz.co.in/admin_dep/images/hills_logo1.png" width="160" height="55" style="display: inline-block;">
        </div>
  
        <div style="clear:both;"></div>
      </div>
  
      <!-- TITLE -->
      <div style="text-align:center; margin-top:5px;">
        <h2 style="margin:0; color:#000000; font-weight:bold; letter-spacing:1px;">HILLS' HIGH SCHOOL</h2>
        <h3 style="margin:0; color:#000000; font-weight:bold;">PROVISIONAL ADMISSION ({{$admission_std}}) ($conf=="C") ? 'Morning Session' : 'Afternoon Session' }}.</h3>
      </div>
  
      <hr style="border:0; border-top:2px solid #943634; margin:18px 0;">
  
      <!-- GREETINGS -->
      <h2 style="margin:0; color:#000000;"><b>Dear Parents,</b></h2>
  
      <p style="font-size:15px; line-height:1.7; margin-top:10px;">
        Hills High School is pleased to grant provisional admission to your ward. Please visit the school between 
        <b>{{date('d-m-Y',strtotime($conf_date))}} between {{$parent_time}} (9:00am to 11:00am)</b>
        - <b>SUNDAY CLOSED</b> - for submitting the required documents & fees.
      </p>
  
      <!-- SECTION A -->
      <h3 style="color:#943634; margin-top:25px; text-decoration:underline;">A) The Admission Form</h3>
  
      <ol style="line-height:1.7; font-size:15px; color:#000000; padding-left: 25px;">
        <li style="margin-bottom: 8px;">The Form must be filled in with a <b>Blue or Black Pen</b>.</li>
        <li style="margin-bottom: 8px;"><b>UDISE, PEN NO, APAAR ID</b> details are <b>MANDATORY</b>.</li>
        <li style="margin-bottom: 8px;">Use <b>CAPITAL LETTERS</b> only.</li>
        <li style="margin-bottom: 8px;">Paste recent photographs of Father, Mother & Child.</li>
        <li style="margin-bottom: 8px;">Ensure every field is filled. <b>Incomplete forms will not be accepted.</b></li>
      </ol>
  
      <!-- SECTION B -->
      <h3 style="color:#943634; margin-top:25px; text-decoration:underline;">B) Documents & Photos</h3>
  
      <ol style="line-height:1.7; font-size:15px; color:#000000; padding-left: 25px;">
        <li style="margin-bottom: 8px;">Child's Birth Certificate (Photocopy).</li>
        <li style="margin-bottom: 8px;">Caste Certificate (if applicable).</li>
        <li style="margin-bottom: 8px;">Recent photograph of child in white collared shirt + 1 extra.</li>
        <li style="margin-bottom: 8px;">Aadhar Card of Child, Father & Mother (Mandatory).</li>
        <li style="margin-bottom: 8px;">Family Photograph (5×7").</li>
        <li style="margin-bottom: 8px;">U-DISE, PEN No., APAAR ID from previous school (<b>MANDATORY</b>).</li>
        <li style="margin-bottom: 8px;">Full-year Fee Receipt + No Due Certificate (Attested).</li>
        <li style="margin-bottom: 8px;">Reference Letter (Not required for siblings).</li>
        <li style="margin-bottom: 8px;">Original School Leaving Certificate.</li>
      </ol>
  
      <!-- SECTION C -->
      <h3 style="color:#943634; margin-top:25px; text-decoration:underline;">C) Completion of NACH Document</h3>
  
      <p style="line-height:1.7; font-size:15px;">
        The school follows a cashless fee-collection system. You must submit:
      </p>
  
      <ul style="line-height:1.7; font-size:15px; padding-left:25px;">
        <li style="margin-bottom: 8px;">Completed NACH Form.</li>
        <li style="margin-bottom: 8px;">Cancelled Cheque of Fee Payment Account.</li>
        <li style="margin-bottom: 8px;">Company Stamp (if account is business/proprietorship).</li>
        <li style="margin-bottom: 8px;">Joint Account → All holders must sign.</li>
      </ul>
  
      <div style="background:#fff4e6; border-left:4px solid #943634; padding:10px 15px; margin-top:10px;">
        <p style="margin:0; color:#000000; font-weight:bold;">
          • Admission remains <u>PROVISIONAL until NACH approval.</u><br>
          • Admission is <u>CONFIRMED only after bank approval.</u>
        </p>
      </div>
  
      <!-- SECTION D -->
      <h3 style="color:#943634; margin-top:25px; text-decoration:underline;">D) Fee Amount</h3>
  
      <ol style="line-height:1.7; font-size:15px; color:#000000; padding-left: 25px;">
        <li style="margin-bottom: 8px;">Fee for year 2026-27: <b style="color:#943634;">₹ 80,800/- *</b></li>
        <li style="margin-bottom: 8px;">Fees payable at time of admission:</li>
      </ol>
  
      <table style="width:100%; border:2px solid #000000; border-collapse:collapse; margin:15px 0;">
        <tr style="background:#f0e8e8;">
          <th style="border:2px solid #000000; padding:10px; text-align:center;">1st Quarter Tuition Fees</th>
          <th style="border:2px solid #000000; padding:10px; text-align:center;">One-Time Admission Fees</th>
          <th style="border:2px solid #000000; padding:10px; text-align:center;">Total Provisional Fees</th>
        </tr>
        <tr>
          <td style="border:2px solid #000000; padding:10px; color:#943634; text-align:center;"><b>₹ 20,200/-</b></td>
          <td style="border:2px solid #000000; padding:10px; color:#943634; text-align:center;"><b>₹ 6,733/-</b></td>
          <td style="border:2px solid #000000; padding:10px; color:#943634; text-align:center;"><b>₹ 26,933/-</b></td>
        </tr>
      </table>
  
      <p style="font-size:15px;"><b>* Final Fee subject to Honorable Court decision.</b></p>
  
      <p style="line-height:1.7; font-size:15px;">
        Pay <b style="color:#943634;">₹ 26,933/-</b> by <b>Demand Draft / Cheque</b> payable to <b>Hills High School</b>.  
        <br><b>No NEFT / No Cash will be accepted.</b>
      </p>
  
      <div style="background:#fff4e6; border-left:4px solid #943634; padding:10px 15px; margin-top:10px;">
        <p style="margin:0; color:#000000; font-weight:bold;">
          • FEES ONCE PAID ARE NON-REFUNDABLE AND NON-TRANSFERABLE.<br>
          • Non-submission of Documents & Fees within due date → <u>Admission Cancelled.</u>
        </p>
      </div>
  
      <!-- SIGNATURES -->
      <div style="width:100%; margin-top:30px;">
        <div style="float:left; width:50%; text-align:center;">
          <p style="color:#943634; font-size:16px; margin:0;"><b>Mr. P.P. Jose</b></p>
          <p style="color:#943634; font-weight:bold;">Principal</p>
        </div>
  
        <div style="float:right; width:50%; text-align:center;">
          <p style="color:#943634; font-size:16px; margin:0;"><b>Mrs. Persis Hilluwala</b></p>
          <p style="color:#943634; font-weight:bold;">Director</p>
        </div>
  
        <div style="clear:both;"></div>
      </div>
    </div>
  </div>      
@endif

</body>
</html>