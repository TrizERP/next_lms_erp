<html>

<body>
    
    <script  src="https://code.jquery.com/jquery-3.2.1.js"  integrity="sha256-DZAnKJ/6XZ9si04Hgrsxu/8s717jcIzLy3oi35EouyE="  crossorigin="anonymous"></script>
     
    <!-- <form style="" name="Formdata" id="Formdata" method="POST" action="https://uat-etendering.axisbank.co.in/easypay2.0/frontend/api/payment" > -->
    	<form style="" name="Formdata" id="Formdata" method="POST" action="https://easypay.axisbank.co.in/index.php/api/payment" >
    
        <input type="hidden" value = "<?php echo $data['send_data']; ?>" name="i" id="i">
        <!-- <textarea name="i" id="i"></textarea>
        <input class="btn btn-primary" type="submit" value="Submit" >        -->
    </form>
    
    <script>
    $(document).ready(function(){ $("#Formdata").submit(); });
    </script>
    
</body>
</html>