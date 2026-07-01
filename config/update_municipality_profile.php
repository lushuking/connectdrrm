<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');

$ok = function(array $data = [], int $status = 200) {
	http_response_code($status);
	echo json_encode(['success' => true, 'data' => $data, 'meta' => ['ts' => (int)(microtime(true)*1000)]]);
	exit;
};

$err = function(string $code, string $message, int $status = 400) {
	http_response_code($status);
	echo json_encode(['success' => false, 'error' => ['code' => $code, 'message' => $message], 'meta' => ['ts' => (int)(microtime(true)*1000)]]);
	exit;
};

try {
	if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
		$err('unauthorized', 'Not authenticated', 401);
	}

	// Resolve DRRMO/municipality id
	$drrmoID = $_SESSION['municipality_id'] ?? null;
	if (!$drrmoID && isset($_SESSION['user_id'])) {
		try {
			$q = $pdo->prepare('SELECT municipalityID FROM users WHERE userID = ? LIMIT 1');
			$q->execute([$_SESSION['user_id']]);
			$row = $q->fetch(PDO::FETCH_ASSOC);
			if ($row && isset($row['municipalityID'])) $drrmoID = (int)$row['municipalityID'];
		} catch (Exception $ignored) {}
	}
	if (!$drrmoID) {
		$err('missing_municipality', 'Municipality (DRRMO) not found in session', 400);
	}

	$body = json_decode(file_get_contents('php://input'), true) ?: [];
	$drrmoHead = trim((string)($body['drrmoHead'] ?? ''));
	$drrmoHeadTitle = trim((string)($body['drrmoHeadTitle'] ?? ''));
	$operatorName = trim((string)($body['operatorName'] ?? ''));
	$operatorTitle = trim((string)($body['operatorTitle'] ?? ''));
	
	$drrmoHeadSignature = $body['drrmoHeadSignature'] ?? null;
	$operatorSignature = $body['operatorSignature'] ?? null;
	$clearDrrmoHeadSignature = !empty($body['clearDrrmoHeadSignature']);
	$clearOperatorSignature = !empty($body['clearOperatorSignature']);
	
	// Check user role - approving_authority can only edit head fields
	$userRole = $_SESSION['user_type'] ?? '';
	$isApprovingAuthority = ($userRole === 'approving_authority');

	// Ensure columns exist on drrmo (best-effort, tolerant)
	try {
		$pdo->query("ALTER TABLE drrmo ADD COLUMN IF NOT EXISTS drrmo_head VARCHAR(255) NULL");
		$pdo->query("ALTER TABLE drrmo ADD COLUMN IF NOT EXISTS drrmo_head_title VARCHAR(255) NULL");
		$pdo->query("ALTER TABLE drrmo ADD COLUMN IF NOT EXISTS operator_name VARCHAR(255) NULL");
		$pdo->query("ALTER TABLE drrmo ADD COLUMN IF NOT EXISTS operator_title VARCHAR(255) NULL");
	} catch (Exception $e) {
		// Fallback for MySQL versions without IF NOT EXISTS
		$cols = [];
		try { $cols = $pdo->query("SHOW COLUMNS FROM drrmo")->fetchAll(PDO::FETCH_ASSOC) ?: []; } catch (Exception $ignored) {}
		$existing = array_map(function($c){ return $c['Field'] ?? ''; }, $cols);
		$maybeAdd = function(string $col, string $ddl) use ($existing, $pdo) {
			if (!in_array($col, $existing, true)) { $pdo->query($ddl); }
		};
		$maybeAdd('drrmo_head', "ALTER TABLE drrmo ADD COLUMN drrmo_head VARCHAR(255) NULL");
		$maybeAdd('drrmo_head_title', "ALTER TABLE drrmo ADD COLUMN drrmo_head_title VARCHAR(255) NULL");
		$maybeAdd('operator_name', "ALTER TABLE drrmo ADD COLUMN operator_name VARCHAR(255) NULL");
		$maybeAdd('operator_title', "ALTER TABLE drrmo ADD COLUMN operator_title VARCHAR(255) NULL");
	}

	// Update based on role: approving_authority can only update head fields
	if ($isApprovingAuthority) {
		$upd = $pdo->prepare('UPDATE drrmo SET drrmo_head = ?, drrmo_head_title = ? WHERE drrmoID = ?');
		$upd->execute([$drrmoHead, $drrmoHeadTitle, $drrmoID]);
	} else {
		$upd = $pdo->prepare('UPDATE drrmo SET drrmo_head = ?, drrmo_head_title = ?, operator_name = ?, operator_title = ? WHERE drrmoID = ?');
		$upd->execute([$drrmoHead, $drrmoHeadTitle, $operatorName, $operatorTitle, $drrmoID]);
	}

	// Update signatures in users table
	try {
		$pdo->exec("ALTER TABLE users MODIFY COLUMN signature LONGTEXT NULL");
	} catch (Exception $ignored) {}

	if ($isApprovingAuthority) {
		if ($clearDrrmoHeadSignature) {
			$updSig = $pdo->prepare("UPDATE users SET signature = NULL WHERE userID = ?");
			$updSig->execute([$_SESSION['user_id']]);
		} elseif (!empty($drrmoHeadSignature)) {
			$updSig = $pdo->prepare("UPDATE users SET signature = ? WHERE userID = ?");
			$updSig->execute([$drrmoHeadSignature, $_SESSION['user_id']]);
		}
	} else {
		if ($clearOperatorSignature) {
			$updSig = $pdo->prepare("UPDATE users SET signature = NULL WHERE userID = ?");
			$updSig->execute([$_SESSION['user_id']]);
		} elseif (!empty($operatorSignature)) {
			$updSig = $pdo->prepare("UPDATE users SET signature = ? WHERE userID = ?");
			$updSig->execute([$operatorSignature, $_SESSION['user_id']]);
		}

		if ($clearDrrmoHeadSignature) {
			$updHeadSig = $pdo->prepare("UPDATE users SET signature = NULL WHERE drrmoID = ? AND role = 'approving_authority'");
			$updHeadSig->execute([$drrmoID]);
		} elseif (!empty($drrmoHeadSignature)) {
			$updHeadSig = $pdo->prepare("UPDATE users SET signature = ? WHERE drrmoID = ? AND role = 'approving_authority'");
			$updHeadSig->execute([$drrmoHeadSignature, $drrmoID]);
		}
	}

	$ok(['updated' => true]);

} catch (Exception $e) {
	$err('server_error', $e->getMessage(), 500);
}

?>


