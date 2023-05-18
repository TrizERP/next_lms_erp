<!doctype html>
<html lang="en">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">


    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="{{asset('/css/style2.css')}}">
    <title>Institute Data | TRIZ INNOVATION PVT LTD</title>


  </head>
  <body>
  	<!-- Header -->
  	<div class="header-log">
  		<div class="headr-logo">
  			<a href="#">
  				<img src="{{asset('Images/logo.png')}}">
  			</a>
  		</div>
  	</div>
<?php 
// echo "<pre>";print_r($data);exit;
 ?>
  	<!-- Setup Your Details -->
    <section class="detail-section">
    	<div class="container">
    		<div class="page-title mb-40">
    			<h1 class="text-center">Setup your details</h1>
    		</div>
    		<form action="{{ route('add-institute') }}" method="POST" enctype="multipart/form-data">
          @csrf
    			<div class="form-group field-complete">
				    <label for="text">Institute Name</label>
				    <input type="text" class="form-control" name="institute_name" placeholder="Institute Name" value="@if(isset($data['institute'])){{$data['institute']}} @endif">

				  </div>
    				    <input type="hidden" name="mobile" value="@if(isset($data['mobile'])){{$data['mobile']}} @endif"/>
    				    <input type="hidden" name="type" value="@if(isset($data['type'])){{$data['type']}} @endif"/>

  				<div class="form-group file-design">
  				    <label class="d-block">Upload Institute Logo</label>
              <div class="upload-file" data-toggle="modal" data-target="#profile">
    				    <input type="file" name="file_input" id="file-input-01" accept=".jpg,.png,.webp" onchange="loadFile(event)"/>
        				<label class="file-label" for="file-input-01">
        					<img src="{{asset('Images/upload-icon.svg')}}" >
          				<span>Upload Institute Logo</span>
          			</label>
              </div>

              <div class="select-file">
                <img id="logoimg">
                <div class="btns">
                  <a class="purple-btn border-btn " style="padding:6px 6px !important" onclick="deleteImage()">Delete</a>
                  <!-- <a class="purple-btn" onclick="replaceLogo()" style="padding:6px 6px !important">Replace</a> -->
                </div>
              </div>
  				</div>
          <div class="form-group field-complete">
            <label for="text">Choose Board</label>
            <div class="form-control select" data-toggle="modal" data-target="#select-board">
              <!-- <div class="placeholder-text">Choose Board</div>   -->
              <div class="select-text d-flex align-item-center">
                <img src="{{asset('Images/GSEB.png')}}">
                <span>GSEB</span>
              </div>,
              <div class="select-text d-flex align-item-center">
                <img src="{{asset('Images/BSEB.png')}}">
                <span>BSEB</span>
              </div>
             2
            </div>
          </div>
          <div class="select-board"></div>

          <div class="form-group">
            <label for="text">Choose Section</label>

            <div class="form-control select" data-toggle="modal" data-target="#select-section">
              <div class="placeholder-text">Choose section</div>
            </div>
          </div>
          <div class="select-ClassSection"></div>

        <!--   <div class="form-group file-design">
              <label class="d-block">Upload Student Data</label>
              <input type="file" name="file-input" id="file-input-02" />
                <label class="file-label" for="file-input-02">
                  <img src="{{asset('Images/upload-icon.svg')}}">
                  <span>Upload Student Data</span>
                </label>
          </div>
          <div class="form-group file-design">
              <label class="d-block">Upload Staff Data</label>
              <input type="file" name="file-input" id="file-input-03" />
                <label class="file-label" for="file-input-03">
                  <img src="{{asset('Images/upload-icon.svg')}}">
                  <span>Upload Staff Data</span>
                </label>
          </div> -->


    <!-- Modal -->
    <div class="modal fade text-center user-profile-select" id="profile" tabindex="-1" aria-labelledby="profile" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header justify-content-center">
            <h5 class="modal-title" id="profile">Crop Photo</h5>
          </div>
          <div class="modal-body">
            <img class="select-photo" id="logoimg1">
          </div>
          <div class="modal-footer justify-content-center">
            <button type="button" class="purple-btn border-btn f-18" data-dismiss="modal">Cancel</button>
            <button type="button" class="purple-btn f-18" data-dismiss="modal">Save</button>
          </div>
        </div>
      </div>
    </div>



    <!-- Modal Board-->
    <div class="modal fade text-center" id="select-board" tabindex="-1" aria-labelledby="select-board" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header justify-content-center border-0 pb-0">
            <h5 class="modal-title" id="select-board">Choose Section</h5>
          </div>

          <div class="modal-body board-list">
              <div class="modal-radio-design board-box">
                  <input class="form-radio-input" type="checkbox" name="exampleRadiosboard[]" id="exampleRadios1" value="GSEB">
                  <label class="form-radio-label" for="exampleRadios1">
                    <img src="{{asset('/Images/GSEB.png')}}">
                    <span class="d-block text-center">GSEB</span>
                  </label>
              </div>
              <div class="modal-radio-design board-box">
                  <input class="form-radio-input" type="checkbox" name="exampleRadiosboard[]" id="exampleRadios2" value="BSEB">
                  <label class="form-radio-label" for="exampleRadios2">
                    <img src="{{asset('/Images/BSEB.png')}}">
                    <span class="d-block text-center">BSEB</span>
                  </label>
              </div>
              <div class="modal-radio-design board-box">
                  <input class="form-radio-input" type="checkbox" name="exampleRadiosboard[]" id="exampleRadios3" value="BSEAP">
                  <label class="form-radio-label" for="exampleRadios3">
                    <img src="{{asset('/Images/BSEAP.png')}}">
                    <span class="d-block text-center">BSEAP</span>
                  </label>
              </div>
              <div class="modal-radio-design board-box">
                  <input class="form-radio-input" type="checkbox" name="exampleRadiosboard[]" id="exampleRadios4" value="CBSE">
                  <label class="form-radio-label" for="exampleRadios4">
                    <img src="{{asset('/Images/CBSE.png')}}">
                    <span class="d-block text-center">CBSE</span>
                  </label>
              </div>
          </div>
          <div class="modal-footer justify-content-center border-0 pt-0">
            <button type="button" class="purple-btn max-500" data-dismiss="modal">Done</button>
          </div>
        </div>
      </div>
    </div>


      <!-- Modal Board-->
    <div class="modal fade text-center" id="select-section" tabindex="-1" aria-labelledby="select-board" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header justify-content-center border-0 pb-0">
            <h5 class="modal-title" id="select-board">Choose Section</h5>
          </div>
          <div class="modal-body board-list">
            <!-- $allRadios = ['PRE-PRIMARY', 'PRIMARY', 'SECONDARY', 'HIGH-SECONDARY']; -->
            
              <div class="modal-radio-design board-box">
                  <input class="form-radio-input" type="checkbox" name="exampleRadios[]" id="exampleRadios5" value="PRE-PRIMARY">
                  <label class="form-radio-label" for="exampleRadios5">
                    <img src="{{asset('/Images/Pre-Primary.png')}}">
                    <span class="d-block text-center">Pre-Primary</span>
                  </label>
              </div>
              <div class="modal-radio-design board-box">
                  <input class="form-radio-input" type="checkbox" name="exampleRadios[]" id="exampleRadios6" value="PRIMARY">
                  <label class="form-radio-label" for="exampleRadios6">
                    <img src="{{asset('/Images/Primary.png')}}">
                    <span class="d-block text-center">Primary</span>
                  </label>
              </div>
            <!--   <div class="modal-radio-design board-box">
                  <input class="form-radio-input" type="checkbox" name="exampleRadios[]" id="exampleRadios7" value="Upper Primary">
                  <label class="form-radio-label" for="exampleRadios7">
                    <img src="{{asset('/Images/Upper-Primary.png')}}">
                    <span class="d-block text-center">Upper Primary</span>
                  </label>
              </div>
              <div class="modal-radio-design board-box">
                  <input class="form-radio-input" type="checkbox" name="exampleRadios[]" id="exampleRadios8" value="Middle Primary">
                  <label class="form-radio-label" for="exampleRadios8">
                    <img src="{{asset('/Images/Middle-Primary.png')}}">
                    <span class="d-block text-center">Middle Primary</span>
                  </label>
              </div> -->
              <div class="modal-radio-design board-box">
                  <input class="form-radio-input" type="checkbox" name="exampleRadios[]" id="exampleRadios9" value="SECONDARY">
                  <label class="form-radio-label" for="exampleRadios9">
                    <img src="{{asset('/Images/Secondary.png')}}">
                    <span class="d-block text-center">Secondary</span>
                  </label>
              </div>
              <div class="modal-radio-design board-box">
                  <input class="form-radio-input" type="checkbox" name="exampleRadios[]" id="exampleRadios10" value="HIGH-SECONDARY">
                  <label class="form-radio-label" for="exampleRadios10">
                    <img src="{{asset('/Images/Higher-Secondary.png')}}">
                    <span class="d-block text-center">Higher Secondary</span>
                  </label>
              </div>
          </div>
          <div class="modal-footer justify-content-center border-0 pt-0">
            <button type="button" class="purple-btn max-500"  data-dismiss="modal">Done</button>
          </div>
        </div>
      </div>
    </div>







				<button type="submit" class="purple-btn w-100 save-btn">Save & Continue <img src="{{asset('/Images/right-arrow-icon.svg')}}"></button>
    		</form>
    	</div>
    </section>







    <!-- Option 1: jQuery and Bootstrap Bundle (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script type="text/javascript">

    $('.select-file').hide();

    var loadFile = function(event) {
    var output = document.getElementById('logoimg1');
    output.src = URL.createObjectURL(event.target.files[0]);
    output.onload = function() {
      URL.revokeObjectURL(output.src) // free memory
    }
    $('.select-file').show();
     var output2 = document.getElementById('logoimg');
    output2.src = URL.createObjectURL(event.target.files[0]);
    output2.onload = function() {
      URL.revokeObjectURL(output2.src) // free memory
    }
  };
  function deleteImage() {
  const image = document.getElementById('logoimg');
  if (image) {
    image.parentNode.removeChild(image);
  }
}

// select board

// Get the radio buttons and select-board div
const radioButtons = document.querySelectorAll('input[name="exampleRadiosboard[]"]');
const selectBoardDiv = document.querySelector('.select-board');

// Add change event listener to radio buttons
radioButtons.forEach((radio) => {
  radio.addEventListener('change', () => {
    updateSelectedRadios();
  });
});

function updateSelectedRadios() {
  // Clear the existing content in the select-board div
  selectBoardDiv.innerHTML = '';
      selectBoardDiv.style.display = 'flex'; // Example style
      selectBoardDiv.style.flexWrap = 'wrap'; // Example style


  // Loop through the radio buttons and check the selected ones
  radioButtons.forEach((radio) => {
    if (radio.checked) {
      // Get the image source and text from the selected radio
      const imageSrc = radio.parentElement.querySelector('img').src;
      const labelText = radio.parentElement.querySelector('.d-block').textContent;

      // Create a new div element
      const newDiv = document.createElement('div');
      newDiv.style.display = 'inline-grid'; // Example style
      newDiv.style.textAlign  = 'center'; // Example style
      newDiv.style.margin = '0px 4px'; // Example style


      // Create a new image element and set its source and style
      const image = document.createElement('img');
      image.src = imageSrc;
      image.style.width = '100px'; 
      // Example style
      newDiv.appendChild(image);

      // Create a new span element and set its text content and style
      const span = document.createElement('span');
      span.textContent = labelText;
      span.style.color = 'blue'; // Example style
      newDiv.appendChild(span);

      // Append the new div to the select-board div
      selectBoardDiv.appendChild(newDiv);
    }
  });
}

// select section

// Get the radio buttons and select-board div
const radioButtonsec = document.querySelectorAll('input[name="exampleRadios[]"]');
const selectSecDiv = document.querySelector('.select-ClassSection');

// Add change event listener to radio buttons
radioButtonsec.forEach((radio) => {
  radio.addEventListener('change', () => {
    updateSelectedRadiossec();
  });
});

function updateSelectedRadiossec() {
  // Clear the existing content in the select-board div
  selectSecDiv.innerHTML = '';
      selectSecDiv.style.display = 'flex'; // Example style
      selectSecDiv.style.flexWrap = 'wrap'; // Example style


  // Loop through the radio buttons and check the selected ones
  radioButtonsec.forEach((radio) => {
    if (radio.checked) {
      // Get the image source and text from the selected radio
      const imageSrc = radio.parentElement.querySelector('img').src;
      const labelText = radio.parentElement.querySelector('.d-block').textContent;

      // Create a new div element
      const newDiv = document.createElement('div');
      newDiv.style.display = 'inline-grid'; // Example style
      newDiv.style.textAlign  = 'center'; // Example style
      newDiv.style.margin = '0px 4px'; // Example style


      // Create a new image element and set its source and style
      const image = document.createElement('img');
      image.src = imageSrc;
      image.style.width = '100px'; 
      // Example style
      newDiv.appendChild(image);

      // Create a new span element and set its text content and style
      const span = document.createElement('span');
      span.textContent = labelText;
      span.style.color = 'blue'; // Example style
      newDiv.appendChild(span);

      // Append the new div to the select-board div
      selectSecDiv.appendChild(newDiv);
    }
  });
}

    </script>
  </body>
</html>