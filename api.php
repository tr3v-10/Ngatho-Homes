<?php
// ============================================
// api.php — All CRUD API Endpoints
// ============================================
require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ============================================
// DASHBOARD STATS
// ============================================
if ($action === 'stats') {
    $stats = [];

    $stats['properties']  = $db->query("SELECT COUNT(*) AS c FROM properties")->fetch_assoc()['c'];
    $stats['units']       = $db->query("SELECT COUNT(*) AS c FROM units")->fetch_assoc()['c'];
    $stats['tenants']     = $db->query("SELECT COUNT(*) AS c FROM tenants WHERE status='active'")->fetch_assoc()['c'];
    $stats['workers']     = $db->query("SELECT COUNT(*) AS c FROM workers")->fetch_assoc()['c'];
    $stats['open_requests']     = $db->query("SELECT COUNT(*) AS c FROM maintenance_requests WHERE status='open'")->fetch_assoc()['c'];
    $stats['resolved_requests'] = $db->query("SELECT COUNT(*) AS c FROM maintenance_requests WHERE status='resolved'")->fetch_assoc()['c'];
    $stats['critical']    = $db->query("SELECT COUNT(*) AS c FROM maintenance_requests WHERE priority='critical' AND status!='resolved'")->fetch_assoc()['c'];
    $stats['vacant_units']= $db->query("SELECT COUNT(*) AS c FROM units WHERE status='vacant'")->fetch_assoc()['c'];

    jsonResponse($stats);
}

// ============================================
// PROPERTIES
// ============================================
if ($action === 'get_properties') {
    $result = $db->query("SELECT * FROM v_property_summary ORDER BY id DESC");
    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    jsonResponse($rows);
}

if ($action === 'add_property' && $method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $name  = $db->real_escape_string($data['name']);
    $addr  = $db->real_escape_string($data['address']);
    $city  = $db->real_escape_string($data['city']);
    $units = (int)$data['total_units'];

    $db->query("INSERT INTO properties (name, address, city, total_units) VALUES ('$name','$addr','$city',$units)");
    jsonResponse(['success' => true, 'id' => $db->insert_id]);
}

if ($action === 'delete_property' && $method === 'DELETE') {
    $id = (int)$_GET['id'];
    $db->query("DELETE FROM properties WHERE id=$id");
    jsonResponse(['success' => true]);
}

// ============================================
// UNITS
// ============================================
if ($action === 'get_units') {
    $prop_id = isset($_GET['property_id']) ? (int)$_GET['property_id'] : 0;
    $where   = $prop_id ? "WHERE u.property_id=$prop_id" : '';
    $result  = $db->query("
        SELECT u.*, p.name AS property_name
        FROM units u
        JOIN properties p ON u.property_id = p.id
        $where
        ORDER BY u.id DESC
    ");
    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    jsonResponse($rows);
}

if ($action === 'add_unit' && $method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $pid    = (int)$data['property_id'];
    $num    = $db->real_escape_string($data['unit_number']);
    $floor  = (int)$data['floor'];
    $beds   = (int)$data['bedrooms'];
    $rent   = (float)$data['rent_amount'];
    $status = $db->real_escape_string($data['status'] ?? 'vacant');

    $db->query("INSERT INTO units (property_id, unit_number, floor, bedrooms, rent_amount, status)
                VALUES ($pid,'$num',$floor,$beds,$rent,'$status')");
    jsonResponse(['success' => true, 'id' => $db->insert_id]);
}

// ============================================
// TENANTS
// ============================================
if ($action === 'get_tenants') {
    $result = $db->query("
        SELECT t.*, u.unit_number, p.name AS property_name
        FROM tenants t
        LEFT JOIN units u ON t.unit_id = u.id
        LEFT JOIN properties p ON u.property_id = p.id
        ORDER BY t.id DESC
    ");
    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    jsonResponse($rows);
}

if ($action === 'add_tenant' && $method === 'POST') {
    $data  = json_decode(file_get_contents('php://input'), true);
    $fn    = $db->real_escape_string($data['first_name']);
    $ln    = $db->real_escape_string($data['last_name']);
    $email = $db->real_escape_string($data['email']);
    $phone = $db->real_escape_string($data['phone']);
    $uid   = (int)$data['unit_id'];
    $ls    = $db->real_escape_string($data['lease_start']);
    $le    = $db->real_escape_string($data['lease_end']);
    $pass  = hash('sha256', $data['password']);

    $check = $db->query("SELECT id FROM tenants WHERE email='$email'");
    if ($check->num_rows > 0) {
        jsonResponse(['error' => 'Email already exists'], 409);
    }

    $db->query("INSERT INTO tenants (unit_id, first_name, last_name, email, phone, password_hash, lease_start, lease_end)
                VALUES ($uid,'$fn','$ln','$email','$phone','$pass','$ls','$le')");

    // Mark unit as occupied
    $db->query("UPDATE units SET status='occupied' WHERE id=$uid");

    jsonResponse(['success' => true, 'id' => $db->insert_id]);
}

if ($action === 'delete_tenant' && $method === 'DELETE') {
    $id = (int)$_GET['id'];
    // Get unit to free it
    $tenant = $db->query("SELECT unit_id FROM tenants WHERE id=$id")->fetch_assoc();
    $db->query("DELETE FROM tenants WHERE id=$id");
    if ($tenant && $tenant['unit_id']) {
        $db->query("UPDATE units SET status='vacant' WHERE id={$tenant['unit_id']}");
    }
    jsonResponse(['success' => true]);
}

// ============================================
// WORKERS
// ============================================
if ($action === 'get_workers') {
    $result = $db->query("SELECT * FROM workers ORDER BY status ASC, id DESC");
    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    jsonResponse($rows);
}

if ($action === 'add_worker' && $method === 'POST') {
    $data  = json_decode(file_get_contents('php://input'), true);
    $fn    = $db->real_escape_string($data['first_name']);
    $ln    = $db->real_escape_string($data['last_name']);
    $email = $db->real_escape_string($data['email']);
    $phone = $db->real_escape_string($data['phone']);
    $skill = $db->real_escape_string($data['skill']);

    $db->query("INSERT INTO workers (first_name, last_name, email, phone, skill)
                VALUES ('$fn','$ln','$email','$phone','$skill')");
    jsonResponse(['success' => true, 'id' => $db->insert_id]);
}

if ($action === 'update_worker_status' && $method === 'PUT') {
    $data   = json_decode(file_get_contents('php://input'), true);
    $id     = (int)$data['id'];
    $status = $db->real_escape_string($data['status']);
    $db->query("UPDATE workers SET status='$status' WHERE id=$id");
    jsonResponse(['success' => true]);
}

// ============================================
// MAINTENANCE REQUESTS
// ============================================
if ($action === 'get_requests') {
    $status = $_GET['status'] ?? '';
    $where  = $status ? "WHERE mr.status='$status'" : '';
    $result = $db->query("SELECT * FROM v_requests_full $where ORDER BY submitted_at DESC");
    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    jsonResponse($rows);
}

if ($action === 'add_request' && $method === 'POST') {
    $data  = json_decode(file_get_contents('php://input'), true);
    $uid   = (int)$data['unit_id'];
    $tid   = (int)$data['tenant_id'];
    $title = $db->real_escape_string($data['title']);
    $desc  = $db->real_escape_string($data['description']);
    $cat   = $db->real_escape_string($data['category']);
    $pri   = $db->real_escape_string($data['priority']);

    $db->query("INSERT INTO maintenance_requests (unit_id, tenant_id, title, description, category, priority)
                VALUES ($uid,$tid,'$title','$desc','$cat','$pri')");
    jsonResponse(['success' => true, 'id' => $db->insert_id]);
}

if ($action === 'assign_request' && $method === 'PUT') {
    $data      = json_decode(file_get_contents('php://input'), true);
    $req_id    = (int)$data['request_id'];
    $worker_id = (int)$data['worker_id'];

    $db->query("UPDATE maintenance_requests
                SET worker_id=$worker_id, status='assigned', assigned_at=NOW()
                WHERE id=$req_id");
    $db->query("UPDATE workers SET status='busy' WHERE id=$worker_id");

    jsonResponse(['success' => true]);
}

if ($action === 'update_request_status' && $method === 'PUT') {
    $data   = json_decode(file_get_contents('php://input'), true);
    $id     = (int)$data['id'];
    $status = $db->real_escape_string($data['status']);
    $resolved = ($status === 'resolved') ? ", resolved_at=NOW()" : '';

    $db->query("UPDATE maintenance_requests SET status='$status'$resolved WHERE id=$id");

    // Free worker if resolved
    if ($status === 'resolved') {
        $req = $db->query("SELECT worker_id FROM maintenance_requests WHERE id=$id")->fetch_assoc();
        if ($req && $req['worker_id']) {
            $db->query("UPDATE workers SET status='available' WHERE id={$req['worker_id']}");
        }
    }
    jsonResponse(['success' => true]);
}

if ($action === 'delete_request' && $method === 'DELETE') {
    $id = (int)$_GET['id'];
    $db->query("DELETE FROM maintenance_requests WHERE id=$id");
    jsonResponse(['success' => true]);
}

// ============================================
// ADMIN LOGIN
// ============================================
if ($action === 'login' && $method === 'POST') {
    $data  = json_decode(file_get_contents('php://input'), true);
    $email = $db->real_escape_string($data['email']);
    $pass  = hash('sha256', $data['password']);

    $result = $db->query("SELECT id, name FROM admins WHERE email='$email' AND password_hash='$pass'");
    if ($result->num_rows === 1) {
        $admin = $result->fetch_assoc();
        $_SESSION['admin_id']   = $admin['id'];
        $_SESSION['admin_name'] = $admin['name'];
        jsonResponse(['success' => true, 'name' => $admin['name']]);
    } else {
        jsonResponse(['error' => 'Invalid email or password'], 401);
    }
}

if ($action === 'logout') {
    session_destroy();
    jsonResponse(['success' => true]);
}

$db->close();
?>
