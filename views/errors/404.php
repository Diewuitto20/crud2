<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error 404 - Página No Encontrada</title>
    <style>
        /* Estilos básicos para centrar y dar formato */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7f6;
            color: #333;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            text-align: center;
        }

        .container {
            max-width: 600px;
            padding: 40px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        /* Estilo para el número 404 grande */
        h1 {
            font-size: 120px;
            margin: 0;
            color: #e74c3c; /* Un rojo suave para alertar */
            line-height: 1;
        }

        /* Estilo para el título del mensaje */
        h2 {
            font-size: 24px;
            margin-top: 10px;
            margin-bottom: 20px;
            color: #2c3e50;
        }

        /* Estilo para el texto explicativo */
        p {
            font-size: 16px;
            line-height: 1.6;
            color: #7f8c8d;
            margin-bottom: 30px;
        }

        /* Estilo para el botón de acción */
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: #3498db; /* Azul profesional */
            color: #fff;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
            transition: background-color 0.3s ease;
        }

        .btn:hover {
            background-color: #2980b9; /* Azul más oscuro al pasar el mouse */
        }

        /* Icono opcional (usando texto) */
        .icon {
            font-size: 60px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🔍</div>
        
        <h1>404</h1>
        
        <h2>¡Ups! Página no encontrada</h2>
        
        <p>Parece que la dirección que introdujiste es incorrecta, la página ha sido movida o ya no está disponible. Por favor, verifica la URL o vuelve al inicio.</p>
        
        <a href="index.php" class="btn">Volver a la Página de Inicio</a>
    </div>
</body>
</html>