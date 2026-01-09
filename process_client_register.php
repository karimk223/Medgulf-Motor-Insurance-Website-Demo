<?php
session_start();
include 'db_connection.php';

function backWithErrors($errors){
  header("Location: client_register.php?error=" . urlencode(implode(" ", $errors)));
  exit;
}

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
  header("Location: client_register.php");
  exit;
}

$first   = trim($_POST['first_name'] ?? '');
$middle  = trim($_POST['middle_name'] ?? '');
$last    = trim($_POST['last_name'] ?? '');
$email   = trim($_POST['email'] ?? '');
$phone   = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');
$dob     = trim($_POST['date_of_birth'] ?? '');

$pw1 = $_POST['password'] ?? '';
$pw2 = $_POST['confirm_password'] ?? '';

$secQ = trim($_POST['security_question'] ?? '');
$secA = trim($_POST['security_answer'] ?? '');

$errors = [];


if($first === '') $errors[] = "First name is required.";
if($last === '')  $errors[] = "Last name is required.";
if($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL))
  $errors[] = "Valid email is required.";
if($phone === '') $errors[] = "Phone is required.";
if($address === '') $errors[] = "Address is required.";


if($dob === ''){
  $errors[] = "Date of birth is required.";
} else {
  $parts = explode('-', $dob);
  if(count($parts) !== 3 || !checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0])){
    $errors[] = "Invalid date format.";
  } else {
    $age = (new DateTime($dob))->diff(new DateTime('today'))->y;
    if($age < 18){
      $errors[] = "You must be 18+ to register.";
    }
  }
}


if($pw1 === '' || $pw2 === '') $errors[] = "Password fields are required.";
if($pw1 !== $pw2) $errors[] = "Passwords do not match.";
if(strlen($pw1) < 6) $errors[] = "Password must be at least 6 characters.";


if($secQ === '') $errors[] = "Security question is required.";
if($secA === '') $errors[] = "Security answer is required.";

if(!empty($errors)){
  backWithErrors($errors);
}


$stmt = $conn->prepare("SELECT client_id FROM clients WHERE email=? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();
if($stmt->num_rows > 0){
  $stmt->close();
  backWithErrors(["This email is already registered."]);
}
$stmt->close();


$pwHash  = password_hash($pw1, PASSWORD_DEFAULT);
$secHash = password_hash($secA, PASSWORD_DEFAULT);


$stmt = $conn->prepare("
  INSERT INTO clients
  (first_name, middle_name, last_name, email, phone, address, password, date_of_birth, security_question, security_answer_hash)
  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
  "ssssssssss",
  $first,
  $middle,
  $last,
  $email,
  $phone,
  $address,
  $pwHash,
  $dob,
  $secQ,
  $secHash
);

$stmt->execute();
$client_id = $stmt->insert_id;
$stmt->close();


$_SESSION['client_id'] = (int)$client_id;

header("Location: client_dashboard.php");
exit;
