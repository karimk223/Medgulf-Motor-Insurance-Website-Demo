<?php
session_start();
if(!isset($_SESSION['employee_id'])){
    http_response_code(401);
    die('Unauthorized');
}

if(!isset($_GET['request_id'])){
    http_response_code(400);
    die('Request ID missing');
}

header('Location: generate_policy_pdf.php?request_id=' . (int)$_GET['request_id'] . '&v=' . time());
exit;
?>
