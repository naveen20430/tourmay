<?php
class Database {
    private $connection;
    private $connectionError;
    private $host;
    private $database;
    private $username;
    private $password;

    public function __construct() {
        $this->host = getenv('DB_HOST') ?: 'localhost';
        $this->database = getenv('DB_NAME') ?: 'u255007981_tour';
        $this->username = getenv('DB_USER') ?: 'root';
        $this->password = getenv('DB_PASS') ?: '';

        $drivers = PDO::getAvailableDrivers();
        if (!in_array('mysql', $drivers, true)) {
            $this->connection = null;
            $this->connectionError = 'PDO MySQL driver is not installed (pdo_mysql).';
            return;
        }

        $dsn = "mysql:host={$this->host};dbname={$this->database};charset=utf8mb4";

        try {
            $this->connection = new PDO(
                $dsn,
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            $this->connection = null;
            $this->connectionError = $e->getMessage();
        }
    }

    public function getConnection() {
        return $this->connection;
    }

    public function getConnectionError() {
        return $this->connectionError;
    }

    public function query($sql, $params = []) {
        if (!$this->connection) {
            throw new Exception($this->connectionError ?: 'Database connection not available.');
        }
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchAll($sql, $params = []) {
        if (!$this->connection) {
            return [];
        }
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetch($sql, $params = []) {
        if (!$this->connection) {
            return false;
        }
        $row = $this->query($sql, $params)->fetch();
        return $row ? $row : false;
    }

    public function execute($sql, $params = []) {
        if (!$this->connection) {
            return 0;
        }
        return $this->query($sql, $params)->rowCount();
    }

    public function lastInsertId() {
        if (!$this->connection) {
            return '0';
        }
        return $this->connection->lastInsertId();
    }
}
?>
