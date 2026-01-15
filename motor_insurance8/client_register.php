<?php
session_start();
if(isset($_SESSION['client_id'])){
    header("Location: client_dashboard.php");
    exit;
}

$questions = [
  "What is your favorite color?",
  "What is your favorite city?",
  "What is your favorite food?",
  "What is the name of your first pet?",
  "What is your mother’s first name?",
  "What is your childhood nickname?"
];
?>
<!DOCTYPE html>
<html>
<head>
  <title>Client Registration</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/app.css?v=9999">

  
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

  <style>
    .pw-meter{ height:10px;border-radius:999px;background:rgba(255,255,255,.12);overflow:hidden; }
    .pw-meter > div{ height:100%; width:0%; border-radius:999px; background:#ff2d2d; transition: width .18s ease; }
    .pw-hint{ font-size:12px; color:rgba(255,255,255,.72); margin-top:8px; }
    .pw-match{ font-size:12px; margin-top:8px; font-weight:800; }
    .pw-match.ok{ color:#2ee59d; }
    .pw-match.bad{ color:#ff8080; }
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

    
    #date_of_birth::placeholder{ color:rgba(255,255,255,.85) !important; opacity:1; }
    .calendar-addon{
      cursor:pointer;
      user-select:none;
      color: #f5c16c;                 
      background: rgba(0,0,0,.25);
      border: 1px solid rgba(255,207,110,.25);
      border-left: none;
    }
    .calendar-addon:hover{
      color:#ffd27d;
      background: rgba(255,207,110,.10);
    }

    
    .invalid-feedback.d-block{ font-weight:700; }

    
.input-group-text.calendar-addon{
  color: #f5c16c !important;                
  font-size: 18px;
  background: rgba(0,0,0,.35) !important;
  border: 1px solid rgba(255,207,110,.35) !important;
  border-left: none !important;
  box-shadow: inset 0 0 0 1px rgba(255,207,110,.15);
}

.input-group-text.calendar-addon:hover{
  color: #ffd27d !important;
  background: rgba(255,207,110,.15) !important;
}

.calendar-addon{
  cursor:pointer;
  background: rgba(0,0,0,.35);
  border: 1px solid rgba(255,207,110,.35);
  border-left: none;
  display:flex;
  align-items:center;
  justify-content:center;
}

.calendar-addon:hover svg{
  stroke: #ffd27d;
}


  </style>
</head>
<body class="app-bg">
<div class="bg-fixed"></div>
<div class="aura"></div>

<div class="app-shell">
  <div class="pro-container">

    <div class="pro-card" style="max-width:620px;margin:40px auto;">
      <div class="pro-card-header text-center">
        <h3 class="pro-title mb-0">Client Registration</h3>
        <div class="pro-sub">Create your demo account.</div>
      </div>
      <div class="pro-divider"></div>

      <div class="pro-card-body">

       
        <?php if(isset($_GET['error'])): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div>
        <?php endif; ?>

        
        <div class="alert alert-danger d-none" id="missingAlert">
          Please fill the highlighted fields.
        </div>

        <form method="POST" action="process_client_register.php" id="regForm" novalidate>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">First Name</label>
              <input class="form-control" name="first_name" required>
              <div class="invalid-feedback">First name is required.</div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Middle Name</label>
              <input class="form-control" name="middle_name">
            </div>
            <div class="col-md-4">
              <label class="form-label">Last Name</label>
              <input class="form-control" name="last_name" required>
              <div class="invalid-feedback">Last name is required.</div>
            </div>

            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input class="form-control" type="email" name="email" required autocomplete="email">
              <div class="invalid-feedback">Valid email is required.</div>
            </div>

            <div class="col-md-6">
              <label class="form-label">Phone</label>
              <input class="form-control" name="phone" type="tel" inputmode="numeric" pattern="[0-9]+" maxlength="15" required>
              <div class="invalid-feedback">Phone is required.</div>
            </div>

            <div class="col-md-12">
              <label class="form-label">Address</label>
              <input class="form-control" name="address" required>
              <div class="invalid-feedback">Address is required.</div>
            </div>

            
            <div class="col-md-6">
              <label class="form-label">Date of Birth</label>
              <div class="input-group">
                <input class="form-control" name="date_of_birth" id="date_of_birth" placeholder="YYYY-MM-DD" required>
                <span class="input-group-text calendar-addon" id="dob_btn" title="Open calendar">
  <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
    <path d="M7 2v2M17 2v2M3 7h18M5 5h14a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z"
      stroke="#f5c16c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
  </svg>
</span>

              </div>
              <div class="invalid-feedback d-block" id="dobFeedback" style="display:none;"></div>
            </div>

            <div class="col-md-6">
              <label class="form-label">Password</label>
              <div class="input-group">
                <input class="form-control" type="password" name="password" id="password" required autocomplete="new-password">
                <button type="button" id="togglePassword" class="pw-btn">👁️</button>
              </div>
              <div class="pw-meter mt-2"><div id="pwBar"></div></div>
              <div class="pw-hint" id="pwHint"></div>
            </div>

            <div class="col-md-6">
              <label class="form-label">Confirm Password</label>
              <div class="input-group">
                <input class="form-control" type="password" name="confirm_password" id="confirm_password" required autocomplete="new-password">
                <button type="button" id="toggleConfirmPassword" class="pw-btn">👁️</button>
              </div>
              <div class="pw-match" id="pwMatch"></div>
            </div>

            
            <div class="col-md-6">
              <label class="form-label">Security Question</label>
              <select class="form-select" name="security_question" required>
                <option value="" selected disabled>Choose a question...</option>
                <?php foreach($questions as $q): ?>
                  <option value="<?= htmlspecialchars($q) ?>"><?= htmlspecialchars($q) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="invalid-feedback">Please choose a security question.</div>
            </div>

            
            <div class="col-md-6">
              <label class="form-label">Security Answer</label>
              <input class="form-control" name="security_answer" required>
              <div class="invalid-feedback">Security answer is required.</div>
            </div>

          </div>

          <button class="btn btn-primary w-100 mt-3">Register</button>
          <a href="client_login.php" class="btn btn-secondary w-100 mt-2">Back</a>
        </form>

      </div>
    </div>

  </div>
</div>


<script>
(function(){
  const pw = document.getElementById('password');
  const cpw = document.getElementById('confirm_password');
  const pwBar = document.getElementById('pwBar');
  const pwHint = document.getElementById('pwHint');
  const pwMatch = document.getElementById('pwMatch');

  function renderStrength(){
    const v = pw.value || "";
    let score = 0;
    if(v.length >= 8) score++;
    if(/[A-Z]/.test(v)) score++;
    if(/[0-9]/.test(v)) score++;
    if(/[^A-Za-z0-9]/.test(v)) score++;

    const pct = [0,25,50,75,100][score];
    pwBar.style.width = pct + "%";

    if(score <= 1){
      pwBar.style.background = "#ff2d2d";
      pwHint.textContent = "Weak — add length, numbers, and symbols.";
    } else if(score === 2){
      pwBar.style.background = "#ffc300";
      pwHint.textContent = "Medium — good, but can be stronger.";
    } else if(score === 3){
      pwBar.style.background = "#2ee59d";
      pwHint.textContent = "Strong — good.";
    } else {
      pwBar.style.background = "#2ee59d";
      pwHint.textContent = "Very strong.";
    }
  }

  function renderMatch(){
    if (!pw.value && !cpw.value){
      pwMatch.textContent = "";
      pwMatch.className = "pw-match";
      return;
    }
    if (pw.value.length > 0 && cpw.value === pw.value){
      pwMatch.textContent = "Passwords match ✅";
      pwMatch.className = "pw-match ok";
    } else {
      pwMatch.textContent = "Passwords do not match ❌";
      pwMatch.className = "pw-match bad";
    }
  }

  function toggleVisibility(input){
    input.type = (input.type === "password") ? "text" : "password";
  }

  const t1 = document.getElementById('togglePassword');
  const t2 = document.getElementById('toggleConfirmPassword');
  if(t1) t1.addEventListener('click', () => toggleVisibility(pw));
  if(t2) t2.addEventListener('click', () => toggleVisibility(cpw));

  pw.addEventListener('input', () => { renderStrength(); renderMatch(); });
  cpw.addEventListener('input', renderMatch);

  renderStrength(); renderMatch();
})();
</script>


<script>
(function(){
  const form = document.getElementById('regForm');
  const missingAlert = document.getElementById('missingAlert');

  const dobInput = document.getElementById('date_of_birth');
  const dobFeedback = document.getElementById('dobFeedback');

  function showDobError(msg){
    dobInput.classList.add('is-invalid');
    dobFeedback.style.display = 'block';
    dobFeedback.textContent = msg;
  }
  function clearDobError(){
    dobInput.classList.remove('is-invalid');
    dobFeedback.style.display = 'none';
    dobFeedback.textContent = '';
  }

  function calcAge(dobStr){
    const parts = (dobStr || '').trim().split('-');
    if(parts.length !== 3) return -1;
    const y = parseInt(parts[0],10);
    const m = parseInt(parts[1],10) - 1;
    const d = parseInt(parts[2],10);
    const dob = new Date(y, m, d);
    if(isNaN(dob.getTime())) return -1;

    const today = new Date();
    let age = today.getFullYear() - dob.getFullYear();
    const mo = today.getMonth() - dob.getMonth();
    if(mo < 0 || (mo === 0 && today.getDate() < dob.getDate())) age--;
    return age;
  }

  
  const dobPicker = flatpickr('#date_of_birth', {
    dateFormat: 'Y-m-d',
    allowInput: true,
    clickOpens: true,
    disableMobile: true,
    maxDate: "today",
    onOpen: function(selectedDates, dateStr, instance){
      instance.calendarContainer.style.zIndex = 9999;
    }
  });
  document.getElementById('dob_btn').addEventListener('click', function(){ dobPicker.open(); });

  
  dobInput.addEventListener('input', () => {
    const v = (dobInput.value || '').trim();
    if(!v){ clearDobError(); return; }
    const age = calcAge(v);
    if(age < 0){
      showDobError("Invalid date format. Use YYYY-MM-DD.");
      return;
    }
    if(age < 18){
      showDobError("You must be 18+ to register.");
      return;
    }
    clearDobError();
  });

  
  if(form){
    form.addEventListener('submit', (e) => {
      let ok = true;

     
      missingAlert.classList.add('d-none');
      form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
      clearDobError();

      
      const required = form.querySelectorAll('[required]');
      required.forEach(el => {
        const tag = el.tagName.toLowerCase();
        const val = (el.value || '').trim();
        if(tag === 'select'){
          if(!el.value){
            el.classList.add('is-invalid');
            ok = false;
          }
        } else {
          if(val === ''){
            el.classList.add('is-invalid');
            ok = false;
          }
        }
      });

      
      const dobVal = (dobInput.value || '').trim();
      if(dobVal){
        const age = calcAge(dobVal);
        if(age < 0){
          showDobError("Invalid date format. Use YYYY-MM-DD.");
          ok = false;
        } else if(age < 18){
          showDobError("You must be 18+ to register.");
          ok = false;
        }
      }

      
      const pw = document.getElementById('password').value || '';
      const cpw = document.getElementById('confirm_password').value || '';
      if(pw && cpw && pw !== cpw){
        document.getElementById('confirm_password').classList.add('is-invalid');
        ok = false;
      }

      if(!ok){
        missingAlert.classList.remove('d-none');
        e.preventDefault();
        e.stopPropagation();
      }
    });
  }
})();
</script>

<script>
(function(){
  const phone = document.querySelector('input[name="phone"]');
  if(!phone) return;
  phone.addEventListener('input', function(){
    this.value = this.value.replace(/[^0-9]/g,'');
  });
})();
</script>

</body>
</html>
