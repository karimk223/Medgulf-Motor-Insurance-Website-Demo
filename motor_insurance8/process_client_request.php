<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if(!isset($_SESSION['client_id'])){
    header("Location: client_login.php");
    exit;
}
include 'db_connection.php';

$client_id = (int)($_SESSION['client_id'] ?? 0);

$upload_dir_fs = __DIR__ . '/images/uploads/';
if(!is_dir($upload_dir_fs)){
    @mkdir($upload_dir_fs, 0775, true);
}

function backWithErrors($errors, $old){
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_old'] = $old;
    header("Location: client_request_form.php");
    exit;
}

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    header("Location: client_request_form.php");
    exit;
}

$old = [
    'vehicle_type'    => $_POST['vehicle_type'] ?? '',
    'vehicle_number'  => $_POST['vehicle_number'] ?? '',
    'plate_letter'    => $_POST['plate_letter'] ?? '',
    'plate_numbers'   => $_POST['plate_numbers'] ?? '',
    'inception_date'  => $_POST['inception_date'] ?? '',
    'insurance_type'  => $_POST['insurance_type'] ?? [],
    'car_value'       => $_POST['car_value'] ?? '',
    'year_built'      => $_POST['year_built'] ?? '',
    'package_option'  => $_POST['package_option'] ?? '',
];

$errors = [];

$vehicle_type    = trim($old['vehicle_type']);
$vehicle_number = strtoupper(trim($old['vehicle_number']));
$vehicle_number = preg_replace('/\s+/', '', $vehicle_number);
$vehicle_number = preg_replace('/[^A-Z0-9]/', '', $vehicle_number);
$plate_letter    = trim($old['plate_letter']);
$plate_numbers   = trim($old['plate_numbers']);
$inception_date  = trim($old['inception_date']);
$insurance_types = $old['insurance_type'];
if(!is_array($insurance_types)) $insurance_types = [];

if($vehicle_type === '')   $errors['vehicle_type']   = "Vehicle type/model is required.";
if($vehicle_number === '') $errors['vehicle_number'] = "Chassis is required.";
if($inception_date === '') $errors['inception_date'] = "Inception date is required.";

$plate_letter_u = strtoupper($plate_letter);
$plate_numbers_u = preg_replace('/\s+/', '', $plate_numbers);


$has_letter = ($plate_letter_u !== '');
$has_numbers = ($plate_numbers_u !== '');

if(($has_letter && !$has_numbers) || (!$has_letter && $has_numbers)){
    $errors['plate_numbers'] = "Enter both plate letter and numbers, or leave both empty.";
}

$license_plate = null;
if($has_letter && $has_numbers){
    $license_plate = $plate_letter_u . $plate_numbers_u;
}

if(empty($insurance_types)){
    $errors['insurance_type'] = "Please select at least one insurance type.";
} else {
    if(count($insurance_types) > 2){
        $errors['insurance_type'] = "Cannot select more than 2 insurance types.";
    }
    if(in_array("All Risk", $insurance_types) && in_array("Third Party Liability", $insurance_types)){
        $errors['insurance_type'] = "Cannot select both All Risk and Third Party Liability.";
    }
}

$car_value_raw = isset($_POST['car_value']) ? str_replace(',', '', $_POST['car_value']) : '';
$car_value = (float)$car_value_raw;
$year_built = isset($_POST['year_built']) ? (int)$_POST['year_built'] : 0;
$package_option = isset($_POST['package_option']) ? trim($_POST['package_option']) : '';

if(in_array("All Risk", $insurance_types)){
    if(trim($car_value_raw) === '' || $car_value <= 0) $errors['car_value'] = "Car value is required.";
    if($year_built <= 0) $errors['year_built'] = "Year built is required.";
    if($package_option !== 'Gold' && $package_option !== 'Silver') $errors['package_option'] = "Please select Gold or Silver.";

    if($car_value > 25000 && $package_option === 'Silver'){
        $errors['package_option'] = "Silver is not allowed for car value above 25,000.";
    }
    if($year_built > 0 && $year_built < 2010 && $package_option === 'Gold'){
        $errors['package_option'] = "Gold is not allowed for cars built before 2010.";
    }
}

function countUploaded($arr){
    if(!isset($arr['error']) || !is_array($arr['error'])) return 0;
    $c = 0;
    foreach($arr['error'] as $e){
        if($e === UPLOAD_ERR_OK) $c++;
    }
    return $c;
}

$dl_count = isset($_FILES['driving_license']) ? countUploaded($_FILES['driving_license']) : 0;
$cl_count = isset($_FILES['car_log']) ? countUploaded($_FILES['car_log']) : 0;

if($dl_count < 2) $errors['driving_license'] = "Please upload 2 driving license images.";
if($cl_count < 2) $errors['car_log'] = "Please upload 2 car log images.";

if(in_array("All Risk", $insurance_types)){
    $car_count = isset($_FILES['allrisk_images']) ? countUploaded($_FILES['allrisk_images']) : 0;
    if($car_count < 4) $errors['allrisk_images'] = "All Risk requires 4 car images.";
}

if(!empty($errors)){
    $errors['general'] = "Please fix the highlighted fields and try again.";
    backWithErrors($errors, $old);
}

function saveUploadsExactCount($files, $prefix, $upload_dir_fs, $requiredCount, &$destArr, &$savedPaths){
    $okFiles = [];
    foreach($files['error'] as $i => $err){
        if($err === UPLOAD_ERR_OK){
            $okFiles[] = $i;
        }
    }
    if(count($okFiles) < $requiredCount){
        return "Not enough files uploaded.";
    }

    $destArr = array_fill(0, $requiredCount, NULL);

    for($k=0; $k<$requiredCount; $k++){
        $i = $okFiles[$k];

        $tmp  = $files['tmp_name'][$i];
        $name = $files['name'][$i];

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if($ext === '') $ext = 'img';

        $newName = $prefix . "_" . time() . "_" . bin2hex(random_bytes(6)) . "." . $ext;
        $destFs  = $upload_dir_fs . $newName;

        if(!move_uploaded_file($tmp, $destFs)){
            return "Move upload failed.";
        }

        $destArr[$k] = $newName;
        $savedPaths[] = $destFs;
    }

    return true;
}

$conn->begin_transaction();
$savedPaths = [];

try {

    $stmt = $conn->prepare("SELECT vehicle_id, client_id FROM vehicles WHERE vehicle_number=? LIMIT 1");
    $stmt->bind_param("s", $vehicle_number);
    $stmt->execute();
    $stmt->bind_result($found_vehicle_id, $found_client_id);
    $hasChassis = $stmt->fetch();
    $stmt->close();

    if($hasChassis){
        $vehicle_id = (int)$found_vehicle_id;
        $chassis_owner = (int)$found_client_id;

        if($chassis_owner !== (int)$client_id){
            $stmt = $conn->prepare("SELECT COUNT(*) FROM requests WHERE vehicle_id=? AND status IN ('Pending','Processed')");
            $stmt->bind_param("i", $vehicle_id);
            $stmt->execute();
            $stmt->bind_result($active_cnt);
            $stmt->fetch();
            $stmt->close();

            if((int)$active_cnt > 0){
                throw new Exception("This chassis is already under review/active for another client. You cannot use this chassis.");
            }

            if($license_plate === null){
                $stmt = $conn->prepare("UPDATE vehicles SET client_id=?, model_car=?, license_plate=NULL WHERE vehicle_id=?");
                $stmt->bind_param("isi", $client_id, $vehicle_type, $vehicle_id);
            } else {
                $stmt = $conn->prepare("UPDATE vehicles SET client_id=?, model_car=?, license_plate=? WHERE vehicle_id=?");
                $stmt->bind_param("issi", $client_id, $vehicle_type, $license_plate, $vehicle_id);
            }
            $stmt->execute();
            $stmt->close();

        } else {
            if($license_plate === null){
                $stmt = $conn->prepare("UPDATE vehicles SET model_car=?, license_plate=NULL WHERE vehicle_id=? AND client_id=?");
                $stmt->bind_param("sii", $vehicle_type, $vehicle_id, $client_id);
            } else {
                $stmt = $conn->prepare("UPDATE vehicles SET model_car=?, license_plate=? WHERE vehicle_id=? AND client_id=?");
                $stmt->bind_param("ssii", $vehicle_type, $license_plate, $vehicle_id, $client_id);
            }
            $stmt->execute();
            $stmt->close();
        }

    } else {
        if($license_plate === null){
            $stmt = $conn->prepare("INSERT INTO vehicles (client_id, model_car, license_plate, vehicle_number) VALUES (?, ?, NULL, ?)");
            $stmt->bind_param("iss", $client_id, $vehicle_type, $vehicle_number);
        } else {
            $stmt = $conn->prepare("INSERT INTO vehicles (client_id, model_car, license_plate, vehicle_number) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $client_id, $vehicle_type, $license_plate, $vehicle_number);
        }
        $stmt->execute();
        $vehicle_id = (int)$stmt->insert_id;
        $stmt->close();
    }

    $stmt = $conn->prepare("
        SELECT DISTINCT rit.insurance_type
        FROM requests r
        JOIN request_insurance_types rit ON r.request_id = rit.request_id
        WHERE r.vehicle_id = ?
          AND r.status IN ('Pending','Processed')
    ");
    $stmt->bind_param("i", $vehicle_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $active_types = [];
    while($row = $res->fetch_assoc()){
        $active_types[] = trim($row['insurance_type']);
    }
    $stmt->close();

    $active_types = array_values(array_unique($active_types));
    $selected_types = array_values(array_unique(array_map('trim', $insurance_types)));

    $dup = array_intersect($selected_types, $active_types);
    if(!empty($dup)){
        throw new Exception("This car already has an active request for: " . implode(", ", $dup) . ".");
    }

    $final_types = array_values(array_unique(array_merge($active_types, $selected_types)));

    if(count($final_types) > 2){
        throw new Exception("Not allowed: This car already has active types: " . implode(", ", $active_types) . ".");
    }
    if(in_array("All Risk", $final_types) && in_array("Third Party Liability", $final_types)){
        throw new Exception("Not allowed: A car cannot have both 'All Risk' and 'Third Party Liability'.");
    }

    $is_allrisk = in_array("All Risk", $insurance_types);

    if($is_allrisk){
        $stmt = $conn->prepare("INSERT INTO requests (client_id, vehicle_id, inception_date, car_value, year_built, package_option, processed, status) VALUES (?, ?, ?, ?, ?, ?, 0, 'Pending')");
        $stmt->bind_param("iisdis", $client_id, $vehicle_id, $inception_date, $car_value, $year_built, $package_option);
    } else {
        $stmt = $conn->prepare("INSERT INTO requests (client_id, vehicle_id, inception_date, processed, status) VALUES (?, ?, ?, 0, 'Pending')");
        $stmt->bind_param("iis", $client_id, $vehicle_id, $inception_date);
    }

    if(!$stmt){ die($conn->error); }
    $stmt->execute();
    $request_id = (int)$stmt->insert_id;
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO request_insurance_types (request_id, insurance_type) VALUES (?, ?)");
    foreach($insurance_types as $type){
        $t = trim($type);
        $stmt->bind_param("is", $request_id, $t);
        $stmt->execute();
    }
    $stmt->close();

    $dl_files = [];
    $cl_files = [];
    $car_files = [];

    $res = saveUploadsExactCount($_FILES['driving_license'], 'dl', $upload_dir_fs, 2, $dl_files, $savedPaths);
    if($res !== true) throw new Exception("Upload failed for driving license images: ".$res);

    $res = saveUploadsExactCount($_FILES['car_log'], 'cl', $upload_dir_fs, 2, $cl_files, $savedPaths);
    if($res !== true) throw new Exception("Upload failed for car log images: ".$res);

    if($is_allrisk){
        $res = saveUploadsExactCount($_FILES['allrisk_images'], 'car', $upload_dir_fs, 4, $car_files, $savedPaths);
        if($res !== true) throw new Exception("Upload failed for car images: ".$res);
    } else {
        $car_files = [NULL, NULL, NULL, NULL];
    }

    $stmt = $conn->prepare("
      INSERT INTO request_documents
      (request_id, driving_license, driving_license2, car_log, car_log2, car_image1, car_image2, car_image3, car_image4)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $dl1 = $dl_files[0] ?? NULL;
    $dl2 = $dl_files[1] ?? NULL;
    $cl1 = $cl_files[0] ?? NULL;
    $cl2 = $cl_files[1] ?? NULL;

    $c1 = $car_files[0] ?? NULL;
    $c2 = $car_files[1] ?? NULL;
    $c3 = $car_files[2] ?? NULL;
    $c4 = $car_files[3] ?? NULL;

    $stmt->bind_param("issssssss", $request_id, $dl1, $dl2, $cl1, $cl2, $c1, $c2, $c3, $c4);
    $stmt->execute();
    $stmt->close();

    $notif_title = "New request submitted";
    $plateTxt = ($license_plate === null) ? "N/A" : $license_plate;
    $notif_message = "Request #{$request_id} • Chassis {$vehicle_number} • Plate {$plateTxt} • " . implode(", ", $selected_types) . " • Inception {$inception_date}";
    $stmtN = $conn->prepare("INSERT INTO employee_notifications (request_id, title, message) VALUES (?, ?, ?)");
    $stmtN->bind_param("iss", $request_id, $notif_title, $notif_message);
    $stmtN->execute();
    $stmtN->close();

    $conn->commit();

    header("Location: client_dashboard.php?submitted=1");
    exit;

} catch (Exception $e) {
    $conn->rollback();
    foreach($savedPaths as $p){
        if(is_file($p)) @unlink($p);
    }
    backWithErrors(['general' => $e->getMessage()], $old);
}
