<?php
/**
 * Clase para la gestión de la conexión a la Base de Datos
 * PSR-12: La llave de apertura de la clase va en la línea siguiente.
 */
class Database
{
    // Propiedad privada para guardar la instancia de PDO
    private ?PDO $pdo = null;

    /**
     * Constructor de la clase
     * PSR-12: Visibilidad (public) siempre declarada.
     */
    public function __construct()
    {
        $envPath = 'env.php';

        if (!file_exists($envPath)) {
            die("Error: El archivo de configuración env.php no existe.");
        }

        $env = require $envPath;

        $dsn = "mysql:host=" . $env['DB_HOST'] . ";dbname=" . $env['DB_NAME'] . ";charset=" . $env['DB_CHARSET'];
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $env['DB_USER'], $env['DB_PASS'], $options);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            die("Error interno del servidor.");
        }
    }

    /**
     * Retorna la instancia de la conexión
     */
    public function getConnection(): PDO
    {
        return $this->pdo;
    }
}