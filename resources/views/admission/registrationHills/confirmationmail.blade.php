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
                                            PROVISIONAL ADMISSION ({{ $admission_std }}) MORNING SESSION</h3>
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
                                                (9:00am to 11:00am)</b>
                                            - <b>SUNDAY CLOSED</b> - for submitting the required documents & fees.
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
                                                                The Form must be filled in with a <b>Blue or Black
                                                                    Pen</b>.</td>
                                                        </tr>
                                                        <tr>
                                                            <td style="vertical-align: top; width: 25px;">2.</td>
                                                            <td
                                                                style="padding-bottom: 8px; font-size:15px; color:#000000; font-family: Arial, sans-serif;">
                                                                <b>UDISE, PEN NO, APAAR ID</b> details are
                                                                <b>MANDATORY</b>.
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td style="vertical-align: top; width: 25px;">3.</td>
                                                            <td
                                                                style="padding-bottom: 8px; font-size:15px; color:#000000; font-family: Arial, sans-serif;">
                                                                Use <b>CAPITAL LETTERS</b> only.</td>
                                                        </tr>
                                                        <tr>
                                                            <td style="vertical-align: top; width: 25px;">4.</td>
                                                            <td
                                                                style="padding-bottom: 8px; font-size:15px; color:#000000; font-family: Arial, sans-serif;">
                                                                Paste recent photographs of Father, Mother & Child.</td>
                                                        </tr>
                                                        <tr>
                                                            <td style="vertical-align: top; width: 25px;">5.</td>
                                                            <td
                                                                style="font-size:15px; color:#000000; font-family: Arial, sans-serif;">
                                                                Ensure every field is filled. <b>Incomplete forms will
                                                                    not be accepted.</b></td>
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
                                                                Child's Birth Certificate (Photocopy).
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td style="vertical-align: top; width: 25px;">2.</td>
                                                            <td
                                                                style="padding-bottom: 8px; font-size:15px; color:#000000; font-family: Arial, sans-serif;">
                                                                Caste Certificate (if applicable).
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td style="vertical-align: top; width: 25px;">3.</td>
                                                            <td
                                                                style="padding-bottom: 8px; font-size:15px; color:#000000; font-family: Arial, sans-serif;">
                                                                Recent photograph of child in white collared shirt + 1
                                                                extra.
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td style="vertical-align: top; width: 25px;">4.</td>
                                                            <td
                                                                style="padding-bottom: 8px; font-size:15px; color:#000000; font-family: Arial, sans-serif;">
                                                                Aadhar Card of Child, Father &amp; Mother (Mandatory).
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td style="vertical-align: top; width: 25px;">5.</td>
                                                            <td
                                                                style="padding-bottom: 8px; font-size:15px; color:#000000; font-family: Arial, sans-serif;">
                                                                Family Photograph (5&#215;7&quot;).
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td style="vertical-align: top; width: 25px;">6.</td>
                                                            <td
                                                                style="padding-bottom: 8px; font-size:15px; color:#000000; font-family: Arial, sans-serif;">
                                                                U-DISE, PEN No., APAAR ID from previous school
                                                                (<b>MANDATORY</b>).
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td style="vertical-align: top; width: 25px;">7.</td>
                                                            <td
                                                                style="padding-bottom: 8px; font-size:15px; color:#000000; font-family: Arial, sans-serif;">
                                                                Full-year Fee Receipt + No Due Certificate (Attested).
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td style="vertical-align: top; width: 25px;">8.</td>
                                                            <td
                                                                style="padding-bottom: 8px; font-size:15px; color:#000000; font-family: Arial, sans-serif;">
                                                                Reference Letter (Not required for siblings).
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td style="vertical-align: top; width: 25px;">9.</td>
                                                            <td
                                                                style="font-size:15px; color:#000000; font-family: Arial, sans-serif;">
                                                                Original School Leaving Certificate.
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
                                            The school follows a cashless fee-collection system. You must submit:
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
                                                                <b>Complete</b> NACH Form.
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td style="vertical-align: top; width: 15px;">&#8226;</td>
                                                            <td
                                                                style="padding-bottom: 8px; font-size:15px; color:#000000; font-family: Arial, sans-serif;">
                                                                <b>Cancelled</b> Cheque of Fee Payment Account.
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td style="vertical-align: top; width: 15px;">&#8226;</td>
                                                            <td
                                                                style="padding-bottom: 8px; font-size:15px; color:#000000; font-family: Arial, sans-serif;">
                                                                <b>Company</b> Stamp (if account is
                                                                business/proprietorship).
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td style="vertical-align: top; width: 15px;">&#8226;</td>
                                                            <td
                                                                style="font-size:15px; color:#000000; font-family: Arial, sans-serif;">
                                                                <b>Joint</b> Account &rarr; All holders must sign.
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
                                                        PROVISIONAL until NACH approval.
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
                                                        Admission to be CONFIRMED only after bank approval.
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
                                                                Fee for year 2026-27: <b
                                                                    style="color:#943634;">&#x20B9; 80,800/- *</b></td>
                                                        </tr>
                                                        <tr>
                                                            <td style="vertical-align: top; width: 25px;">2.</td>
                                                            <td
                                                                style="font-size:15px; color:#000000; font-family: Arial, sans-serif;">
                                                                Fees payable at time of admission:</td>
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
                                                    1st Quarter Tuition Fees</td>
                                                <td width="33%"
                                                    style="border:2px solid #000000; padding:10px; text-align:center; font-weight:bold; font-family: Arial, sans-serif;">
                                                    One-Time Admission Fees</td>
                                                <td width="34%"
                                                    style="border:2px solid #000000; padding:10px; text-align:center; font-weight:bold; font-family: Arial, sans-serif;">
                                                    Total Provisional Fees</td>
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

                                        <p style="font-size:15px; font-family: Arial, sans-serif;"><b>* Final Fee
                                                subject to Honorable Court decision.</b></p>

                                        <p style="line-height:1.7; font-size:15px; font-family: Arial, sans-serif;">
                                            Pay <b style="color:#943634;">&#x20B9; 26,933/-</b> by <b>Demand Draft /
                                                Cheque</b> payable to <b>Hills High School</b>.
                                            <br><b>No NEFT / No Cash will be accepted.</b>
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
                                                                FEES ONCE PAID ARE NON-REFUNDABLE AND NON-TRANSFERABLE.
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td style="vertical-align: top; width: 15px;">&#8226;</td>
                                                            <td
                                                                style="font-size:15px; color:#000000; font-weight:bold; font-family: Arial, sans-serif;">
                                                                Non-submission of Documents &amp; Fees within due date
                                                                &rarr; <u>Admission Cancelled.</u>
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
