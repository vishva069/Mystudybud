<?php
require_once __DIR__ . '/../config/database.php';

class Course {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->initializeTables();
    }
    
    /**
     * Initialize necessary tables if they don't exist (for SQLite)
     */
    private function initializeTables() {
        if (Database::getInstance()->getDatabaseType() === 'sqlite') {
            // Create courses table
            $this->db->exec("CREATE TABLE IF NOT EXISTS courses (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                description TEXT,
                instructor_id INTEGER NOT NULL,
                thumbnail TEXT,
                category TEXT,
                level TEXT,
                price REAL DEFAULT 0,
                is_featured INTEGER DEFAULT 0,
                is_published INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (instructor_id) REFERENCES users(id)
            )");
            
            // Create videos table
            $this->db->exec("CREATE TABLE IF NOT EXISTS videos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                course_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                description TEXT,
                video_url TEXT NOT NULL,
                duration INTEGER,
                position INTEGER DEFAULT 0,
                is_free INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                uploaded_by INTEGER NOT NULL,
                FOREIGN KEY (course_id) REFERENCES courses(id),
                FOREIGN KEY (uploaded_by) REFERENCES users(id)
            )");
            
            // Create books table
            $this->db->exec("CREATE TABLE IF NOT EXISTS books (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                course_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                description TEXT,
                file_path TEXT NOT NULL,
                file_size INTEGER NOT NULL,
                position INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                uploaded_by INTEGER NOT NULL,
                FOREIGN KEY (course_id) REFERENCES courses(id),
                FOREIGN KEY (uploaded_by) REFERENCES users(id)
            )");
            
            // Create enrollments table
            $this->db->exec("CREATE TABLE IF NOT EXISTS enrollments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                course_id INTEGER NOT NULL,
                enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (course_id) REFERENCES courses(id),
                UNIQUE(user_id, course_id)
            )");
            
            // Create video_progress table
            $this->db->exec("CREATE TABLE IF NOT EXISTS video_progress (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                video_id INTEGER NOT NULL,
                position INTEGER DEFAULT 0,
                is_completed INTEGER DEFAULT 0,
                last_watched TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (video_id) REFERENCES videos(id),
                UNIQUE(user_id, video_id)
            )");
            
            // Create saved_videos table
            $this->db->exec("CREATE TABLE IF NOT EXISTS saved_videos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                video_id INTEGER NOT NULL,
                saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (video_id) REFERENCES videos(id),
                UNIQUE(user_id, video_id)
            )");
        }
    }
    
    /**
     * Get all courses with pagination
     */
    public function getAllCourses($page = 1, $limit = 10, $category = null, $search = null) {
        $offset = ($page - 1) * $limit;
        $params = [];
        
        $sql = "SELECT c.*, u.username as instructor_name, 
                (SELECT COUNT(*) FROM videos WHERE course_id = c.id) as video_count 
                FROM courses c
                JOIN users u ON c.instructor_id = u.id
                WHERE c.is_published = 1";
        
        if ($category) {
            $sql .= " AND c.category = :category";
            $params[':category'] = $category;
        }
        
        if ($search) {
            $sql .= " AND (c.title LIKE :search OR c.description LIKE :search)";
            $params[':search'] = "%$search%";
        }
        
        $sql .= " ORDER BY c.is_featured DESC, c.created_at DESC
                 LIMIT :limit OFFSET :offset";
        
        try {
            $stmt = $this->db->prepare($sql);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get courses error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get total course count
     */
    public function getCourseCount($category = null, $search = null) {
        $params = [];
        $sql = "SELECT COUNT(*) FROM courses WHERE is_published = 1";
        
        if ($category) {
            $sql .= " AND category = :category";
            $params[':category'] = $category;
        }
        
        if ($search) {
            $sql .= " AND (title LIKE :search OR description LIKE :search)";
            $params[':search'] = "%$search%";
        }
        
        try {
            $stmt = $this->db->prepare($sql);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Get course count error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get featured courses
     */
    public function getFeaturedCourses($limit = 5) {
        try {
            $stmt = $this->db->prepare(
                "SELECT c.*, u.username as instructor_name, 
                (SELECT COUNT(*) FROM videos WHERE course_id = c.id) as video_count 
                FROM courses c
                JOIN users u ON c.instructor_id = u.id
                WHERE c.is_published = 1 AND c.is_featured = 1
                ORDER BY c.created_at DESC
                LIMIT :limit"
            );
            
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get featured courses error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get courses by instructor ID
     */
    public function getCoursesByInstructor($instructorId) {
        try {
            $stmt = $this->db->prepare(
                "SELECT c.*, 
                (SELECT COUNT(*) FROM videos WHERE course_id = c.id) as video_count,
                (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as enrollment_count
                FROM courses c
                WHERE c.instructor_id = :instructor_id
                ORDER BY c.created_at DESC"
            );
            
            $stmt->execute([':instructor_id' => $instructorId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get instructor courses error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Create a new course
     */
    public function createCourse($title, $description, $instructorId, $category = null, $level = null) {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO courses (title, description, instructor_id, status) 
                 VALUES (:title, :description, :instructor_id, 'draft')"
            );
            
            $stmt->execute([
                ':title' => $title,
                ':description' => $description,
                ':instructor_id' => $instructorId
            ]);
            
            $courseId = $this->db->lastInsertId();
            
            // Log the successful course creation
            error_log("Course created successfully with ID: " . $courseId);
            
            return $courseId;
        } catch (PDOException $e) {
            error_log("Create course error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update a course
     */
    public function updateCourse($courseId, $data) {
        try {
            $fields = [];
            $params = [':id' => $courseId];
            
            foreach ($data as $key => $value) {
                if ($key !== 'id') {
                    $fields[] = "`$key` = :$key";
                    $params[":$key"] = $value;
                }
            }
            
            $sql = "UPDATE courses SET " . implode(', ', $fields) . " WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Update course error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get videos for a course
     */
    public function getVideos($courseId) {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM videos 
                 WHERE course_id = :course_id 
                 ORDER BY position ASC, created_at ASC"
            );
            
            $stmt->execute([':course_id' => $courseId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get videos error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get books for a course
     */
    public function getBooks($courseId) {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM books 
                 WHERE course_id = :course_id 
                 ORDER BY position ASC, created_at ASC"
            );
            
            $stmt->execute([':course_id' => $courseId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get books error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Add a video to a course
     */
    public function addVideo($courseId, $title, $description, $videoUrl, $duration, $position, $uploadedBy) {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO videos (course_id, title, description, video_url, duration, position, uploaded_by) 
                 VALUES (:course_id, :title, :description, :video_url, :duration, :position, :uploaded_by)"
            );
            
            $stmt->execute([
                ':course_id' => $courseId,
                ':title' => $title,
                ':description' => $description,
                ':video_url' => $videoUrl,
                ':duration' => $duration,
                ':position' => $position,
                ':uploaded_by' => $uploadedBy
            ]);
            
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Add video error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add a book to a course
     */
    public function addBook($courseId, $title, $description, $filePath, $fileSize, $position, $uploadedBy) {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO books (course_id, title, description, file_path, file_size, position, uploaded_by) 
                 VALUES (:course_id, :title, :description, :file_path, :file_size, :position, :uploaded_by)"
            );
            
            $stmt->execute([
                ':course_id' => $courseId,
                ':title' => $title,
                ':description' => $description,
                ':file_path' => $filePath,
                ':file_size' => $fileSize,
                ':position' => $position,
                ':uploaded_by' => $uploadedBy
            ]);
            
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Add book error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete a video
     */
    public function deleteVideo($videoId, $courseId, $userId) {
        try {
            // Get video file path first
            $stmt = $this->db->prepare(
                "SELECT video_url FROM videos 
                 WHERE id = :id AND course_id = :course_id AND uploaded_by = :uploaded_by"
            );
            
            $stmt->execute([
                ':id' => $videoId,
                ':course_id' => $courseId,
                ':uploaded_by' => $userId
            ]);
            
            $video = $stmt->fetch();
            
            if (!$video) {
                return false;
            }
            
            // Delete the video file if it exists
            if (file_exists($video['video_url'])) {
                unlink($video['video_url']);
            }
            
            // Delete from database
            $stmt = $this->db->prepare(
                "DELETE FROM videos 
                 WHERE id = :id AND course_id = :course_id AND uploaded_by = :uploaded_by"
            );
            
            return $stmt->execute([
                ':id' => $videoId,
                ':course_id' => $courseId,
                ':uploaded_by' => $userId
            ]);
        } catch (PDOException $e) {
            error_log("Delete video error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete a book
     */
    public function deleteBook($bookId, $courseId, $userId) {
        try {
            // Get book file path first
            $stmt = $this->db->prepare(
                "SELECT file_path FROM books 
                 WHERE id = :id AND course_id = :course_id AND uploaded_by = :uploaded_by"
            );
            
            $stmt->execute([
                ':id' => $bookId,
                ':course_id' => $courseId,
                ':uploaded_by' => $userId
            ]);
            
            $book = $stmt->fetch();
            
            if (!$book) {
                return false;
            }
            
            // Delete the book file if it exists
            if (file_exists($book['file_path'])) {
                unlink($book['file_path']);
            }
            
            // Delete from database
            $stmt = $this->db->prepare(
                "DELETE FROM books 
                 WHERE id = :id AND course_id = :course_id AND uploaded_by = :uploaded_by"
            );
            
            return $stmt->execute([
                ':id' => $bookId,
                ':course_id' => $courseId,
                ':uploaded_by' => $userId
            ]);
        } catch (PDOException $e) {
            error_log("Delete book error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get course by ID
     */
    public function getCourseById($id, $includeUnpublished = false) {
        try {
            $sql = "SELECT c.*, u.username as instructor_name, u.full_name as instructor_full_name,
                (SELECT COUNT(*) FROM videos WHERE course_id = c.id) as video_count,
                (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as enrollment_count
                FROM courses c
                JOIN users u ON c.instructor_id = u.id
                WHERE c.id = :id";
                
            if (!$includeUnpublished) {
                $sql .= " AND c.is_published = 1";
            }
            
            $stmt = $this->db->prepare($sql);
            
            $stmt->execute([':id' => $id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Get course error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get videos for a course
     */
    public function getCourseVideos($courseId) {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM videos 
                WHERE course_id = :course_id
                ORDER BY position ASC"
            );
            
            $stmt->execute([':course_id' => $courseId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get course videos error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if user is enrolled in a course
     */
    public function isUserEnrolled($userId, $courseId) {
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM enrollments 
                WHERE user_id = :user_id AND course_id = :course_id"
            );
            
            $stmt->execute([
                ':user_id' => $userId,
                ':course_id' => $courseId
            ]);
            
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log("Check enrollment error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Enroll user in a course
     */
    public function enrollUser($userId, $courseId) {
        try {
            // Check if already enrolled
            if ($this->isUserEnrolled($userId, $courseId)) {
                return true;
            }
            
            $stmt = $this->db->prepare(
                "INSERT INTO enrollments (user_id, course_id) 
                VALUES (:user_id, :course_id)"
            );
            
            return $stmt->execute([
                ':user_id' => $userId,
                ':course_id' => $courseId
            ]);
        } catch (PDOException $e) {
            error_log("Enroll user error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get enrolled courses for a user
     */
    public function getUserEnrollments($userId, $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        
        try {
            $stmt = $this->db->prepare(
                "SELECT c.*, e.enrolled_at,
                (SELECT COUNT(*) FROM videos WHERE course_id = c.id) as total_videos,
                (SELECT COUNT(*) FROM videos v 
                 JOIN video_progress vp ON v.id = vp.video_id 
                 WHERE v.course_id = c.id AND vp.user_id = :user_id AND vp.is_completed = 1) as completed_videos
                FROM enrollments e
                JOIN courses c ON e.course_id = c.id
                WHERE e.user_id = :user_id
                ORDER BY e.enrolled_at DESC
                LIMIT :limit OFFSET :offset"
            );
            
            $stmt->bindValue(':user_id', $userId);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get user enrollments error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get user enrollment count
     */
    public function getUserEnrollmentCount($userId) {
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM enrollments WHERE user_id = :user_id"
            );
            
            $stmt->execute([':user_id' => $userId]);
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Get enrollment count error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Update video progress
     */
    public function updateVideoProgress($userId, $videoId, $position, $isCompleted = 0) {
        try {
            // Check if progress record exists
            $stmt = $this->db->prepare(
                "SELECT id FROM video_progress 
                WHERE user_id = :user_id AND video_id = :video_id"
            );
            
            $stmt->execute([
                ':user_id' => $userId,
                ':video_id' => $videoId
            ]);
            
            $progressId = $stmt->fetchColumn();
            
            if ($progressId) {
                // Update existing record
                $stmt = $this->db->prepare(
                    "UPDATE video_progress 
                    SET position = :position, is_completed = :is_completed, last_watched = CURRENT_TIMESTAMP
                    WHERE id = :id"
                );
                
                return $stmt->execute([
                    ':position' => $position,
                    ':is_completed' => $isCompleted,
                    ':id' => $progressId
                ]);
            } else {
                // Create new record
                $stmt = $this->db->prepare(
                    "INSERT INTO video_progress (user_id, video_id, position, is_completed) 
                    VALUES (:user_id, :video_id, :position, :is_completed)"
                );
                
                return $stmt->execute([
                    ':user_id' => $userId,
                    ':video_id' => $videoId,
                    ':position' => $position,
                    ':is_completed' => $isCompleted
                ]);
            }
        } catch (PDOException $e) {
            error_log("Update video progress error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get video progress
     */
    public function getVideoProgress($userId, $videoId) {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM video_progress 
                WHERE user_id = :user_id AND video_id = :video_id"
            );
            
            $stmt->execute([
                ':user_id' => $userId,
                ':video_id' => $videoId
            ]);
            
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Get video progress error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get video by ID
     */
    public function getVideoById($id) {
        try {
            $stmt = $this->db->prepare(
                "SELECT v.*, c.title as course_title, c.id as course_id
                FROM videos v
                JOIN courses c ON v.course_id = c.id
                WHERE v.id = :id"
            );
            
            $stmt->execute([':id' => $id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Get video error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Save video for later
     */
    public function saveVideo($userId, $videoId) {
        try {
            // Check if already saved
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM saved_videos 
                WHERE user_id = :user_id AND video_id = :video_id"
            );
            
            $stmt->execute([
                ':user_id' => $userId,
                ':video_id' => $videoId
            ]);
            
            if ($stmt->fetchColumn() > 0) {
                return true;
            }
            
            $stmt = $this->db->prepare(
                "INSERT INTO saved_videos (user_id, video_id) 
                VALUES (:user_id, :video_id)"
            );
            
            return $stmt->execute([
                ':user_id' => $userId,
                ':video_id' => $videoId
            ]);
        } catch (PDOException $e) {
            error_log("Save video error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Unsave video
     */
    public function unsaveVideo($userId, $videoId) {
        try {
            $stmt = $this->db->prepare(
                "DELETE FROM saved_videos 
                WHERE user_id = :user_id AND video_id = :video_id"
            );
            
            return $stmt->execute([
                ':user_id' => $userId,
                ':video_id' => $videoId
            ]);
        } catch (PDOException $e) {
            error_log("Unsave video error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get saved videos for a user
     */
    public function getSavedVideos($userId, $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        
        try {
            $stmt = $this->db->prepare(
                "SELECT v.*, c.title as course_title, c.id as course_id, sv.saved_at
                FROM saved_videos sv
                JOIN videos v ON sv.video_id = v.id
                JOIN courses c ON v.course_id = c.id
                WHERE sv.user_id = :user_id
                ORDER BY sv.saved_at DESC
                LIMIT :limit OFFSET :offset"
            );
            
            $stmt->bindValue(':user_id', $userId);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get saved videos error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get saved video count
     */
    public function getSavedVideoCount($userId) {
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM saved_videos WHERE user_id = :user_id"
            );
            
            $stmt->execute([':user_id' => $userId]);
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Get saved video count error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Check if video is saved
     */
    public function isVideoSaved($userId, $videoId) {
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM saved_videos 
                WHERE user_id = :user_id AND video_id = :video_id"
            );
            
            $stmt->execute([
                ':user_id' => $userId,
                ':video_id' => $videoId
            ]);
            
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log("Check saved video error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get categories
     */
    public function getCategories() {
        try {
            $stmt = $this->db->prepare(
                "SELECT DISTINCT category FROM courses WHERE category IS NOT NULL AND category != ''"
            );
            
            $stmt->execute();
            $categories = [];
            
            while ($row = $stmt->fetch()) {
                $categories[] = $row['category'];
            }
            
            return $categories;
        } catch (PDOException $e) {
            error_log("Get categories error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Create a course with full data array
     */
    public function createCourseFromData($data) {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO courses (
                    title, description, instructor_id, thumbnail, 
                    category, level, price, is_featured, is_published
                ) VALUES (
                    :title, :description, :instructor_id, :thumbnail,
                    :category, :level, :price, :is_featured, :is_published
                )"
            );
            
            $stmt->execute([
                ':title' => $data['title'],
                ':description' => $data['description'],
                ':instructor_id' => $data['instructor_id'],
                ':thumbnail' => $data['thumbnail'] ?? null,
                ':category' => $data['category'] ?? null,
                ':level' => $data['level'] ?? 'Beginner',
                ':price' => $data['price'] ?? 0,
                ':is_featured' => $data['is_featured'] ?? 0,
                ':is_published' => $data['is_published'] ?? 0
            ]);
            
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Create course error: " . $e->getMessage());
            throw new Exception("Failed to create course");
        }
    }
    
    /**
     * Add a video to a course from data array
     */
    public function addVideoFromData($data) {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO videos (
                    course_id, title, description, video_url, 
                    duration, position, is_free
                ) VALUES (
                    :course_id, :title, :description, :video_url,
                    :duration, :position, :is_free
                )"
            );
            
            $stmt->execute([
                ':course_id' => $data['course_id'],
                ':title' => $data['title'],
                ':description' => $data['description'] ?? null,
                ':video_url' => $data['video_url'],
                ':duration' => $data['duration'] ?? null,
                ':position' => $data['position'] ?? 0,
                ':is_free' => $data['is_free'] ?? 0
            ]);
            
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Add video error: " . $e->getMessage());
            throw new Exception("Failed to add video");
        }
    }
}
