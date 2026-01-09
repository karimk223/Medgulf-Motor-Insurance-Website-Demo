<?php
session_start();
if(!isset($_SESSION['employee_id'])){
    header("Location: employee_login.php");
    exit;
}
include 'db_connection.php';

$employee_id = (int)$_SESSION['employee_id'];
$request_id  = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;

if($request_id <= 0){
    header("Location: employee_dashboard.php?error=Invalid+request");
    exit;
}


$stmt = $conn->prepare("UPDATE requests SET processed = 1, processed_by = ? WHERE request_id = ?");
$stmt->bind_param("ii", $employee_id, $request_id);
$stmt->execute();
$stmt->close();

$conn->close();


header("Location: view_request.php?request_id=" . $request_id);
exit;
