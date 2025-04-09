<?php
// MySQL configuration
define('DB_HOST', '127.0.0.1'); // Using IP instead of localhost to avoid socket issues
define('DB_PORT', '3306');     // Default MySQL port
define('DB_USER', 'root');     // Change in production
define('DB_PASS', '');         // Set strong password in production
define('DB_NAME', 'studybud');

// SQLite fallback configuration
define('USE_SQLITE_FALLBACK', true); // Set to false to disable SQLite fallback
define('SQLITE_DB_PATH', __DIR__ . '/../database/studybud.sqlite');

class Database {
    private static $instance = null;
    private $connection;
    private $dbType = 'mysql'; // Default to MySQL
    
    private function __construct() {
        try {
            // Try MySQL first
            $this->connectToMySQL();
        } catch (PDOException $e) {
            // If MySQL fails and SQLite fallback is enabled, try SQLite
            if (USE_SQLITE_FALLBACK) {
                error_log("MySQL connection failed, falling back to SQLite: " . $e->getMessage());
                $this->connectToSQLite();
            } else {
                $errorCode = $e->getCode();
                $errorMessage = $e->getMessage();
                error_log("Database connection failed: " . $errorMessage);
                
                // Provide more helpful error messages based on error code
                if ($errorCode == 2002) {
                    throw new Exception("Cannot connect to MySQL server. Please ensure MySQL is running in XAMPP Control Panel.");
                } elseif ($errorCode == 1045) {
                    throw new Exception("Access denied for user '" . DB_USER . "'. Please check your MySQL credentials.");
                } else {
                    throw new Exception("Database connection failed: " . $errorMessage);
                }
            }
        }
    }
    
    private function connectToMySQL() {
        // First connect without specifying a database
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 3, // 3 second timeout
            PDO::ATTR_PERSISTENT => true // Use persistent connections
        ];
        
        $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        $this->dbType = 'mysql';
        
        // Check if database exists, if not create it
        $this->createDatabaseIfNotExists();
        
        // Select the database
        $this->connection->exec("USE " . DB_NAME);
    }
    
    private function connectToSQLite() {
        // Ensure the directory exists
        $dbDir = dirname(SQLITE_DB_PATH);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }
        
        // Connect to SQLite database
        $this->connection = new PDO('sqlite:' . SQLITE_DB_PATH, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        $this->dbType = 'sqlite';
        
        // Create tables if they don't exist
        $this->createSQLiteTables();
    }
    
    private function createDatabaseIfNotExists() {
        $stmt = $this->connection->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . DB_NAME . "'");
        $dbExists = (bool) $stmt->fetchColumn();
        
        if (!$dbExists) {
            // Create the database
            $this->connection->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            // Create tables from schema
            $this->createTables();
        }
    }
    
    private function createTables() {
        // Use the database
        $this->connection->exec("USE " . DB_NAME);
        
        // Create users table
        $this->connection->exec("CREATE TABLE IF NOT EXISTS users (
            id INT PRIMARY KEY AUTO_INCREMENT,
            email VARCHAR(255) NOT NULL UNIQUE,
            username VARCHAR(50) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            full_name VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL,
            is_active BOOLEAN DEFAULT TRUE,
            role ENUM('student', 'tutor', 'instructor', 'admin') DEFAULT 'student',
            INDEX idx_email (email),
            INDEX idx_username (username)
        ) ENGINE=InnoDB");
        
        // Create courses table
        $this->connection->exec("CREATE TABLE IF NOT EXISTS courses (
            id INT PRIMARY KEY AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            instructor_id INT NOT NULL,
            thumbnail_url VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
            FOREIGN KEY (instructor_id) REFERENCES users(id),
            INDEX idx_instructor (instructor_id),
            FULLTEXT INDEX idx_course_search (title, description)
        ) ENGINE=InnoDB");
        
        // Create videos table
        $this->connection->exec("CREATE TABLE IF NOT EXISTS videos (
            id INT PRIMARY KEY AUTO_INCREMENT,
            course_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            video_url VARCHAR(255) NOT NULL,
            duration INT NOT NULL,
            order_index INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            uploaded_by INT NOT NULL,
            FOREIGN KEY (course_id) REFERENCES courses(id),
            FOREIGN KEY (uploaded_by) REFERENCES users(id),
            INDEX idx_course_order (course_id, order_index)
        ) ENGINE=InnoDB");
        
        // Create books table
        $this->connection->exec("CREATE TABLE IF NOT EXISTS books (
            id INT PRIMARY KEY AUTO_INCREMENT,
            course_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            file_path VARCHAR(255) NOT NULL,
            file_size INT NOT NULL,
            order_index INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            uploaded_by INT NOT NULL,
            FOREIGN KEY (course_id) REFERENCES courses(id),
            FOREIGN KEY (uploaded_by) REFERENCES users(id),
            INDEX idx_course_order (course_id, order_index)
        ) ENGINE=InnoDB");
        
        // Create user_progress table
        $this->connection->exec("CREATE TABLE IF NOT EXISTS user_progress (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            video_id INT NOT NULL,
            progress_seconds INT DEFAULT 0,
            completed BOOLEAN DEFAULT FALSE,
            last_watched TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (video_id) REFERENCES videos(id),
            UNIQUE KEY unique_user_video (user_id, video_id),
            INDEX idx_user_progress (user_id, completed)
        ) ENGINE=InnoDB");
        
        // Create enrollments table
        $this->connection->exec("CREATE TABLE IF NOT EXISTS enrollments (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            course_id INT NOT NULL,
            enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            completed BOOLEAN DEFAULT FALSE,
            completion_date TIMESTAMP NULL,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (course_id) REFERENCES courses(id),
            UNIQUE KEY unique_enrollment (user_id, course_id),
            INDEX idx_user_enrollments (user_id, completed)
        ) ENGINE=InnoDB");
        
        // Create saved_videos table
        $this->connection->exec("CREATE TABLE IF NOT EXISTS saved_videos (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            video_id INT NOT NULL,
            saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (video_id) REFERENCES videos(id),
            UNIQUE KEY unique_saved_video (user_id, video_id),
            INDEX idx_user_saved (user_id)
        ) ENGINE=InnoDB");
    }
    
    private function createSQLiteTables() {
        // Create users table
        $this->connection->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL UNIQUE,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            full_name TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL,
            is_active INTEGER DEFAULT 1,
            role TEXT DEFAULT 'student'
        )");
        
        // Create courses table
        $this->connection->exec("CREATE TABLE IF NOT EXISTS courses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            description TEXT,
            instructor_id INTEGER NOT NULL,
            thumbnail_url TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status TEXT DEFAULT 'draft',
            FOREIGN KEY (instructor_id) REFERENCES users(id)
        )");
        
        // Create videos table
        $this->connection->exec("CREATE TABLE IF NOT EXISTS videos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            course_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            description TEXT,
            video_url TEXT NOT NULL,
            duration INTEGER NOT NULL,
            order_index INTEGER NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (course_id) REFERENCES courses(id)
        )");
        
        // Create user_progress table
        $this->connection->exec("CREATE TABLE IF NOT EXISTS user_progress (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            video_id INTEGER NOT NULL,
            progress_seconds INTEGER DEFAULT 0,
            completed INTEGER DEFAULT 0,
            last_watched TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (video_id) REFERENCES videos(id),
            UNIQUE (user_id, video_id)
        )");
        
        // Create enrollments table
        $this->connection->exec("CREATE TABLE IF NOT EXISTS enrollments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            course_id INTEGER NOT NULL,
            enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            completed INTEGER DEFAULT 0,
            completion_date TIMESTAMP NULL,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (course_id) REFERENCES courses(id),
            UNIQUE (user_id, course_id)
        )");
        
        // Create saved_videos table
        $this->connection->exec("CREATE TABLE IF NOT EXISTS saved_videos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            video_id INTEGER NOT NULL,
            saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (video_id) REFERENCES videos(id),
            UNIQUE (user_id, video_id)
        )");
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function getDatabaseType() {
        return $this->dbType;
    }
    
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    public function commit() {
        return $this->connection->commit();
    }
    
    public function rollBack() {
        return $this->connection->rollBack();
    }
}
