<!DOCTYPE html>
<html>

<head>
    <title>Admission Confirmation</title>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
</head>

<body style="padding: 10px; margin: 0; font-family: Arial, Helvetica, sans-serif; background: #f4f6f9;">

    <!--[if mso]>
<style type="text/css">
body, table, td, div, p {font-family: Arial, Helvetica, sans-serif !important;}
</style>
<![endif]-->

    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background: #f4f6f9;">
        <tr>
            <td align="center">
                <!-- Main Container -->
                <table width="600" cellpadding="0" cellspacing="0" border="0"
                    style="border:3px solid #000000; background: #ffffff;">
                    <tr>
                        <td style="border:1px solid #000000; padding: 18px;">

                            <!-- Header Section -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0"
                                style="margin-bottom:10px;">
                                <tr>
                                    <td width="50%" style="vertical-align: top;">
                                        <p
                                            style="color:#943634; font-size:18px; font-weight:bold; margin:0; font-family: Arial, sans-serif;">
                                            HHS/EC/2/2026-27</p>
                                    </td>
                                    <td width="50%" style="text-align: right; vertical-align: top;">
                                        <img src="https://erp.triz.co.in/admin_dep/images/hills_logo1.png"
                                            width="160" height="55" style="display: inline-block;"
                                            alt="Hills High School">
                                    </td>
                                </tr>
                            </table>

                            <!-- Title Section -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0"
                                style="margin-top:5px;">
                                <tr>
                                    <td style="text-align:center;">
                                        <h2
                                            style="margin:0; color:#000000; font-weight:bold; letter-spacing:1px; font-family: Arial, sans-serif;">
                                            HILLS' HIGH SCHOOL</h2>
                                        <h3
                                            style="margin:5px 0 0 0; color:#000000; font-weight:bold; font-family: Arial, sans-serif;">
                                            PROVISIONAL ADMISSION ({{ $admission_std }}) {{ ($conf=="C") ? 'Morning Session' : 'Afternoon Session' }} </h3>
                                    </td>
                                </tr>
                            </table>

                            <!-- Horizontal Line -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:18px 0;">
                                <tr>
                                    <td height="2" style="background:#943634;"></td>
                                </tr>
                            </table>

                            <!-- Greetings -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td>
                                        <h2 style="margin:0; color:#000000; font-family: Arial, sans-serif;"><b>Dear
                                                Parents,</b></h2>
                                        <p
                                            style="font-size:15px; line-height:1.7; margin-top:10px; font-family: Arial, sans-serif;">
                                            Hills High School is pleased to grant provisional admission to your ward.
                                            Please visit the school between
                                            <b>{{ date('d-m-Y', strtotime($conf_date)) }} between {{ $parent_time }}
                                                </b>
                                            - <b>SUNDAY CLOSED</b> 
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <!-- Section A -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0"
                                style="margin-top:25px;">
                                <tr>
                                    <td>
                                        <h3
                                            style="color:#943634; margin:0; text-decoration:underline; font-family: Arial, sans-serif;">
                                            A) The Admission Form</h3>
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0"
                                            style="margin-top:10px;">
                                            <tr>
                                                <td style="padding-left: 20px;">
                                                    <table width="100%" cellpadding="0" cellspacing="0"
                                                        border="0">
                                                        <tr>
                                                            <td style="vertical-align: top; width: 25px;">1.</td>
                                                            <td
                                                                style="padding-bottom: 8px; font-size:15px; color:#000000; font-family: Arial, sans-serif;">
                                                                The Form Must Be Filled in with A Blue or Black Pen.</td>
                                                        </tr>
                                                        <tr>
                                                            <td style="vertical-align: top; width: 25px;">2.</td>
                                                            <td
                                                                style="padding-bottom: 8px; font-size:15px; color:#000000; font-family: Arial, sans-serif;">
                                                                <b>Please Note: The UDISE, PEN NO, APAAR ID Number (Under Heading - PREVIOUS SCHOOL RECORD) Is MANDATORY </b>.
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td style="vertical-align: top; width: 25px;">3.</td>
                                                            <td
                                                                style="padding-bottom: 8px; font-size:15px; color:#000000; font-family: Arial, sans-serif;">
                                                                Please Use CAPITAL LETTERS to Fill in the Form.</td>
                                                        </tr>
                                                        <tr>
                                                            <td style="vertical-align: top; width: 25px;">4.</td>
                                                            <td
                                                                style="padding-bottom: 8px; font-size:15px; color:#000000; font-family: Arial, sans-serif;">
                                                                Please Paste One <b>Recent</b> Photograph of the Father, Mother & Child in The Space Provided in The Form.</td>
                                                        </tr>
                                                        <tr>
                                                            <td style="vertical-align: top; width: 25px;">5.</td>
                                                            <td
                                                                style="font-size:15px; color:#000000; font-family: Arial, sans-serif;">
                                                                Please Ensure All Details of the Form Are Completed. <b>Incomplete Forms Will Not Be Accepted.</b></td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Section B -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0"
                                style="margin-top:25px;">
                                <tr>
                                    <td>
                                        <h3
                                            style="color:#943634; margin:0; text-decoration:underline; font-family: Arial, sans-serif;">
                                            B) Documents &amp; Photos
                                        </h3>
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0"
                                            style="margin-top:10px;">
                                            <tr>
                                                <td style="padding-left: 20px;">
                                                    <table width="100%" cellpadding="0" cellspacing="0"
                                                        border="0">
                                                        <tr>
                                                            <td style="vertical-align: top; width: 25px;">1.</td>
                                                            <td
                                                                style="padding-bottom: 8px; font-size:15px; color:#000000; font-family: Arial, sans-serif;">
                                                                Please bring a photo Copy of the child's Birth Certificate.
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td style="vertical-align: top; width: 25px;">2.</td>
                                                            <td
                                                                style="padding-bottom: 8px; font-size:15px; color:#000000; font-family: Arial, sans-serif;">
                                                                A caste certificate of child or Father is to be submitted if mentioned in form.
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td style="vertical-align: top; width: 25px;">3.</td>
                                                            <td
                                                                style="padding-bottom: 8px; font-size:15px; color:#000000; font-family: Arial, sans-serif;">
                                                                <b>Recent</b> passport size photographs of the child in a <b>white collared shirt</b> to be attached on the form and 1extra photograph for administrative purposes.
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td style="vertical-align: top; width: 25px;">4.</td>
                                                            <td
                                                                style="padding-bottom: 8px; font-size:15px; color:#000000; font-family: Arial, sans-serif;">
                                                                A photo copy of the Aadhar cards of 1) Child  2) Father  3) Mother is Compulsory.
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td style="vertical-align: top; width: 25px;">5.</td>
                                                            <td
                                                                style="padding-bottom: 8px; font-size:15px; color:#000000; font-family: Arial, sans-serif;">
                                                                A recent family photo (father, mother & child) of 5&#215;7&quot; size to be stuck on admission form.(Refer admission form for reference)
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td style="vertical-align: top; width: 25px;">6.</td>
                                                            <td
                                                                style="padding-bottom: 8px; font-size:15px; color:#000000; font-family: Arial, sans-serif;">
                                                                <b>U-DISE CODE, PEN No., APAAR ID</b> to be taken from previous school and to be written on the Admission Form.(MANDATORY)
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td style="vertical-align: top; width: 25px;">7.</td>
                                                            <td
                                                                style="padding-bottom: 8px; font-size:15px; color:#000000; font-family: Arial, sans-serif;">
                                                                FULL YEAR FEE RECEIPT (XEROX AND ATTESTED FROM THE SCHOOL) & NO DUE CERTIFICATE from Current School for Fees Payment Status. 
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td style="vertical-align: top; width: 25px;">8.</td>
                                                            <td
                                                                style="padding-bottom: 8px; font-size:15px; color:#000000; font-family: Arial, sans-serif;">
                                                                8.	A Reference Letter from an Existing Parent of Hills' High School or C.A, Family Doctor or Lawyer.(Sibling Parent Need Not Submit the Same)

                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td style="vertical-align: top; width: 25px;">9.</td>
                                                            <td
                                                                style="font-size:15px; color:#000000; font-family: Arial, sans-serif;">
                                                                ORIGINAL SCHOOL LEAVING CERTIFICATE Once You Receive from Present School (Admission Remains Provisional till The L.C is Not Submitted).

                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Section C -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0"
                                style="margin-top:25px;">
                                <tr>
                                    <td>
                                        <h3
                                            style="color:#943634; margin:0; text-decoration:underline; font-family: Arial, sans-serif;">
                                            C) Completion of NACH Document</h3>
                                        <p
                                            style="line-height:1.7; font-size:15px; margin-top:10px; font-family: Arial, sans-serif;">
                                            The school follows a cashless and digital system of on-line fee collections. It is compulsory to submit the following documents at the time of admission:
                                        </p>
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0"
                                            style="margin-top:10px;">
                                            <tr>
                                                <td style="padding-left: 20px;">
                                                    <table width="100%" cellpadding="0" cellspacing="0"
                                                        border="0">
                                                        <tr>
                                                            <td style="vertical-align: top; width: 15px;">&#8226;</td>
                                                            <td
                                                                style="padding-bottom: 8px; font-size:15px; color:#000000; font-family: Arial, sans-serif;">
                                                                Completed NACH form <b>(PLEASE COLLECT THE NACH FORM FROM THE SCHOOL OFFICE WHILE   SUBMISSION OF FORM OR BEFORE)</b>.
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td style="vertical-align: top; width: 15px;">&#8226;</td>
                                                            <td
                                                                style="padding-bottom: 8px; font-size:15px; color:#000000; font-family: Arial, sans-serif;">
                                                                Cancelled cheque of the Bank a/c you will be using for all school fee payments from the 2ndquarter onwards.
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td style="vertical-align: top; width: 15px;">&#8226;</td>
                                                            <td
                                                                style="padding-bottom: 8px; font-size:15px; color:#000000; font-family: Arial, sans-serif;">
                                                                In case you are using an account other than an individual account, like a Partnership, Proprietorship, Shop,  Factory, Mill.  You are requested to please bring along the rubber stamp of the company to stamp the original NACH form. 
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td style="vertical-align: top; width: 15px;">&#8226;</td>
                                                            <td
                                                                style="font-size:15px; color:#000000; font-family: Arial, sans-serif;">
                                                                In case it is a joint account it would be compulsory to have the signature of all account holders.
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>

                                        <!-- Horizontal Line -->
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0"
                                            style="margin:15px 0;">
                                            <tr>
                                                <td height="1" style="background:#cccccc;"></td>
                                            </tr>
                                        </table>

                                        <!-- Additional remarks -->
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td>
                                                    <p
                                                        style="margin:0; color:#000000; font-weight:bold; font-family: Arial, sans-serif;">
                                                        <b>Till completion and acceptance of NACH forms by the Bank, the admission will be considered PROVISIONAL </b>
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>

                                        <!-- Horizontal Line -->
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0"
                                            style="margin:15px 0;">
                                            <tr>
                                                <td height="1" style="background:#cccccc;"></td>
                                            </tr>
                                        </table>

                                        <!-- Admission confirmation -->
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td>
                                                    <p
                                                        style="margin:0; color:#000000; font-weight:bold; font-family: Arial, sans-serif;">
                                                        <b>Admission will be CONFIRMED only after NACH form is approved by the bank.</b>
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Section D -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0"
                                style="margin-top:25px;">
                                <tr>
                                    <td>
                                        <h3
                                            style="color:#943634; margin:0; text-decoration:underline; font-family: Arial, sans-serif;">
                                            D) Fee Amount</h3>
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0"
                                            style="margin-top:10px;">
                                            <tr>
                                                <td style="padding-left: 20px;">
                                                    <table width="100%" cellpadding="0" cellspacing="0"
                                                        border="0">
                                                        <tr>
                                                            <td style="vertical-align: top; width: 25px;">1.</td>
                                                            <td
                                                                style="padding-bottom: 8px; font-size:15px; color:#000000; font-family: Arial, sans-serif;">
                                                                Fee for the year 2026-27 is: <b
                                                                    style="color:#943634;">&#x20B9; 80,800/- *</b></td>
                                                        </tr>
                                                        <tr>
                                                            <td style="vertical-align: top; width: 25px;">2.</td>
                                                            <td
                                                                style="font-size:15px; color:#000000; font-family: Arial, sans-serif;">
                                                                 Fees to be paid at the time of the admission:</td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>

                                        <!-- Fees Table -->
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0"
                                            style="margin:15px 0; border:2px solid #000000;">
                                            <tr style="background:#f0e8e8;">
                                                <td width="33%"
                                                    style="border:2px solid #000000; padding:10px; text-align:center; font-weight:bold; font-family: Arial, sans-serif;">
                                                    1st Quarter Provisional Tuition Fees </td>
                                                <td width="33%"
                                                    style="border:2px solid #000000; padding:10px; text-align:center; font-weight:bold; font-family: Arial, sans-serif;">
                                                    One time  Provisional Admission Fees</td>
                                                <td width="34%"
                                                    style="border:2px solid #000000; padding:10px; text-align:center; font-weight:bold; font-family: Arial, sans-serif;">
                                                    Total provisional fees to be paid at time of  the admission</td>
                                            </tr>
                                            <tr>
                                                <td
                                                    style="border:2px solid #000000; padding:10px; color:#943634; text-align:center; font-weight:bold; font-family: Arial, sans-serif;">
                                                    &#x20B9; 20,200/-</td>
                                                <td
                                                    style="border:2px solid #000000; padding:10px; color:#943634; text-align:center; font-weight:bold; font-family: Arial, sans-serif;">
                                                    &#x20B9; 6,733/-</td>
                                                <td
                                                    style="border:2px solid #000000; padding:10px; color:#943634; text-align:center; font-weight:bold; font-family: Arial, sans-serif;">
                                                    &#x20B9; 26,933/-</td>
                                            </tr>
                                        </table>

                                        <p style="font-size:15px; font-family: Arial, sans-serif;"><b>* Final Fee Subject to Decision of Honorable Court.</b></p>

                                        <p style="line-height:1.7; font-size:15px; font-family: Arial, sans-serif;">
                                            You are required to pay provisional fees <b style="color:#943634;">&#x20B9; 26,933/- at the school office by</b> <b>Demand Draft  payable to Hills High School. [Please note: No NEFT / cash will be accepted]</b>
                                        </p>

                                        <!-- Highlight Box -->
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0"
                                            style="margin-top:10px; background:#fff4e6; border-left:4px solid #943634;">
                                            <tr>
                                                <td style="padding:10px 15px;">
                                                    <table width="100%" cellpadding="0" cellspacing="0"
                                                        border="0">
                                                        <tr>
                                                            <td style="vertical-align: top; width: 15px;">&#8226;</td>
                                                            <td
                                                                style="font-size:15px; color:#000000; font-weight:bold; font-family: Arial, sans-serif;">
                                                                FEES ONCE PAID IS NON REFUNDABLE AND NON TRANSFERABLE.
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td style="vertical-align: top; width: 15px;">&#8226;</td>
                                                            <td
                                                                style="font-size:15px; color:#000000; font-weight:bold; font-family: Arial, sans-serif;">
                                                                Candidates who fail to submit DOCUMENTS AND FEES IN THE GIVEN TIME PERIOD without any information will not be eligible for admission at Hills' High School.</u>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Signatures -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0"
                                style="margin-top:30px;">
                                <tr>
                                    <td width="50%" style="text-align:center;">
                                        <p
                                            style="color:#943634; font-size:16px; margin:0; font-weight:bold; font-family: Arial, sans-serif;">
                                            Mr. P.P. Jose</p>
                                        <p style="color:#943634; font-weight:bold; font-family: Arial, sans-serif;">
                                            Principal</p>
                                    </td>
                                    <td width="50%" style="text-align:center;">
                                        <p
                                            style="color:#943634; font-size:16px; margin:0; font-weight:bold; font-family: Arial, sans-serif;">
                                            Mrs. Persis Hilluwala</p>
                                        <p style="color:#943634; font-weight:bold; font-family: Arial, sans-serif;">
                                            Director</p>
                                    </td>
                                </tr>
                            </table>

                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

</body>

</html>
