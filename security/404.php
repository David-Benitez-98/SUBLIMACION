<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Página no encontrada</title>
    <style>
        * { box-sizing: border-box; }
        body { padding: 0; margin: 0; }
        #notfound {
            position: relative;
            height: 100vh;
            background-color: #fafbfd;
        }
        .notfound {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            max-width: 520px;
            width: 100%;
            text-align: center;
        }
        .notfound h1 {
            font-family: sans-serif;
            font-size: 86px;
            text-transform: uppercase;
            font-weight: 700;
            margin: 0 0 8px;
            color: #151515;
        }
        .notfound h2 {
            font-family: sans-serif;
            font-size: 22px;
            margin: 0 0 20px;
            font-weight: 600;
            color: #151515;
        }
        .notfound a {
            font-family: sans-serif;
            font-size: 14px;
            text-decoration: none;
            text-transform: uppercase;
            background: #18e06f;
            display: inline-block;
            padding: 15px 30px;
            border-radius: 5px;
            color: #fff;
            font-weight: 700;
        }
    </style>
</head>
<body>
<div id="notfound">
    <div class="notfound">
        <h1>404</h1>
        <h2>La página que buscás no existe</h2>
        <a href="/dashboard.php">Volver al inicio</a>
    </div>
</div>
</body>
</html>