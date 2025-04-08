<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Auth.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$db = Database::getInstance()->getConnection();

// Get user's viewing history
try {
    $stmt = $db->prepare("
        SELECT h.*, v.title as video_title, v.thumbnail, c.title as course_title, c.id as course_id
        FROM history h
        JOIN videos v ON h.video_id = v.id
        JOIN courses c ON v.course_id = c.id
        WHERE h.user_id = :user_id
        ORDER BY h.viewed_at DESC
        LIMIT 50
    ");
    $stmt->execute([':user_id' => $userId]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $history = [];
}

// Helper function to format time
function timeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->d > 7) {
        return date('M j, Y', strtotime($datetime));
    } elseif ($diff->d > 0) {
        return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    } elseif ($diff->h > 0) {
        return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    } elseif ($diff->i > 0) {
        return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    } else {
        return 'Just now';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Viewing History - StudyBud</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .video-thumbnail {
            width: 120px;
            height: 68px;
            object-fit: cover;
        }
        
        .history-item {
            transition: all 0.2s ease;
        }
        
        .history-item:hover {
            background-color: #f8f9fa;
        }
        
        /* Fix for button hover color */
        .btn-primary:hover, .btn-outline-primary:hover {
            color: #000 !important;
        }
        
        .btn-success:hover, .btn-outline-success:hover {
            color: #000 !important;
        }
        
        .btn-info:hover, .btn-outline-info:hover {
            color: #000 !important;
        }
        
        .btn-warning:hover, .btn-outline-warning:hover {
            color: #000 !important;
        }
        
        .btn-danger:hover, .btn-outline-danger:hover {
            color: #000 !important;
        }
        
        .btn-secondary:hover, .btn-outline-secondary:hover {
            color: #fff !important;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-clock-history me-2"></i>Viewing History</h1>
            <div>
                <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#clearHistoryModal">
                    <i class="bi bi-trash me-1"></i>Clear History
                </button>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (empty($history)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>Your viewing history is empty. Start watching videos to see them here!
            </div>
            <div class="text-center mt-4">
                <a href="courses.php" class="btn btn-primary">Browse Courses</a>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="list-group list-group-flush">
                    <?php foreach ($history as $item): ?>
                        <div class="list-group-item history-item p-3">
                            <div class="row align-items-center">
                                <div class="col-md-1 text-center">
                                    <img src="<?php echo htmlspecialchars($item['thumbnail'] ?? 'assets/img/default-thumbnail.jpg'); ?>" 
                                         class="video-thumbnail rounded" alt="Video thumbnail">
                                </div>
                                <div class="col-md-8">
                                    <h5 class="mb-1"><?php echo htmlspecialchars($item['video_title']); ?></h5>
                                    <p class="mb-0 text-muted">
                                        <small>
                                            <i class="bi bi-book me-1"></i>
                                            <a href="course.php?id=<?php echo $item['course_id']; ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($item['course_title']); ?>
                                            </a>
                                        </small>
                                    </p>
                                </div>
                                <div class="col-md-2 text-end">
                                    <small class="text-muted">
                                        <i class="bi bi-clock me-1"></i><?php echo timeAgo($item['viewed_at']); ?>
                                    </small>
                                </div>
                                <div class="col-md-1 text-end">
                                    <a href="video.php?id=<?php echo $item['video_id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-play-fill"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Clear History Modal -->
    <div class="modal fade" id="clearHistoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Clear Viewing History</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to clear your entire viewing history? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form action="clear_history.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                        <button type="submit" class="btn btn-danger">Clear History</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
