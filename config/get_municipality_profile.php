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

	$drrmoID = isset($_GET['drrmoID']) ? (int)$_GET['drrmoID'] : 0;
	if ($drrmoID <= 0) {
		$drrmoID = (int)($_SESSION['municipality_id'] ?? 0);
	}
	
	// If still no drrmoID, try to get it from user's record
	if ($drrmoID <= 0 && isset($_SESSION['user_id'])) {
		try {
			$userStmt = $pdo->prepare('SELECT drrmoID FROM users WHERE userID = ? LIMIT 1');
			$userStmt->execute([$_SESSION['user_id']]);
			$userRow = $userStmt->fetch(PDO::FETCH_ASSOC);
			if ($userRow && isset($userRow['drrmoID']) && $userRow['drrmoID'] > 0) {
				$drrmoID = (int)$userRow['drrmoID'];
				// Update session for future requests
				$_SESSION['municipality_id'] = $drrmoID;
			}
		} catch (Exception $e) {
			error_log('[get_municipality_profile] Error fetching drrmoID from user: ' . $e->getMessage());
		}
	}
	
	if ($drrmoID <= 0) {
		$err('missing_municipality', 'Municipality (DRRMO) not provided', 400);
	}

	// Select tolerant to missing columns
	$cols = ['name', 'logo_url', 'drrmo_head', 'drrmo_head_title', 'operator_name', 'operator_title'];
	$existing = [];
	try {
		$res = $pdo->query("SHOW COLUMNS FROM drrmo")->fetchAll(PDO::FETCH_ASSOC) ?: [];
		$existing = array_map(function($c){ return $c['Field'] ?? ''; }, $res);
	} catch (Exception $ignored) {}

	$sel = ['name'];
	foreach (['logo_url','drrmo_head','drrmo_head_title','operator_name','operator_title'] as $c) {
		if (in_array($c, $existing, true)) { $sel[] = $c; }
	}
	$selectSql = 'SELECT ' . implode(', ', $sel) . ' FROM drrmo WHERE drrmoID = ? LIMIT 1';

	$stmt = $pdo->prepare($selectSql);
	$stmt->execute([$drrmoID]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

	// Ensure expected keys exist (even if columns are missing)
	foreach (['logo_url','drrmo_head','drrmo_head_title','operator_name','operator_title'] as $k) {
		if (!array_key_exists($k, $row)) { $row[$k] = ''; }
	}

	// Fallback: if municipality profile fields are empty, derive from user accounts
	// - drrmo_head + title: from the approving_authority account in users
	// - operator_name + title: from the currently logged-in user (requestor)
	try {
		if (empty($row['drrmo_head']) || empty($row['drrmo_head_title'])) {
			$headStmt = $pdo->prepare("SELECT fullName, position FROM users WHERE drrmoID = ? AND role = 'approving_authority' ORDER BY userID DESC LIMIT 1");
			$headStmt->execute([$drrmoID]);
			$headUser = $headStmt->fetch(PDO::FETCH_ASSOC) ?: [];
			if (empty($row['drrmo_head']) && !empty($headUser['fullName'])) {
				$row['drrmo_head'] = (string)$headUser['fullName'];
			}
			if (empty($row['drrmo_head_title']) && !empty($headUser['position'])) {
				$row['drrmo_head_title'] = (string)$headUser['position'];
			}
		}
	} catch (Exception $ignored) {}

	try {
		if (empty($row['operator_name']) || empty($row['operator_title'])) {
			$op = [];
			if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0) {
				$opStmt = $pdo->prepare("SELECT fullName, position FROM users WHERE userID = ? LIMIT 1");
				$opStmt->execute([(int)$_SESSION['user_id']]);
				$op = $opStmt->fetch(PDO::FETCH_ASSOC) ?: [];
			}
			// Fallback if session user_id isn't present for some reason
			if (empty($op)) {
				$opStmt = $pdo->prepare("SELECT fullName, position FROM users WHERE drrmoID = ? AND role = 'drrmo_staff' ORDER BY userID DESC LIMIT 1");
				$opStmt->execute([$drrmoID]);
				$op = $opStmt->fetch(PDO::FETCH_ASSOC) ?: [];
			}
			if (empty($row['operator_name']) && !empty($op['fullName'])) {
				$row['operator_name'] = (string)$op['fullName'];
			}
			if (empty($row['operator_title']) && !empty($op['position'])) {
				$row['operator_title'] = (string)$op['position'];
			}
		}
	} catch (Exception $ignored) {}

	// Convert relative logo URL to absolute URL if needed
	if (!empty($row['logo_url']) && !preg_match('/^https?:\/\//', $row['logo_url'])) {
		// Get base URL from request
		$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
		$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
		$scriptPath = dirname(dirname($_SERVER['SCRIPT_NAME'])); // Go up from config/ to root
		$baseUrl = rtrim($protocol . '://' . $host . $scriptPath, '/');
		
		// Ensure logo_url starts with / or is relative to base
		if (strpos($row['logo_url'], '/') !== 0) {
			$row['logo_url'] = '/' . ltrim($row['logo_url'], '/');
		}
		
		// Build absolute URL
		$row['logo_url'] = $baseUrl . '/' . ltrim($row['logo_url'], '/');
	}

	// Include e-signatures from users table (saved at profile completion) so they show inside the system
	$row['operator_signature'] = '';
	$row['drrmo_head_signature'] = '';
	try {
		$hasSignatureCol = $pdo->query("SHOW COLUMNS FROM users LIKE 'signature'")->fetch();
		if ($hasSignatureCol) {
			// Operator/requestor signature: current user's signature
			if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0) {
				$sigStmt = $pdo->prepare("SELECT signature FROM users WHERE userID = ? AND signature IS NOT NULL AND signature != '' LIMIT 1");
				$sigStmt->execute([(int)$_SESSION['user_id']]);
				$sigRow = $sigStmt->fetch(PDO::FETCH_ASSOC);
				if (!empty($sigRow['signature'])) {
					$row['operator_signature'] = $sigRow['signature'];
				}
			}
			// DRRMO Head signature: approving_authority user for this municipality
			$headSigStmt = $pdo->prepare("SELECT signature FROM users WHERE drrmoID = ? AND role = 'approving_authority' AND signature IS NOT NULL AND signature != '' ORDER BY userID DESC LIMIT 1");
			$headSigStmt->execute([$drrmoID]);
			$headSigRow = $headSigStmt->fetch(PDO::FETCH_ASSOC);
			if (!empty($headSigRow['signature'])) {
				$row['drrmo_head_signature'] = $headSigRow['signature'];
			}
		}
	} catch (Exception $e) {
		error_log('[get_municipality_profile] Error fetching signatures: ' . $e->getMessage());
	}

	$ok(['drrmoID' => $drrmoID, 'profile' => $row]);

} catch (Exception $e) {
	$err('server_error', $e->getMessage(), 500);
}

?>


