<html>
<head>
    <meta charset="UTF-8">
</head>
<body>
    <script  src="https://code.jquery.com/jquery-3.2.1.js"  integrity="sha256-DZAnKJ/6XZ9si04Hgrsxu/8s717jcIzLy3oi35EouyE="  crossorigin="anonymous"></script>

    <?php
    /* echo("<pre>");
    print_r($data['send_response']);
    echo("</pre>");
    die; */
    ?>
    @php

        $url = "Location: " . $data['send_response']['redirectURI'] ."?tranCtx=". $data['send_response']['tranCtx'];
            header($url, true);
            exit();
    @endphp      
</body>
</html>