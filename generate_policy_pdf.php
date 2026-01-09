<?php
session_start();
if(!isset($_SESSION['employee_id'])){
    http_response_code(401);
    die('Unauthorized');
}

include 'db_connection.php';
require_once __DIR__ . '/simple_pdf.php';

if(!isset($_GET['request_id'])){
    http_response_code(400);
    die('Request ID missing');
}

$request_id = intval($_GET['request_id']);

$stmt = $conn->prepare("
    SELECT 
        r.request_id,
        r.inception_date,
        r.processed,
        r.car_value,
        r.year_built,
        r.allrisk_plan,
        c.first_name, c.middle_name, c.last_name, c.date_of_birth, c.phone, c.email, c.address,
        v.model_car, v.license_plate, v.vehicle_number,
        e.first_name AS emp_first,
        e.last_name  AS emp_last
    FROM requests r
    JOIN clients c ON r.client_id = c.client_id
    JOIN vehicles v ON r.vehicle_id = v.vehicle_id
    LEFT JOIN employees e ON r.processed_by = e.employee_id
    WHERE r.request_id = ?
");
$stmt->bind_param("i", $request_id);
$stmt->execute();
$stmt->store_result();

if($stmt->num_rows !== 1){
    http_response_code(404);
    die('Request not found');
}

$stmt->bind_result(
    $r_request_id,
    $r_inception_date,
    $r_processed,
    $r_car_value,
    $r_year_built,
    $r_allrisk_plan,
    $c_first_name, $c_middle_name, $c_last_name, $c_dob, $c_phone, $c_email, $c_address,
    $v_model_car, $v_license_plate, $v_vehicle_number,
    $emp_first, $emp_last
);
$stmt->fetch();
$stmt->close();

$stmt = $conn->prepare("
    SELECT insurance_type
    FROM request_insurance_types
    WHERE request_id = ?
    ORDER BY insurance_type ASC
");
$stmt->bind_param("i", $request_id);
$stmt->execute();
$stmt->bind_result($insurance_type);

$types = [];
while($stmt->fetch()){
    $types[] = $insurance_type;
}
$stmt->close();

function normPlan($s){
    $s = trim((string)$s);
    $s = strtolower($s);
    if($s === 'gold') return 'Gold';
    if($s === 'silver') return 'Silver';
    return '';
}

function allRiskRateGold($year, $value){
    $y = (int)$year;
    $v = (float)$value;

    if($y >= 2023 && $y <= 2026){
        if($v <= 50000) return 2.60;
        if($v <= 100000) return 2.30;
        return 2.00;
    }

    if($y >= 2019 && $y <= 2022){
        if($v <= 50000) return 2.80;
        if($v <= 100000) return 2.40;
        return 2.15;
    }

    if($y >= 2010 && $y <= 2018){
        if($v <= 50000) return 3.40;
        if($v <= 100000) return 3.20;
        return 2.75;
    }

    if($v <= 50000) return 3.40;
    if($v <= 100000) return 3.20;
    return 2.75;
}

function allRiskRateSilver($year){
    $y = (int)$year;

    if($y >= 2023 && $y <= 2026) return 2.40;
    if($y >= 2019 && $y <= 2022) return 2.70;
    if($y >= 2008 && $y <= 2018) return 3.00;

    if($y < 2008) return 3.00;
    return 2.40;
}

function calcAllRiskPremium($plan, $yearBuilt, $carValue){
    $plan = normPlan($plan);
    $year = (int)$yearBuilt;
    $val  = (float)$carValue;

    if($val <= 0 || $year <= 0) return 0.0;

    if($plan === 'Gold'){
        $rate = allRiskRateGold($year, $val);
        $p = $val * ($rate / 100.0);
        if($p < 600) $p = 600;
        return $p;
    }

    if($plan === 'Silver'){
        $rate = allRiskRateSilver($year);
        $p = $val * ($rate / 100.0);
        if($p < 500) $p = 500;
        return $p;
    }

    return 0.0;
}

$hasObl = in_array('Obligatory', $types, true);
$hasTpl = in_array('Third Party Liability', $types, true);
$hasAll = in_array('All Risk', $types, true);

$base = 0.0;
if($hasObl && $hasTpl){
    $base = 100.0;
} else {
    if($hasObl) $base += 45.0;
    if($hasTpl) $base += 60.0;
}

$allRiskPremium = 0.0;
if($hasAll){
    $allRiskPremium = calcAllRiskPremium($r_allrisk_plan, $r_year_built, $r_car_value);
}

$premium = $base + $allRiskPremium;

$client_name = trim($c_first_name . ' ' . $c_middle_name . ' ' . $c_last_name);
$employee_name = trim($emp_first . ' ' . $emp_last);
if($employee_name === '') $employee_name = '—';

$expiration_date = date('Y-m-d', strtotime($r_inception_date . ' +1 year'));

$pdf = new SimplePDF();

$pdf->addLine(60, 760, 18, 'Motor Insurance Policy');
$pdf->addLine(60, 738, 10, 'MedGulf Internship Demo - System Generated');

$pdf->addLine(60, 708, 12, 'Policy / Request #: ' . $r_request_id);
$pdf->addLine(60, 690, 12, 'Issue Date: ' . date('Y-m-d'));
$pdf->addLine(60, 672, 12, 'Inception Date: ' . $r_inception_date);
$pdf->addLine(60, 654, 12, 'Expiration Date: ' . $expiration_date);

$pdf->addLine(60, 624, 14, 'Client Information');
$pdf->addLine(60, 604, 12, 'Name: ' . $client_name);
$pdf->addLine(60, 588, 12, 'Email: ' . $c_email);
$pdf->addLine(60, 572, 12, 'Phone: ' . $c_phone);
$pdf->addLine(60, 556, 12, 'Address: ' . $c_address);

$pdf->addLine(60, 526, 14, 'Vehicle Information');
$pdf->addLine(60, 506, 12, 'Model: ' . $v_model_car);
$pdf->addLine(60, 490, 12, 'License Plate: ' . $v_license_plate);
$pdf->addLine(60, 474, 12, 'Vehicle Number: ' . ($v_vehicle_number ?? ''));

$pdf->addLine(60, 444, 14, 'Insurance Details');
$pdf->addLine(60, 424, 12, 'Types: ' . (count($types) ? implode(', ', $types) : '—'));

$y = 408;
if($hasAll){
    $planOut = normPlan($r_allrisk_plan);
    $valOut = is_null($r_car_value) ? '' : (string)$r_car_value;
    $yrOut  = is_null($r_year_built) ? '' : (string)$r_year_built;

    $pdf->addLine(60, $y, 12, 'All Risk Plan: ' . ($planOut !== '' ? $planOut : '—'));
    $y -= 16;
    $pdf->addLine(60, $y, 12, 'Car Value: $' . number_format((float)$r_car_value, 2) . '   Year Built: ' . (int)$r_year_built);
    $y -= 16;
    $pdf->addLine(60, $y, 12, 'All Risk Premium: $' . number_format($allRiskPremium, 2));
    $y -= 16;
}

$pdf->addLine(60, $y, 12, 'Total Premium: $' . number_format($premium, 2));

$footer_y = 360;
$pdf->addLine(60, $footer_y, 10, 'Status: ' . ($r_processed ? 'Processed' : 'Pending'));
$pdf->addLine(60, $footer_y - 14, 10, 'Processed By: ' . $employee_name);
$pdf->addLine(60, $footer_y - 34, 9, 'This document is system-generated for demonstration purposes and valid without signature.');

$pdf->output('policy_' . $r_request_id . '.pdf');
?>
