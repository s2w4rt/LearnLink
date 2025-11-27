<?php
// api/materials.php

header('Content-Type: application/json');

// 👉 If you already have a config.php with PDO, you can replace this block
$host = 'localhost';
$db   = 'allshs_elms';   // ✅ matches your SQL dump
$user = 'root';          // ⬅️ change if needed
$pass = '';              // ⬅️ change if needed
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
  $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'message' => 'Database connection failed: ' . $e->getMessage(),
  ]);
  exit;
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
  case 'GET':
    handleGet($pdo);
    break;

  case 'POST':
    handlePost($pdo);
    break;

  case 'DELETE':
    handleDelete($pdo);
    break;

  default:
    http_response_code(405);
    echo json_encode([
      'success' => false,
      'message' => 'Method not allowed',
    ]);
}

// ========== GET: list materials ==========
function handleGet(PDO $pdo) {
  // Single material by id
  if (isset($_GET['id']) && $_GET['id'] !== '') {
    $stmt = $pdo->prepare("SELECT * FROM learning_materials WHERE id = :id");
    $stmt->execute([':id' => (int)$_GET['id']]);
    $material = $stmt->fetch();

    if (!$material) {
      http_response_code(404);
      echo json_encode([
        'success' => false,
        'message' => 'Material not found',
      ]);
      return;
    }

    echo json_encode($material);
    return;
  }

  // List with filters (quarter, strand, gradeLevel, subject)
  $sql = "SELECT * FROM learning_materials WHERE 1";
  $params = [];

  if (!empty($_GET['quarter']) && $_GET['quarter'] !== 'ALL') {
    $sql .= " AND quarter = :quarter";
    $params[':quarter'] = (int)$_GET['quarter'];
  }

  if (!empty($_GET['strand']) && $_GET['strand'] !== 'ALL') {
    $sql .= " AND strand = :strand";
    $params[':strand'] = $_GET['strand'];
  }

  if (!empty($_GET['gradeLevel']) && $_GET['gradeLevel'] !== 'ALL') {
    $sql .= " AND grade_level = :grade_level";
    $params[':grade_level'] = $_GET['gradeLevel']; // '11' / '12' / 'ALL'
  }

  if (!empty($_GET['subject']) && $_GET['subject'] !== 'ALL') {
    $sql .= " AND subject = :subject";
    $params[':subject'] = $_GET['subject'];        // can be subject id or code
  }

  $sql .= " ORDER BY created_at DESC";

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $materials = $stmt->fetchAll();

  echo json_encode($materials);
}

// ========== POST: upload + INSERT INTO learning_materials ==========
function handlePost(PDO $pdo) {
  // Required fields (from your admin form)
  $title       = trim($_POST['title'] ?? '');
  $description = trim($_POST['description'] ?? '');
  $type        = trim($_POST['type'] ?? '');         // enum: Handout/PPT/Video/...
  $strand      = trim($_POST['strand'] ?? '');       // enum: HUMSS/ICT/STEM/TVL/TVL-HE
  $quarter     = (int)($_POST['quarter'] ?? 0);
  $gradeLevel  = trim($_POST['gradeLevel'] ?? '');   // '11' / '12' / 'ALL'
  $subject     = trim($_POST['subject'] ?? '');      // in your dump, can be '6' (subject id)
  $status      = trim($_POST['status'] ?? 'published');

  $directToStudents = !empty($_POST['directToStudents']) ? 1 : 1; // default 1

  if ($title === '' || $type === '' || $strand === '' || $quarter === 0 || $gradeLevel === '' || $subject === '') {
    http_response_code(400);
    echo json_encode([
      'success' => false,
      'message' => 'Missing required fields.',
    ]);
    return;
  }

  // ===== File upload handling (optional but recommended) =====
  $fileUrl = null;

  if (!empty($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = dirname(__DIR__) . '/materials/'; // physical path
    if (!is_dir($uploadDir)) {
      mkdir($uploadDir, 0775, true);
    }

    $originalName = $_FILES['file']['name'];
    $tmpName      = $_FILES['file']['tmp_name'];
    $ext          = pathinfo($originalName, PATHINFO_EXTENSION);
    $safeName     = uniqid('mat_', true) . '.' . strtolower($ext);

    $targetPath = $uploadDir . $safeName;

    if (!move_uploaded_file($tmpName, $targetPath)) {
      http_response_code(500);
      echo json_encode([
        'success' => false,
        'message' => 'Failed to move uploaded file.',
      ]);
      return;
    }

    // This is what goes to learning_materials.file_url
    $fileUrl = '/materials/' . $safeName;
  }

  try {
    // ✅ This INSERT matches your learning_materials table exactly
    $stmt = $pdo->prepare("
      INSERT INTO learning_materials
        (title, description, type, strand, quarter, grade_level, subject,
         file_url, direct_to_students, status, created_at)
      VALUES
        (:title, :description, :type, :strand, :quarter, :grade_level, :subject,
         :file_url, :direct_to_students, :status, NOW())
    ");

    $stmt->execute([
      ':title'             => $title,
      ':description'       => $description,
      ':type'              => $type,
      ':strand'            => $strand,
      ':quarter'           => $quarter,
      ':grade_level'       => $gradeLevel,
      ':subject'           => $subject,
      ':file_url'          => $fileUrl,
      ':direct_to_students'=> $directToStudents,
      ':status'            => $status,
    ]);

    $id = $pdo->lastInsertId();

    echo json_encode([
      'success' => true,
      'message' => 'Material uploaded and stored in learning_materials.',
      'data'    => [
        'id'        => $id,
        'title'     => $title,
        'file_url'  => $fileUrl,
        'strand'    => $strand,
        'quarter'   => $quarter,
        'gradeLevel'=> $gradeLevel,
        'subject'   => $subject,
      ],
    ]);
  } catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
      'success' => false,
      'message' => 'DB error: ' . $e->getMessage(),
    ]);
  }
}

// ========== DELETE: remove from DB + delete file ==========
function handleDelete(PDO $pdo) {
  if (empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode([
      'success' => false,
      'message' => 'Missing material id.',
    ]);
    return;
  }

  $id = (int)$_GET['id'];

  // Get file_url first
  $stmt = $pdo->prepare("SELECT file_url FROM learning_materials WHERE id = :id");
  $stmt->execute([':id' => $id]);
  $material = $stmt->fetch();

  if (!$material) {
    http_response_code(404);
    echo json_encode([
      'success' => false,
      'message' => 'Material not found.',
    ]);
    return;
  }

  // Delete row from learning_materials
  $del = $pdo->prepare("DELETE FROM learning_materials WHERE id = :id");
  $del->execute([':id' => $id]);

  // Delete file if exists
  if (!empty($material['file_url'])) {
    $filePath = dirname(__DIR__) . $material['file_url']; // '/materials/xyz'
    if (is_file($filePath)) {
      @unlink($filePath);
    }
  }

  echo json_encode([
    'success' => true,
    'message' => 'Material deleted successfully.',
  ]);
}
