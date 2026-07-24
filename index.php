<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/email.php';

startSession();
requireAdmin();

$pdo = getDatabaseConnection();
$emailService = new EmailService();
$message = '';
$messageType = 'info';

if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $messageType = $_SESSION['flash_message_type'] ?? 'info';
    unset($_SESSION['flash_message'], $_SESSION['flash_message_type']);
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'save') {
        $studentId = trim($_POST['student_id'] ?? '');
        if ($studentId === '') {
            $studentId = generateStudentId($pdo);
        }

        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $courseName = trim($_POST['course_name'] ?? '');
        $completionDate = trim($_POST['completion_date'] ?? '');
        $signedStatus = isset($_POST['signed_status']) ? 1 : 0;
        $collected = isset($_POST['collected']) ? 1 : 0;
        $collectedAt = $collected ? date('Y-m-d H:i:s') : null;
        $sendEmail = isset($_POST['send_email']) ? 1 : 0;
        $studentIdToEdit = isset($_POST['student_id_to_edit']) ? (int) $_POST['student_id_to_edit'] : 0;

        if ($studentIdToEdit > 0) {
            $stmt = $pdo->prepare('UPDATE students SET student_id = :student_id, full_name = :full_name, email = :email, course_name = :course_name, completion_date = :completion_date, signed_status = :signed_status, collected = :collected, collected_at = :collected_at WHERE id = :id');
            $stmt->execute([
                ':student_id' => $studentId,
                ':full_name' => $fullName,
                ':email' => $email,
                ':course_name' => $courseName,
                ':completion_date' => $completionDate,
                ':signed_status' => $signedStatus,
                ':collected' => $collected,
                ':collected_at' => $collectedAt,
                ':id' => $studentIdToEdit,
            ]);
            $message = 'Student updated successfully.';
            $messageType = 'success';
        } else {
            $stmt = $pdo->prepare('INSERT INTO students (student_id, full_name, email, course_name, completion_date, signed_status, collected, collected_at) VALUES (:student_id, :full_name, :email, :course_name, :completion_date, :signed_status, :collected, :collected_at)');
            $stmt->execute([
                ':student_id' => $studentId,
                ':full_name' => $fullName,
                ':email' => $email,
                ':course_name' => $courseName,
                ':completion_date' => $completionDate,
                ':signed_status' => $signedStatus,
                ':collected' => $collected,
                ':collected_at' => $collectedAt,
            ]);
            
            // Send notification email if not collected and email checkbox is checked
            if (!$collected && $sendEmail && $emailService->isConfigured()) {
                $stmt = $pdo->prepare('SELECT id FROM students WHERE student_id = :student_id');
                $stmt->execute([':student_id' => $studentId]);
                $newStudentId = $stmt->fetchColumn();
                
                if ($emailService->sendCertificateReadyNotification($fullName, $email, $courseName)) {
                    $stmt = $pdo->prepare('INSERT INTO email_logs (student_id, recipient_email, subject, status) VALUES (:student_id, :recipient_email, :subject, :status)');
                    $stmt->execute([
                        ':student_id' => $studentId,
                        ':recipient_email' => $email,
                        ':subject' => 'Your Certificate is Ready for Collection',
                        ':status' => 'sent',
                    ]);
                    $message .= ' Email notification sent to student.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO email_logs (student_id, recipient_email, subject, status, error_message) VALUES (:student_id, :recipient_email, :subject, :status, :error_message)');
                    $stmt->execute([
                        ':student_id' => $studentId,
                        ':recipient_email' => $email,
                        ':subject' => 'Your Certificate is Ready for Collection',
                        ':status' => 'failed',
                        ':error_message' => 'Email delivery failed',
                    ]);
                }
            }
            
            $message = 'Student added successfully.';
            $messageType = 'success';
        }
    } elseif ($action === 'mark_collected') {
        $studentIdToCollect = isset($_POST['student_id']) ? (int) $_POST['student_id'] : 0;
        if ($studentIdToCollect > 0) {
            $stmt = $pdo->prepare('UPDATE students SET collected = 1, collected_at = :collected_at WHERE id = :id');
            $stmt->execute([
                ':collected_at' => date('Y-m-d H:i:s'),
                ':id' => $studentIdToCollect,
            ]);
            $message = 'Certificate marked as collected.';
            $messageType = 'success';
        }
    } elseif ($action === 'delete_student') {
        $studentIdToDelete = isset($_POST['student_id']) ? (int) $_POST['student_id'] : 0;
        if ($studentIdToDelete > 0) {
            $stmt = $pdo->prepare('DELETE FROM students WHERE id = :id');
            $stmt->execute([':id' => $studentIdToDelete]);
            $message = 'Student record deleted.';
            $messageType = 'success';
        }
    }
}

$search = trim($_GET['search'] ?? '');
$courseFilter = trim($_GET['course'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'all');
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editingStudent = null;

if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM students WHERE id = :id');
    $stmt->execute([':id' => $editId]);
    $editingStudent = $stmt->fetch(PDO::FETCH_ASSOC);
}

$query = 'SELECT * FROM students WHERE 1 = 1';
$params = [];

if ($search !== '') {
    $query .= ' AND (full_name LIKE :search OR email LIKE :search OR course_name LIKE :search OR student_id LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}

if ($courseFilter !== '') {
    $query .= ' AND course_name LIKE :course';
    $params[':course'] = '%' . $courseFilter . '%';
}

if ($statusFilter === 'collected') {
    $query .= ' AND collected = 1';
} elseif ($statusFilter === 'pending') {
    $query .= ' AND collected = 0';
}

$query .= ' ORDER BY created_at DESC';
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

$courseOptions = $pdo->query('SELECT DISTINCT course_name FROM students ORDER BY course_name')->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Collection Management System</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
    <div class="container">
        <header>
            <div class="header-main">
                <div>
                    <h1>Certificate Collection Management System</h1>
                    <p>Manage student records, track certificate collection, and send reminder emails.</p>
                </div>
                <div class="header-actions">
                    <span>Signed in as <?php echo h(getLoggedInAdmin()); ?></span>
                    <a href="settings.php" class="secondary-link">Settings</a>
                    <a href="logout.php" class="secondary-link">Sign out</a>
                </div>
            </div>
        </header>

        <?php if ($message !== ''): ?>
            <div class="alert alert-<?php echo h($messageType); ?>"><?php echo h($message); ?></div>
        <?php endif; ?>

        <section class="card">
            <h2><?php echo $editingStudent ? 'Edit Student' : 'Add Student'; ?></h2>
            <form method="post" class="student-form">
                <input type="hidden" name="action" value="save">
                <?php if ($editingStudent): ?>
                    <input type="hidden" name="student_id_to_edit" value="<?php echo h($editingStudent['id']); ?>">
                <?php endif; ?>

                <div class="grid">
                    <label>
                        Student ID
                        <input type="text" name="student_id" value="<?php echo h($editingStudent['student_id'] ?? ''); ?>" placeholder="Leave blank to auto-generate">
                    </label>
                    <label>
                        Full Name
                        <input type="text" name="full_name" value="<?php echo h($editingStudent['full_name'] ?? ''); ?>" required>
                    </label>
                    <label>
                        Email Address
                        <input type="email" name="email" value="<?php echo h($editingStudent['email'] ?? ''); ?>" required>
                    </label>
                    <label>
                        Course Name
                        <input type="text" name="course_name" value="<?php echo h($editingStudent['course_name'] ?? ''); ?>" required>
                    </label>
                    <label>
                        Completion Date
                        <input type="date" name="completion_date" value="<?php echo h($editingStudent['completion_date'] ?? ''); ?>" required>
                    </label>
                    <label class="checkbox">
                        <input type="checkbox" name="signed_status" value="1" <?php echo ($editingStudent['signed_status'] ?? 0) ? 'checked' : ''; ?>>
                        Signed status
                    </label>
                    <label class="checkbox">
                        <input type="checkbox" name="collected" value="1" <?php echo ($editingStudent['collected'] ?? 0) ? 'checked' : ''; ?>>
                        Collected status
                    </label>
                    <?php if (!$editingStudent || !($editingStudent['collected'] ?? 0)): ?>
                        <label class="checkbox">
                            <input type="checkbox" name="send_email" value="1" <?php echo $emailService->isConfigured() ? '' : 'disabled'; ?> title="<?php echo $emailService->isConfigured() ? 'Send notification email to student' : 'Email not configured'; ?>">
                            Send notification email <?php echo !$emailService->isConfigured() ? '<span style="color: #ef4444; font-size: 0.8em;">(not configured)</span>' : ''; ?>
                        </label>
                    <?php endif; ?>

                <div class="actions">
                    <button type="submit"><?php echo $editingStudent ? 'Update Student' : 'Add Student'; ?></button>
                    <?php if ($editingStudent): ?>
                        <a href="index.php" class="secondary-link">Cancel Edit</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="card">
            <div class="toolbar">
                <div class="toolbar-left">
                    <h2>Student Records</h2>
                </div>
                <div class="toolbar-right">
                    <div class="email-status">
                        <?php if ($emailService->isConfigured()): ?>
                            <span class="status-badge status-configured">✓ Email Configured</span>
                        <?php else: ?>
                            <span class="status-badge status-not-configured">✗ Email Not Configured</span>
                            <a href="settings.php" class="secondary-link" style="margin-left: 8px;">Configure Email</a>
                        <?php endif; ?>
                    </div>
                    <form method="post" action="reminders-scheduler.php" class="inline-form" style="display: inline-block; margin-left: 12px;">
                        <button type="submit" class="small-btn" style="background: #8b5cf6;">📧 Send Reminders</button>
                    </form>
                    <form method="get" class="filters">
                        <input type="text" name="search" value="<?php echo h($search); ?>" placeholder="Search by name, email, or course">
                        <select name="course">
                            <option value="">All courses</option>
                            <?php foreach ($courseOptions as $option): ?>
                                <option value="<?php echo h($option); ?>" <?php echo $courseFilter === $option ? 'selected' : ''; ?>><?php echo h($option); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="status">
                            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All records</option>
                            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending collection</option>
                            <option value="collected" <?php echo $statusFilter === 'collected' ? 'selected' : ''; ?>>Collected</option>
                        </select>
                        <button type="submit">Filter</button>
                    </form>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Course</th>
                            <th>Completion Date</th>
                            <th>Signed</th>
                            <th>Collected</th>
                            <th>Collected At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="9">No student records found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><?php echo h($student['student_id']); ?></td>
                                    <td><?php echo h($student['full_name']); ?></td>
                                    <td><?php echo h($student['email']); ?></td>
                                    <td><?php echo h($student['course_name']); ?></td>
                                    <td><?php echo h($student['completion_date']); ?></td>
                                    <td><?php echo $student['signed_status'] ? 'Yes' : 'No'; ?></td>
                                    <td><?php echo $student['collected'] ? 'Yes' : 'No'; ?></td>
                                    <td><?php echo $student['collected_at'] ? h($student['collected_at']) : '—'; ?></td>
                                    <td>
                                        <div class="actions-dropdown">
                                            <button class="dropdown-toggle" onclick="toggleDropdown(this)">⋮ Actions</button>
                                            <div class="dropdown-menu">
                                                <a href="index.php?edit=<?php echo (int) $student['id']; ?>" class="dropdown-item edit-item">
                                                    ✎ Edit
                                                </a>
                                                <form method="post" class="dropdown-form">
                                                    <input type="hidden" name="action" value="mark_collected">
                                                    <input type="hidden" name="student_id" value="<?php echo (int) $student['id']; ?>">
                                                    <button type="submit" class="dropdown-item collect-item" <?php echo $student['collected'] ? 'disabled' : ''; ?>>
                                                        ✓ Mark Collected
                                                    </button>
                                                </form>
                                                <form method="post" class="dropdown-form">
                                                    <input type="hidden" name="action" value="delete_student">
                                                    <input type="hidden" name="student_id" value="<?php echo (int) $student['id']; ?>">
                                                    <button type="submit" class="dropdown-item delete-item" onclick="return confirm('Are you sure you want to delete this student record?');">
                                                        ✕ Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <script>
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.actions-dropdown')) {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.classList.remove('active');
                });
            }
        });

        function toggleDropdown(button) {
            const dropdown = button.closest('.actions-dropdown');
            const menu = dropdown.querySelector('.dropdown-menu');
            
            // Close all other dropdowns
            document.querySelectorAll('.dropdown-menu').forEach(m => {
                if (m !== menu) m.classList.remove('active');
            });
            
            // Toggle this dropdown
            menu.classList.toggle('active');
        }
    </script>
</body>
</html>
