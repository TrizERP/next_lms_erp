<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Document</title>
    <link href="{{ asset('css/style.css') }}" rel="stylesheet" />
</head>

<body>
    <section class="tbl__container">
        <div class="tbl__header">
            <h1>Lesson Plan</h1>
        </div>
        <div class="tbl__box">
            <svg width="18" height="18" class="expand-all" viewBox="0 0 256 256" xml:space="preserve"
                onclick="handleExpandAll()">
                <defs></defs>
                <g style="
              stroke: none;
              stroke-width: 0;
              stroke-dasharray: none;
              stroke-linecap: butt;
              stroke-linejoin: miter;
              stroke-miterlimit: 10;
              fill: none;
              fill-rule: nonzero;
              opacity: 1;
            "
                    transform="translate(1.4065934065934016 1.4065934065934016) scale(2.81 2.81)">
                    <path
                        d="M 13.657 8 h 5.021 c 2.209 0 4 -1.791 4 -4 s -1.791 -4 -4 -4 H 4 C 3.984 0 3.968 0.005 3.952 0.005 C 3.706 0.008 3.46 0.031 3.217 0.079 c -0.121 0.024 -0.233 0.069 -0.35 0.104 C 2.734 0.222 2.6 0.252 2.47 0.306 c -0.132 0.055 -0.252 0.13 -0.377 0.198 c -0.104 0.057 -0.213 0.103 -0.312 0.17 C 1.58 0.808 1.395 0.963 1.222 1.13 C 1.206 1.145 1.187 1.156 1.171 1.171 C 1.155 1.188 1.145 1.207 1.129 1.224 C 0.962 1.396 0.808 1.58 0.674 1.78 c -0.07 0.104 -0.118 0.216 -0.176 0.325 C 0.432 2.226 0.359 2.341 0.306 2.469 c -0.057 0.137 -0.09 0.279 -0.13 0.42 C 0.144 2.998 0.102 3.103 0.079 3.216 C 0.028 3.475 0 3.738 0 4.001 v 14.677 c 0 2.209 1.791 4 4 4 s 4 -1.791 4 -4 v -5.021 l 23.958 23.958 c 0.781 0.781 1.805 1.171 2.829 1.171 s 2.047 -0.391 2.829 -1.171 c 1.562 -1.563 1.562 -4.095 0 -5.657 L 13.657 8 z"
                        style="
                stroke: none;
                stroke-width: 1;
                stroke-dasharray: none;
                stroke-linecap: butt;
                stroke-linejoin: miter;
                stroke-miterlimit: 10;
                fill: rgb(0, 0, 0);
                fill-rule: nonzero;
                opacity: 1;
              "
                        transform=" matrix(1 0 0 1 0 0) " stroke-linecap="round" />
                    <path
                        d="M 86 67.321 c -2.209 0 -4 1.791 -4 4 v 5.022 L 58.042 52.386 c -1.561 -1.563 -4.096 -1.563 -5.656 0 c -1.563 1.562 -1.563 4.095 0 5.656 L 76.344 82 h -5.022 c -2.209 0 -4 1.791 -4 4 s 1.791 4 4 4 H 86 c 0.263 0 0.525 -0.028 0.783 -0.079 c 0.117 -0.023 0.226 -0.067 0.339 -0.101 c 0.137 -0.04 0.275 -0.072 0.408 -0.127 c 0.133 -0.055 0.254 -0.131 0.38 -0.2 c 0.103 -0.056 0.21 -0.101 0.308 -0.167 c 0.439 -0.293 0.815 -0.67 1.109 -1.109 c 0.065 -0.097 0.109 -0.201 0.164 -0.302 c 0.07 -0.128 0.147 -0.251 0.203 -0.386 c 0.055 -0.132 0.086 -0.269 0.126 -0.405 c 0.034 -0.114 0.078 -0.223 0.101 -0.341 C 89.972 86.525 90 86.263 90 86 V 71.321 C 90 69.112 88.209 67.321 86 67.321 z"
                        style="
                stroke: none;
                stroke-width: 1;
                stroke-dasharray: none;
                stroke-linecap: butt;
                stroke-linejoin: miter;
                stroke-miterlimit: 10;
                fill: rgb(0, 0, 0);
                fill-rule: nonzero;
                opacity: 1;
              "
                        transform=" matrix(1 0 0 1 0 0) " stroke-linecap="round" />
                    <path
                        d="M 31.958 52.386 L 8 76.343 v -5.022 c 0 -2.209 -1.791 -4 -4 -4 s -4 1.791 -4 4 v 14.677 c 0 0.263 0.028 0.526 0.079 0.785 c 0.023 0.113 0.065 0.218 0.097 0.328 c 0.041 0.141 0.074 0.283 0.13 0.419 C 0.36 87.66 0.434 87.777 0.5 87.899 c 0.058 0.107 0.105 0.218 0.174 0.32 c 0.145 0.217 0.31 0.42 0.493 0.604 c 0.002 0.002 0.003 0.004 0.004 0.005 c 0 0 0 0 0 0 c 0.186 0.186 0.391 0.352 0.61 0.498 c 0.1 0.067 0.208 0.112 0.312 0.169 c 0.125 0.068 0.244 0.143 0.377 0.198 c 0.134 0.055 0.273 0.087 0.411 0.128 c 0.112 0.033 0.22 0.076 0.336 0.099 C 3.475 89.972 3.737 90 4 90 h 14.679 c 2.209 0 4 -1.791 4 -4 s -1.791 -4 -4 -4 h -5.022 l 23.958 -23.958 c 1.562 -1.562 1.562 -4.095 0 -5.656 C 36.052 50.823 33.52 50.823 31.958 52.386 z"
                        style="
                stroke: none;
                stroke-width: 1;
                stroke-dasharray: none;
                stroke-linecap: butt;
                stroke-linejoin: miter;
                stroke-miterlimit: 10;
                fill: rgb(0, 0, 0);
                fill-rule: nonzero;
                opacity: 1;
              "
                        transform=" matrix(1 0 0 1 0 0) " stroke-linecap="round" />
                    <path
                        d="M 89.921 3.217 c -0.023 -0.118 -0.067 -0.227 -0.101 -0.34 c -0.04 -0.136 -0.071 -0.274 -0.126 -0.406 c -0.056 -0.134 -0.132 -0.256 -0.201 -0.382 c -0.056 -0.102 -0.101 -0.209 -0.167 -0.307 c -0.147 -0.219 -0.313 -0.424 -0.498 -0.61 c 0 0 0 0 0 0 c -0.002 -0.002 -0.004 -0.003 -0.005 -0.004 c -0.184 -0.184 -0.387 -0.348 -0.604 -0.493 c -0.101 -0.068 -0.21 -0.114 -0.316 -0.171 C 87.78 0.435 87.661 0.36 87.53 0.306 c -0.131 -0.054 -0.267 -0.085 -0.401 -0.125 c -0.116 -0.034 -0.226 -0.079 -0.346 -0.102 c -0.242 -0.048 -0.489 -0.071 -0.735 -0.074 C 86.032 0.005 86.016 0 86 0 H 71.321 c -2.209 0 -4 1.791 -4 4 s 1.791 4 4 4 h 5.022 L 52.386 31.958 c -1.563 1.563 -1.563 4.095 0 5.657 c 0.78 0.781 1.805 1.171 2.828 1.171 s 2.048 -0.391 2.828 -1.171 L 82 13.657 v 5.022 c 0 2.209 1.791 4 4 4 s 4 -1.791 4 -4 V 4 C 90 3.737 89.972 3.475 89.921 3.217 z"
                        style="
                stroke: none;
                stroke-width: 1;
                stroke-dasharray: none;
                stroke-linecap: butt;
                stroke-linejoin: miter;
                stroke-miterlimit: 10;
                fill: rgb(0, 0, 0);
                fill-rule: nonzero;
                opacity: 1;
              "
                        transform=" matrix(1 0 0 1 0 0) " stroke-linecap="round" />
                </g>
            </svg>
            <table>
                <thead>
                    <tr class="table-head">
                        <th>
                            <div>
                                <p>Summary of lesson plan</p>
                                <img onclick="handleAddAll(0)" src="{{asset('admin_dep/images/expand.svg')}}" />
                            </div>
                        </th>
                        <th>
                            <div>
                                <p>Teaching</p>
                                <img onclick="handleAddAll(1)" src="{{asset('admin_dep/images/expand.svg')}}" />
                            </div>
                        </th>
                        <th>
                            <div>
                                <p>Learning</p>
                                <img onclick="handleAddAll(2)" src="{{asset('admin_dep/images/expand.svg')}}" />
                            </div>
                        </th>
                        <th>
                            <div>
                                <p>Day Planning</p>
                                <img onclick="handleAddAll(3)" src="{{asset('admin_dep/images/expand.svg')}}" />
                            </div>
                        </th>
                        <th>
                            <div>
                                <p>Map & alignment</p>
                                <img onclick="handleAddAll(4)" src="{{asset('admin_dep/images/expand.svg')}}" />
                            </div>
                        </th>
                    </tr>
                    <tr>
                        <th>Admin</th>
                        <th>Admin</th>
                        <th>Admin</th>
                        <th>Admin</th>
                        <th>Admin</th>
                    </tr>
                    <tr>
                        <th>Teacher</th>
                        <th>Teacher</th>
                        <th>Teacher</th>
                        <th>Teacher</th>
                        <th>Teacher</th>
                    </tr>
                    <tr>
                        <th>Student</th>
                        <th>Student</th>
                        <th>Student</th>
                        <th>Student</th>
                        <th>Student</th>
                    </tr>
                </thead>

                <tbody id="table-body"></tbody>
            </table>
        </div>
    </section>
    <section id="accordion-container" class="accordion-container"></section>
    <script>
        var standards = JSON.parse(("{{ $data['standards']}}").replace(/&quot;/ig,'"'));
        var subjects = JSON.parse(("{{ $data['subjects']}}").replace(/&quot;/ig,'"'));
        var chapters = JSON.parse(("{{ $data['chapters']}}").replace(/&quot;/ig,'"'));
        var topics = JSON.parse(("{{ $data['topics']}}").replace(/&quot;/ig,'"'));
    </script>
    <script src="{{ asset('js/main.js') }}"></script>
</body>

</html>
