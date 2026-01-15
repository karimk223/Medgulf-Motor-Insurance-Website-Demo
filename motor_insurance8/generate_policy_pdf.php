<?php
session_start();
if(!isset($_SESSION['employee_id'])){
    http_response_code(401);
    die('Unauthorized');
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

include 'db_connection.php';
require_once __DIR__ . '/policy_pdf.php';

if(!isset($_GET['request_id'])){
    http_response_code(400);
    die('Request ID missing');
}

$request_id = (int)$_GET['request_id'];

$stmt = $conn->prepare("
    SELECT 
        r.request_id,
        r.inception_date,
        r.processed,
        r.status,
        r.car_value,
        r.year_built,
        r.allrisk_plan,
        r.package_option,
        c.first_name, c.middle_name, c.last_name, c.phone, c.email, c.address,
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
    $r_status,
    $r_car_value,
    $r_year_built,
    $r_allrisk_plan,
    $r_package_option,
    $c_first_name, $c_middle_name, $c_last_name, $c_phone, $c_email, $c_address,
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
    $types[] = trim((string)$insurance_type);
}
$stmt->close();

function normPlan($s){
    $s = strtolower(trim((string)$s));
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
    return 3.00;
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

function getCoverageRows($type){
    $t = strtolower(trim((string)$type));

    if($t === strtolower('Obligatory')){
        return [
            ['Third Party Bodily Injury 500,000']
        ];
    }

    if($t === strtolower('Third Party Liability')){
        return [
            ['Third Party Material Damage 500,000'],
            ['Medical Expenses of the Passenger']
        ];
    }

    if($t === strtolower('All Risk')){
        return [
            ['Third Party Material Damage 1,000,000'],
            ['Material Own Damage of the Insured Vehicle'],
            ['Fire of the Vehicle'],
            ['Theft of the Insured Vehicle'],
            ['Hold Up of the Insured Vehicle'],
            ['Medical Expenses of the Passenger'],
            ['Bodily Injury of the Passengers']
        ];
    }

    return [];
}

$types = array_values(array_filter($types, function($x){ return $x !== ''; }));
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

$total = $base + $allRiskPremium;

$client_name = trim($c_first_name.' '.$c_middle_name.' '.$c_last_name);
if($client_name === '') $client_name = '—';

$employee_name = trim(($emp_first ?? '').' '.($emp_last ?? ''));
if($employee_name === '') $employee_name = '—';

$issue_date = date('Y-m-d');
$exp_date = date('Y-m-d', strtotime($r_inception_date . ' +1 year'));

$pdf = new PolicyPDF('P','mm','A4');
$pdf->SetTitle('Policy '.$r_request_id);
$pdf->SetAuthor('MedGulf Demo');
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 20);

$pdf->SectionTitle('Policy');
$pdf->KeyValueRow('Policy #', (string)$r_request_id, 'Status', (string)($r_status ?: ($r_processed ? 'Processed' : 'Pending')));
$pdf->KeyValueRow('Issue Date', $issue_date, 'Processed By', $employee_name);
$pdf->KeyValueRow('Inception', (string)$r_inception_date, 'Expiration', $exp_date);

$pdf->Ln(2);
$pdf->SectionTitle('Client & Vehicle');
$pdf->KeyValueRow('Client', $client_name, 'Phone', trim((string)$c_phone) !== '' ? (string)$c_phone : '—');
$pdf->KeyValueRow('Email', trim((string)$c_email) !== '' ? (string)$c_email : '—', 'Chassis', trim((string)$v_vehicle_number) !== '' ? (string)$v_vehicle_number : '—');
$pdf->KeyValueRow('Address', trim((string)$c_address) !== '' ? (string)$c_address : '—', 'Plate', trim((string)$v_license_plate) !== '' ? (string)$v_license_plate : '—');
$pdf->KeyValueRow('Model', trim((string)$v_model_car) !== '' ? (string)$v_model_car : '—');

$pdf->Ln(2);
$pdf->SectionTitle('Coverage');
$pdf->SetFont('Arial','B',9);
$pdf->Cell(0,6,'Selected Types: '.(count($types)?implode(', ', $types):'—'),0,1,'L');
$pdf->Ln(1);

$pdf->TableHeader('Coverage', 'Sum Insured');
foreach($types as $t){
    $rows = getCoverageRows($t);
    if(!$rows) continue;

    $pdf->SetFont('Arial','B',9);
    $pdf->Cell(0,7,$t,0,1,'L');
    foreach($rows as $r){
        $pdf->TableRow('  - '.$r[0], $r[1]);
    }
    $pdf->Ln(2);
}

$pdf->Ln(1);
$pdf->SectionTitle('Premium Summary');
$pdf->KeyValueRow('Base Premium', $pdf->Money($base), 'Total Premium', $pdf->Money($total));
if($hasAll){
    $planOut = normPlan($r_allrisk_plan);
    $pdf->KeyValueRow('All Risk Premium', $pdf->Money($allRiskPremium), 'All Risk Plan', $planOut !== '' ? $planOut : '—');
    $pdf->KeyValueRow('Car Value', $pdf->Money((float)$r_car_value), 'Year Built', (string)((int)$r_year_built));
}

$pdf->Output('I', 'policy_'.$r_request_id.'_'.time().'.pdf');
exit;
?>
