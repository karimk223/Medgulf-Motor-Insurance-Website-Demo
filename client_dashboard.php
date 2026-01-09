<?php
session_start();
if(!isset($_SESSION['client_id'])){
    header("Location: client_login.php");
    exit;
}

include 'db_connection.php';
$client_id = (int)$_SESSION['client_id'];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function calcAllRiskPremium($carValue, $yearBuilt, $packageOption){
    $v = (float)$carValue;
    $y = (int)$yearBuilt;
    $plan = ($packageOption === 'Gold' || $packageOption === 'Silver') ? $packageOption : 'Gold';

    if($y < 2010){
        $plan = 'Silver';
    } elseif($v > 25000){
        $plan = 'Gold';
    }

    if($v <= 0){
        return [0.0, $plan, 0.0, 0.0];
    }

    if($plan === 'Gold'){
        $min = 600.0;
        $rate = 0.0;

        if($y >= 2023 && $y <= 2026){
            if($v <= 50000) $rate = 2.60;
            elseif($v <= 100000) $rate = 2.30;
            else $rate = 2.00;
        } elseif($y >= 2019 && $y <= 2022){
            if($v <= 50000) $rate = 2.80;
            elseif($v <= 100000) $rate = 2.40;
            else $rate = 2.15;
        } elseif($y >= 2010 && $y <= 2018){
            if($v <= 50000) $rate = 3.40;
            elseif($v <= 100000) $rate = 3.20;
            else $rate = 2.75;
        } else {
            if($v <= 50000) $rate = 3.40;
            elseif($v <= 100000) $rate = 3.20;
            else $rate = 2.75;
        }

        $p = ($v * $rate) / 100.0;
        if($p < $min) $p = $min;
        return [$p, $plan, $rate, $min];
    }

    $min = 500.0;
    $rate = 0.0;

    if($y >= 2023 && $y <= 2026) $rate = 2.40;
    elseif($y >= 2019 && $y <= 2022) $rate = 2.70;
    elseif($y >= 2008 && $y <= 2018) $rate = 3.00;
    else $rate = 3.00;

    $p = ($v * $rate) / 100.0;
    if($p < $min) $p = $min;
    return [$p, 'Silver', $rate, $min];
}

function calcTotalPremium($types, $carValue, $yearBuilt, $packageOption){
    $hasObl = in_array('Obligatory', $types);
    $hasTpl = in_array('Third Party Liability', $types);
    $hasAr  = in_array('All Risk', $types);

    $base = 0.0;
    if($hasObl && $hasTpl) $base += 100.0;
    else {
        if($hasObl) $base += 45.0;
        if($hasTpl) $base += 60.0;
    }

    $allRiskPremium = 0.0;
    $effPlan = ($packageOption === 'Gold' || $packageOption === 'Silver') ? $packageOption : '—';
    $effRate = 0.0;
    $effMin  = 0.0;

    if($hasAr){
        [$allRiskPremium, $effPlan, $effRate, $effMin] = calcAllRiskPremium($carValue, $yearBuilt, $packageOption);
        $base += $allRiskPremium;
    }

    return [$base, $allRiskPremium, $effPlan, $effRate, $effMin];
}

if(isset($_GET['ajax_notifs']) && $_GET['ajax_notifs'] == '1'){
    header('Content-Type: text/html; charset=utf-8');

    $stmt = $conn->prepare("
      SELECT notification_id, request_id, title, message, is_read, created_at
      FROM notifications
      WHERE client_id = ?
      ORDER BY notification_id DESC
      LIMIT 60
    ");
    $stmt->bind_param("i", $client_id);
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
        $title = h($n['title']);
        $msg = h($n['message']);
        $dt = h($n['created_at']);
        $unread = ((int)$n['is_read'] === 0);

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

if(isset($_GET['ajax_mark_notifs']) && $_GET['ajax_mark_notifs'] == '1'){
    header('Content-Type: application/json; charset=utf-8');

    $stmt = $conn->prepare("UPDATE notifications SET is_read=1 WHERE client_id=? AND is_read=0");
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(["ok" => true]);
    exit;
}

if(isset($_GET['ajax_notif_count']) && $_GET['ajax_notif_count'] == '1'){
    header('Content-Type: application/json; charset=utf-8');

    $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE client_id=? AND is_read=0");
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $stmt->bind_result($cnt);
    $stmt->fetch();
    $stmt->close();

    echo json_encode(["count" => (int)$cnt]);
    exit;
}

if(isset($_GET['ajax_policies'])){
    header('Content-Type: text/html; charset=utf-8');

    $mode = $_GET['ajax_policies'];
    if($mode !== 'ongoing' && $mode !== 'expired'){
        http_response_code(400);
        echo "<div class='p-4'>Invalid.</div>";
        exit;
    }

    $today = date('Y-m-d');

    $where = ($mode === 'ongoing')
        ? "DATE_ADD(r.inception_date, INTERVAL 1 YEAR) >= ?"
        : "DATE_ADD(r.inception_date, INTERVAL 1 YEAR) < ?";

    $stmt = $conn->prepare("
      SELECT
        r.request_id,
        r.inception_date,
        DATE_ADD(r.inception_date, INTERVAL 1 YEAR) AS expiry_date,
        v.model_car,
        v.license_plate,
        v.vehicle_number,
        GROUP_CONCAT(DISTINCT rit.insurance_type ORDER BY rit.insurance_type SEPARATOR ', ') AS insurance_types
      FROM requests r
      JOIN vehicles v ON r.vehicle_id = v.vehicle_id
      LEFT JOIN request_insurance_types rit ON rit.request_id = r.request_id
      WHERE r.client_id = ?
        AND (r.status='Processed' OR r.processed=1)
        AND {$where}
      GROUP BY r.request_id, r.inception_date, expiry_date, v.model_car, v.license_plate, v.vehicle_number
      ORDER BY r.request_id DESC
      LIMIT 200
    ");
    $stmt->bind_param("is", $client_id, $today);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();

    if(empty($rows)){
        if($mode === 'ongoing'){
            echo "<div class='p-4 text-muted'>No ongoing policies found.</div>";
        } else {
            echo "<div class='p-4 text-muted'>No expired policies found.</div>";
        }
        exit;
    }

    echo "<div class='p-3'>";
    foreach($rows as $p){
        $rid = (int)$p['request_id'];
        $inception = $p['inception_date'] ?? '';
        $expiry = $p['expiry_date'] ?? '';
        $model = $p['model_car'] ?? '';
        $plate = $p['license_plate'] ?? '';
        $vehno = $p['vehicle_number'] ?? '';
        $types = $p['insurance_types'] ?? '';

        $d1 = new DateTime($today);
        $d2 = new DateTime($expiry ?: $today);
        $days = (int)$d1->diff($d2)->format('%r%a');

        if($mode === 'ongoing'){
            $label = "Expires in: " . max(0, $days) . " day(s)";
            $chipClass = ($days <= 14) ? "chip warn" : "chip good";
        } else {
            $label = "Expired since: " . abs($days) . " day(s)";
            $chipClass = "chip warn";
        }

        echo "
          <div class='policy-item' data-request-id='{$rid}'>
            <div class='d-flex justify-content-between align-items-start gap-3'>
              <div>
                <div class='policy-ttl'>Request #{$rid}</div>
                <div class='policy-sub'>".h($model)." • Plate: ".h($plate)."</div>
                <div class='policy-meta'>Vehicle No: <b>".h($vehno ?: '—')."</b> • Types: <b>".h($types ?: '—')."</b></div>
                <div class='policy-dates'>Inception: <b>".h($inception)."</b> • Expiry: <b>".h($expiry)."</b></div>
              </div>
              <div class='{$chipClass}'>".h($label)."</div>
            </div>
          </div>
        ";
    }
    echo "</div>";
    exit;
}

if(isset($_GET['ajax']) && $_GET['ajax'] == '1'){
    header('Content-Type: text/html; charset=utf-8');

    $request_id = isset($_GET['request_id']) ? (int)$_GET['request_id'] : 0;
    if($request_id <= 0){
        http_response_code(400);
        echo "<div class='p-4'>Invalid request.</div>";
        exit;
    }

    $stmt = $conn->prepare("
        SELECT 
            r.request_id,
            r.inception_date,
            r.status,
            r.rejected_reason,
            r.rejected_at,
            r.car_value,
            r.year_built,
            r.package_option,
            v.model_car,
            v.license_plate,
            v.vehicle_number
        FROM requests r
        JOIN vehicles v ON r.vehicle_id = v.vehicle_id
        WHERE r.request_id = ? AND r.client_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $request_id, $client_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $req = $res->fetch_assoc();
    $stmt->close();

    if(!$req){
        http_response_code(404);
        echo "<div class='p-4'>Request not found.</div>";
        exit;
    }

    $types = [];
    $stmt = $conn->prepare("SELECT insurance_type FROM request_insurance_types WHERE request_id = ? ORDER BY insurance_type ASC");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $r2 = $stmt->get_result();
    while($t = $r2->fetch_assoc()){
        $types[] = $t['insurance_type'];
    }
    $stmt->close();

    $docs = null;
    $stmt = $conn->prepare("
        SELECT driving_license, driving_license2, car_log, car_log2, car_image1, car_image2, car_image3, car_image4
        FROM request_documents
        WHERE request_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $r3 = $stmt->get_result();
    $docs = $r3->fetch_assoc();
    $stmt->close();

    function imgCard($file){
        if(!$file) return "<div class='text-muted'>N/A</div>";
        $src = "images/uploads/" . htmlspecialchars($file);
        return "<img class='preview-img mt-2 js-preview' src='{$src}' data-src='{$src}' alt='upload'>";
    }

    $status = $req['status'] ?? 'Pending';
    $badge = "badge-warn";
    if($status === 'Processed') $badge = "badge-ok";
    if($status === 'Rejected') $badge = "badge-reject";

    $submitted_state = "done";
    $pending_state   = "done";
    $final_state     = ($status === 'Processed') ? "done" : (($status === 'Rejected') ? "reject" : "wait");

    $is_all_risk = in_array('All Risk', $types);

    [$totalPremium, $allRiskPremium, $effPlan, $effRate, $effMin] = calcTotalPremium(
        $types,
        $req['car_value'] ?? 0,
        $req['year_built'] ?? 0,
        $req['package_option'] ?? ''
    );
    ?>
    <div class="drawer-pad">
      <div class="d-flex justify-content-between align-items-start gap-3">
        <div>
          <div class="drawer-title">Request #<?= (int)$req['request_id'] ?></div>
          <div class="drawer-sub">
            Vehicle: <?= h($req['model_car']) ?> • Plate: <?= h($req['license_plate']) ?>
          </div>
        </div>
        <div>
          <span class="badge <?= $badge ?>"><?= h($status) ?></span>
        </div>
      </div>

      <div class="rq-timeline mt-3">
        <div class="rq-step <?= $submitted_state ?>">
          <div class="dot"></div>
          <div class="lbl">Submitted</div>
        </div>

        <div class="rq-line"></div>

        <div class="rq-step <?= $pending_state ?>">
          <div class="dot"></div>
          <div class="lbl">Pending</div>
        </div>

        <div class="rq-line"></div>

        <div class="rq-step <?= $final_state ?>">
          <div class="dot"></div>
          <div class="lbl"><?= ($status === 'Rejected') ? 'Rejected' : 'Processed' ?></div>
        </div>
      </div>

      <div class="drawer-divider"></div>

      <div class="row g-4">
        <div class="col-12">
          <h6 class="mb-2" style="font-weight:950;">Request Info</h6>
          <div class="drawer-kv"><b>Inception Date:</b> <?= h($req['inception_date']) ?></div>
          <div class="drawer-kv"><b>Vehicle Number:</b> <?= h(($req['vehicle_number'] ?? '') !== '' ? $req['vehicle_number'] : '—') ?></div>
          <div class="drawer-kv"><b>Year Built:</b> <?= h(($req['year_built'] ?? '') !== '' ? $req['year_built'] : '—') ?></div>
          <div class="drawer-kv"><b>Car Value:</b> $<?= number_format((float)($req['car_value'] ?? 0), 2) ?></div>
          <div class="drawer-kv"><b>Insurance Types:</b> <?= h(count($types) ? implode(", ", $types) : 'N/A') ?></div>

          <?php if($is_all_risk): ?>
            <div class="drawer-kv"><b>All Risk Plan:</b> <?= h($effPlan ?: '—') ?></div>
            <div class="drawer-kv"><b>All Risk Premium:</b> $<?= number_format((float)$allRiskPremium, 2) ?></div>
          <?php endif; ?>

          <div class="drawer-kv"><b>Total Premium:</b> $<?= number_format((float)$totalPremium, 2) ?></div>

          <?php if($status === 'Rejected'): ?>
            <div class="mt-3">
              <div class="drawer-kv"><b>Rejected Reason:</b> <?= h($req['rejected_reason'] ?? '') ?></div>
              <div class="drawer-kv"><b>Rejected At:</b> <?= h($req['rejected_at'] ?? '') ?></div>
            </div>
          <?php endif; ?>
        </div>

        <div class="col-12">
          <h6 class="mb-2" style="font-weight:950;">Documents</h6>

          <div class="row g-3">
            <div class="col-6">
              <div><b>Driving License 1</b></div>
              <?= $docs ? imgCard($docs['driving_license']) : "<div class='text-muted'>N/A</div>" ?>
            </div>
            <div class="col-6">
              <div><b>Driving License 2</b></div>
              <?= $docs ? imgCard($docs['driving_license2']) : "<div class='text-muted'>N/A</div>" ?>
            </div>
            <div class="col-6">
              <div><b>Car Log 1</b></div>
              <?= $docs ? imgCard($docs['car_log']) : "<div class='text-muted'>N/A</div>" ?>
            </div>
            <div class="col-6">
              <div><b>Car Log 2</b></div>
              <?= $docs ? imgCard($docs['car_log2']) : "<div class='text-muted'>N/A</div>" ?>
            </div>

            <?php if($is_all_risk): ?>
              <div class="col-12">
                <div class="mt-2"><b>Car Images (All Risk)</b></div>
                <div class="row g-3">
                  <div class="col-6 col-md-3"><?= $docs ? imgCard($docs['car_image1']) : "<div class='text-muted'>N/A</div>" ?></div>
                  <div class="col-6 col-md-3"><?= $docs ? imgCard($docs['car_image2']) : "<div class='text-muted'>N/A</div>" ?></div>
                  <div class="col-6 col-md-3"><?= $docs ? imgCard($docs['car_image3']) : "<div class='text-muted'>N/A</div>" ?></div>
                  <div class="col-6 col-md-3"><?= $docs ? imgCard($docs['car_image4']) : "<div class='text-muted'>N/A</div>" ?></div>
                </div>
              </div>
            <?php endif; ?>
          </div>

        </div>
      </div>
    </div>
    <?php
    exit;
}

$stmt = $conn->prepare("SELECT first_name, middle_name, last_name, email FROM clients WHERE client_id=?");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$stmt->bind_result($first_name, $middle_name, $last_name, $email);
$stmt->fetch();
$stmt->close();

$client_name = trim($first_name . ' ' . $middle_name . ' ' . $last_name);

$stmt = $conn->prepare("
    SELECT 
        r.request_id,
        r.inception_date,
        r.status,
        r.processed,
        v.model_car,
        v.license_plate,
        GROUP_CONCAT(DISTINCT rit.insurance_type ORDER BY rit.insurance_type SEPARATOR ', ') AS insurance_types
    FROM requests r
    JOIN vehicles v ON r.vehicle_id = v.vehicle_id
    LEFT JOIN request_insurance_types rit ON r.request_id = rit.request_id
    WHERE r.client_id = ?
    GROUP BY r.request_id, r.inception_date, r.status,
        r.processed, v.model_car, v.license_plate
    ORDER BY r.request_id DESC
");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$stmt->bind_result($request_id, $inception_date, $status, $processed, $model_car, $license_plate, $insurance_types);

$history = [];
while($stmt->fetch()){
    $history[] = [
        'request_id' => $request_id,
        'inception_date' => $inception_date,
        'status' => $status,
        'processed' => (int)$processed,
        'model_car' => $model_car,
        'license_plate' => $license_plate,
        'insurance_types' => $insurance_types ?: ''
    ];
}
$stmt->close();

$client_total = count($history);
$client_pending = 0;
$client_processed = 0;
$client_rejected = 0;
foreach($history as $hrow){
    $st = $hrow['status'] ?? '';
    if($st === 'Rejected') $client_rejected++;
    elseif($st === 'Processed' || (int)$hrow['processed'] === 1) $client_processed++;
    else $client_pending++;
}

$notif_count = 0;
$stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE client_id=? AND is_read=0");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$stmt->bind_result($notif_count);
$stmt->fetch();
$stmt->close();

$ongoing_count = 0;
$expired_count = 0;
$today = date('Y-m-d');
$stmt = $conn->prepare("
  SELECT
    SUM(CASE WHEN DATE_ADD(inception_date, INTERVAL 1 YEAR) >= ? THEN 1 ELSE 0 END) AS ongoing_cnt,
    SUM(CASE WHEN DATE_ADD(inception_date, INTERVAL 1 YEAR) <  ? THEN 1 ELSE 0 END) AS expired_cnt
  FROM requests
  WHERE client_id = ?
    AND (status='Processed' OR processed=1)
");
$stmt->bind_param("ssi", $today, $today, $client_id);
$stmt->execute();
$stmt->bind_result($ongoing_count, $expired_count);
$stmt->fetch();
$stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Client Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
    <style>
      .req-row{
        cursor:pointer;
        transition: transform .14s ease, background .14s ease, box-shadow .14s ease;
      }
      .req-row:hover{
        transform: translateY(-1px);
        background: rgba(255,207,110,.12) !important;
      }
      #searchBoxClient::placeholder{
        color: rgba(255,255,255,.92) !important;
        opacity: 1 !important;
      }
      .chip{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        padding: 10px 12px;
        border-radius: 14px;
        font-weight: 950;
        font-size: 13px;
        letter-spacing: .01em;
        color: #fff !important;
        background: rgba(255,255,255,0.10) !important;
        border: 1px solid rgba(255,207,110,0.60) !important;
        text-shadow: 0 1px 0 rgba(0,0,0,.60);
        white-space: nowrap;
      }
      .chip.good{
        background: rgba(34,197,94,0.20) !important;
        border-color: rgba(34,197,94,0.75) !important;
      }
      .chip.warn{
        background: rgba(255,195,0,0.20) !important;
        border-color: rgba(255,195,0,0.80) !important;
      }
      .pol-overlay{
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,.55);
        z-index: 99980;
        display: none;
      }
      .pol-panel{
        position: fixed;
        top: 90px;
        right: 22px;
        width: 720px;
        max-width: 96vw;
        max-height: 76vh;
        z-index: 99990;
        display: none;
        background: rgba(16,17,20,.92);
        border: 1px solid rgba(255,207,110,.25);
        border-radius: 18px;
        box-shadow: 0 30px 120px rgba(0,0,0,.70);
        overflow: hidden;
      }
      .pol-panel.open{ display:block; }
      .pol-overlay.open{ display:block; }
      .pol-head{
        padding: 14px 16px;
        border-bottom: 1px solid rgba(255,207,110,.18);
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap: 12px;
      }
      .pol-head .ttl{
        color: var(--gold2);
        font-weight: 950;
        margin: 0;
        font-size: 15px;
      }
      .pol-x{
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
      .pol-x:hover{
        background: rgba(255,207,110,.18);
        color: var(--gold2);
      }
      .pol-body{
        overflow:auto;
        max-height: calc(76vh - 58px);
      }
      .policy-item{
        padding: 14px 14px;
        margin: 10px;
        border-radius: 16px;
        border: 1px solid rgba(255,207,110,.16);
        background: rgba(0,0,0,.18);
        cursor: pointer;
        transition: transform .14s ease, background .14s ease, border-color .14s ease;
      }
      .policy-item:hover{
        transform: translateY(-1px);
        background: rgba(255,207,110,.10);
        border-color: rgba(255,207,110,.30);
      }
      .policy-ttl{ font-weight: 950; font-size: 15px; }
      .policy-sub{ color: var(--muted); font-size: 13px; margin-top: 3px; }
      .policy-meta{ color: rgba(255,255,255,.85); font-size: 12.5px; margin-top: 6px; }
      .policy-dates{ color: rgba(255,255,255,.85); font-size: 12.5px; margin-top: 6px; }
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
        width: 420px;
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
      .notif-item:hover{
        background: rgba(255,207,110,.10);
      }
      .notif-item.unread{
        background: rgba(255,207,110,.06);
      }
      .notif-title{
        font-weight: 950;
        letter-spacing: -0.01em;
      }
      .notif-msg{
        color: var(--muted);
        font-size: 13px;
        margin-top: 4px;
      }
      .notif-date{
        color: rgba(255,255,255,.60);
        font-size: 12px;
        margin-top: 6px;
      }
      .notif-dot{
        width: 10px;
        height: 10px;
        border-radius: 999px;
        background: rgba(255,207,110,.95);
        box-shadow: 0 0 0 4px rgba(255,207,110,.16);
        margin-top: 4px;
      }
      .drawer-backdrop{
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,.55);
        z-index: 99980;
        display: none;
      }
      .drawer{
        position: fixed;
        top: 0;
        right: 0;
        height: 100vh;
        width: 560px;
        max-width: 92vw;
        background: rgba(16,17,20,.92);
        border-left: 1px solid rgba(255,207,110,.25);
        box-shadow: 0 30px 120px rgba(0,0,0,.70);
        z-index: 99990;
        transform: translateX(100%);
        transition: transform .18s ease;
        display: flex;
        flex-direction: column;
      }
      .drawer.open{ transform: translateX(0); }
      .drawer-backdrop.open{ display:block; }
      .drawer-head{
        padding: 16px 18px;
        border-bottom: 1px solid rgba(255,207,110,.18);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
      }
      .drawer-head .ttl{
        color: var(--gold2);
        font-weight: 950;
        margin: 0;
        font-size: 16px;
        letter-spacing: -0.02em;
      }
      .drawer-x{
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
      .drawer-x:hover{
        background: rgba(255,207,110,.18);
        color: var(--gold2);
      }
      .drawer-body{ overflow: auto; padding: 0; }
      .drawer-pad{ padding: 16px 18px 22px; }
      .drawer-title{ font-weight: 950; font-size: 20px; letter-spacing:-0.02em; }
      .drawer-sub{ color: var(--muted); font-size: 13px; margin-top: 4px; }
      .drawer-divider{ height: 1px; background: rgba(255,207,110,.18); margin: 14px 0 16px; }
      .drawer-kv{ margin-top: 6px; }
      .rq-timeline{
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 12px;
        border: 1px solid rgba(255,207,110,.18);
        border-radius: 16px;
        background: rgba(0,0,0,.18);
      }
      .rq-step{
        display:flex;
        align-items:center;
        gap: 8px;
        min-width: 92px;
        justify-content: center;
        flex-direction: column;
        text-align:center;
      }
      .rq-step .dot{
        width: 12px;
        height: 12px;
        border-radius: 999px;
        border: 2px solid rgba(255,207,110,.45);
        background: rgba(255,207,110,.08);
      }
      .rq-step .lbl{
        font-size: 12px;
        color: var(--muted);
        font-weight: 800;
      }
      .rq-line{ flex: 1; height: 2px; background: rgba(255,207,110,.20); border-radius: 999px; }
      .rq-step.done .dot{ background: rgba(255,207,110,.95); border-color: rgba(255,207,110,.95); }
      .rq-step.done .lbl{ color: var(--text); }
      .rq-step.wait .dot{ background: rgba(255,195,0,.35); border-color: rgba(255,195,0,.70); }
      .rq-step.reject .dot{ background: #ff2d2d; border-color: #ff2d2d; }
      .rq-step.reject .lbl{ color: #ff9a9a; }
      .img-overlay{
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,.78);
        z-index: 100000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 18px;
      }
      .img-box{
        position: relative;
        max-width: 92vw;
        max-height: 88vh;
        background: rgba(16,17,20,.92);
        border: 1px solid rgba(255,207,110,.25);
        border-radius: 18px;
        padding: 14px;
        box-shadow: 0 30px 120px rgba(0,0,0,.75);
      }
      .img-box img{
        max-width: 88vw;
        max-height: 78vh;
        border-radius: 14px;
        display: block;
      }
      .img-x{
        position: absolute;
        top: 10px;
        right: 10px;
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
      .img-x:hover{
        background: rgba(255,207,110,.18);
        color: var(--gold2);
      }
      @media (max-width: 720px){
        .notif-panel{
          right: 10px;
          left: 10px;
          width: auto;
        }
        .pol-panel{
          right: 10px;
          left: 10px;
          width: auto;
        }
        .drawer{
          width: 100%;
          max-width: 100%;
          height: 82vh;
          top: auto;
          bottom: 0;
          right: 0;
          border-left: none;
          border-top: 1px solid rgba(255,207,110,.25);
          border-radius: 18px 18px 0 0;
          transform: translateY(110%);
        }
        .drawer.open{ transform: translateY(0); }
      }
    </style>
</head>
<body class="app-bg">

<div class="bg-fixed"></div>
<div class="aura"></div>
<div class="app-shell">
<div class="pro-container">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <div class="pro-title">Client Dashboard</div>
            <div class="pro-sub">Welcome, <?= h($client_name ?: 'Client'); ?> • <?= h($email ?: ''); ?></div>
        </div>

        <div class="d-flex gap-2 flex-wrap align-items-center">

            <button class="notif-btn" id="notifBtn" type="button" aria-label="Notifications" title="Notifications">
              🔔
              <?php if((int)$notif_count > 0): ?>
                <span class="notif-badge" id="notifBadge"><?= (int)$notif_count ?></span>
              <?php else: ?>
                <span class="notif-badge" id="notifBadge" style="display:none;"></span>
              <?php endif; ?>
            </button>

            <button class="btn btn-secondary" id="btnOngoing" type="button">
              Ongoing (<?= (int)$ongoing_count ?>)
            </button>
            <button class="btn btn-secondary" id="btnExpired" type="button">
              Expired (<?= (int)$expired_count ?>)
            </button>

            <a href="client_request_form.php" class="btn btn-primary">+ New Request</a>
            <a href="client_logout.php" class="btn btn-secondary">Logout</a>
        </div>
    </div>

    <div class="pro-card">
        <div class="pro-card-header">
            <div class="pro-title mb-0">Request History</div>
            <div class="pro-sub">All your past insurance requests are shown here.</div>
        </div>
        <div class="pro-divider"></div>
        <div class="pro-card-body">

            <?php if(isset($_GET['submitted']) && $_GET['submitted'] == 1): ?>
                <div class="alert alert-success">Request submitted successfully.</div>
            <?php endif; ?>

            <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
              <div class="d-flex gap-2 align-items-center flex-wrap">
                <input id="searchBoxClient" class="form-control" style="width: 420px; height: 52px; font-size: 16px;"
                       placeholder="Search (Request ID)">
              </div>

              <div class="d-flex gap-2 flex-wrap">
                <span class="badge" style="background: rgba(255,255,255,.10); border:1px solid rgba(255,207,110,.25);">
                  Total: <b><?= (int)$client_total ?></b>
                </span>
                <span class="badge badge-warn">Pending: <b><?= (int)$client_pending ?></b></span>
                <span class="badge badge-ok">Processed: <b><?= (int)$client_processed ?></b></span>
                <span class="badge badge-reject">Rejected: <b><?= (int)$client_rejected ?></b></span>
              </div>
            </div>

            <?php if(empty($history)): ?>
                <div class="alert alert-info mb-0">No requests yet. Click <strong>New Request</strong> to create one.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle" id="clientTable">
                        <thead>
                            <tr>
                                <th>Request #</th>
                                <th>Vehicle</th>
                                <th>Plate</th>
                                <th>Insurance Types</th>
                                <th>Inception Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach($history as $row): ?>
                            <tr class="req-row" data-request-id="<?= (int)$row['request_id']; ?>">
                                <td><?= (int)$row['request_id']; ?></td>
                                <td><?= h($row['model_car']); ?></td>
                                <td><?= h($row['license_plate']); ?></td>
                                <td><?= h($row['insurance_types']); ?></td>
                                <td><?= h($row['inception_date']); ?></td>
                                <td>
                                    <?php
                                        $st = $row['status'] ?? '';
                                        if($st === 'Rejected'){
                                            echo '<span class="badge badge-reject">Rejected</span>';
                                        } elseif($st === 'Processed' || $row['processed']){
                                            echo '<span class="badge badge-ok">Processed</span>';
                                        } else {
                                            echo '<span class="badge badge-warn">Pending</span>';
                                        }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        </div>
    </div>

</div>
</div>

<div class="notif-overlay" id="notifOverlay"></div>
<div class="notif-panel" id="notifPanel">
  <div class="notif-head">
    <div class="ttl">Notifications</div>
    <button class="notif-x" id="notifX" type="button" aria-label="Close">✕</button>
  </div>
  <div class="notif-body" id="notifBody">
    <div class="p-4 text-muted">Loading...</div>
  </div>
</div>

<div class="pol-overlay" id="polOverlay"></div>
<div class="pol-panel" id="polPanel">
  <div class="pol-head">
    <div class="ttl" id="polTitle">Policies</div>
    <button class="pol-x" id="polX" type="button" aria-label="Close">✕</button>
  </div>
  <div class="pol-body" id="polBody">
    <div class="p-4 text-muted">Loading...</div>
  </div>
</div>

<div class="drawer-backdrop" id="drawerBackdrop"></div>

<div class="drawer" id="drawer">
  <div class="drawer-head">
    <div class="ttl">Request Details</div>
    <button class="drawer-x" id="drawerX" type="button" aria-label="Close">✕</button>
  </div>
  <div class="drawer-body" id="drawerBody">
    <div class="p-4">Select a request...</div>
  </div>
</div>

<div class="img-overlay" id="imgOverlay">
  <div class="img-box">
    <button class="img-x" id="imgX" type="button" aria-label="Close">✕</button>
    <img id="imgBig" src="" alt="preview">
  </div>
</div>

<script>
const drawer = document.getElementById('drawer');
const drawerBackdrop = document.getElementById('drawerBackdrop');
const drawerBody = document.getElementById('drawerBody');
const drawerX = document.getElementById('drawerX');

function openDrawer(){
  drawer.classList.add('open');
  drawerBackdrop.classList.add('open');
}
function closeDrawer(){
  drawer.classList.remove('open');
  drawerBackdrop.classList.remove('open');
}
drawerX.addEventListener('click', closeDrawer);
drawerBackdrop.addEventListener('click', closeDrawer);

async function loadRequest(rid){
  drawerBody.innerHTML = "<div class='p-4'>Loading...</div>";
  openDrawer();
  try{
    const res = await fetch('client_dashboard.php?ajax=1&request_id=' + encodeURIComponent(rid) + '&t=' + Date.now());
    const html = await res.text();
    drawerBody.innerHTML = html;
  }catch(e){
    drawerBody.innerHTML = "<div class='p-4 text-danger'>Failed to load request.</div>";
  }
}
document.querySelectorAll('.req-row').forEach(row => {
  row.addEventListener('click', () => {
    const rid = row.getAttribute('data-request-id');
    loadRequest(rid);
  });
});

const searchBoxClient = document.getElementById('searchBoxClient');
if(searchBoxClient){
  searchBoxClient.addEventListener('input', () => {
    const q = searchBoxClient.value.trim().toLowerCase();
    const isNum = /^[0-9]+$/.test(q);

    document.querySelectorAll('#clientTable tbody tr.req-row').forEach(tr => {
      const rid = (tr.getAttribute('data-request-id') || '').toLowerCase();
      const txt = tr.innerText.toLowerCase();

      let show = true;

      if(q !== ''){
        if(isNum){
          show = rid === q;
        } else {
          show = txt.includes(q);
        }
      }

      tr.style.display = show ? '' : 'none';
    });
  });
}

document.addEventListener('click', (e) => {
  const img = e.target.closest('.js-preview');
  if(!img) return;
  const src = img.getAttribute('data-src') || img.getAttribute('src');
  document.getElementById('imgBig').src = src;
  document.getElementById('imgOverlay').style.display = 'flex';
});
function closeImg(){
  document.getElementById('imgOverlay').style.display = 'none';
  document.getElementById('imgBig').src = '';
}
document.getElementById('imgX').addEventListener('click', closeImg);
document.getElementById('imgOverlay').addEventListener('click', (e) => {
  if(e.target.id === 'imgOverlay') closeImg();
});

const notifBtn = document.getElementById('notifBtn');
const notifOverlay = document.getElementById('notifOverlay');
const notifPanel = document.getElementById('notifPanel');
const notifX = document.getElementById('notifX');
const notifBody = document.getElementById('notifBody');
const notifBadge = document.getElementById('notifBadge');

function openNotifs(){
  notifOverlay.classList.add('open');
  notifPanel.classList.add('open');
}
function closeNotifs(){
  notifOverlay.classList.remove('open');
  notifPanel.classList.remove('open');
}
notifX.addEventListener('click', closeNotifs);
notifOverlay.addEventListener('click', closeNotifs);

async function refreshNotifCount(){
  try{
    const res = await fetch('client_dashboard.php?ajax_notif_count=1&t=' + Date.now());
    const j = await res.json();
    const c = (j && typeof j.count !== 'undefined') ? parseInt(j.count,10) : 0;
    if(c > 0){
      notifBadge.style.display = 'inline-flex';
      notifBadge.textContent = c;
    } else {
      notifBadge.style.display = 'none';
      notifBadge.textContent = '';
    }
  }catch(e){}
}

async function loadNotifs(){
  notifBody.innerHTML = "<div class='p-4 text-muted'>Loading...</div>";
  openNotifs();

  try{
    const res = await fetch('client_dashboard.php?ajax_notifs=1&t=' + Date.now());
    const html = await res.text();
    notifBody.innerHTML = html;

    await fetch('client_dashboard.php?ajax_mark_notifs=1&t=' + Date.now());
    await refreshNotifCount();

  }catch(e){
    notifBody.innerHTML = "<div class='p-4 text-danger'>Failed to load notifications.</div>";
  }
}
notifBtn.addEventListener('click', loadNotifs);

notifBody.addEventListener('click', (e) => {
  const item = e.target.closest('.notif-item');
  if(!item) return;
  const rid = item.getAttribute('data-request-id');
  if(rid){
    closeNotifs();
    loadRequest(rid);
  }
});

const polOverlay = document.getElementById('polOverlay');
const polPanel   = document.getElementById('polPanel');
const polX       = document.getElementById('polX');
const polBody    = document.getElementById('polBody');
const polTitle   = document.getElementById('polTitle');
const btnOngoing = document.getElementById('btnOngoing');
const btnExpired = document.getElementById('btnExpired');

function openPolicies(){
  polOverlay.classList.add('open');
  polPanel.classList.add('open');
}
function closePolicies(){
  polOverlay.classList.remove('open');
  polPanel.classList.remove('open');
}
polX.addEventListener('click', closePolicies);
polOverlay.addEventListener('click', closePolicies);

async function loadPolicies(mode){
  polBody.innerHTML = "<div class='p-4 text-muted'>Loading...</div>";
  polTitle.textContent = (mode === 'ongoing') ? 'Ongoing Policies' : 'Expired Policies';
  openPolicies();
  try{
    const res = await fetch('client_dashboard.php?ajax_policies=' + encodeURIComponent(mode) + '&t=' + Date.now());
    const html = await res.text();
    polBody.innerHTML = html;
  }catch(e){
    polBody.innerHTML = "<div class='p-4 text-danger'>Failed to load.</div>";
  }
}
btnOngoing.addEventListener('click', () => loadPolicies('ongoing'));
btnExpired.addEventListener('click', () => loadPolicies('expired'));

polBody.addEventListener('click', (e) => {
  const item = e.target.closest('.policy-item');
  if(!item) return;
  const rid = item.getAttribute('data-request-id');
  if(rid){
    closePolicies();
    loadRequest(rid);
  }
});

document.addEventListener('keydown', (e) => {
  if(e.key === 'Escape'){
    closeDrawer();
    closeNotifs();
    closePolicies();
    closeImg();
  }
});

setInterval(refreshNotifCount, 20000);
</script>

</body>
</html>
<?php $conn->close(); ?>
