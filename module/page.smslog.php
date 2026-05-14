<?php
/**
 * SMS Event Logging - Admin page controller
 * Native integration with simontelephonics/smsconnector (sms_messages table).
 */

if (!defined('FREEPBX_IS_AUTH') || !FREEPBX_IS_AUTH) {
    die('No direct script access allowed');
}

/** @var \FreePBX\modules\Smslog $smslog */
$smslog = \FreePBX::create()->Smslog;

// ─── AJAX / JSON endpoints ────────────────────────────────────────────────────

if (isset($_REQUEST['action'])) {
    $action = $_REQUEST['action'];

    if ($action === 'get_events') {
        header('Content-Type: application/json');
        $filters = array_filter([
            'date_from'  => $_REQUEST['date_from']  ?? '',
            'date_to'    => $_REQUEST['date_to']    ?? '',
            'direction'  => $_REQUEST['direction']  ?? '',
            'delivered'  => $_REQUEST['delivered']  ?? '',
            'read_flag'  => $_REQUEST['read_flag']  ?? '',
            'src'        => $_REQUEST['src']        ?? '',
            'dst'        => $_REQUEST['dst']        ?? '',
            'adaptor'    => $_REQUEST['adaptor']    ?? '',
            'did'        => $_REQUEST['did']        ?? '',
            'threadid'   => $_REQUEST['threadid']   ?? '',
            'search'     => $_REQUEST['search']     ?? '',
        ], function($v) { return $v !== ''; });

        $page     = (int)($_REQUEST['page']     ?? 1);
        $per_page = (int)($_REQUEST['per_page'] ?? 50);
        $sort     = $_REQUEST['sort']  ?? 'tx_rx_datetime';
        $order    = $_REQUEST['order'] ?? 'DESC';

        echo json_encode($smslog->getEvents($filters, $page, $per_page, $sort, $order));
        exit;
    }

    if ($action === 'get_stats') {
        header('Content-Type: application/json');
        $filters = array_filter([
            'date_from' => $_REQUEST['date_from'] ?? '',
            'date_to'   => $_REQUEST['date_to']   ?? '',
        ], function($v) { return $v !== ''; });
        echo json_encode($smslog->getStats($filters));
        exit;
    }

    if ($action === 'get_volume') {
        header('Content-Type: application/json');
        $days = min(365, max(7, (int)($_REQUEST['days'] ?? 30)));
        echo json_encode($smslog->getDailyVolume($days));
        exit;
    }

    if ($action === 'get_event') {
        header('Content-Type: application/json');
        $event = $smslog->getEvent((int)($_REQUEST['id'] ?? 0));
        echo json_encode($event ?: ['error' => 'Not found']);
        exit;
    }

    if ($action === 'get_filter_options') {
        header('Content-Type: application/json');
        echo json_encode([
            'adaptors' => $smslog->getAdaptors(),
            'dids'     => $smslog->getDids(),
        ]);
        exit;
    }

    if ($action === 'export_csv') {
        $filters = array_filter([
            'date_from'  => $_REQUEST['date_from']  ?? '',
            'date_to'    => $_REQUEST['date_to']    ?? '',
            'direction'  => $_REQUEST['direction']  ?? '',
            'delivered'  => $_REQUEST['delivered']  ?? '',
            'src'        => $_REQUEST['src']        ?? '',
            'dst'        => $_REQUEST['dst']        ?? '',
            'adaptor'    => $_REQUEST['adaptor']    ?? '',
            'did'        => $_REQUEST['did']        ?? '',
            'search'     => $_REQUEST['search']     ?? '',
        ], function($v) { return $v !== ''; });

        $filename = 'smslog_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $smslog->exportCsv($filters);
        exit;
    }
}

// ─── Render page ──────────────────────────────────────────────────────────────

$pagetitle = _('SMS Event Logging');
\FreePBX::create()->Hooks->enqueueCSS('smslog', 'smslog.css');
\FreePBX::create()->Hooks->enqueueJS('smslog', 'smslog.js');

require_once 'views/main.php';
