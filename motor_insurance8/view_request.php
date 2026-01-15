<?php
session_start();
if(!isset($_SESSION['employee_id'])){
    header("Location: employee_login.php");
    exit;
}

include 'db_connection.php';
require_once __DIR__ . '/simple_pdf.php';

$request_id = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;
if($request_id <= 0){
    die("Invalid request id");
}

$employee_id = intval($_SESSION['employee_id']);

function createNotification($conn, $client_id, $request_id, $title, $message){
    $stmt = $conn->prepare("INSERT INTO notifications (client_id, request_id, title, message, is_read) VALUES (?, ?, ?, ?, 0)");
    $stmt->bind_param("iiss", $client_id, $request_id, $title, $message);
    $stmt->execute();
    $stmt->close();
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function naText($v){
    $s = trim((string)$v);
    if($s === '') return 'N/A';
    $low = strtolower($s);
    if($low === 'a' || $low === 'n/a' || $low === 'na' || $low === '-' || $low === '—') return 'N/A';
    return $s;
}

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
    $hasObl = in_array('Obligatory', $types, true);
    $hasTpl = in_array('Third Party Liability', $types, true);
    $hasAr  = in_array('All Risk', $types, true);

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

function getCoverageByType($type){
    if($type === 'Obligatory'){
        return [
            'Third Party Bodily Injury USD 500,000'
        ];
    }
    if($type === 'Third Party Liability'){
        return [
            'Third Party Material Damage USD 500,000',
            'Medical Expenses of the Passenger'
        ];
    }
    if($type === 'All Risk'){
        return [
            'Third Party Material Damage  USD 1,000,000',
            'Material Own Damage of the Insured Vehicle',
            'Fire of the Vehicle',
            'Theft of the Insured Vehicle',
            'Hold Up of the Insured Vehicle',
            'Medical Expenses of the Passenger',
            'Bodily Injury of the Passengers'
        ];
    }
    return [];
}

function cleanOut(){
    if(function_exists('ob_get_level')){
        while(ob_get_level() > 0){
            ob_end_clean();
        }
    }
}

if(isset($_GET['pdf']) && $_GET['pdf'] == 1){

    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);

    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    $stmt = $conn->prepare("
        SELECT 
            r.request_id,
            r.inception_date,
            r.processed,
            r.status,
            r.processed_by,
            r.rejected_by,
            r.rejected_reason,
            r.rejected_at,
            r.car_value,
            r.year_built,
            r.package_option,
            c.first_name, c.middle_name, c.last_name, c.date_of_birth, c.phone, c.email, c.address,
            v.model_car, v.license_plate, v.vehicle_number,
            pe.first_name AS p_emp_first, pe.last_name AS p_emp_last
        FROM requests r
        JOIN clients c ON r.client_id = c.client_id
        JOIN vehicles v ON r.vehicle_id = v.vehicle_id
        LEFT JOIN employees pe ON r.processed_by = pe.employee_id
        WHERE r.request_id = ?
    ");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $stmt->store_result();

    if($stmt->num_rows !== 1){
        $stmt->close();
        http_response_code(404);
        die("Request not found");
    }

    $stmt->bind_result(
        $r_id, $r_inception, $r_processed, $r_status, $r_processed_by,
        $r_rejected_by, $r_rejected_reason, $r_rejected_at,
        $r_car_value, $r_year_built, $r_package_option,
        $c_fn, $c_mn, $c_ln, $c_dob, $c_phone, $c_email, $c_address,
        $v_model, $v_plate, $v_number,
        $p_emp_fn, $p_emp_ln
    );
    $stmt->fetch();
    $stmt->close();

    $stmt = $conn->prepare("SELECT insurance_type FROM request_insurance_types WHERE request_id = ? ORDER BY insurance_type ASC");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $stmt->bind_result($insurance_type);
    $types = [];
    while($stmt->fetch()){
        $types[] = $insurance_type;
    }
    $stmt->close();

    $types = array_values(array_filter(array_map(function($x){
        $x = trim((string)$x);
        $x = preg_replace('/\s+/', ' ', $x);
        return $x;
    }, $types), function($x){ return $x !== ''; }));

    $is_all_risk = in_array('All Risk', $types, true);

    [$basePremium, $allRiskPremium, $effPlan, $effRate, $effMin] = calcTotalPremium($types, $r_car_value, $r_year_built, $r_package_option);
    $totalPremium = (float)$basePremium;

    $full_name = naText(trim(($c_fn ?? '') . ' ' . ($c_mn ?? '') . ' ' . ($c_ln ?? '')));
    $processed_by_name = naText(trim(($p_emp_fn ?? '') . ' ' . ($p_emp_ln ?? '')));

    $exp_date = 'N/A';
    if(!empty($r_inception)){
        $dt = date_create($r_inception);
        if($dt){
            date_add($dt, date_interval_create_from_date_string("1 year"));
            $exp_date = date_format($dt, "Y-m-d");
        }
    }

    $modelOut = naText($v_model);
    $plateOut = naText($v_plate);
    $vehNoOut = naText($v_number);
    $emailOut = naText($c_email);
    $phoneOut = naText($c_phone);
    $addrOut  = naText($c_address);

    $issueDate = date('Y-m-d');

    cleanOut();
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="policy_'.$r_id.'.pdf"');

    $pdf = new SimplePDF();

    $L = 60;
    $C = 305;
    $R = 545;

    $pdf->addLine($L, 772, 20, 'MOTOR INSURANCE POLICY');
    $pdf->addLine($L, 754, 10, 'MedGulf Internship Demo - System Generated');
    $pdf->addLine($L, 744, 9, str_repeat('_', 96));

    $pdf->addLine($L, 722, 12, 'Policy / Request #: ' . $r_id);
    $pdf->addLine($C, 722, 12, 'Status: ' . (($r_status ?? 'Pending') !== '' ? ($r_status ?? 'Pending') : 'Pending'));

    $pdf->addLine($L, 706, 12, 'Issue Date: ' . $issueDate);
    $pdf->addLine($C, 706, 12, 'Processed By: ' . $processed_by_name);

    $pdf->addLine($L, 690, 12, 'Inception Date: ' . naText($r_inception));
    $pdf->addLine($C, 690, 12, 'Expiration Date: ' . $exp_date);

    $pdf->addLine($L, 674, 9, str_repeat('_', 96));

    $pdf->addLine($L, 652, 14, 'CLIENT');
    $pdf->addLine($C, 652, 14, 'VEHICLE');

    $pdf->addLine($L, 634, 12, 'Name: ' . $full_name);
    $pdf->addLine($C, 634, 12, 'Model: ' . $modelOut);

    $pdf->addLine($L, 618, 12, 'Email: ' . $emailOut);
    $pdf->addLine($C, 618, 12, 'Plate: ' . $plateOut);

    $pdf->addLine($L, 602, 12, 'Phone: ' . $phoneOut);
    $pdf->addLine($C, 602, 12, 'Chassis: ' . $vehNoOut);

    $pdf->addLine($L, 586, 12, 'Address: ' . $addrOut);

    $pdf->addLine($L, 568, 9, str_repeat('_', 96));

    $pdf->addLine($L, 546, 14, 'INSURANCE DETAILS');

    $typesOut = count($types) ? implode(', ', $types) : 'N/A';
    $pdf->addLine($L, 528, 12, 'Selected Types: ' . $typesOut);

    $y = 506;
    $pdf->addLine($L, $y, 14, 'COVERAGE');
    $y -= 18;

    foreach($types as $t){
        $cov = getCoverageByType($t);
        if(empty($cov)) continue;

        $pdf->addLine($L, $y, 12, strtoupper($t));
        $y -= 14;

        foreach($cov as $line){
            $pdf->addLine($L + 16, $y, 11, '- ' . $line);
            $y -= 12;
        }

        $y -= 8;
    }

    if($is_all_risk){
        $y -= 2;
        $pdf->addLine($L, $y, 14, 'ALL RISK DETAILS');
        $y -= 18;

        $planOut = ($effPlan === 'Gold' || $effPlan === 'Silver') ? $effPlan : 'N/A';
        $pdf->addLine($L, $y, 12, 'Plan: ' . $planOut);
        $y -= 16;

        $yrOut = naText($r_year_built);
        $valOut = number_format((float)$r_car_value, 2);
        $pdf->addLine($L, $y, 12, 'Year Built: ' . $yrOut);
        $pdf->addLine($C, $y, 12, 'Car Value: $' . $valOut);
        $y -= 16;

        $pdf->addLine($L, $y, 12, 'All Risk Premium: $' . number_format((float)$allRiskPremium, 2));
        $y -= 10;
    }

    $y -= 6;
    $y -= 18;

    $pdf->addLine($L, $y, 12, 'Total Premium: $' . number_format((float)$totalPremium, 2));

    $pdf->addLine($L, 110, 9, str_repeat('_', 96));
    $pdf->addLine($L, 92, 9, 'This document is system-generated for demonstration purposes and valid without signature.');

    $pdf->output('policy_' . $r_id . '.pdf');
    exit;
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])){
    $rid = intval($_POST['request_id']);

    $stmt = $conn->prepare("SELECT status, client_id FROM requests WHERE request_id = ?");
    $stmt->bind_param("i", $rid);
    $stmt->execute();
    $stmt->bind_result($status_now, $notif_client_id);

    if(!$stmt->fetch()){
        $stmt->close();
        header("Location: employee_dashboard.php");
        exit;
    }
    $stmt->close();

    if($status_now === 'Rejected'){
        $stmt = $conn->prepare("
          UPDATE requests
          SET status='Pending',
              processed=0,
              processed_by=NULL,
              rejected_by=NULL,
              rejected_reason=NULL,
              rejected_at=NULL
          WHERE request_id=?
        ");
        $stmt->bind_param("i", $rid);
        $stmt->execute();
        $stmt->close();

        createNotification($conn, (int)$notif_client_id, $rid, "Request Updated", "Request #{$rid} was moved back to Pending.");

        header("Location: view_request.php?request_id=".$rid."&updated=1");
        exit;
    }

    if($status_now === 'Pending'){
        $stmt = $conn->prepare("UPDATE requests SET status='Processed', processed=1, processed_by=? WHERE request_id=?");
        $stmt->bind_param("ii", $employee_id, $rid);
        $stmt->execute();
        $stmt->close();

        createNotification($conn, (int)$notif_client_id, $rid, "Request Processed", "Your request #{$rid} has been processed.");

    } else {
        $stmt = $conn->prepare("UPDATE requests SET status='Pending', processed=0, processed_by=NULL WHERE request_id=?");
        $stmt->bind_param("i", $rid);
        $stmt->execute();
        $stmt->close();

        createNotification($conn, (int)$notif_client_id, $rid, "Request Updated", "Request #{$rid} was marked back to Pending.");
    }

    header("Location: view_request.php?request_id=".$rid."&updated=1");
    exit;
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_request'])){
    $rid = intval($_POST['request_id']);
    $reason = trim($_POST['reject_reason'] ?? '');
    if($reason === ''){
        $reason = 'Rejected by employee.';
    }

    $stmt = $conn->prepare("SELECT client_id FROM requests WHERE request_id=?");
    $stmt->bind_param("i", $rid);
    $stmt->execute();
    $stmt->bind_result($notif_client_id);
    $stmt->fetch();
    $stmt->close();

    $stmt = $conn->prepare("
      UPDATE requests
      SET status='Rejected',
          processed=0,
          processed_by=NULL,
          rejected_by=?,
          rejected_reason=?,
          rejected_at=NOW()
      WHERE request_id=?
    ");
    $stmt->bind_param("isi", $employee_id, $reason, $rid);
    $stmt->execute();
    $stmt->close();

    createNotification($conn, (int)$notif_client_id, $rid, "Request Rejected", "Your request #{$rid} was rejected. Reason: {$reason}");

    header("Location: view_request.php?request_id=".$rid."&updated=1");
    exit;
}

$stmt = $conn->prepare("
    SELECT
        r.request_id,
        r.inception_date,
        r.status,
        r.processed,
        r.processed_by,
        r.rejected_by,
        r.rejected_reason,
        r.rejected_at,
        r.car_value,
        r.year_built,
        r.package_option,
        c.first_name, c.middle_name, c.last_name, c.phone, c.email,
        v.model_car, v.license_plate, v.vehicle_number,
        pe.first_name AS p_emp_first, pe.last_name AS p_emp_last,
        re.first_name AS r_emp_first, re.last_name AS r_emp_last
    FROM requests r
    JOIN clients c ON r.client_id = c.client_id
    JOIN vehicles v ON r.vehicle_id = v.vehicle_id
    LEFT JOIN employees pe ON r.processed_by = pe.employee_id
    LEFT JOIN employees re ON r.rejected_by = re.employee_id
    WHERE r.request_id = ?
");
$stmt->bind_param("i", $request_id);
$stmt->execute();
$stmt->store_result();

if($stmt->num_rows !== 1){
    $stmt->close();
    die("Request not found");
}

$stmt->bind_result(
    $r_id, $r_inception, $r_status, $r_processed, $r_processed_by,
    $r_rejected_by, $r_rejected_reason, $r_rejected_at,
    $r_car_value, $r_year_built, $r_package_option,
    $c_fn, $c_mn, $c_ln, $c_phone, $c_email,
    $v_model, $v_plate, $v_number,
    $p_emp_fn, $p_emp_ln,
    $r_emp_fn, $r_emp_ln
);
$stmt->fetch();
$stmt->close();

$client_name = trim($c_fn.' '.$c_mn.' '.$c_ln);
$processed_by_name = trim(($p_emp_fn ?? '').' '.($p_emp_ln ?? ''));
$rejected_by_name = trim(($r_emp_fn ?? '').' '.($r_emp_ln ?? ''));

$stmt = $conn->prepare("SELECT insurance_type FROM request_insurance_types WHERE request_id = ? ORDER BY insurance_type ASC");
$stmt->bind_param("i", $request_id);
$stmt->execute();
$stmt->bind_result($t);
$types = [];
while($stmt->fetch()){ $types[] = $t; }
$stmt->close();

$stmt = $conn->prepare("
    SELECT driving_license, driving_license2, car_log, car_log2, car_image1, car_image2, car_image3, car_image4
    FROM request_documents
    WHERE request_id = ?
");
$stmt->bind_param("i", $request_id);
$stmt->execute();
$stmt->store_result();

$docs = null;
if($stmt->num_rows === 1){
    $stmt->bind_result($dl1,$dl2,$cl1,$cl2,$ci1,$ci2,$ci3,$ci4);
    $stmt->fetch();
    $docs = ['dl1'=>$dl1,'dl2'=>$dl2,'cl1'=>$cl1,'cl2'=>$cl2,'ci1'=>$ci1,'ci2'=>$ci2,'ci3'=>$ci3,'ci4'=>$ci4];
}
$stmt->close();

$exp_date = '';
if(!empty($r_inception)){
    $dt = date_create($r_inception);
    if($dt){
        date_add($dt, date_interval_create_from_date_string("1 year"));
        $exp_date = date_format($dt, "Y-m-d");
    }
}

function imgTag($file){
    if(!$file) return "<div class='text-muted'>N/A</div>";
    $src = "images/uploads/" . htmlspecialchars($file);
    return "<img class='preview-img mt-2 js-preview' src='{$src}' data-src='{$src}' alt='upload'>";
}

$is_all_risk = in_array('All Risk', $types, true);
[$totalPremium, $allRiskPremium, $effPlan, $effRate, $effMin] = calcTotalPremium($types, $r_car_value, $r_year_built, $r_package_option);
?>
<!DOCTYPE html>
<html>
<head>
    <title>View Request</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/app.css?v=9999" rel="stylesheet">
    <style>
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
      .js-preview{ cursor: zoom-in; }
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
            <h2 class="pro-title">Request #<?= h($r_id) ?></h2>
            <div class="pro-sub">Vehicle: <?= h($v_model) ?> • Plate: <?= h($v_plate) ?></div>
          </div>

          <div class="pro-actions">
            <a href="employee_dashboard.php" class="btn btn-secondary">Back</a>

            <form method="POST" class="d-inline">
              <input type="hidden" name="toggle_status" value="1">
              <input type="hidden" name="request_id" value="<?= (int)$request_id ?>">
              <?php if(($r_status ?? 'Pending') === 'Processed'): ?>
                <button type="submit" class="btn btn-secondary">Mark Pending</button>
              <?php elseif(($r_status ?? 'Pending') === 'Rejected'): ?>
                <button type="submit" class="btn btn-secondary">Unreject (Back to Pending)</button>
              <?php else: ?>
                <button type="submit" class="btn btn-primary">Mark Processed</button>
              <?php endif; ?>
            </form>

            <?php if(($r_status ?? 'Pending') !== 'Rejected'): ?>
              <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#rejectModal">Reject</button>
            <?php endif; ?>

            <button type="button" class="btn btn-primary" id="openPdfBtn" data-request-id="<?= (int)$request_id ?>">
              Generate Policy PDF
            </button>
          </div>
        </div>

        <div class="pro-divider"></div>

        <?php if(isset($_GET['updated'])): ?>
          <div class="alert alert-success mb-0">Updated successfully.</div>
        <?php endif; ?>
      </div>

      <div class="pro-card-body">
        <div class="row g-4">
          <div class="col-md-6">
            <h5 class="mb-2">Client</h5>
            <div><b>Name:</b> <?= h($client_name) ?></div>
            <div><b>Email:</b> <?= h($c_email) ?></div>
            <div><b>Phone:</b> <?= h($c_phone) ?></div>
          </div>

          <div class="col-md-6">
            <h5 class="mb-2">Request Info</h5>
            <div><b>Inception Date:</b> <?= h($r_inception) ?></div>
            <div><b>Expiration Date:</b> <?= h($exp_date ?: 'N/A') ?></div>
            <div><b>Insurance Types:</b> <?= h(implode(", ", $types)) ?></div>

            <?php if($is_all_risk): ?>
              <div><b>Year Built:</b> <?= h(($r_year_built ?? '') !== '' ? $r_year_built : '—') ?></div>
              <div><b>Car Value:</b> $<?= number_format((float)$r_car_value, 2) ?></div>
              <div><b>All Risk Plan:</b> <?= h($effPlan ?: '—') ?></div>
              <div><b>All Risk Premium:</b> $<?= number_format((float)$allRiskPremium, 2) ?></div>
            <?php endif; ?>

            <div class="mt-2"><b>Total Premium:</b> $<?= number_format((float)$totalPremium, 2) ?></div>

            <div class="mt-2">
              <b>Status:</b>
              <?php if(($r_status ?? 'Pending') === 'Processed'): ?>
                <span class="badge badge-ok">Processed</span>
              <?php elseif(($r_status ?? 'Pending') === 'Rejected'): ?>
                <span class="badge badge-reject">Rejected</span>
              <?php else: ?>
                <span class="badge badge-warn">Pending</span>
              <?php endif; ?>
            </div>

            <div class="mt-2">
              <b>Processed By:</b> <?= $processed_by_name !== '' ? h($processed_by_name) : "N/A" ?>
            </div>

            <?php if(($r_status ?? 'Pending') === 'Rejected'): ?>
              <div class="mt-2">
                <b>Rejected By:</b> <?= $rejected_by_name !== '' ? h($rejected_by_name) : "N/A" ?>
              </div>
              <div class="mt-2">
                <b>Rejected Reason:</b> <?= h($r_rejected_reason ?? '') ?>
              </div>
              <div class="mt-2">
                <b>Rejected At:</b> <?= h($r_rejected_at ?? '') ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <hr class="my-4" style="border-color: rgba(255,207,110,.20);">

        <h5 class="mb-3">Uploaded Documents</h5>

        <div class="row g-4">
          <div class="col-md-6">
            <div><b>Driving License 1</b></div>
            <?= $docs ? imgTag($docs['dl1']) : "<div class='text-muted'>N/A</div>" ?>
          </div>
          <div class="col-md-6">
            <div><b>Driving License 2</b></div>
            <?= $docs ? imgTag($docs['dl2']) : "<div class='text-muted'>N/A</div>" ?>
          </div>

          <div class="col-md-6">
            <div><b>Car Log 1</b></div>
            <?= $docs ? imgTag($docs['cl1']) : "<div class='text-muted'>N/A</div>" ?>
          </div>
          <div class="col-md-6">
            <div><b>Car Log 2</b></div>
            <?= $docs ? imgTag($docs['cl2']) : "<div class='text-muted'>N/A</div>" ?>
          </div>

          <?php if($is_all_risk): ?>
          <div class="col-12">
            <div><b>Car Images (All Risk)</b></div>
            <div class="row g-3">
              <div class="col-md-3"><?= $docs ? imgTag($docs['ci1']) : "<div class='text-muted'>N/A</div>" ?></div>
              <div class="col-md-3"><?= $docs ? imgTag($docs['ci2']) : "<div class='text-muted'>N/A</div>" ?></div>
              <div class="col-md-3"><?= $docs ? imgTag($docs['ci3']) : "<div class='text-muted'>N/A</div>" ?></div>
              <div class="col-md-3"><?= $docs ? imgTag($docs['ci4']) : "<div class='text-muted'>N/A</div>" ?></div>
            </div>
          </div>
          <?php endif; ?>
        </div>

      </div>
    </div>

  </div>
</div>

<div class="img-overlay" id="imgOverlay">
  <div class="img-box">
    <button class="img-x" id="imgX" type="button" aria-label="Close">✕</button>
    <img id="imgBig" src="" alt="preview">
  </div>
</div>

<div class="modal fade" id="pdfModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content" style="background: rgba(16,17,20,.92); border:1px solid rgba(255,207,110,.25); border-radius:18px;">
      <div class="modal-header" style="border-bottom:1px solid rgba(255,207,110,.18);">
        <h5 class="modal-title" style="color:#ffcf6e; font-weight:900;">Policy PDF Preview</h5>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
      <div class="modal-body p-0" style="height: 80vh;">
        <iframe id="pdfFrame" src="" style="width:100%; height:100%; border:0;"></iframe>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="background: rgba(16,17,20,.92); border:1px solid rgba(255,207,110,.25); border-radius:18px;">
      <form method="POST">
        <div class="modal-header" style="border-bottom:1px solid rgba(255,207,110,.18);">
          <h5 class="modal-title" style="color:#ffcf6e; font-weight:900;">Reject Request</h5>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="reject_request" value="1">
          <input type="hidden" name="request_id" value="<?= (int)$request_id ?>">
          <label class="form-label">Reason</label>
          <input class="form-control" name="reject_reason" >
        </div>
        <div class="modal-footer" style="border-top:1px solid rgba(255,207,110,.18);">
          <button type="submit" class="btn btn-primary">Confirm Reject</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('openPdfBtn').addEventListener('click', function(){
  const requestId = this.getAttribute('data-request-id');
  const iframe = document.getElementById('pdfFrame');
  iframe.src = 'view_request.php?request_id=' + encodeURIComponent(requestId) + '&pdf=1&t=' + Date.now();
  const modal = new bootstrap.Modal(document.getElementById('pdfModal'));
  modal.show();
});

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
document.addEventListener('keydown', (e) => {
  if(e.key === 'Escape') closeImg();
});
</script>

</body>
</html>
