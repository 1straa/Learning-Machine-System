<?php
// api/users.php — CRUD for users
require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Read JSON body if any
$input = json_decode(file_get_contents('php://input'), true) ?? [];

function sanitize($str) { return trim($str ?? ''); }
function now() { return date('Y-m-d H:i:s'); }

if ($method === 'GET' && $action === 'list') {
  $q = $pdo->query("SELECT id, name, email, role, status, last_login, created_at FROM users ORDER BY id DESC");
  $users = $q->fetchAll();
  json_response(['ok' => true, 'data' => $users]);
}

if ($method === 'POST' && $action === 'create') {
  $name = sanitize($input['name'] ?? '');
  $email = sanitize($input['email'] ?? '');
  $role = sanitize($input['role'] ?? 'student');
  $status = sanitize($input['status'] ?? 'active');
  $password = $input['password'] ?? 'password123';

  if (!$name || !$email) json_response(['ok'=>false,'error'=>'Name and email are required.'], 400);

  // Basic validation + defaults
  $validRoles = ['admin','teacher','student'];
  if (!in_array($role, $validRoles)) $role = 'student';

  $validStatus = ['active','inactive'];
  if (!in_array($status, $validStatus)) $status = 'active';

  try {
    $stmt = $pdo->prepare("INSERT INTO users (name, email, role, status, last_login, created_at, updated_at, password_hash) 
                           VALUES (:name, :email, :role, :status, :last_login, :created_at, :updated_at, :password_hash)");
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $now = now();
    $stmt->execute([
      ':name'=>$name,
      ':email'=>$email,
      ':role'=>$role,
      ':status'=>$status,
      ':last_login'=>NULL,
      ':created_at'=>$now,
      ':updated_at'=>$now,
      ':password_hash'=>$hash,
    ]);
    $id = $pdo->lastInsertId();
    $row = $pdo->query("SELECT id, name, email, role, status, last_login, created_at FROM users WHERE id = ".intval($id))->fetch();
    json_response(['ok'=>true,'data'=>$row], 201);
  } catch (PDOException $e) {
    if ($e->getCode() === '23000') { // duplicate email
      json_response(['ok'=>false,'error'=>'Email already exists.'], 409);
    }
    json_response(['ok'=>false,'error'=>'Insert failed','details'=>$e->getMessage()], 500);
  }
}

if ($method === 'PUT' && $action === 'update') {
  $id = intval($_GET['id'] ?? 0);
  if ($id <= 0) json_response(['ok'=>false,'error'=>'Missing id'], 400);

  $fields = [];
  $params = [':id'=>$id];
  foreach (['name','email','role','status'] as $f) {
    if (isset($input[$f])) {
      $fields[] = "$f = :$f";
      $params[":$f"] = sanitize($input[$f]);
    }
  }
  if (isset($input['password']) && $input['password'] !== '') {
    $fields[] = "password_hash = :password_hash";
    $params[":password_hash"] = password_hash($input['password'], PASSWORD_DEFAULT);
  }
  if (!$fields) json_response(['ok'=>false,'error'=>'No fields to update'], 400);

  $fields[] = "updated_at = :updated_at";
  $params[':updated_at'] = now();

  $sql = "UPDATE users SET ".implode(', ', $fields)." WHERE id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);

  $row = $pdo->query("SELECT id, name, email, role, status, last_login, created_at FROM users WHERE id = ".intval($id))->fetch();
  json_response(['ok'=>true,'data'=>$row]);
}

if ($method === 'DELETE' && $action === 'delete') {
  $id = intval($_GET['id'] ?? 0);
  if ($id <= 0) json_response(['ok'=>false,'error'=>'Missing id'], 400);
  $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
  $stmt->execute([':id'=>$id]);
  json_response(['ok'=>true]);
}

// fallback
json_response(['ok'=>false,'error'=>'Unsupported route.'], 404);
?>