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

    <div style="border:3px solid black;padding:1px;color:black;">
        <div style="border:1px solid black;padding:6px;color:black;">
            <div class="logo-section" style="text-align: right;color:black;">
                <img src="https://erp.triz.co.in/admin_dep/images/hills_logo1.png" width="150" height="50" />
            </div>
            <div class="content-div" style="color:black;color:black;">
                <h2 class="h1line underline" style="text-align: center;text-decoration: underline;color:black;color:black;">{{$admission_std}} ADMISSION PROCEDURE AT HILLS HIGH SCHOOL, A.Y- {{$aca_year}}</h2>
                <p style="font-size:16px;color:black;color:black;">Dear Parents,</p>
                <p style="text-decoration:underline;font-size:16px;color:black;color:black;">Dear Parents,Please note that the issue of Admission Forms DOES NOT GUARANTEE
                    ADMISSION.</p>

                <h3 style="text-decoration:underline;color:black;">CONTACT WITH HILLS’ HIGH SCHOOL</h3>
                <ul>
                    <li style="font-size:16px">Please note that all communication with the Parents will be done via e- mail ONLY.
                        The school mail id is admission@hillshigh.com / session2@hillshigh.com .</li>
                    <li style="font-size:16px">The STATUS of admission of your child’s admission will be communicated via email.</li>
                </ul>

                <h3 style="text-decoration:underline;color:black;">ADMISSION PROCEDURE</h3>
                <p style="font-size:16px;color:black;">The Procedure for admissions at Hills High School has 4 stages</p>

                <h3 style="text-decoration:underline;color:black;">STAGE-1 COLLECTION OF FORM</h3>
                <ul>
                    <li style="font-size:16px;color:black;">Collection of Admission Form from Hills’ High School, Vesu at the date & time given.</li>
                </ul>

                <h3 style="text-decoration:underline;color:black;">STAGE 2 Activity session :</h3>
                <ul>
                    <li style="font-size:16px;color:black;">A fun filled activity session will be conducted at Hills’ High School, Vesu, on <b>{{ \Carbon\Carbon::parse($parent_date)->format('d-m-Y') }} at {{ \Carbon\Carbon::parse($parent_time)->format('h:i a') }}</b></li>
                    <li style="font-size:16px;color:black;">Child should be present with <span style="background-color: yellow;color:black;">current School I-card.</span></li>
                    <li style="font-size:16px;color:black;">The session would be for approx 45mins.</li>
                </ul>

                <h3 style="text-decoration:underline;color:black;">STAGE 3- Parents Interaction (If the students Activity session is cleared): </h3>
                <ul>
                    <li style="font-size:16px;color:black;">Parents interaction with the Admission committee will be held at a specific date and time. For
                        the interaction, it is COMPULSORY FOR BOTH MOTHER & FATHER TO BE PRESENT. Please do not bring
                        the child for the parents interaction.</li>
                </ul>

                <h3 style="text-decoration:underline;color:black;">STAGE 4- Submission of form and required documents:</h3>
                <p style="text-indent: 3ch;font-size:16px;color:black;">Candidates who are selected after the interaction will need to submit all
                    the necessary documents along with the admission form & fees to Hills’ High School office, within
                    the stipulated time, to complete the Provisional Admission procedure.</p>
                <ul style="margin:0px 8px;color:black;">
                    <li style="font-size:16px"><b>PLEASE NOTE :-</b> In case parents or child are unable to attend any of the sessions on the
                        dates / time allotted by us, kindly send a mail to admission@hillshigh.com /
                        session2@hillshigh.com so that we can arrange an alternative date / time. In case parents or
                        child are unable to attend any of the sessions on the dates / time allotted by us, kindly send a
                        mail to <a href="mailto:admission@hillshigh.com">admission@hillshigh.com</a> / <a href="mailto:session2@hillshigh.com">session2@hillshigh.com</a> so that we can arrange an alternative
                        date / time.</li>
                </ul>
                <p style="font-size:16px;color:black;"><b>Selection of Shift – </b>Morning or Noon will be on the basis of first come first serve, Once the
                    total number of students are enrolled in either morning or afternoon session admission will be
                    granted only in the other session</p>

                <div class="thank-you" style="text-align:center;color:black;">
                    <h3 style="font-family: Arial Narrow;font-size:16px;color:black;">Wishing you all the Best!!!</h3>
                    <h3 style="font-family: Lucida Handwriting;color:black;">THANK YOU!! <br>Hills High Family</h3>
                    <p style="font-size:16px;color:black;"><b style="font-family: Arial Narrow;color:black;">Address:</b> 65-A RCC Canal Road Opp.Nandavan Apt,Vesu, Surat, Gujarat 395007.<br>
                        Contact Number-9033095477 / 3477 <span style="color:red !important;">Connect us on Instagram</span> :- @hillshighschool <br>
                        School website – www.hillshigh.com<br> 
                        Email Id: <a href="mailto:admission@hillshigh.com">admission@hillshigh.com</a> / <a href="mailto:session2@hillshigh.com">session2@hillshigh.com</a> (For any admission query)

                    </p>
                </div>
            </div>
        </div>
    </div>
</body>

</html>