
<!DOCTYPE html>
<html lang="en">
   <head>
      <meta charset="UTF-8" />
      <meta name="viewport" content="width=device-width, initial-scale=1.0" />
      <link
         rel="stylesheet"
         href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css"
         integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm"
         crossorigin="anonymous"
      />
      <meta http-equiv="X-UA-Compatible" content="ie=edge" />
      <title>Admission Forms</title>
      <link rel="stylesheet" href="/styles/main.css" />
   </head>
   <body>
      <div class="container">
         <!-- Include your partials here -->
         <header>
   <!-- Image and text -->
   <div class="container header text-center">
      <div class="row">
         <div class="col-lg-3">
            <div class="logo-div">
              @if(isset($data['Logo']))
                <img id="logo" src="{{$data['Logo']}}" alt="Logo" style="height: 250px; width: 250px;" />
                <link rel="icon" href="{{$data['Logo']}}" sizes="192x192" />
              @else
                <img id="logo" src="http://202.47.117.124/admin_dep/images/logo_triz.png" alt="Logo" />
                <link rel="icon" href="http://202.47.117.124/admin_dep/images/logo_triz.png" sizes="192x192" />
              @endif
            </div>
         </div>
         <div class="col-lg-9 text-primary">
            <h1>Welcome to<h1>
<h1>{{$data['SchoolName']}}</h1>
            
         </div>
      </div>
   </div>
</header>
  <div class="container text-center">
  <hr />
  
  <p class="mt-4 w-75 text-justify mx-auto">
    MULJIBHAI MEHTA INTERNATIONAL SCHOOL has initiated Online Admission to Class 11 to simplify
    the admission process and start online classes presently.
  </p>

  <p class="w-75 text-justify mx-auto">
    The admission will be provisional and will be considered as complete only on
    submission of all requisite documents like marksheet and transfer
    certificate in original.
  </p>
  <p class="w-75 text-justify mx-auto" style="text-decoration: underline;">Process of provisional admission</p>
  <ol class="w-75 text-justify mx-auto">
    <li>Fill in the application form carefully.</li>
    <li>
      The form has provision of selecting two streams. In that case,
      preference of stream has to be clearly mentioned.
    </li>
    <li>
      Do upload a) PHOTOGRAPH b) COPY OF CLASS X BOARD MARKSHEET. Without these 2
      documents, the application will be invalid.
    </li>
    <li>
      You will receive a confirmation mail from school after the application
      form is checked and the admission criteria is met with.
    </li> 
    <li>
      On receiving confirmation mail pay the admission fee within 5 working days
      of receiving the mail.
    </li>
    <li>
      When regular classes resume all necessary documents will have to be
      submitted.
    </li>
    
    <li>
      For any queries please email to 
      <a href="mailto:contact@muljibhaimehtainternationalschool.in "
        >contact@muljibhaimehtainternationalschool.in</a
      > Kindly always quote the registration number and name of the student when communicating with the school.
       <!-- .Please always quote the
      registration number and name of student when communicating with school. -->
    </li>
  </ol>

  <br />
  <div class="mx-auto">
    <a href="{{route('onlineEnquiry', ['id' => $data['Id'], 'title' => $data['SchoolName']])}}" class="sticky btn btn-warning mt-2 mr-3">Fill Class XI Admission Form</a>
   
      
  </div>


  <!-- <a href="/forms" class="btn btn-warning">Fill Class XI admission Form</a> -->
</div>

      </div>

      <!-- Javascript files -->
      <script
         defer
         src="https://code.jquery.com/jquery-3.2.1.slim.min.js"
         integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN"
         crossorigin="anonymous"
      ></script>
      <script
         defer
         src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js"
         integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q"
         crossorigin="anonymous"
      ></script>
      <script
         defer
         src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"
         integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl"
         crossorigin="anonymous"
      ></script>
      <script defer src="/scripts/script.js"></script>
   </body>
</html>
