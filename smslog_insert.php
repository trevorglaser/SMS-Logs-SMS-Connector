<?php
/**
 * smslog_insert.php
 * Deployed to: /var/www/html/smsconn/smslog_insert.php
 *
 * Called via Asterisk System() to log messages that bypass smsconnector —
 * specifically internal extension-to-extension SMS and inbound messages
 * routed directly to an extension via route_to_ext.
 *
 * Reads params from QUERY_STRING env var (same pattern as provider.php).
 *
 * Usage from dialplan:
 *   same => n,Set(ENV(QUERY_STRING)=direction=internal&src=${NUMBER_FROM}&dst=${NUMBER_TO}&body=${URIENCODE(${MESSAGE(body)})}&status=${MESSAGE_SEND_STATUS}&did=${DID})
 *   same => n,Set(ENV(REQUEST_METHOD)=GET)
 *   same => n,System(php /var/www/html/smsconn/smslog_insert.php)
 *   same => n,Set(ENV(QUERY_STRING)=)
 */

// Parse the QUERY_STRING set by Asterisk
parse_str($_SERVER['QUERY_STRING'] ?? getenv('QUERY_STRING') ?? '', $params);

$direction = $params['direction'] ?? 'internal'; // internal | inbound | outbound
$src       = $params['src']       ?? '';
$dst       = $params['dst']       ?? '';
$body      = $params['body']      ?? '';
$status    = $params['status']    ?? '';
$did       = $params['did']       ?? '';          // optional DID number
$adaptor   = $params['adaptor']   ?? 'dialplan';  // identifies source as dialplan-logged

// Normalise direction to what smsconnector uses (in/out) plus our 'internal'
$dir_map = [
    'inbound'  => 'in',
    'outbound' => 'out',
    'internal' => 'internal',
    'in'       => 'in',
    'out'      => 'out',
];
$direction = $dir_map[$direction] ?? 'internal';

// Normalise delivered flag from MESSAGE_SEND_STATUS
$delivered = (stripos($status, 'SUCCESS') !== false) ? 1 : 0;

// Load FreePBX bootstrap
$bootstrap_files = [
    '/etc/freepbx.conf',
    '/var/www/html/admin/bootstrap.php',
];
$bootstrapped = false;
foreach ($bootstrap_files as $f) {
    if (file_exists($f)) {
        require_once $f;
        $bootstrapped = true;
        break;
    }
}

if (!$bootstrapped) {
    // Fallback: direct PDO connection using FreePBX DB credentials
    $conf = parse_ini_file('/etc/asterisk/freepbx.conf') ?:
            parse_ini_file('/var/www/html/admin/freepbx.conf') ?: [];

    $dsn  = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4',
        $conf['AMPDBHOST'] ?? 'localhost',
        $conf['AMPDBNAME'] ?? 'asterisk'
    );
    $pdo = new PDO($dsn, $conf['AMPDBUSER'] ?? 'freepbxuser', $conf['AMPDBPASS'] ?? '');

    insertDirect($pdo, $direction, $src, $dst, $body, $delivered, $adaptor, $did);
    exit;
}

// Use FreePBX DB object if available
try {
    $freepbx = FreePBX::Create();
    $db      = $freepbx->Database;
    insertDirect($db, $direction, $src, $dst, $body, $delivered, $adaptor, $did);
} catch (Exception $e) {
    error_log("smslog_insert.php error: " . $e->getMessage());
    exit(1);
}

// ─── Helper ───────────────────────────────────────────────────────────────────

function insertDirect($db, $direction, $src, $dst, $body, $delivered, $adaptor, $did) {
    // Look up didid from smsconnector_dids if we have a DID number
    $didid = 0;
    if ($did) {
        try {
            $stmt = $db->prepare("SELECT id FROM smsconnector_dids WHERE did = :did LIMIT 1");
            $stmt->execute([':did' => $did]);
            $didid = (int)($stmt->fetchColumn() ?: 0);
        } catch (Exception $e) {
            // Table may not exist — continue with didid = 0
        }
    }

    // Build a threadid consistent with smsconnector's convention:
    // sorted pair of numbers joined with '_'
    $pair = [$src, $dst];
    sort($pair);
    $threadid = implode('_', $pair);

    $sql = "INSERT INTO sms_messages
                (`from`, `to`, cnam, direction, tx_rx_datetime, body,
                 delivered, `read`, adaptor, emid, threadid, didid, `timestamp`)
            VALUES
                (:from, :to, '', :direction, NOW(), :body,
                 :delivered, 0, :adaptor, '', :threadid, :didid, UNIX_TIMESTAMP())";

    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':from'      => substr($src,  0, 20),
        ':to'        => substr($dst,  0, 20),
        ':direction' => $direction,
        ':body'      => substr($body, 0, 1600),
        ':delivered' => $delivered,
        ':adaptor'   => substr($adaptor, 0, 45),
        ':threadid'  => substr($threadid, 0, 50),
        ':didid'     => $didid,
    ]);
}
