<html>
<body>
    <script  src="https://code.jquery.com/jquery-3.2.1.js"  integrity="sha256-DZAnKJ/6XZ9si04Hgrsxu/8s717jcIzLy3oi35EouyE="  crossorigin="anonymous"></script>


    <form name="Formdata" id="Formdata" method="POST" action="{{$data['send_data']}}" >
        <input type="hidden" value ="{{$data['send_data']}}" name="all_data" id="all_data">
    </form>
    <script>
        $(document).ready(function(){ $("#Formdata").submit(); });
    </script>
</body>
</html>