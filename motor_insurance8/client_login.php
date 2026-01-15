<?php
session_start();
if(isset($_SESSION['client_id'])){
    header("Location: client_dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Client Login</title>
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
      justify-content:space-between;
      align-items:center;
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

      <div class="pro-card" style="max-width:460px;margin:60px auto;">
        <div class="pro-card-header text-center">
          <h3 class="pro-title mb-0">Client Login</h3>
          <div class="pro-sub">Access your dashboard and submit requests.</div>
        </div>
        <div class="pro-divider"></div>
        <div class="pro-card-body">

          <?php if(isset($_GET['error'])): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div>
          <?php endif; ?>

          <form action="process_client_login.php" method="post">
            <div class="mb-3">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" required autocomplete="email">
            </div>

            <div class="mb-3">
              <label class="form-label">Password</label>
              <div class="input-group">
                <input type="password" name="password" id="client_password"
                       class="form-control" required autocomplete="current-password">
                <button type="button" class="pw-btn" onclick="togglePw('client_password')">👁️</button>
              </div>

              <div class="login-links">
                <a href="client_register.php">Create an account</a>
                <a href="forgot_password.php?role=client">Forgot password?</a>
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
