<?php
// cron/send-deadline-reminders.php
// Run this daily via Task Scheduler

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/notification-mailer.php';

$database = new Database();
$db = $database->getConnection();

echo "Checking for deadlines...\n";

// Get tomorrow's date
$tomorrow = date('Y-m-d', strtotime('+1 day'));
echo "Looking for deadlines on: " . $tomorrow . "\n";

// Get deadlines for tomorrow
$query = "SELECT * FROM deadlines 
          WHERE deadline_date = :tomorrow 
          AND is_active = 1";

$stmt = $db->prepare($query);
$stmt->bindParam(':tomorrow', $tomorrow);
$stmt->execute();
$deadlines = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($deadlines)) {
    echo "No deadlines found for tomorrow.\n";
    exit;
}

echo "Found " . count($deadlines) . " deadline(s) for tomorrow.\n";
$sent_count = 0;

foreach ($deadlines as $deadline) {
    echo "\nProcessing deadline: " . $deadline['title'] . "\n";
    
    // Get users who want deadline reminders
    $users_query = "SELECT u.id, u.email, u.full_name, up.deadline_reminders 
                    FROM users u
                    LEFT JOIN user_preferences up ON u.id = up.user_id
                    WHERE u.role IN ('student', 'adviser')
                    AND (up.deadline_reminders = 1 OR up.deadline_reminders IS NULL)";
    
    $users_stmt = $db->prepare($users_query);
    $users_stmt->execute();
    $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as $user) {
        // Skip if no email
        if (empty($user['email'])) {
            continue;
        }
        
        $subject = "Reminder: Upcoming Deadline - " . $deadline['title'];
        $message = "Dear " . $user['full_name'] . ",\n\n";
        $message .= "This is a reminder that the following deadline is tomorrow:\n\n";
        $message .= "Title: " . $deadline['title'] . "\n";
        $message .= "Date: " . date('F j, Y', strtotime($deadline['deadline_date'])) . "\n";
        if (!empty($deadline['deadline_time']) && $deadline['deadline_time'] !== '00:00:00') {
            $message .= "Time: " . date('g:i A', strtotime($deadline['deadline_time'])) . "\n";
        }
        if (!empty($deadline['description'])) {
            $message .= "\nDescription: " . $deadline['description'] . "\n";
        }
        $message .= "\nPlease log in to the system for more details.\n\n";
        $message .= "Best regards,\nKLD Capstone Team";
        
        // Send email using your existing function
        $sent = sendCapstoneNotification($user['email'], $user['full_name'], $deadline['title'], 'reminder', '', 'System');
        
        if ($sent) {
            $sent_count++;
            echo "  ✓ Sent reminder to: " . $user['email'] . "\n";
        } else {
            echo "  ✗ Failed to send to: " . $user['email'] . "\n";
        }
    }
}

echo "\n✅ Sent $sent_count reminder(s)\n";