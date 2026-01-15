<?php
session_start();
include 'db_connection.php';

function back_with_error($msg){
    header("Location: client_login.php?error=" . urlencode($msg));
    exit;
}

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    back_with_error("Invalid request.");
}

$email = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

if($email === '' || $password === ''){
    back_with_error("Please enter your email and password.");
}

$stmt = $conn->prepare("SELECT client_id, password FROM clients WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if($stmt->num_rows !== 1){
    $stmt->close();
    back_with_error("No account found with this email. Please create an account first.");
}

$stmt->bind_result($client_id, $db_password);
$stmt->fetch();
$stmt->close();


$ok = false;
if(!empty($db_password) && strpos($db_password, '$2') === 0){
    
    $ok = password_verify($password, $db_password);
} else {
    
    $ok = ($password === $db_password);
}
if(!$ok){
    back_with_error("Incorrect password. Please try again.");
}

$_SESSION['client_id'] = $client_id;

header("Location: client_dashboard.php");
exit;
