<?php
session_start();
if(!isset($_SESSION['employee_id'])){
    header("Location: employee_login.php");
    exit;
}
include 'db_connection.php';

$employee_id = intval($_SESSION['employee_id']);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

if(isset($_GET['ajax_emp_notifs']) && $_GET['ajax_emp_notifs'] == '1'){
    header('Content-Type: text/html; charset=utf-8');

    $stmt = $conn->prepare("
      SELECT notification_id, request_id, title, message, is_read, created_at
      FROM employee_notifications
      ORDER BY notification_id DESC
      LIMIT 80
    ");
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();

    if(empty($rows)){
        echo "<div class='p-4 text-muted'>No notifications yet.</div>";
        exit;
    }

    foreach($rows as $n){
        $nid = (int)$n['notification_id'];
        $rid = (int)($n['request_id'] ?? 0);
        $title = h($n['title'] ?? 'Notification');
        $msg = h($n['message'] ?? '');
        $dt = h($n['created_at'] ?? '');
        $unread = ((int)($n['is_read'] ?? 0) === 0);

        $cls = $unread ? "notif-item unread" : "notif-item";
        $dataRid = $rid > 0 ? "data-request-id='{$rid}'" : "";

        echo "
          <div class='{$cls}' data-notif-id='{$nid}' {$dataRid}>
            <div class='d-flex justify-content-between align-items-start gap-3'>
              <div>
                <div class='notif-title'>{$title}</div>
                <div class='notif-msg'>{$msg}</div>
                <div class='notif-date'>{$dt}</div>
              </div>
              ".($unread ? "<span class='notif-dot'></span>" : "")."
            </div>
          </div>
        ";
    }
    exit;
}

if(isset($_GET['ajax_emp_mark_notifs']) && $_GET['ajax_emp_mark_notifs'] == '1'){
    header('Content-Type: application/json; charset=utf-8');

    $stmt = $conn->prepare("UPDATE employee_notifications SET is_read=1 WHERE is_read=0");
    $stmt->execute();
    $stmt->close();

    echo json_encode(["ok" => true]);
    exit;
}

if(isset($_GET['ajax_emp_notif_count']) && $_GET['ajax_emp_notif_count'] == '1'){
    header('Content-Type: application/json; charset=utf-8');

    $cnt = 0;
    $stmt = $conn->prepare("SELECT COUNT(*) FROM employee_notifications WHERE is_read=0");
    $stmt->execute();
    $stmt->bind_result($cnt);
    $stmt->fetch();
    $stmt->close();

    echo json_encode(["count" => (int)$cnt]);
    exit;
}

$stmt = $conn->prepare("SELECT first_name, last_name, email FROM employees WHERE employee_id=?");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$stmt->bind_result($efn,$eln,$eemail);
$stmt->fetch();
$stmt->close();

$emp_name = trim($efn.' '.$eln);

$res = $conn->query("
  SELECT 
    r.request_id, r.client_id, r.inception_date, r.status, r.processed, r.processed_by,
    r.rejected_reason,
    c.first_name, c.last_name,
    v.license_plate, v.model_car
  FROM requests r
  JOIN clients c ON r.client_id = c.client_id
  JOIN vehicles v ON r.vehicle_id = v.vehicle_id
  ORDER BY r.request_id DESC
");

$emp_total = 0;
$emp_pending = 0;
$emp_processed = 0;
$emp_rejected = 0;
$tmp = $conn->query("SELECT status, processed FROM requests");
if($tmp){
    while($r = $tmp->fetch_assoc()){
        $emp_total++;
        $st = $r['status'] ?? '';
        if($st === 'Rejected') $emp_rejected++;
        elseif($st === 'Processed' || (int)$r['processed'] === 1) $emp_processed++;
        else $emp_pending++;
    }
    $tmp->close();
}

$emp_notif_count = 0;
$stmt = $conn->prepare("SELECT COUNT(*) FROM employee_notifications WHERE is_read=0");
$stmt->execute();
$stmt->bind_result($emp_notif_count);
$stmt->fetch();
$stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Staff Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/app.css?v=9999" rel="stylesheet">

  <style>
    #searchBoxEmp::placeholder{
      color: rgba(255,255,255,.92) !important;
      opacity: 1 !important;
    }

    #searchBoxEmp{
      width: 560px;
      max-width: 92vw;
    }

    .notif-btn{
      position: relative;
      width: 46px;
      min-width: 46px;
      height: 46px;
      border-radius: 14px;
      border: 1px solid rgba(255,207,110,.32);
      background: rgba(0,0,0,.28);
      color: #fff;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: transform .14s ease, border-color .14s ease, background .14s ease;
    }
    .notif-btn:hover{
      transform: translateY(-2px);
      border-color: rgba(255,207,110,.60);
      background: rgba(255,207,110,.10);
      color: var(--gold2);
    }
    .notif-badge{
      position: absolute;
      top: -6px;
      right: -6px;
      min-width: 20px;
      height: 20px;
      padding: 0 6px;
      border-radius: 999px;
      background: linear-gradient(90deg,#ff2d2d,#b30000);
      color: #fff;
      font-size: 12px;
      font-weight: 950;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border: 2px solid rgba(16,17,20,.92);
    }

    .notif-overlay{
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,.55);
      z-index: 99980;
      display: none;
    }
    .notif-panel{
      position: fixed;
      top: 90px;
      right: 22px;
      width: 460px;
      max-width: 92vw;
      max-height: 72vh;
      z-index: 99990;
      display: none;
      background: rgba(16,17,20,.92);
      border: 1px solid rgba(255,207,110,.25);
      border-radius: 18px;
      box-shadow: 0 30px 120px rgba(0,0,0,.70);
      overflow: hidden;
    }
    .notif-panel.open{ display:block; }
    .notif-overlay.open{ display:block; }

    .notif-head{
      padding: 14px 16px;
      border-bottom: 1px solid rgba(255,207,110,.18);
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap: 12px;
    }
    .notif-head .ttl{
      color: var(--gold2);
      font-weight: 950;
      margin: 0;
      font-size: 15px;
    }
    .notif-x{
      width: 44px;
      height: 44px;
      border-radius: 14px;
      border: 1px solid rgba(255,207,110,.28);
      background: rgba(0,0,0,.35);
      color: #fff;
      font-size: 18px;
      font-weight: 950;
      cursor: pointer;
    }
    .notif-x:hover{
      background: rgba(255,207,110,.18);
      color: var(--gold2);
    }
    .notif-body{
      overflow: auto;
      max-height: calc(72vh - 58px);
    }
    .notif-item{
      padding: 14px 16px;
      border-bottom: 1px solid rgba(255,207,110,.12);
      cursor: pointer;
      transition: background .14s ease;
    }
    .notif-item:hover{ background: rgba(255,207,110,.10); }
    .notif-item.unread{ background: rgba(255,207,110,.06); }
    .notif-title{ font-weight: 950; letter-spacing: -0.01em; }
    .notif-msg{ color: var(--muted); font-size: 13px; margin-top: 4px; }
    .notif-date{ color: rgba(255,255,255,.75); font-size: 12px; margin-top: 6px; }
    .notif-dot{
      width: 10px;
      height: 10px;
      border-radius: 999px;
      background: rgba(255,207,110,.95);
      box-shadow: 0 0 0 4px rgba(255,207,110,.16);
      margin-top: 4px;
    }

    @media (max-width: 720px){
      .notif-panel{ right: 10px; left: 10px; width: auto; }
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
        <div class="pro-topbar">
          <div>
            <h2 class="pro-title">Staff Dashboard</h2>
            <div class="pro-sub">
              Logged in as: <b><?= h($emp_name) ?></b> • ID: <b><?= (int)$employee_id ?></b> • <?= h($eemail) ?>
            </div>
          </div>

          <div class="pro-actions">
            <button class="notif-btn" id="empNotifBtn" type="button" aria-label="Notifications" title="Notifications">
              🔔
              <?php if((int)$emp_notif_count > 0): ?>
                <span class="notif-badge" id="empNotifBadge"><?= (int)$emp_notif_count ?></span>
              <?php else: ?>
                <span class="notif-badge" id="empNotifBadge" style="display:none;"></span>
              <?php endif; ?>
            </button>

            <a class="btn btn-secondary" href="employee_logout.php">Logout</a>
          </div>
        </div>
      </div>

      <div class="pro-card-body">

        <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
          <div class="d-flex gap-2 align-items-center flex-wrap">
            <input id="searchBoxEmp" class="form-control"
                   placeholder="Search (Client ID, Client First and Last name)">
          </div>

          <div class="d-flex gap-2 flex-wrap">
            <span class="badge" style="background: rgba(255,255,255,.10); border:1px solid rgba(255,207,110,.25);">
              Total: <b><?= (int)$emp_total ?></b>
            </span>
            <span class="badge badge-warn">Pending: <b><?= (int)$emp_pending ?></b></span>
            <span class="badge badge-ok">Processed: <b><?= (int)$emp_processed ?></b></span>
            <span class="badge badge-reject">Rejected: <b><?= (int)$emp_rejected ?></b></span>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table align-middle" id="empTable">
            <thead>
              <tr>
                <th>ID</th>
                <th>Client ID</th>
                <th>Client</th>
                <th>Vehicle</th>
                <th>Plate</th>
                <th>Inception</th>
                <th>Status</th>
                <th>Note</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
            <?php while($row = $res->fetch_assoc()): ?>
              <tr data-client-id="<?= (int)$row['client_id'] ?>">
                <td><?= (int)$row['request_id'] ?></td>
                <td><?= (int)$row['client_id'] ?></td>
                <td><?= h($row['first_name'].' '.$row['last_name']) ?></td>
                <td><?= h($row['model_car']) ?></td>
                <td><?= h($row['license_plate']) ?></td>
                <td><?= h($row['inception_date']) ?></td>
                <td>
                  <?php
                    $status = $row['status'] ?? 'Pending';
                    if($status === 'Processed') echo '<span class="badge badge-ok">Processed</span>';
                    elseif($status === 'Rejected') echo '<span class="badge badge-reject">Rejected</span>';
                    else echo '<span class="badge badge-warn">Pending</span>';
                  ?>
                </td>
                <td style="max-width:260px;">
                  <?= ($status==='Rejected') ? h($row['rejected_reason'] ?? '') : '' ?>
                </td>
                <td>
                  <a class="btn btn-primary btn-sm" href="view_request.php?request_id=<?= (int)$row['request_id'] ?>">View</a>
                </td>
              </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
</div>

<div class="notif-overlay" id="empNotifOverlay"></div>
<div class="notif-panel" id="empNotifPanel">
  <div class="notif-head">
    <div class="ttl">Notifications</div>
    <button class="notif-x" id="empNotifX" type="button" aria-label="Close">✕</button>
  </div>
  <div class="notif-body" id="empNotifBody">
    <div class="p-4 text-muted">Loading...</div>
  </div>
</div>

<script>
const searchBoxEmp = document.getElementById('searchBoxEmp');
if(searchBoxEmp){
  searchBoxEmp.addEventListener('input', () => {
    const q = searchBoxEmp.value.trim().toLowerCase();
    const isNum = /^[0-9]+$/.test(q);
    const tokens = q.split(/\s+/).filter(Boolean);

    document.querySelectorAll('#empTable tbody tr').forEach(tr => {
      const name = (tr.children[2]?.innerText || '').toLowerCase();
      const clientId = (tr.getAttribute('data-client-id') || '').toLowerCase();

      let show = true;

      if(q !== ''){
        if(isNum){
          show = clientId === q;
        } else {
          show = tokens.every(t => name.includes(t));
        }
      }

      tr.style.display = show ? '' : 'none';
    });
  });
}

const empNotifBtn = document.getElementById('empNotifBtn');
const empNotifOverlay = document.getElementById('empNotifOverlay');
const empNotifPanel = document.getElementById('empNotifPanel');
const empNotifX = document.getElementById('empNotifX');
const empNotifBody = document.getElementById('empNotifBody');
const empNotifBadge = document.getElementById('empNotifBadge');

function openEmpNotifs(){
  empNotifOverlay.classList.add('open');
  empNotifPanel.classList.add('open');
}
function closeEmpNotifs(){
  empNotifOverlay.classList.remove('open');
  empNotifPanel.classList.remove('open');
}
empNotifX.addEventListener('click', closeEmpNotifs);
empNotifOverlay.addEventListener('click', closeEmpNotifs);

async function refreshEmpNotifCount(){
  try{
    const res = await fetch('employee_dashboard.php?ajax_emp_notif_count=1&t=' + Date.now());
    const j = await res.json();
    const c = (j && typeof j.count !== 'undefined') ? parseInt(j.count,10) : 0;
    if(c > 0){
      empNotifBadge.style.display = 'inline-flex';
      empNotifBadge.textContent = c;
    } else {
      empNotifBadge.style.display = 'none';
      empNotifBadge.textContent = '';
    }
  }catch(e){}
}

async function loadEmpNotifs(){
  empNotifBody.innerHTML = "<div class='p-4 text-muted'>Loading...</div>";
  openEmpNotifs();

  try{
    const res = await fetch('employee_dashboard.php?ajax_emp_notifs=1&t=' + Date.now());
    const html = await res.text();
    empNotifBody.innerHTML = html;

    await fetch('employee_dashboard.php?ajax_emp_mark_notifs=1&t=' + Date.now());
    await refreshEmpNotifCount();

  }catch(e){
    empNotifBody.innerHTML = "<div class='p-4 text-danger'>Failed to load.</div>";
  }
}

empNotifBtn.addEventListener('click', loadEmpNotifs);

empNotifBody.addEventListener('click', (e) => {
  const item = e.target.closest('.notif-item');
  if(!item) return;
  const rid = item.getAttribute('data-request-id');
  if(rid){
    window.location.href = 'view_request.php?request_id=' + encodeURIComponent(rid);
  }
});

document.addEventListener('keydown', (e) => {
  if(e.key === 'Escape'){
    closeEmpNotifs();
  }
});

setInterval(refreshEmpNotifCount, 20000);
</script>

</body>
</html>
