<?php
require_once __DIR__ . '/../config/database.php';

// Simple security check - delete this file after use
$allowed_ips = ['127.0.0.1', '::1', $_SERVER['REMOTE_ADDR']];
if (!in_array($_SERVER['REMOTE_ADDR'], $allowed_ips)) {
    die("Access denied");
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Create history table if it doesn't exist
    $db->exec("CREATE TABLE IF NOT EXISTS history (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        video_id INTEGER NOT NULL,
        viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        progress INTEGER DEFAULT 0,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (video_id) REFERENCES videos(id)
    )");
    
    // Create videos table if it doesn't exist
    $db->exec("CREATE TABLE IF NOT EXISTS videos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        course_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        description TEXT,
        video_url TEXT NOT NULL,
        thumbnail TEXT,
        duration INTEGER,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (course_id) REFERENCES courses(id)
    )");
    
    // Create courses table if it doesn't exist
    $db->exec("CREATE TABLE IF NOT EXISTS courses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        instructor_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        description TEXT,
        thumbnail TEXT,
        price REAL DEFAULT 0,
        is_published BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (instructor_id) REFERENCES users(id)
    )");
    
    // Create bookmarks table if it doesn't exist
    $db->exec("CREATE TABLE IF NOT EXISTS bookmarks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        video_id INTEGER NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (video_id) REFERENCES videos(id)
    )");
    
    // Create enrollments table if it doesn't exist
    $db->exec("CREATE TABLE IF NOT EXISTS enrollments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        course_id INTEGER NOT NULL,
        enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (course_id) REFERENCES courses(id)
    )");
    
    // Insert dummy users if not already present
    $checkUsers = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($checkUsers < 5) {
        // Insert admin user
        $hashedPassword = password_hash('admin123' . PASSWORD_PEPPER, PASSWORD_ARGON2ID);
        $db->exec("INSERT INTO users (email, username, password_hash, full_name, role, is_active) 
                  VALUES ('admin@studybud.com', 'admin', '$hashedPassword', 'Admin User', 'admin', 1)");
        
        // Insert regular users
        $hashedPassword = password_hash('user123' . PASSWORD_PEPPER, PASSWORD_ARGON2ID);
        $db->exec("INSERT INTO users (email, username, password_hash, full_name, role, is_active) 
                  VALUES ('john@example.com', 'john_doe', '$hashedPassword', 'John Doe', 'user', 1)");
        
        $db->exec("INSERT INTO users (email, username, password_hash, full_name, role, is_active) 
                  VALUES ('jane@example.com', 'jane_smith', '$hashedPassword', 'Jane Smith', 'user', 1)");
        
        $db->exec("INSERT INTO users (email, username, password_hash, full_name, role, is_active) 
                  VALUES ('bob@example.com', 'bob_johnson', '$hashedPassword', 'Bob Johnson', 'user', 1)");
        
        $db->exec("INSERT INTO users (email, username, password_hash, full_name, role, is_active) 
                  VALUES ('alice@example.com', 'alice_wong', '$hashedPassword', 'Alice Wong', 'user', 0)");
        
        echo "<p>Added dummy users</p>";
    }
    
    // Insert dummy courses if not already present
    $checkCourses = $db->query("SELECT COUNT(*) FROM courses")->fetchColumn();
    if ($checkCourses < 3) {
        $db->exec("INSERT INTO courses (instructor_id, title, description, thumbnail, price, is_published) 
                  VALUES (1, 'Introduction to Web Development', 'Learn the basics of HTML, CSS, and JavaScript', 'assets/images/courses/web-dev.jpg', 49.99, 1)");
        
        $db->exec("INSERT INTO courses (instructor_id, title, description, thumbnail, price, is_published) 
                  VALUES (1, 'Python Programming for Beginners', 'Start your journey with Python programming', 'assets/images/courses/python.jpg', 39.99, 1)");
        
        $db->exec("INSERT INTO courses (instructor_id, title, description, thumbnail, price, is_published) 
                  VALUES (1, 'Data Science Fundamentals', 'Learn the basics of data analysis and visualization', 'assets/images/courses/data-science.jpg', 59.99, 1)");
        
        echo "<p>Added dummy courses</p>";
    }
    
    // Insert dummy videos if not already present
    $checkVideos = $db->query("SELECT COUNT(*) FROM videos")->fetchColumn();
    if ($checkVideos < 10) {
        // Web Development course videos
        $db->exec("INSERT INTO videos (course_id, title, description, video_url, thumbnail, duration) 
                  VALUES (1, 'HTML Basics', 'Introduction to HTML tags and structure', 'https://example.com/videos/html-basics', 'assets/images/videos/html.jpg', 1800)");
        
        $db->exec("INSERT INTO videos (course_id, title, description, video_url, thumbnail, duration) 
                  VALUES (1, 'CSS Styling', 'Learn how to style your web pages', 'https://example.com/videos/css-styling', 'assets/images/videos/css.jpg', 2400)");
        
        $db->exec("INSERT INTO videos (course_id, title, description, video_url, thumbnail, duration) 
                  VALUES (1, 'JavaScript Fundamentals', 'Introduction to JavaScript programming', 'https://example.com/videos/js-fundamentals', 'assets/images/videos/javascript.jpg', 3000)");
        
        // Python course videos
        $db->exec("INSERT INTO videos (course_id, title, description, video_url, thumbnail, duration) 
                  VALUES (2, 'Python Installation', 'Setting up your Python environment', 'https://example.com/videos/python-install', 'assets/images/videos/python-setup.jpg', 1200)");
        
        $db->exec("INSERT INTO videos (course_id, title, description, video_url, thumbnail, duration) 
                  VALUES (2, 'Python Variables', 'Understanding variables and data types', 'https://example.com/videos/python-variables', 'assets/images/videos/python-vars.jpg', 1800)");
        
        $db->exec("INSERT INTO videos (course_id, title, description, video_url, thumbnail, duration) 
                  VALUES (2, 'Python Functions', 'Creating and using functions', 'https://example.com/videos/python-functions', 'assets/images/videos/python-func.jpg', 2100)");
        
        // Data Science course videos
        $db->exec("INSERT INTO videos (course_id, title, description, video_url, thumbnail, duration) 
                  VALUES (3, 'Introduction to Data Analysis', 'Overview of data analysis process', 'https://example.com/videos/data-analysis-intro', 'assets/images/videos/data-analysis.jpg', 2700)");
        
        $db->exec("INSERT INTO videos (course_id, title, description, video_url, thumbnail, duration) 
                  VALUES (3, 'Data Visualization with Python', 'Creating charts and graphs', 'https://example.com/videos/data-viz', 'assets/images/videos/data-viz.jpg', 3300)");
        
        $db->exec("INSERT INTO videos (course_id, title, description, video_url, thumbnail, duration) 
                  VALUES (3, 'Statistical Analysis Basics', 'Understanding statistical concepts', 'https://example.com/videos/stats-basics', 'assets/images/videos/stats.jpg', 2400)");
        
        echo "<p>Added dummy videos</p>";
    }
    
    // Insert dummy enrollments if not already present
    $checkEnrollments = $db->query("SELECT COUNT(*) FROM enrollments")->fetchColumn();
    if ($checkEnrollments < 5) {
        $db->exec("INSERT INTO enrollments (user_id, course_id) VALUES (2, 1)");
        $db->exec("INSERT INTO enrollments (user_id, course_id) VALUES (2, 2)");
        $db->exec("INSERT INTO enrollments (user_id, course_id) VALUES (3, 1)");
        $db->exec("INSERT INTO enrollments (user_id, course_id) VALUES (3, 3)");
        $db->exec("INSERT INTO enrollments (user_id, course_id) VALUES (4, 2)");
        
        echo "<p>Added dummy enrollments</p>";
    }
    
    // Insert dummy history data if not already present
    $checkHistory = $db->query("SELECT COUNT(*) FROM history")->fetchColumn();
    if ($checkHistory < 10) {
        // User 2 history
        $db->exec("INSERT INTO history (user_id, video_id, viewed_at, progress) 
                  VALUES (2, 1, datetime('now', '-2 days'), 100)");
        
        $db->exec("INSERT INTO history (user_id, video_id, viewed_at, progress) 
                  VALUES (2, 2, datetime('now', '-1 day'), 75)");
        
        $db->exec("INSERT INTO history (user_id, video_id, viewed_at, progress) 
                  VALUES (2, 4, datetime('now', '-5 hours'), 50)");
        
        // User 3 history
        $db->exec("INSERT INTO history (user_id, video_id, viewed_at, progress) 
                  VALUES (3, 1, datetime('now', '-3 days'), 100)");
        
        $db->exec("INSERT INTO history (user_id, video_id, viewed_at, progress) 
                  VALUES (3, 7, datetime('now', '-1 day'), 60)");
        
        $db->exec("INSERT INTO history (user_id, video_id, viewed_at, progress) 
                  VALUES (3, 8, datetime('now', '-2 hours'), 25)");
        
        // User 4 history
        $db->exec("INSERT INTO history (user_id, video_id, viewed_at, progress) 
                  VALUES (4, 4, datetime('now', '-4 days'), 100)");
        
        $db->exec("INSERT INTO history (user_id, video_id, viewed_at, progress) 
                  VALUES (4, 5, datetime('now', '-2 days'), 100)");
        
        $db->exec("INSERT INTO history (user_id, video_id, viewed_at, progress) 
                  VALUES (4, 6, datetime('now', '-1 day'), 80)");
        
        echo "<p>Added dummy history data</p>";
    }
    
    // Insert dummy bookmarks if not already present
    $checkBookmarks = $db->query("SELECT COUNT(*) FROM bookmarks")->fetchColumn();
    if ($checkBookmarks < 5) {
        $db->exec("INSERT INTO bookmarks (user_id, video_id) VALUES (2, 3)");
        $db->exec("INSERT INTO bookmarks (user_id, video_id) VALUES (2, 5)");
        $db->exec("INSERT INTO bookmarks (user_id, video_id) VALUES (3, 2)");
        $db->exec("INSERT INTO bookmarks (user_id, video_id) VALUES (3, 9)");
        $db->exec("INSERT INTO bookmarks (user_id, video_id) VALUES (4, 7)");
        
        echo "<p>Added dummy bookmarks</p>";
    }
    
    echo "<div class='alert alert-success'>Successfully set up dummy data!</div>";
    echo "<p>You can now log in with the following credentials:</p>";
    echo "<ul>";
    echo "<li><strong>Admin:</strong> Username: admin, Password: admin123</li>";
    echo "<li><strong>User:</strong> Username: john_doe, Password: user123</li>";
    echo "<li><strong>User:</strong> Username: jane_smith, Password: user123</li>";
    echo "</ul>";
    echo "<p><a href='index.php' class='btn btn-primary'>Go to Homepage</a></p>";
    
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Database error: " . $e->getMessage() . "</div>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Dummy Data - StudyBud</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            padding: 20px;
            font-family: Arial, sans-serif;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        h1 {
            margin-bottom: 20px;
        }
        .alert {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>StudyBud - Setup Dummy Data</h1>
        <div class="alert alert-warning">
            <strong>Warning:</strong> This script is for development purposes only. Delete this file after use.
        </div>
    </div>
</body>
</html>
