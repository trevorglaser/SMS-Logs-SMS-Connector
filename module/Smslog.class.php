<?php
/**
 * FreePBX SMS Event Logging Module
 * Class: Smslog
 *
 * Reads directly from the smsconnector module's sms_messages table.
 * No separate logging table or AGI hook needed — works natively with
 * simontelephonics/smsconnector out of the box.
 */

namespace FreePBX\modules;

class Smslog extends \FreePBX_Helpers implements \BMO {

    public static $FREEPBX_MODULE_DESCRIPTION = "SMS Event Logging";
    public static $FREEPBX_MODULE_VERSION     = "16.0.2";
    public static $FREEPBX_MODULE_RAWNAME     = "smslog";
    public static $FREEPBX_MODULE_NAME        = "SMS Event Logging";
    public static $FREEPBX_MODULE_AUTH_TYPE   = "read";

    /** smsconnector's message table (read-only) */
    const MSG_TABLE = "sms_messages";

    /** smsconnector's DID table — joined for provider/DID number info */
    const DID_TABLE = "smsconnector_dids";

    public function __construct($freepbx = null) {
        if ($freepbx === null) {
            throw new \Exception("Not given a FreePBX Object");
        }
        $this->FreePBX = $freepbx;
        $this->db = $freepbx->Database;
    }

    // BMO Interface
    public function install() {
        if (!$this->tableExists(self::MSG_TABLE)) {
            throw new \Exception(
                "The sms_messages table was not found. Please ensure the " .
                "smsconnector module (simontelephonics/smsconnector) is " .
                "installed before installing SMS Event Logging."
            );
        }
    }
    public function uninstall() {}
    public function backup() {}
    public function restore($backup) {}
    public function doConfigPageInit($page) {}

    /**
     * Fetch events with filtering, sorting, and pagination.
     */
    public function getEvents(array $filters = [], $page = 1, $per_page = 50, $sort = 'tx_rx_datetime', $order = 'DESC') {
        $allowed_sort  = ['id','tx_rx_datetime','from','to','direction','delivered','adaptor','cnam','didid'];
        $allowed_order = ['ASC','DESC'];
        $sort  = in_array($sort,  $allowed_sort)  ? $sort  : 'tx_rx_datetime';
        $order = in_array(strtoupper($order), $allowed_order) ? strtoupper($order) : 'DESC';

        [$where, $params] = $this->buildWhere($filters);
        $from_clause = $this->buildFromClause();

        $count_stmt = $this->db->prepare("SELECT COUNT(*) FROM $from_clause $where");
        $count_stmt->execute($params);
        $total = (int)$count_stmt->fetchColumn();

        $page     = max(1, (int)$page);
        $per_page = max(1, min(500, (int)$per_page));
        $offset   = ($page - 1) * $per_page;

        $sql = "SELECT
                    m.id,
                    m.`from`,
                    m.`to`,
                    m.cnam,
                    m.direction,
                    m.tx_rx_datetime,
                    m.body,
                    m.delivered,
                    m.`read`,
                    m.adaptor,
                    m.emid,
                    m.threadid,
                    m.didid,
                    m.timestamp,
                    d.did       AS did_number,
                    d.provider  AS provider_name
                FROM $from_clause
                $where
                ORDER BY m.`$sort` $order
                LIMIT $per_page OFFSET $offset";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'total'    => $total,
            'page'     => $page,
            'per_page' => $per_page,
            'pages'    => (int)ceil(max(1, $total) / $per_page),
            'rows'     => $rows,
        ];
    }

    /**
     * Summary stats for dashboard cards.
     */
    public function getStats(array $filters = []) {
        [$where, $params] = $this->buildWhere($filters);
        $from_clause = $this->buildFromClause();

        $sql = "SELECT
            COUNT(*)                                                    AS total,
            SUM(m.direction = 'out')                                    AS outbound,
            SUM(m.direction = 'in')                                     AS inbound,
            SUM(m.direction = 'internal')                               AS internal,
            SUM(m.delivered = 0 AND m.direction IN ('out','internal'))  AS failed,
            SUM(m.delivered = 1)                                        AS delivered,
            COUNT(DISTINCT m.`from`)                                    AS unique_src,
            COUNT(DISTINCT m.`to`)                                      AS unique_dst,
            SUM(m.`read` = 0 AND m.direction IN ('in','internal'))      AS unread
        FROM $from_clause $where";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Daily message volume for the bar chart.
     */
    public function getDailyVolume($days = 30, array $filters = []) {
        $date_filters = array_merge($filters, [
            'date_from' => date('Y-m-d', strtotime("-{$days} days")),
        ]);
        [$where, $params] = $this->buildWhere($date_filters);
        $from_clause = $this->buildFromClause();

        $sql = "SELECT
                    DATE(m.tx_rx_datetime)           AS day,
                    SUM(m.direction = 'in')          AS inbound,
                    SUM(m.direction = 'out')         AS outbound,
                    SUM(m.direction = 'internal')    AS internal
                FROM $from_clause $where
                GROUP BY DATE(m.tx_rx_datetime)
                ORDER BY day ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get a single event by ID.
     */
    public function getEvent($id) {
        $from_clause = $this->buildFromClause();
        $sql = "SELECT m.*, d.did AS did_number, d.provider AS provider_name
                FROM $from_clause
                WHERE m.id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => (int)$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Export events as CSV string.
     */
    public function exportCsv(array $filters = []) {
        [$where, $params] = $this->buildWhere($filters);
        $from_clause = $this->buildFromClause();

        $sql = "SELECT
                    m.id, m.tx_rx_datetime, m.direction,
                    m.`from`, m.`to`, m.cnam, m.body,
                    m.delivered, m.`read`, m.adaptor,
                    d.did AS did_number, d.provider,
                    m.threadid, m.emid
                FROM $from_clause $where
                ORDER BY m.tx_rx_datetime DESC LIMIT 50000";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $out = fopen('php://temp', 'r+');
        if (!empty($rows)) {
            fputcsv($out, array_keys($rows[0]));
            foreach ($rows as $row) { fputcsv($out, $row); }
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);
        return $csv;
    }

    /**
     * Distinct adaptors present in the data (for filter dropdown).
     */
    public function getAdaptors() {
        $sql = "SELECT DISTINCT adaptor FROM `" . self::MSG_TABLE . "`
                WHERE adaptor IS NOT NULL AND adaptor != '' ORDER BY adaptor";
        return $this->db->query($sql)->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Distinct DIDs that have messages (for filter dropdown).
     */
    public function getDids() {
        if (!$this->tableExists(self::DID_TABLE)) { return []; }
        $sql = "SELECT DISTINCT d.id, d.did, d.provider
                FROM `" . self::DID_TABLE . "` d
                INNER JOIN `" . self::MSG_TABLE . "` m ON m.didid = d.id
                ORDER BY d.did";
        return $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    private function buildFromClause() {
        $msg = self::MSG_TABLE;
        $did = self::DID_TABLE;
        if ($this->tableExists($did)) {
            return "`$msg` m LEFT JOIN `$did` d ON m.didid = d.id";
        }
        // Fallback: still works without DID table, provider/did columns will be NULL
        return "`$msg` m LEFT JOIN (SELECT NULL AS id, NULL AS did, NULL AS provider LIMIT 0) d ON FALSE";
    }

    private function buildWhere(array $filters) {
        $clauses = [];
        $params  = [];

        if (!empty($filters['date_from'])) {
            $clauses[] = "m.tx_rx_datetime >= :date_from";
            $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $clauses[] = "m.tx_rx_datetime <= :date_to";
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['direction'])) {
            // Normalise UI values → DB values (in/out/internal)
            $dir_map = ['inbound' => 'in', 'outbound' => 'out', 'internal' => 'internal', 'in' => 'in', 'out' => 'out'];
            $clauses[] = "m.direction = :direction";
            $params[':direction'] = $dir_map[$filters['direction']] ?? $filters['direction'];
        }
        if (isset($filters['delivered']) && $filters['delivered'] !== '') {
            $clauses[] = "m.delivered = :delivered";
            $params[':delivered'] = (int)$filters['delivered'];
        }
        if (isset($filters['read_flag']) && $filters['read_flag'] !== '') {
            $clauses[] = "m.`read` = :read_flag";
            $params[':read_flag'] = (int)$filters['read_flag'];
        }
        if (!empty($filters['src'])) {
            $clauses[] = "m.`from` LIKE :src";
            $params[':src'] = '%' . $filters['src'] . '%';
        }
        if (!empty($filters['dst'])) {
            $clauses[] = "m.`to` LIKE :dst";
            $params[':dst'] = '%' . $filters['dst'] . '%';
        }
        if (!empty($filters['adaptor'])) {
            $clauses[] = "m.adaptor = :adaptor";
            $params[':adaptor'] = $filters['adaptor'];
        }
        if (!empty($filters['did'])) {
            $clauses[] = "m.didid = :didid";
            $params[':didid'] = (int)$filters['did'];
        }
        if (!empty($filters['threadid'])) {
            $clauses[] = "m.threadid = :threadid";
            $params[':threadid'] = $filters['threadid'];
        }
        if (!empty($filters['search'])) {
            $clauses[] = "(m.`from` LIKE :search OR m.`to` LIKE :search OR m.body LIKE :search OR m.cnam LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $where = $clauses ? 'WHERE ' . implode(' AND ', $clauses) : '';
        return [$where, $params];
    }

    private function tableExists($table) {
        try {
            $this->db->query("SELECT 1 FROM `$table` LIMIT 1");
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
