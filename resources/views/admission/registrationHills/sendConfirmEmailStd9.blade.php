<!DOCTYPE html>
<html>

<head>
    <title>Admission confirmation</title>
</head>
<style>
    body {
        padding: 10px;
    }
</style>

<body>
    @if($page_type=="parent")
    @if (isset($pint) && $pint == 'I')
        <div style="color:black">
            <b style="color:black">Dear Parent,</b>
            <p style="color:black">
                We are glad to inform you that you have proceeded to stage 3 of admission for your child at Hills High
                School, Vesu, Surat. You are requested to be present at Hills High School campus on <span
                    style="background:yellow;">{{ \Carbon\Carbon::parse($parent_date)->format('d/m/Y') }} at
                    {{ \Carbon\Carbon::parse($parent_time)->format('h:i a') }}</span>. sharp, for a brief interaction
                (approx.45mins) with the Admission Committee. This is an opportunity for all of us to get familiar with
                each other. It is compulsory for BOTH the parents to be present and also to strictly follow the time.
                This will be an exclusive interaction with the parents, kindly avoid bringing your child along with you.
                Parents who cannot be present for the interaction please mail on admission@hillshigh.com for an
                alternative date.
            </p>
            <p>&nbsp;</p>
            <p style="color:black"><b>Please revert your confirmation for same.</b></p>
            <p style="color:black"><b>THIS IS NOT A CONFIRMATION OF ADMISSION</b></p>
            <p>&nbsp;</p>
            <p style="color:black"><b>Mr.P.P.Jose</b></p>
            <p style="color:black"><b>(Principal)</b></p>

        </div>
    @elseif(isset($pint) && $pint == 'W/L')
        <div style="color:black">
            <h3 style="color:black">HILLS HIGH SCHOOL</h3>
            <h3 style="color:black;text-align:center"><b>Waitlisted</b></h3>
            <p style="color:black">
                Your child has been placed on the waitlist. Please re-check your e-mail id by end of this month.
                Your co-operation is really appreciated.
            </p>
            <p style="color:black">Thanks</p>

        </div>
    @elseif(isset($pint) && $pint == 'NO')
        <div style="color:black">
            <h3 style="color:black;text-align:center"><b>Regret mail</b></h3>
            <p style="color:black">Dear Parent,</p>
            <p style="color:black">We regret to inform you that we are unable to grant admission for your child at
                Hill's' High School.
                Any inconvenience caused is deeply regretted.
            </p>
            <p style="color:black">Regards ,</p>
            <p style="color:black">Hills High School</p>
        </div>
    @else
        <div style="border:3px solid black;padding:1px;color:black;">
            <div style="border:1px solid black;padding:6px;color:black;">
                <div class="logo-section" style="text-align: right;color:black;">
                    <img src="https://erp.triz.co.in/admin_dep/images/hills_logo1.png" width="150" height="50" />
                </div>
                <div class="content-div" style="color:black;color:black;">
                    <h2 class="h1line underline" style="text-align: center;text-decoration: underline;color:black;">
                        {{ $admission_std }} ADMISSION PROCEDURE AT HILLS HIGH SCHOOL, A.Y- {{ $aca_year }}</h2>
                    <p style="font-size:16px;color:black;">Dear Parents,</p>
                    <p style="text-decoration:underline;font-size:16px;color:black;">Dear Parents,Please note that the
                        issue of Admission Forms DOES NOT GUARANTEE
                        ADMISSION.</p>

                    <h3 style="text-decoration:underline;color:black;">CONTACT WITH HILLS HIGH SCHOOL</h3>
                    <ul>
                        <li style="font-size:16px;color:black;">Please note that all communication with the Parents will
                            be done via e- mail ONLY.
                            The school mail id is admission@hillshigh.com.</li>
                        <li style="font-size:16px;color:black;">The STATUS of admission of your child's admission will
                            be communicated via email.</li>
                    </ul>

                    <h3 style="text-decoration:underline;color:black;">ADMISSION PROCEDURE</h3>
                    <p style="font-size:16px;color:black;">The Procedure for admissions at Hills High School has 4
                        stages</p>

                    <h3 style="text-decoration:underline;color:black;">STAGE-1 COLLECTION OF FORM</h3>
                    <ul>
                        <li style="font-size:16px;color:black;">Collection of Admission Form from Hills High School,
                            Vesu at the date & time given.</li>
                    </ul>

                    <h3 style="text-decoration:underline;color:black;">STAGE 2 Activity session :</h3>
                    <ul>
                        <li style="font-size:16px;color:black;">A fun filled activity session will be conducted at Hills
                            High School, Vesu, on <b>{{ \Carbon\Carbon::parse($parent_date)->format('d-m-Y') }} at
                                {{ \Carbon\Carbon::parse($parent_time)->format('h:i a') }}</b></li>
                        <li style="font-size:16px;color:black;">Child should be present with <span
                                style="background-color: yellow;color:black;">current School I-card.</span></li>
                        <li style="font-size:16px;color:black;">The session would be for approx 45mins.</li>
                    </ul>

                    <h3 style="text-decoration:underline;color:black;">STAGE 3- Parents Interaction (If the students
                        Activity session is cleared): </h3>
                    <ul>
                        <li style="font-size:16px;color:black;">Parents interaction with the Admission committee will be
                            held at a specific date and time. For
                            the interaction, it is COMPULSORY FOR BOTH MOTHER & FATHER TO BE PRESENT. Please do not
                            bring
                            the child for the parents interaction.</li>
                    </ul>

                    <h3 style="text-decoration:underline;color:black;">STAGE 4- Submission of form and required
                        documents:</h3>
                    <p style="text-indent: 3ch;font-size:16px;color:black;">Candidates who are selected after the
                        interaction will need to submit all
                        the necessary documents along with the admission form & fees to Hills High School office, within
                        the stipulated time, to complete the Provisional Admission procedure.</p>
                    <ul style="margin:0px 8px;color:black;">
                        <li style="font-size:16px"><b>PLEASE NOTE :-</b> In case parents or child are unable to attend
                            any of the sessions on the
                            dates / time allotted by us, kindly send a mail to admission@hillshigh.com so that we can
                            arrange an alternative date / time. In case parents or
                            child are unable to attend any of the sessions on the dates / time allotted by us, kindly
                            send a
                            mail to <a href="mailto:admission@hillshigh.com">admission@hillshigh.com</a> <a
                                href="mailto:session2@hillshigh.com"></a> so that we can arrange an alternative
                            date / time.</li>
                    </ul>
                    <p style="font-size:16px;color:black;"><b>Selection of Shift - </b>Morning or Noon will be on the
                        basis of first come first serve, Once the
                        total number of students are enrolled in either morning or afternoon session admission will be
                        granted only in the other session</p>

                    <div class="thank-you" style="text-align:center;color:black;">
                        <h3 style="font-family: Arial Narrow;font-size:16px;color:black;">Wishing you all the Best!!!
                        </h3>
                        <h3 style="font-family: Lucida Handwriting;color:black;">THANK YOU!! <br>Hills High Family</h3>
                        <p style="font-size:16px;color:black;"><b
                                style="font-family: Arial Narrow;color:black;">Address:</b> 65-A RCC Canal Road
                            Opp.Nandavan Apt,Vesu, Surat, Gujarat 395007.<br>
                            Contact Number-9033095477 / 3477 <span style="color:red !important;">Connect us on
                                Instagram</span> :- @hillshighschool <br>
                            School website - www.hillshigh.com<br>
                            Email Id: <a href="mailto:admission@hillshigh.com">admission@hillshigh.com</a> <a
                                href="mailto:session2@hillshigh.com"></a> (For any admission query)

                        </p>
                    </div>
                </div>
            </div>
        </div>
    @endif
    @elseif($page_type=="confirm")
<!--[if mso]>
<style type="text/css">
body, table, td, div, p {font-family: Arial, Helvetica, sans-serif !important;}
</style>
<![endif]-->

<div style="border: 3px solid #000; padding: 2px; font-family: Arial, sans-serif;">
  <div style="border: 1px solid #000; padding: 18px;">
  
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
      <h2 style="margin:0; color:#000; font-weight:bold; letter-spacing:1px;">HILLS' HIGH SCHOOL</h2>
      <h3 style="margin:0; color:#000; font-weight:bold;">PROVISIONAL ADMISSION ({{$admission_std}}) {{($conf=="C") ? 'Morning Session' : 'Afternoon Session' }}.</h3>
    </div>
  
    <hr style="border:0; border-top:2px solid #943634; margin:18px 0;">
  
    <!-- GREETINGS -->
    <h2 style="margin:0; color:#000;"><b>Dear Parents,</b></h2>
  
    <p style="font-size:15px; line-height:1.7; margin-top:10px;">
      Hills High School is pleased to grant provisional admission to your ward. In order to submit the required documents & fees you are requested to come between
      <b>{{date('d-m-Y',strtotime($conf_date))}}  {{$parent_time}} </b> - <b>SUNDAY CLOSED</b>.
    </p>
  
    <!-- SECTION A -->
    <h3 style="color:#943634; margin-top:25px; text-decoration:underline;">A) The Admission Form</h3>
  
    <ol style="line-height:1.7; font-size:15px; color:#000; padding-left: 25px;">
      <li style="margin-bottom: 8px;">The Form Must Be Filled in with A Blue or Black Pen.</li>
      <li style="margin-bottom: 8px;">Please Note: <b>The UDISE, PEN NO, APAAR ID Number (Under Heading - PREVIOUS SCHOOL RECORD) Is MANDATORY </b>.</li>
      <li style="margin-bottom: 8px;">Please Use CAPITAL LETTERS to Fill in the Form.</li>
      <li style="margin-bottom: 8px;">Please Paste One <b>Recent</b> Photograph of the Father, Mother & Child in The Space Provided in The Form.</li>
      <li style="margin-bottom: 8px;">Please Ensure All Details of the Form Are Completed. <b>Incomplete Forms Will Not Be Accepted.</b></li>
    </ol>
  
    <!-- SECTION B -->
    <h3 style="color:#943634; margin-top:25px; text-decoration:underline;">B) Documents & Photos</h3>
  
    <ol style="line-height:1.7; font-size:15px; color:#000; padding-left: 25px;">
      <li style="margin-bottom: 8px;">Please bring a photo Copy of the child's Birth Certificate.</li>
      <li style="margin-bottom: 8px;">A caste certificate of child or Father is to be submitted if mentioned in form.</li>
      <li style="margin-bottom: 8px;"><b>Recent</b> passport size photographs of the child in a <b>white collared shirt</b> to be attached on the form and 1extra photograph for administrative purposes.</li>
      <li style="margin-bottom: 8px;">A photo copy of the Aadhar cards of 1) Child  2) Father  3) Mother is Compulsory.</li>
      <li style="margin-bottom: 8px;">A recent family photo (father, mother & child) of 5&#215;7&quot; size to be stuck on admission form.(Refer admission form for reference)</li>
      <li style="margin-bottom: 8px;"><b>U-DISE CODE, PEN No., APAAR ID</b> to be taken from previous school and to be written on the Admission Form.(MANDATORY)</li>
      <li style="margin-bottom: 8px;">FULL YEAR FEE RECEIPT (XEROX AND ATTESTED FROM THE SCHOOL) and NO DUE CERTIFICATE from Current School for Fees Payment Status.</li>
      <li style="margin-bottom: 8px;">A Reference Letter from an Existing Parent of Hills' High School or C.A, Family Doctor or Lawyer. 
        (Sibling Parent Need Not Submit the Same)
        </li>
      <li style="margin-bottom: 8px;"><b>ORIGINAL SCHOOL LEAVING CERTIFICATE</b> Once You Receive from Present School 
        (Admission Remains Provisional till The L.C is Not Submitted).
        </li>
    </ol>
  
    <!-- SECTION C -->
    <h3 style="color:#943634; margin-top:25px; text-decoration:underline;">C) Completion of NACH Document: </h3>
  
    <p style="line-height:1.7; font-size:15px;">
      The school follows a cashless and digital system of on-line fee collections. It is compulsory to submit the following documents at the time of admission:     
    </p>
  
    <ul style="line-height:1.7; font-size:15px; padding-left:25px;">
      <li style="margin-bottom: 8px;">Completed NACH form (PLEASE COLLECT THE NACH FORM FROM THE SCHOOL OFFICE WHILE SUBMISSION OF FORM OR BEFORE)</li>
      <li style="margin-bottom: 8px;">Cancelled cheque of the Bank a/c you will be using for all school fee payments from the 2ndquarter onwards.</li>
      <li style="margin-bottom: 8px;">In case you are using an account other than an individual account, like a Partnership, Proprietorship, Shop, Factory, Mill. You are requested to please bring along the rubber stamp of the company to stamp the original NACH form. 
  </li>
      <li style="margin-bottom: 8px;">In case it is a joint account it would be compulsory to have the signature of all account holders.</li>
    </ul>
  
    <div style="background:#fff4e6; border-left:4px solid #943634; padding:10px 15px; margin-top:10px;">
      <p style="margin:0; color:#000; font-weight:bold;">
        
        <li>Till completion and acceptance of NACH forms by the Bank, the admission will be considered PROVISIONAL</li>
        <li>Admission will be CONFIRMED only after NACH form is approved by the bank.</li>
      </p>
    </div>
  
    <!-- SECTION D -->
    <h3 style="color:#943634; margin-top:25px; text-decoration:underline;">D) Fee Amount</h3>
  
    <ol style="line-height:1.7; font-size:15px; color:#000; padding-left: 25px;">
      <li style="margin-bottom: 8px;">Fee for the year 2026-27 is <b style="color:#943634;">&#x20B9; 87,000/- *</b></li>
      <li style="margin-bottom: 8px;">Fees to be paid at the time of admission:</li>
    </ol>
  
    <table style="width:100%; border:2px solid #000; border-collapse:collapse; margin:15px 0;">
      <tr style="background:#f0e8e8;">
        <th style="border:2px solid #000; padding:10px; text-align:center;">1st Quarter Provisional Tuition Fees</th>
        <th style="border:2px solid #000; padding:10px; text-align:center;">One-Time Provisional  Admission Fees</th>
        <th style="border:2px solid #000; padding:10px; text-align:center;">Total Provisional Fees to be paid at time of  the admission</th>
      </tr>
      <tr>
        <td style="border:2px solid #000; padding:10px; color:#943634; text-align:center;"><b>&#x20B9; 21,750/-</b></td>
        <td style="border:2px solid #000; padding:10px; color:#943634; text-align:center;"><b>&#x20B9; 7,250/-</b></td>
        <td style="border:2px solid #000; padding:10px; color:#943634; text-align:center;"><b>&#x20B9; 29,000/-</b></td>
      </tr>
    </table>
  
    <p style="font-size:15px;"><b>* Final Fee Subject to Decision of Honorable Court.</b></p>
  
    <p style="line-height:1.7; font-size:15px;">
      You are required to pay provisional fees of <b>&#x20B9; 29,000/-</b> at the school office by <b>Demand Draft payable to Hills High School. [Please note: No NEFT / cash will be accepted]</b>
    </p>
  
    <div style="background:#fff4e6; border-left:4px solid #943634; padding:10px 15px; margin-top:10px;">
      <p style="margin:0; color:#000; font-weight:bold;">
        <li> FEES ONCE PAID IS NON REFUNDABLE AND NON TRANSFERABLE.<br></li>
        <li> Candidates who fail to submit DOCUMENTS AND FEES IN THE GIVEN TIME PERIOD without any information will not be eligible for admission at Hills' High School.</li>
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
