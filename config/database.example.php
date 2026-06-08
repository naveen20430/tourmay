<?php
class Database {
    private $host = 'your-mysql-host';
    private $database = 'your_database_name';
    private $username = 'your_database_user';
    private $password = 'your_database_password';

    private $connection = null;

    public function __construct() {
    }

    public function connect() {
        if ($this->connection === null) {
            try {
                $this->connection = new PDO(
                    "mysql:host={$this->host};dbname={$this->database};charset=utf8mb4",
                    $this->username,
                    $this->password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::ATTR_PERSISTENT => true,
                    ]
                );
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), '1226') !== false) {
                    die('Server Busy: The maximum number of connections per hour has been exceeded. Please try again in 5-10 minutes.');
                }
                die('Database connection failed: ' . $e->getMessage());
            }
        }
    }

    public function getConnection() {
        if ($this->connection === null) {
            $this->connect();
        }
        return $this->connection;
    }

    public function disconnect() {
        $this->connection = null;
    }

    public function query($sql, $params = []) {
        try {
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new Exception('Query failed: ' . $e->getMessage());
        }
    }

    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetch($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    public function execute($sql, $params = []) {
        return $this->query($sql, $params)->rowCount();
    }

    public function lastInsertId() {
        return $this->getConnection()->lastInsertId();
    }
}
