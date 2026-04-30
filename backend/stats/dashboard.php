<?php

require_once '../config.php';

validateRequestMethod('GET');
requireAuth(['super_admin', 'admin', 'assistant']);

function getCount($query)
{
    global $conn;

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return (int)($row['total'] ?? 0);
}

function getSum($query)
{
    global $conn;

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return (float)($row['total'] ?? 0);
}

$stats = [];

$stats['total_students'] = getCount("SELECT COUNT(*) AS total FROM students");
$stats['active_students'] = getCount("SELECT COUNT(*) AS total FROM students WHERE status = 'active'");
$stats['inactive_students'] = getCount("SELECT COUNT(*) AS total FROM students WHERE status = 'inactive'");

$stats['total_admins'] = getCount("SELECT COUNT(*) AS total FROM admins");
$stats['active_admins'] = getCount("SELECT COUNT(*) AS total FROM admins WHERE status = 'active'");

$stats['total_centers'] = getCount("SELECT COUNT(*) AS total FROM centers");
$stats['active_centers'] = getCount("SELECT COUNT(*) AS total FROM centers WHERE status = 'active'");

$stats['total_courses'] = getCount("SELECT COUNT(*) AS total FROM items WHERE item_type = 'course'");
$stats['total_chapters'] = getCount("SELECT COUNT(*) AS total FROM items WHERE item_type = 'chapter'");
$stats['total_lessons'] = getCount("SELECT COUNT(*) AS total FROM items WHERE item_type = 'lesson'");
$stats['total_packages'] = getCount("SELECT COUNT(*) AS total FROM items WHERE item_type = 'package'");
$stats['total_passes'] = getCount("SELECT COUNT(*) AS total FROM items WHERE item_type = 'pass'");
$stats['published_items'] = getCount("SELECT COUNT(*) AS total FROM items WHERE is_published = 1");
$stats['unpublished_items'] = getCount("SELECT COUNT(*) AS total FROM items WHERE is_published = 0");

$stats['total_quizzes'] = getCount("SELECT COUNT(*) AS total FROM quizzes");
$stats['published_quizzes'] = getCount("SELECT COUNT(*) AS total FROM quizzes WHERE is_published = 1");
$stats['total_questions'] = getCount("SELECT COUNT(*) AS total FROM quiz_questions");
$stats['total_quiz_attempts'] = getCount("SELECT COUNT(*) AS total FROM quiz_attempts");
$stats['pending_quiz_reviews'] = getCount("
    SELECT COUNT(*) AS total 
    FROM quiz_attempts 
    WHERE status = 'pending_review' OR status = 'submitted'
");

$stats['total_assignments'] = getCount("SELECT COUNT(*) AS total FROM assignments");
$stats['total_assignment_submissions'] = getCount("SELECT COUNT(*) AS total FROM assignment_submissions");
$stats['pending_assignment_reviews'] = getCount("
    SELECT COUNT(*) AS total 
    FROM assignment_submissions 
    WHERE grade IS NULL
");

$stats['total_code_batches'] = getCount("SELECT COUNT(*) AS total FROM code_batches");
$stats['total_codes'] = getCount("SELECT COUNT(*) AS total FROM codes");
$stats['used_codes'] = getCount("SELECT COUNT(*) AS total FROM codes WHERE is_used = 1");
$stats['unused_codes'] = getCount("SELECT COUNT(*) AS total FROM codes WHERE is_used = 0");
$stats['expired_codes'] = getCount("
    SELECT COUNT(*) AS total 
    FROM codes 
    WHERE expires_at IS NOT NULL AND expires_at < NOW()
");

$stats['total_payments'] = getCount("SELECT COUNT(*) AS total FROM payments");
$stats['completed_payments'] = getCount("SELECT COUNT(*) AS total FROM payments WHERE status = 'completed'");
$stats['pending_payments'] = getCount("SELECT COUNT(*) AS total FROM payments WHERE status = 'pending'");
$stats['failed_payments'] = getCount("SELECT COUNT(*) AS total FROM payments WHERE status = 'failed'");
$stats['cancelled_payments'] = getCount("SELECT COUNT(*) AS total FROM payments WHERE status = 'cancelled'");

$stats['total_revenue'] = getSum("
    SELECT COALESCE(SUM(amount), 0) AS total 
    FROM payments 
    WHERE status = 'completed'
");

$stats['monthly_revenue'] = getSum("
    SELECT COALESCE(SUM(amount), 0) AS total 
    FROM payments 
    WHERE status = 'completed'
    AND MONTH(created_at) = MONTH(CURRENT_DATE())
    AND YEAR(created_at) = YEAR(CURRENT_DATE())
");

$stats['today_revenue'] = getSum("
    SELECT COALESCE(SUM(amount), 0) AS total 
    FROM payments 
    WHERE status = 'completed'
    AND DATE(created_at) = CURRENT_DATE()
");

$stats['active_access'] = getCount("SELECT COUNT(*) AS total FROM student_access WHERE status = 'active'");
$stats['expired_access'] = getCount("SELECT COUNT(*) AS total FROM student_access WHERE status = 'expired'");
$stats['cancelled_access'] = getCount("SELECT COUNT(*) AS total FROM student_access WHERE status = 'cancelled'");

$stats['total_notifications'] = getCount("SELECT COUNT(*) AS total FROM notifications");
$stats['unread_notifications'] = getCount("SELECT COUNT(*) AS total FROM notifications WHERE is_read = 0");
$stats['total_announcements'] = getCount("SELECT COUNT(*) AS total FROM announcements");

respond('success', $stats);