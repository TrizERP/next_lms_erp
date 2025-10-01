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
    <div style="border: 3px solid black; padding: 1px">
      <div style="border: 1px solid black; padding: 10px;">
        <div class="header" style="width: 100%;">
          <div class="prefix" style="float: left; width: 50%;">
            <p style="color: #943634"><b>HHS/EC/01/2026-27</b></p>
          </div>
          <div class="logo-section" style="float: right; width: 50%; text-align: right;">
            <img
              src="https://erp.triz.co.in/admin_dep/images/hills_logo1.png"
              width="150"
              height="50"
            />
          </div>
          <div style="clear: both;"></div>
        </div>
        <div class="content-div" style="text-align:center">
            <h3 style="color:black;margin:0px;"><b>HILLS HIGH SCHOOL</b></h3>
            <h3 style="color:black;margin:0px;"><b>PROVISIONAL ADMISSION ({{$admission_std}})</b></h3>
        </div>
        <div class="greetings">
            <h2 style="color:black;margin:0px;"><b>Dear Parents,</b></h2>
            <h2 style="color:black;margin:0px;"><b>Congratulations!!</b></h2>
        </div>
        <div class="parage">
            <p><span style="color:black;margin:0px;"><b>Your Wards Provisional Admission has been Granted in {{ ($conf=="C") ? 'Morning Session' : 'Afternoon Session' }}.</b></span>Please complete the documentation process within the given date and time to confirm your wards admission at Hills High School.
                Kindly visit Hills High School before <span style="color: #943634"><b>{{date('d-m-Y',strtotime($conf_date))}} between {{$parent_time}}</b></span> with all the documents mentioned below (on Sunday and Bank Holidays the office will be closed)
            </p>
            <p style="color:black"><b>Please note the documents required: -</b></p>
            <p style="color:black;text-decoration:underline"><b>A) The Admission form</b></p>
            <ol style="color:black">
                <li>he form must be filled in with a blue pen only.</li>
                <li>Please use CAPITAL LETTERS to fill in the form.</li>
                <li>Please ensure all details of the form are completed. <b>Incomplete forms will not be accepted.</b></li>
                <li>It is Mandatory to fill child's Medical information in the form. (Blood group, Height, Weight etc.)</li>
            </ol>
            <p style="color:black;text-decoration:underline"><b>B) Documents & Photos</b></p>
            <ol style="color:black">
                <li>Please bring a photo Copy of the child's Birth Certificate.</li>
                <li>A caste certificate of child or Father is to be submitted if mentioned in form.</li>
                <li><b>Recent</b> passport size photographs of the child in a <b>white collared shirt</b> to be attached on the form and 1extra photograph for administrative purposes.</li>
                <li>A photo copy of the Aadhar cards of 1) Child 2) Father 3) Mother is Compulsory.</li>
                <li>A recent family photo (father, mother & child) of 5*7" size to be stuck on admission form.</li>
                <li><b><i>2<sup>nd</sup> Term Fees Receipt for Hills Nursery Students & NO DUE CERTIFICATE for Other school students</i></b> from previous school.</li>
                <li>A reference letter from an existing parent of Hills' High School or C.A, family doctor or lawyer.
                    <b><i>(Sibling parents need not submit the same)</b></i></li>
            </ol>
            <p style="color:black;text-decoration:underline"><b>C) Completion of NACH document:</b></p>
            <p style="color:black">The school follows a cashless and digital system of on-line fee collections. It is compulsory to submit the following       
            documents at the time of admission:</p>
            <ul style="list-style-type:none;color:black">
                <li>i) Completed NACH form <b>(PLEASE COLLECT THE NACH FORM FROM THE SCHOOL OFFICE WHILE SUBMISSION OF FORM OR BEFORE)</b></li>
                <li>ii) Cancelled cheque of the Bank a/c you will be using for all school fee payments from the 2nd quarter onwards.</li>
                <li>iii) In case you are using an account other than an individual account, like a Partnership, Proprietorship, Shop, Factory, Mill. You are requested to please bring along the rubber stamp of the company to stamp the original NACH form.</li>
                <li>iv) In case it is a joint account it would be compulsory to have the signature of all account holders.</li>
            </ul>
           <p style="color:black;text-decoration:underline"><b><i>V.V.Imp</i></b></p>
           <ul>
            <li style="text-decoration:underline;color:black"><b>Till completion and acceptance of NACH forms by the Bank, the admission will be considered PROVISIONAL.</b></li>
            <li style="text-decoration:underline;color:black"><b>Admission will be CONFIRMED only after NACH form is approved by the bank.</b></li>
           </ul>
           <p style="color:black;text-decoration:underline"><b>D) Fee Amount </b></p>
           <ol style="color:black">
            <li> Fee for the year {{$aca_year}} for {{$admission_std}}  <span style="color:#943634"><b>&#8377; 80,800/-<sup>*</sup></b></span></li>
              <li> Fees to be paid at the time of the admission:-</li>
           </ol>
           <table style="border: 2px solid black; border-collapse: collapse;" align="center">
            <tr>
                <th style="border: 2px solid black; padding: 8px; color:black">1<sup>st</sup> Quarter Provisional Tuition Fees </th>
                <th style="border: 2px solid black; padding: 8px; color:black">One time  Provisional Admission Fees</th>
                <th style="border: 2px solid black; padding: 8px; color:black">* Total provisional fees to be paid at time of the admission</th>
            </tr>
            <tr>
                <td style="border: 2px solid black; padding: 8px;"><span style="color:#943634"><b>&#8377; 20,200/-</b></span></td>
                <td style="border: 2px solid black; padding: 8px;"><span style="color:#943634"><b>&#8377; 6,733/-</b></span></td>
                <td style="border: 2px solid black; padding: 8px;"><span style="color:#943634"><b>&#8377; 26,933/-</b></span></td>
            </tr>
           </table>
           <p style="color:black"><b>* Final Fee Subject to Decision of Honorable Court.</b></p>
           <p style="color:black">You are required to pay provisional fees of  <span style="color:#943634"><b>&#8377; 26,933/-</b></span> at the school office. <b><span>Parents are given option to pay the first installment by Demand Draft OR Cheque payable to “Hills High School”.</span> <i>[Please note: If Cheque is dishonoured the admission of your ward will not be considered / No cash will be accepted.]</i></b></p>
           <p style="color:black;text-decoration:underline"><b>V.V.IMP</b></p>
           <p style="color:black"><b>FEES ONCE PAID IS NON REFUNDABLE AND NON TRANSFERABLE</b></p>
           <p style="color:black;text-decoration:underline"><b>Candidates who fail to submit DOCUMENTS AND FEES IN THE GIVEN TIME PERIOD without any information will not be eligible for admission at Hills High School.</b></p>
        </div>
         <div class="footer" style="width:100%;">
            <div class="princople" style="float: left; width: 50%; text-align: center;">
                <span style="color:#943634"><b>{{ ($conf=="C") ? 'Mr.P.P.Jose' : 'Mrs. Rehana Patni' }}</b></span><br>
                <span style="color:#943634"><b>Principal</b></span><br>
            </div>
            <div class="director" style="float: right; width: 50%; text-align: center;">
                <span style="color:#943634"><b>Mrs. Persis Hilluwala</b></span><br>
                <span style="color:#943634"><b>Director </b></span><br>
            </div>
            <div style="clear: both;"></div>
        </div>
      </div>
    </div>
    @endif
</body>

</html>
