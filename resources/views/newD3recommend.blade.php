<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neo4j</title>
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            overflow:hidden;
        }
        .scrollbar-hide::-webkit-scrollbar {
        display: none; 
         }
  
        .scrollbar-hide {
         -ms-overflow-style: none;          
         scrollbar-width: none;        
        }
        .container-fluid {
            height: 100vh; /* Full height of viewport */
            width: 100vw;  /* Full width of viewport */
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .iframe-container-new {
            width: 100%;
            height: 100%;
        }
        .responsive-iframe-new {
            width: 100%;
            height: 100%;
            border: none;
        }
    </style>
</head>
<body>
   <div class="scrollbar-hide">
    <div class="container-fluid">
        <div class="iframe-container-new">
            <iframe src="https://dev.triz.co.in/dashboard_new" class="responsive-iframe-new"></iframe>
        </div>
    </div>
    </div>
</body>
</html>
