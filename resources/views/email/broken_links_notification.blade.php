<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Broken Links Notification</title>
</head>
<body>
<h2>Broken Links Notification</h2>
    @if (!empty($brokenLinks))
        <h3>Broken Links:</h3>
        <ul>
            @foreach ($brokenLinks as $link)
                <li>{{ $link }}</li>
            @endforeach
        </ul>
    @endif

    @if (!empty($otherErrors))
        <h3>Other Links:</h3>
        <ul>
            @foreach ($otherErrors as $link2)
                <li>{{ $link2 }}</li>
            @endforeach
        </ul>
    @endif

</body>
</html>