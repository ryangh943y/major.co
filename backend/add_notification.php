<?php
// backend/add_notification.php
function addNotification($pdo, $user_id, $type, $message, $related_id = null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, message, related_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $type, $message, $related_id]);
        return true;
    } catch (PDOException $e) {
        error_log("Failed to add notification: " . $e->getMessage());
        return false;
    }
}
?>
