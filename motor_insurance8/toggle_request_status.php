<?php
session_start();
if(!isset($_SESSION['employee_id'])){
    http_response_code(401);
    die("Unauthorized");
}

include 'db_connection.php';

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    http_response_code(405);
    die("Method not allowed");
}

$request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
if($request_id <= 0){
    http_response_code(400);
    die("Invalid request id");
}

$employee_id = intval($_SESSION['employee_id']);



$stmt = $conn->prepare("SELECT processed FROM requests WHERE request_id = ?");
$stmt->bind_param("i", $request_id);
$stmt->execute();
$stmt->bind_result($processed);
if(!$stmt->fetch()){
    $stmt->close();
    http_response_code(404);
    die("Request not found");
}
$stmt->close();

if(intval($processed) === 0){
    $stmt = $conn->prepare("UPDATE requests SET processed = 1, processed_by = ? WHERE request_id = ?");
    $stmt->bind_param("ii", $employee_id, $request_id);
    $stmt->execute();
    $stmt->close();
} else {
    $stmt = $conn->prepare("UPDATE requests SET processed = 0, processed_by = NULL WHERE request_id = ?");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $stmt->close();
}

header("Location: employee_view_request.php?request_id=" . $request_id . "&updated=1");
exit;
