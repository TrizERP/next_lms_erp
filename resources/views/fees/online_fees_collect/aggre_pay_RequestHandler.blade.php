<html>
<body>
    <script  src="https://code.jquery.com/jquery-3.2.1.js"  integrity="sha256-DZAnKJ/6XZ9si04Hgrsxu/8s717jcIzLy3oi35EouyE="  crossorigin="anonymous"></script>
    	<form name="payment_form" id="payment_form" method="POST" action="https://biz.aggrepaypayments.com/v2/paymentrequest" >
            <input type="hidden" value="<?php echo $data['hash']; ?>"          name="hash"/>
            <input type="hidden" value="<?php echo $data['api_key']; ?>"       name="api_key"/>
            <input type="hidden" value="<?php echo $data['return_url']; ?>"    name="return_url"/>
            <input type="hidden" value="<?php echo $data['mode'];?>"           name="mode"/>
            <input type="hidden" value="<?php echo $data['order_id'];?>"       name="order_id"/>
            <input type="hidden" value="<?php echo $data['amount'];?>"         name="amount"/>
            <input type="hidden" value="<?php echo $data['currency'];?>"       name="currency"/>
            <input type="hidden" value="<?php echo $data['description'];?>"    name="description"/>
            <input type="hidden" value="<?php echo $data['name'];?>"           name="name"/>
            <input type="hidden" value="<?php echo $data['email'];?>"          name="email"/>
            <input type="hidden" value="<?php echo $data['phone'];?>"          name="phone"/>
            <input type="hidden" value="<?php echo $data['address_line_1'];?>" name="address_line_1"/>
            <input type="hidden" value="<?php echo $data['address_line_2'];?>" name="address_line_2"/>
            <input type="hidden" value="<?php echo $data['city'];?>"           name="city"/>
            <input type="hidden" value="<?php echo $data['state'];?>"          name="state"/>
            <input type="hidden" value="<?php echo $data['zip_code'];?>"       name="zip_code"/>
            <input type="hidden" value="<?php echo $data['country'];?>"        name="country"/>
            <input type="hidden" value="<?php echo $data['udf1'];?>"           name="udf1"/>
            <input type="hidden" value="<?php echo $data['udf2'];?>"           name="udf2"/>
            <input type="hidden" value="<?php echo $data['udf3'];?>"           name="udf3"/>
            <input type="hidden" value="<?php echo $data['udf4'];?>"           name="udf4"/>
            <input type="hidden" value="<?php echo $data['udf5'];?>"           name="udf5"/>
        </form>
    <script>
    function formAutoSubmit () {
        var payform = document.getElementById("payment_form");
        payform.submit();
    }
    window.onload = formAutoSubmit;
</script>
</body>
</html>