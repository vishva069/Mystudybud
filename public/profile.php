<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Auth.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$userId = $_SESSION['user_id'];
$auth = new Auth();
$user = $auth->getCurrentUser();

if (!$user) {
    header('Location: error.php?message=User not found');
    exit;
}

// Ensure bio and profile_image fields exist
if (!isset($user['bio'])) {
    $user['bio'] = '';
}

if (!isset($user['profile_image'])) {
    $user['profile_image'] = '';
}

$db = Database::getInstance()->getConnection();

// Get user's course enrollments
try {
    $stmt = $db->prepare("
        SELECT c.*, e.enrolled_at
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.user_id = :user_id
        ORDER BY e.enrolled_at DESC
        LIMIT 5
    ");
    $stmt->execute([':user_id' => $userId]);
    $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $enrollments = [];
}

// Get user's recent activity
try {
    $stmt = $db->prepare("
        SELECT h.*, v.title as video_title, c.title as course_title, c.id as course_id
        FROM history h
        JOIN videos v ON h.video_id = v.id
        JOIN courses c ON v.course_id = c.id
        WHERE h.user_id = :user_id
        ORDER BY h.viewed_at DESC
        LIMIT 5
    ");
    $stmt->execute([':user_id' => $userId]);
    $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recentActivity = [];
}

// Process profile update
$success = '';
$error = '';

// Process tutor application
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['become_tutor'])) {
    try {
        if ($auth->updateUserToTutor($userId)) {
            $success = 'Congratulations! You are now a tutor. You can create courses and upload content.';
            // Refresh user data
            $user = $auth->getUserById($userId);
        } else {
            $error = 'Failed to update your role to tutor';
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fullName = filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING);
    $bio = filter_input(INPUT_POST, 'bio', FILTER_SANITIZE_STRING);
    
    try {
        $stmt = $db->prepare("
            UPDATE users 
            SET full_name = :full_name, bio = :bio
            WHERE id = :user_id
        ");
        
        if ($stmt->execute([
            ':full_name' => $fullName,
            ':bio' => $bio,
            ':user_id' => $userId
        ])) {
            $success = 'Profile updated successfully';
            // Update session data
            $_SESSION['full_name'] = $fullName;
            // Refresh user data
            $user = $auth->getUserById($userId);
        } else {
            $error = 'Failed to update profile';
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
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
    <title>My Profile - StudyBud</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/custom.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container py-5">
        <div class="row">
            <!-- Profile Sidebar -->
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <?php if (!empty($user['profile_image'])): ?>
                                <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" class="rounded-circle img-thumbnail" style="width: 150px; height: 150px; object-fit: cover;">
                            <?php else: ?>
                                <div class="mx-auto rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 150px; height: 150px; font-size: 4rem;">
                                    <?php echo strtoupper(substr($user['full_name'] ?? $user['username'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <h4><?php echo htmlspecialchars($user['full_name'] ?? ($user['username'] ?? 'User')); ?></h4>
                        <p class="text-muted">@<?php echo htmlspecialchars($user['username'] ?? 'username'); ?></p>
                        
                        <?php if (!empty($user['bio'])): ?>
                            <p><?php echo nl2br(htmlspecialchars($user['bio'])); ?></p>
                        <?php else: ?>
                            <p class="text-muted fst-italic">No bio provided</p>
                        <?php endif; ?>
                        
                        <div class="d-grid gap-2">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                                <i class="bi bi-pencil-square me-2"></i>Edit Profile
                            </button>
                            <a href="settings.php" class="btn btn-outline-secondary">
                                <i class="bi bi-gear me-2"></i>Account Settings
                            </a>
                            <?php if (isset($user['role']) && $user['role'] === 'student'): ?>
                            <form method="POST" class="mt-2">
                                <button type="submit" name="become_tutor" class="btn btn-success w-100">
                                    <i class="bi bi-mortarboard me-2"></i>Become a Tutor
                                </button>
                            </form>
                            <?php elseif (isset($user['role']) && ($user['role'] === 'tutor' || $user['role'] === 'instructor' || $user['role'] === 'admin')): ?>
                            <a href="my-courses.php" class="btn btn-info mt-2 w-100">
                                <i class="bi bi-book me-2"></i>Manage My Courses
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-footer bg-light">
                        <div class="row text-center">
                            <div class="col">
                                <h5><?php echo count($enrollments); ?></h5>
                                <small class="text-muted">Courses</small>
                            </div>
                            <div class="col">
                                <h5><?php echo count($recentActivity); ?></h5>
                                <small class="text-muted">Videos</small>
                            </div>
                            <div class="col">
                                <h5><?php echo ucfirst($user['role'] ?? 'student'); ?></h5>
                                <small class="text-muted">Role</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Profile Content -->
            <div class="col-md-8">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <!-- My Courses -->
                <div class="card mb-4">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-book me-2"></i>My Courses</h5>
                        <a href="library.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($enrollments)): ?>
                            <div class="p-4 text-center">
                                <p class="text-muted mb-3">You haven't enrolled in any courses yet.</p>
                                <a href="courses.php" class="btn btn-primary">Browse Courses</a>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($enrollments as $course): ?>
                                    <a href="course.php?id=<?php echo $course['id']; ?>" class="list-group-item list-group-item-action">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0">
                                                <img src="<?php echo htmlspecialchars($course['thumbnail'] ?? 'assets/images/default-course.jpg'); ?>" alt="Course thumbnail" class="rounded" style="width: 60px; height: 60px; object-fit: cover;">
                                            </div>
                                            <div class="ms-3">
                                                <h6 class="mb-0"><?php echo htmlspecialchars($course['title']); ?></h6>
                                                <small class="text-muted">Enrolled: <?php echo date('M j, Y', strtotime($course['enrolled_at'])); ?></small>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="card mb-4">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Activity</h5>
                        <a href="history.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recentActivity)): ?>
                            <div class="p-4 text-center">
                                <p class="text-muted">No recent activity found.</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recentActivity as $activity): ?>
                                    <a href="video.php?id=<?php echo $activity['video_id']; ?>" class="list-group-item list-group-item-action">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-0"><?php echo htmlspecialchars($activity['video_title']); ?></h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($activity['course_title']); ?></small>
                                            </div>
                                            <small class="text-muted"><?php echo timeAgo($activity['viewed_at']); ?></small>
                                        </div>
                                        <div class="progress mt-2" style="height: 5px;">
                                            <div class="progress-bar" role="progressbar" style="width: <?php echo $activity['progress']; ?>%"></div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Achievements -->
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="bi bi-trophy me-2"></i>Achievements</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 text-center">
                            <div class="col-md-4">
                                <div class="p-3 border rounded">
                                    <div class="display-4 text-primary mb-2">
                                        <i class="bi bi-mortarboard"></i>
                                    </div>
                                    <h6>Course Completer</h6>
                                    <p class="small text-muted mb-0">Complete your first course</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 border rounded">
                                    <div class="display-4 text-secondary mb-2">
                                        <i class="bi bi-calendar-check"></i>
                                    </div>
                                    <h6>Regular Learner</h6>
                                    <p class="small text-muted mb-0">Study for 7 days in a row</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 border rounded">
                                    <div class="display-4 text-muted mb-2">
                                        <i class="bi bi-star"></i>
                                    </div>
                                    <h6>First Review</h6>
                                    <p class="small text-muted mb-0">Rate your first course</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Profile Modal -->
    <div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editProfileModalLabel">Edit Profile</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="bio" class="form-label">Bio</label>
                            <textarea class="form-control" id="bio" name="bio" rows="4"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                            <div class="form-text">Tell us a bit about yourself</div>
                        </div>
                        <div class="mb-3">
                            <label for="profile_image" class="form-label">Profile Image</label>
                            <input type="file" class="form-control" id="profile_image" name="profile_image" disabled>
                            <div class="form-text">Image upload functionality coming soon</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_profile" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
