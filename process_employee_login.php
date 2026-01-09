<?php
session_start();
include 'db_connection.php';

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT employee_id, password FROM employees WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($employee_id, $db_password);
    if($stmt->fetch()){
        $stmt->close();

        $ok = false;


        if(is_string($db_password) && strlen($db_password) > 0 && $db_password[0] === '$'){
            $ok = password_verify($password, $db_password);
        } else {
            $ok = ($password === $db_password);
        }

        if($ok){
            $_SESSION['employee_id'] = $employee_id;
            header("Location: employee_dashboard.php");
            exit;
        } else {
            header("Location: employee_login.php?error=Invalid credentials");
            exit;
        }
    } else {
        $stmt->close();
        header("Location: employee_login.php?error=Invalid credentials");
        exit;
    }
} else {
    header("Location: employee_login.php");
    exit;
}
?>
