<html>
<!-- https://test.ccavenue.com/transaction/transaction.do?command=initiateTransaction -->
<body>
    <form method="post" name="redirect" action="https://secure.ccavenue.com/transaction/transaction.do?command=initiateTransaction">
        <input type=hidden name="encRequest" value="{{$data['merchant_data']}}">
        <input type=hidden name="access_code" value="{{$data['ac_code']}}">
    </form>
    </center>
    <script language='javascript'>
        document.redirect.submit();
    </script>

</body>

</html>