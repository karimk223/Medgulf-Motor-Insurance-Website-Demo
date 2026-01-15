<?php
session_start();
if(isset($_SESSION['employee_id'])){
    header("Location: employee_dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Employee Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/app.css">

  <style>
    .pw-btn{
      width:48px;
      min-width:48px;
      border:1px solid rgba(255,255,255,0.18);
      border-left:none;
      background: rgba(255,255,255,0.06);
      color:#fff;
      cursor:pointer;
      border-top-right-radius:10px;
      border-bottom-right-radius:10px;
    }
    .pw-btn:hover{ color:#f5c16c; }
    .login-links{
      display:flex;
      justify-content:flex-end;
      margin-top:10px;
      font-weight:800;
    }
  </style>
</head>
<body class="app-bg">
<div class="bg-fixed"></div>
<div class="aura"></div>
<div class="app-shell">
<div class="pro-container">

<div class="pro-card" style="max-width:420px;margin:auto;">
<div class="pro-card-header text-center">
  <h3 class="pro-title">Staff Login</h3>
  <div class="pro-sub">Internal access</div>
</div>
<div class="pro-divider"></div>
<div class="pro-card-body">

<?php if(isset($_GET['error'])): ?>
<div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div>
<?php endif; ?>

<form method="post" action="process_employee_login.php">
  <div class="mb-3">
    <label class="form-label">Email</label>
    <input type="email" name="email" class="form-control" required>
  </div>

  <div class="mb-3">
    <label class="form-label">Password</label>
    <div class="input-group">
      <input type="password" name="password" id="emp_password" class="form-control" required>
      <button type="button" class="pw-btn" onclick="togglePw('emp_password')">👁️</button>
    </div>

    <div class="login-links">
      <a href="forgot_password.php?role=employee">Forgot password?</a>
    </div>
  </div>

  <button class="btn btn-primary w-100">Login</button>
  <a href="index.php" class="btn btn-secondary w-100 mt-2">Back</a>
</form>

</div>
</div>

</div>
</div>

<script>
function togglePw(id){
  const i = document.getElementById(id);
  i.type = (i.type === 'password') ? 'text' : 'password';
}
</script>

</body>
</html>
