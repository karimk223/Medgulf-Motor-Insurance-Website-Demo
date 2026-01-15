<?php
session_start();
include 'db_connection.php';

$role = $_GET['role'] ?? 'client';
$role = ($role === 'employee') ? 'employee' : 'client';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }


if($role === 'employee'){
  $table = "employees";
  $idCol = "employee_id";
  $emailCol = "email";
  $passCol = "password";
  $qCol = "security_question";
  $aCol = "security_answer_hash";
} else {
  $table = "clients";
  $idCol = "client_id";
  $emailCol = "email";
  $passCol = "password";
  $qCol = "security_question";
  $aCol = "security_answer_hash";
}


if(isset($_GET['ajax_q']) && $_GET['ajax_q'] == '1'){
  header('Content-Type: application/json; charset=utf-8');

  $email = trim($_GET['email'] ?? '');
  if($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)){
    echo json_encode(["ok"=>false, "question"=>""]);
    exit;
  }

  $sql = "SELECT {$qCol} AS q FROM {$table} WHERE {$emailCol}=? LIMIT 1";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res ? $res->fetch_assoc() : null;
  $stmt->close();

  $q = $row ? ($row['q'] ?? '') : '';
  echo json_encode(["ok"=>true, "question"=>$q ?: ""]);
  exit;
}


$msg = "";
$err = "";

if($_SERVER['REQUEST_METHOD'] === 'POST'){
  $email = trim($_POST['email'] ?? '');
  $answer = trim($_POST['security_answer'] ?? '');
  $newpw = $_POST['new_password'] ?? '';
  $conf = $_POST['confirm_password'] ?? '';

  if($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $err = "Enter a valid email.";
  else if($answer === '') $err = "Security answer is required.";
  else if($newpw === '' || $conf === '') $err = "New password fields are required.";
  else if($newpw !== $conf) $err = "Passwords do not match.";
  else if(strlen($newpw) < 6) $err = "Password must be at least 6 characters.";

  if($err === ""){
    $sql = "SELECT {$idCol} AS id, {$qCol} AS q, {$aCol} AS ahash FROM {$table} WHERE {$emailCol}=? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if(!$row){
      $err = "Email not found.";
    } else {
      $hash = $row['ahash'] ?? '';
      if($hash === ''){
        $err = "Security question is not set for this account.";
      } else if(!password_verify($answer, $hash)){
        $err = "Wrong security answer.";
      } else {
        $pwHash = password_hash($newpw, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE {$table} SET {$passCol}=? WHERE {$idCol}=? LIMIT 1");
        $id = (int)$row['id'];
        $stmt->bind_param("si", $pwHash, $id);
        $stmt->execute();
        $stmt->close();

        $msg = "Password reset successful. You can login now.";
      }
    }
  }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Forgot Password</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/app.css?v=9999">
  <style>
    
    .qbox[readonly]{ opacity: 1; }
    #secQuestion{ color: rgba(255,255,255,.92); }
    #secQuestion::placeholder{ color: rgba(255,255,255,.55); opacity: 1; }
  </style>
</head>
<body class="app-bg">
<div class="bg-fixed"></div>
<div class="aura"></div>

<div class="app-shell">
  <div class="pro-container">
    <div class="pro-card" style="max-width:680px;margin:40px auto;">
      <div class="pro-card-header text-center">
        <h3 class="pro-title mb-0">Forgot Password</h3>
        <div class="pro-sub"><?= $role === 'employee' ? 'Employee reset • Security Question' : 'Client reset • Security Question' ?></div>
      </div>
      <div class="pro-divider"></div>

      <div class="pro-card-body">
        <?php if($err !== ""): ?>
          <div class="alert alert-danger"><?= h($err) ?></div>
        <?php endif; ?>
        <?php if($msg !== ""): ?>
          <div class="alert alert-success"><?= h($msg) ?></div>
        <?php endif; ?>

        <form method="POST" action="forgot_password.php?role=<?= h($role) ?>" id="fpForm">
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input class="form-control" type="email" name="email" id="email"
                   value="<?= h($_POST['email'] ?? '') ?>"
                   required autocomplete="email"
                   placeholder="Enter your email">
            <div class="small text-muted mt-1">Use the same email used during registration.</div>
          </div>

          <div class="mb-3">
            <label class="form-label">Security Question</label>
            <input class="form-control qbox" id="secQuestion" readonly
                   placeholder="Type your email to load your security question"
                   value="">
            <div class="small text-muted mt-1" id="qHint"></div>
          </div>

          <div class="mb-3">
            <label class="form-label">Answer</label>
            <input class="form-control" name="security_answer" required>
          </div>

          <div class="mb-3">
            <label class="form-label">New Password</label>
            <input class="form-control" type="password" name="new_password" required autocomplete="new-password">
          </div>

          <div class="mb-3">
            <label class="form-label">Confirm Password</label>
            <input class="form-control" type="password" name="confirm_password" required autocomplete="new-password">
          </div>

          <button class="btn btn-primary w-100">Reset Password</button>

          <?php if($role === 'employee'): ?>
            <a class="btn btn-secondary w-100 mt-2" href="employee_login.php">Back</a>
          <?php else: ?>
            <a class="btn btn-secondary w-100 mt-2" href="client_login.php">Back</a>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  const email = document.getElementById('email');
  const secQuestion = document.getElementById('secQuestion');
  const qHint = document.getElementById('qHint');

  let t = null;

  async function fetchQuestion(){
    const v = (email.value || "").trim();
    secQuestion.value = "";
    qHint.textContent = "";

    if(v.length < 5) return;

    try{
      const url = "forgot_password.php?role=<?= h($role) ?>&ajax_q=1&email=" + encodeURIComponent(v) + "&t=" + Date.now();
      const res = await fetch(url);
      const j = await res.json();

      const q = (j && j.question) ? j.question : "";
      if(q){
        secQuestion.value = q;
        qHint.textContent = "Loaded from your account.";
      } else {
        secQuestion.value = "";
        qHint.textContent = "No question found for this email (or email not registered).";
      }
    }catch(e){
      qHint.textContent = "Failed to load the security question.";
    }
  }

  email.addEventListener('input', () => {
    clearTimeout(t);
    t = setTimeout(fetchQuestion, 400);
  });

  email.addEventListener('blur', fetchQuestion);


  if(email.value.trim() !== "") fetchQuestion();
})();
</script>

</body>
</html>
