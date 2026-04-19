<?php
//Sirve para obligar a PHP a que respete rigurosamente los tipos de datos 
// definidas estrictamente en las funciones.
declare(strict_types=1);
define('APP_LOG_FILE', __DIR__ . '/logs/app.log');

/**
 * Función global para escapar HTML y prevenir XSS.
 */
function e(string $string): string
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function logger(string $mensaje): void
{
    // Formateamos el mensaje con fecha, hora y un salto de línea (PHP_EOL)
    $fecha = date('Y-m-d H:i:s');
    $contenido = "[$fecha] " . $mensaje . PHP_EOL;

    // El parámetro '3' indica que escribiremos en un archivo específico
    error_log($contenido, 3, APP_LOG_FILE);
}