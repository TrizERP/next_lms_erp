<html>

<body>
    <?php echo '<pre>'; print_r($data); exit; ?>
    <form method="post" name="redirect" action="https://secure.ccavenue.com/transaction/transaction.do?command=initiateTransaction">
        <input type=hidden name="encRequest" value="<?php echo $data['merchant_data'] ?>">
        <input type=hidden name="access_code" value="<?php echo $data['ac_code'] ?>">
        <?php
        // echo "<input type=hidden name=encRequest value=$encrypted_data>";
        // echo "<input type=hidden name=access_code value=$access_code>";
        ?>
    </form>
    </center>
    <script language='javascript'>
        // document.redirect.submit();
    </script>

</body>

</html>