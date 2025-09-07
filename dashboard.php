<?php
session_start();
require_once '../db_connect.php';

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: index.php");
    exit;
}

// Fetch tickets from database
$sql = "SELECT * FROM tickets ORDER BY created_at DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
        }
        table, th, td {
            border: 1px solid #ddd;
            padding: 8px;
        }
        th {
            background: #f4f4f4;
        }
    </style>
</head>
<body>
    <h2>Support Tickets</h2>
    <a href="logout.php">Logout</a>
    <table>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Subject</th>
            <th>Message</th>
            <th>Created At</th>
        </tr>
        <?php
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "<tr>
                        <td>{$row['id']}</td>
                        <td>{$row['name']}</td>
                        <td>{$row['email']}</td>
                        <td>{$row['subject']}</td>
                        <td>{$row['message']}</td>
                        <td>{$row['created_at']}</td>
                    </tr>";
            }
        } else {
            echo "<tr><td colspan='6'>No tickets found</td></tr>";
        }
        ?>
    </table>
</body>
</html>
