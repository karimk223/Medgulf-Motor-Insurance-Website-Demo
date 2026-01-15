<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if(!isset($_SESSION['client_id'])){
    header("Location: client_login.php");
    exit;
}

$errors = $_SESSION['form_errors'] ?? [];
$old    = $_SESSION['form_old'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_old']);

function old($key, $default=''){
    global $old;
    return isset($old[$key]) ? htmlspecialchars($old[$key]) : $default;
}
function oldArr($key){
    global $old;
    return isset($old[$key]) && is_array($old[$key]) ? $old[$key] : [];
}
function hasErr($key){
    global $errors;
    return isset($errors[$key]);
}
function errMsg($key){
    global $errors;
    return isset($errors[$key]) ? $errors[$key] : '';
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>New Insurance Request</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/app.css?v=9999" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
      #inception_date::placeholder { color:#fff !important; opacity:1; }
      #inception_date::-webkit-input-placeholder { color:#fff !important; }
      .calendar-addon{ cursor:pointer; user-select:none; }
      .calendar-gold{
        background: rgba(0,0,0,.28) !important;
        border-color: rgba(255,207,110,.25) !important;
      }
      .calendar-gold .cal-ico{
        width: 18px;
        height: 18px;
        display: block;
        fill: var(--gold2, #ffcf6e);
        filter: drop-shadow(0 0 8px rgba(255,207,110,.25));
      }

      .plan-hint{
        margin-top: .35rem;
        display:flex;
        gap:.5rem;
        align-items:center;
        flex-wrap:wrap;
      }
      .plan-pill{
        border: 1px solid rgba(255,255,255,.18);
        background: rgba(255,255,255,.06);
        color: rgba(255,255,255,.92);
        padding: .28rem .65rem;
        border-radius: 999px;
        font-size: .85rem;
        line-height: 1;
        transition: .15s ease;
      }
      .plan-pill:hover{
        background: rgba(255,255,255,.10);
        border-color: rgba(255,255,255,.28);
      }
      .plan-pill:active{
        transform: translateY(1px);
      }

      .plan-modal{
        background:#0f1114;
        border:1px solid rgba(255,255,255,.12);
        border-radius:14px;
      }
      .plan-modal .modal-header{
        border-bottom:1px solid rgba(255,255,255,.12);
      }
      .plan-modal-title{
        color:#fff;
        font-weight:600;
        letter-spacing:.2px;
      }
      .plan-modal-sub{
        color:rgba(255,255,255,.65);
        font-size:.9rem;
      }
      .plan-frame{
        background:#0b0c0e;
        border:1px solid rgba(255,255,255,.10);
        border-radius:12px;
        overflow:hidden;
      }
      .plan-img{
        width:100%;
        height:auto;
        max-height:72vh;
        object-fit:contain;
        display:block;
      }
      .plan-status{
        color:rgba(255,255,255,.72);
        padding:18px 8px;
        text-align:center;
      }
      .plan-error{
        background: rgba(220,53,69,.12);
        border: 1px solid rgba(220,53,69,.35);
        color: rgba(255,255,255,.92);
        border-radius: 12px;
        padding: 14px 14px;
        word-break: break-all;
      }
      .plan-link-inline{
        color: rgba(255,255,255,.75);
        font-size:.9rem;
        text-decoration:none;
      }
      .plan-link-inline:hover{
        color:#fff;
        text-decoration:underline;
      }
    </style>
</head>
<body class="app-bg">
<div class="bg-fixed"></div>
<div class="aura"></div>

<div class="app-shell">
  <div class="pro-container">
    <div class="pro-card">
      <div class="pro-card-header">
        <h3 class="pro-title">Submit New Insurance Request</h3>
        <div class="pro-sub">Fill all required fields. Missing items will be highlighted.</div>

        <?php if(!empty($errors['general'])): ?>
          <div class="alert alert-danger mt-3 mb-0">
            <?= htmlspecialchars($errors['general']) ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="pro-card-body">
        <form action="process_client_request.php" method="post" enctype="multipart/form-data" id="requestForm" novalidate>

          <div class="mb-3">
            <label class="form-label">Insurance Type *</label>

            <?php $selectedTypes = oldArr('insurance_type'); ?>

            <div class="form-check">
              <input class="form-check-input insurance"
                     type="checkbox"
                     name="insurance_type[]"
                     value="Obligatory"
                     id="ins1"
                     <?= in_array('Obligatory', $selectedTypes) ? 'checked' : '' ?>>
              <label class="form-check-label" for="ins1">Obligatory</label>
            </div>

            <div class="form-check">
              <input class="form-check-input insurance"
                     type="checkbox"
                     name="insurance_type[]"
                     value="Third Party Liability"
                     id="ins2"
                     <?= in_array('Third Party Liability', $selectedTypes) ? 'checked' : '' ?>>
              <label class="form-check-label" for="ins2">Third Party Liability</label>
            </div>

            <div class="form-check">
              <input class="form-check-input insurance"
                     type="checkbox"
                     name="insurance_type[]"
                     value="All Risk"
                     id="ins3"
                     <?= in_array('All Risk', $selectedTypes) ? 'checked' : '' ?>>
              <label class="form-check-label" for="ins3">All Risk</label>
            </div>

            <?php if(hasErr('insurance_type')): ?>
              <div class="invalid-feedback d-block mt-2"><?= htmlspecialchars(errMsg('insurance_type')) ?></div>
            <?php endif; ?>
          </div>

          <div class="mb-3">
            <label class="form-label">Vehicle Type & Model *</label>
            <input type="text"
                   name="vehicle_type"
                   class="form-control <?= hasErr('vehicle_type') ? 'is-invalid' : '' ?>"
                   value="<?= old('vehicle_type') ?>"
                   required>
            <?php if(hasErr('vehicle_type')): ?>
              <div class="invalid-feedback d-block"><?= htmlspecialchars(errMsg('vehicle_type')) ?></div>
            <?php endif; ?>
          </div>

          <div class="mb-3">
            <label class="form-label"> Chassis *</label>
            <input type="text"
                   pattern="[A-Za-z0-9]+"
                   oninput="this.value=this.value.replace(/[^A-Za-z0-9]/g,'')"
                   name="vehicle_number"
                   class="form-control <?= hasErr('vehicle_number') ? 'is-invalid' : '' ?>"
                   value="<?= old('vehicle_number') ?>"
                   required>
          </div>

          <div class="mb-3 row">
            <div class="col-md-5">
              <label class="form-label">License Plate Numbers</label>
              <input type="text"
                     inputmode="numeric"
                     pattern="[0-9]*"
                     oninput="this.value=this.value.replace(/[^0-9]/g,'')"
                     name="plate_numbers"
                     class="form-control <?= hasErr('plate_numbers') ? 'is-invalid' : '' ?>"
                     value="<?= old('plate_numbers') ?>">
              <?php if(hasErr('plate_numbers')): ?>
                <div class="invalid-feedback d-block"><?= htmlspecialchars(errMsg('plate_numbers')) ?></div>
              <?php endif; ?>
            </div>
            <div class="col-md-3">
              <label class="form-label">License Plate Letter</label>
              <select name="plate_letter"
                      class="form-select <?= hasErr('plate_letter') ? 'is-invalid' : '' ?>">
                <option value="">Select</option>
                <?php foreach(range('A','Z') as $letter): ?>
                  <option value="<?= $letter ?>" <?= old('plate_letter')===$letter ? 'selected' : '' ?>>
                    <?= $letter ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php if(hasErr('plate_letter')): ?>
                <div class="invalid-feedback d-block"><?= htmlspecialchars(errMsg('plate_letter')) ?></div>
              <?php endif; ?>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Inception Date *</label>
            <div class="input-group">
              <input type="text" id="inception_date" name="inception_date"
                     class="form-control <?= hasErr('inception_date') ? 'is-invalid' : '' ?>"
                     value="<?= old('inception_date') ?>" placeholder="YYYY-MM-DD"
                     required>
              <span class="input-group-text calendar-addon calendar-gold" id="inc_btn" title="Open calendar" aria-label="Open calendar">
                <svg class="cal-ico" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M7 2a1 1 0 0 1 1 1v1h8V3a1 1 0 1 1 2 0v1h1.5A2.5 2.5 0 0 1 22 6.5v14A2.5 2.5 0 0 1 19.5 23h-15A2.5 2.5 0 0 1 2 20.5v-14A2.5 2.5 0 0 1 4.5 4H6V3a1 1 0 0 1 1-1Zm12.5 7H4.5v11.5c0 .276.224.5.5.5h14c.276 0 .5-.224.5-.5V9ZM6 6H4.5a.5.5 0 0 0-.5.5V7h18v-.5a.5.5 0 0 0-.5-.5H18v1a1 1 0 1 1-2 0V6H8v1a1 1 0 1 1-2 0V6Z"/>
                </svg>
              </span>
            </div>
            <?php if(hasErr('inception_date')): ?>
              <div class="invalid-feedback d-block"><?= htmlspecialchars(errMsg('inception_date')) ?></div>
            <?php endif; ?>
          </div>

          <div class="mb-3" id="allrisk_info" style="display:none;">
            <label class="form-label">All Risk Additional Info *</label>

            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">Car Value (USD) *</label>
                <input type="text"
                       id="carValue"
                       name="car_value"
                       inputmode="decimal"
                       class="form-control <?= hasErr('car_value') ? 'is-invalid' : '' ?>"
                       value="<?= old('car_value') ?>">
                <?php if(hasErr('car_value')): ?>
                  <div class="invalid-feedback d-block"><?= htmlspecialchars(errMsg('car_value')) ?></div>
                <?php endif; ?>
              </div>

              <div class="col-md-4">
                <label class="form-label">Year Built *</label>
                <input type="text"
                       id="yearBuilt"
                       name="year_built"
                       inputmode="numeric"
                       maxlength="4"
                       pattern="[0-9]*"
                       oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,4)"
                       class="form-control <?= hasErr('year_built') ? 'is-invalid' : '' ?>"
                       value="<?= old('year_built') ?>">
                <?php if(hasErr('year_built')): ?>
                  <div class="invalid-feedback d-block"><?= htmlspecialchars(errMsg('year_built')) ?></div>
                <?php endif; ?>
              </div>

              <div class="col-md-4">
                <label class="form-label">Plan *</label>
                <select id="allRiskPlan"
                        name="package_option"
                        class="form-select <?= hasErr('package_option') ? 'is-invalid' : '' ?>">
                  <option value="">Select</option>
                  <option value="Gold" <?= old('package_option')==='Gold' ? 'selected' : '' ?>>Gold</option>
                  <option value="Silver" <?= old('package_option')==='Silver' ? 'selected' : '' ?>>Silver</option>
                </select>

                <div class="plan-hint">
                  <button type="button" class="plan-pill" id="viewGoldBtn">View Gold coverage</button>
                  <button type="button" class="plan-pill" id="viewSilverBtn">View Silver coverage</button>
                  
                </div>

                <?php if(hasErr('package_option')): ?>
                  <div class="invalid-feedback d-block"><?= htmlspecialchars(errMsg('package_option')) ?></div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Upload Driving License Images (2 required) *</label>
            <input type="file" name="driving_license[]" class="form-control mb-2 <?= hasErr('driving_license') ? 'is-invalid' : '' ?>" accept="image/*" required>
            <input type="file" name="driving_license[]" class="form-control <?= hasErr('driving_license') ? 'is-invalid' : '' ?>" accept="image/*" required>
            <?php if(hasErr('driving_license')): ?>
              <div class="invalid-feedback d-block mt-2"><?= htmlspecialchars(errMsg('driving_license')) ?></div>
            <?php endif; ?>
          </div>

          <div class="mb-3">
            <label class="form-label">Upload Car Log Images (2 required) *</label>
            <input type="file" name="car_log[]" class="form-control mb-2 <?= hasErr('car_log') ? 'is-invalid' : '' ?>" accept="image/*" required>
            <input type="file" name="car_log[]" class="form-control <?= hasErr('car_log') ? 'is-invalid' : '' ?>" accept="image/*" required>
            <?php if(hasErr('car_log')): ?>
              <div class="invalid-feedback d-block mt-2"><?= htmlspecialchars(errMsg('car_log')) ?></div>
            <?php endif; ?>
          </div>

          <div class="mb-3" id="allrisk_images" style="display:none;">
            <label class="form-label">Upload 4 Car Images (All Risk only) *</label>
            <input type="file" name="allrisk_images[]" class="form-control mb-2 <?= hasErr('allrisk_images') ? 'is-invalid' : '' ?>" accept="image/*">
            <input type="file" name="allrisk_images[]" class="form-control mb-2 <?= hasErr('allrisk_images') ? 'is-invalid' : '' ?>" accept="image/*">
            <input type="file" name="allrisk_images[]" class="form-control mb-2 <?= hasErr('allrisk_images') ? 'is-invalid' : '' ?>" accept="image/*">
            <input type="file" name="allrisk_images[]" class="form-control <?= hasErr('allrisk_images') ? 'is-invalid' : '' ?>" accept="image/*">
            <?php if(hasErr('allrisk_images')): ?>
              <div class="invalid-feedback d-block mt-2"><?= htmlspecialchars(errMsg('allrisk_images')) ?></div>
            <?php endif; ?>
          </div>

          <button type="submit" class="btn btn-primary w-100">Submit Request</button>
          <a href="client_dashboard.php" class="btn btn-secondary w-100 mt-2">Back</a>

        </form>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="planModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content plan-modal">
      <div class="modal-header">
        <div class="d-flex flex-column">
          <h5 class="modal-title plan-modal-title" id="planModalTitle">Plan Coverage</h5>
          <small class="plan-modal-sub" id="planModalSub">Preview</small>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body" style="padding:18px;">
        <div id="planLoading" class="plan-status">Loading…</div>

        <div id="planError" class="plan-error d-none">
          Image not found: <span id="planErrorPath"></span>
        </div>

        <div class="plan-frame d-none" id="planFrame">
          <img id="planModalImg" src="" alt="Plan coverage" class="plan-img">
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
const checkboxes = document.querySelectorAll('.insurance');
const allriskDiv = document.getElementById('allrisk_images');
const allriskInfo = document.getElementById('allrisk_info');

function syncAllRisk(){
  const checked = document.querySelectorAll('.insurance:checked');
  const values = Array.from(checked).map(c => c.value);
  const show = values.includes("All Risk");
  allriskDiv.style.display = show ? 'block' : 'none';
  allriskInfo.style.display = show ? 'block' : 'none';
  applyAllRiskRules();
}

function valNum(x){
  const n = parseFloat(String(x || '').replace(/,/g,'').trim());
  return isNaN(n) ? 0 : n;
}

function applyAllRiskRules(){
  const carValueEl = document.getElementById('carValue');
  const yearBuiltEl = document.getElementById('yearBuilt');
  const planEl = document.getElementById('allRiskPlan');
  if(!carValueEl || !yearBuiltEl || !planEl) return;

  const checked = document.querySelectorAll('.insurance:checked');
  const values = Array.from(checked).map(c => c.value);
  if(!values.includes("All Risk")) return;

  const carValue = valNum(carValueEl.value);
  const yearBuilt = parseInt(String(yearBuiltEl.value || '').trim(), 10) || 0;

  const optGold = Array.from(planEl.options).find(o => (o.value || '').toLowerCase() === 'gold');
  const optSilver = Array.from(planEl.options).find(o => (o.value || '').toLowerCase() === 'silver');

  const mustBeGold = carValue > 25000;
  const mustBeSilver = yearBuilt > 0 && yearBuilt < 2010;

  if(optGold) optGold.disabled = mustBeSilver;
  if(optSilver) optSilver.disabled = mustBeGold;

  const cur = (planEl.value || '').toLowerCase();

  if(mustBeGold && cur === 'silver' && optGold && !optGold.disabled) planEl.value = optGold.value;
  if(mustBeSilver && cur === 'gold' && optSilver && !optSilver.disabled) planEl.value = optSilver.value;

  if(optGold && optGold.disabled && cur === 'gold' && optSilver && !optSilver.disabled) planEl.value = optSilver.value;
  if(optSilver && optSilver.disabled && cur === 'silver' && optGold && !optGold.disabled) planEl.value = optGold.value;
}

syncAllRisk();

checkboxes.forEach(cb => cb.addEventListener('change', () => {
  const checked = document.querySelectorAll('.insurance:checked');
  const values = Array.from(checked).map(c => c.value);

  if (checked.length > 2){
    alert("Cannot select more than 2 insurance types.");
    cb.checked = false;
    return;
  }
  if(values.includes("All Risk") && values.includes("Third Party Liability")){
    alert("Cannot select both All Risk and Third Party Liability.");
    cb.checked = false;
    return;
  }
  syncAllRisk();
}));

document.getElementById('carValue')?.addEventListener('input', applyAllRiskRules);
document.getElementById('yearBuilt')?.addEventListener('input', applyAllRiskRules);
document.getElementById('allRiskPlan')?.addEventListener('change', applyAllRiskRules);

const PLAN_GOLD_IMG = "/motor_insurance8/images/plans/gold.jpg";
const PLAN_SILVER_IMG = "/motor_insurance8/images/plans/silver.jpg";

const planModal = new bootstrap.Modal(document.getElementById('planModal'));
const planTitle = document.getElementById('planModalTitle');
const planSub = document.getElementById('planModalSub');
const planImg = document.getElementById('planModalImg');
const planLoading = document.getElementById('planLoading');
const planError = document.getElementById('planError');
const planErrorPath = document.getElementById('planErrorPath');
const planFrame = document.getElementById('planFrame');

let currentPlanUrl = "";

function openPlan(which){
  const w = String(which || '').toLowerCase();
  currentPlanUrl = (w === 'gold') ? PLAN_GOLD_IMG : PLAN_SILVER_IMG;

  planTitle.textContent = (w === 'gold') ? "Gold Plan Coverage" : "Silver Plan Coverage";
  planSub.textContent = "All Risk plan details";

  planError.classList.add('d-none');
  planFrame.classList.add('d-none');
  planLoading.classList.remove('d-none');

  planImg.onload = function(){
    planLoading.classList.add('d-none');
    planError.classList.add('d-none');
    planFrame.classList.remove('d-none');
  };
  planImg.onerror = function(){
    planLoading.classList.add('d-none');
    planFrame.classList.add('d-none');
    planError.classList.remove('d-none');
    planErrorPath.textContent = currentPlanUrl;
  };

  planImg.src = currentPlanUrl + "?v=" + Date.now();
  planModal.show();
}

document.getElementById('viewGoldBtn')?.addEventListener('click', () => openPlan('gold'));
document.getElementById('viewSilverBtn')?.addEventListener('click', () => openPlan('silver'));


</script>

<script>
  const incPicker = flatpickr('#inception_date', {
    dateFormat: 'Y-m-d',
    minDate: 'today',
    allowInput: true,
    clickOpens: true,
    disableMobile: true,
    position: 'auto',
    onOpen: function(selectedDates, dateStr, instance){
      instance.calendarContainer.style.zIndex = 9999;
    }
  });
  document.getElementById('inc_btn').addEventListener('click', function(){ incPicker.open(); });
</script>
</body>
</html>
