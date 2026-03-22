<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ╔══════════════════════════════════════════════════════════════╗
 * ║  Xigenix Sync Tool — Laravel Controller (Abstracted)       ║
 * ║  Copy this file to your project, then customise the        ║
 * ║  CONFIG section below. See INTEGRATION.md for full guide.  ║
 * ╚══════════════════════════════════════════════════════════════╝
 *
 * PROD ENDPOINTS  (X-Sync-Token header):
 *   /api/sync/listtables     /api/sync/exporttable
 *   /api/sync/export          /api/sync/logexport
 *   /api/sync/logwipe         /api/sync/filereceive
 *   /api/sync/cacheclear      /api/sync/sqlquery
 *
 * DEV ENDPOINTS   (?key= param, dev-only):
 *   /sync/api/gettables      /sync/api/fetchtable
 *   /sync/api/logpull         /sync/api/logclear
 *   /sync/api/filepush        /sync/api/clearcache
 *   /sync/api/runsql          /sync/api/backupdb
 *
 * DASHBOARD UI:
 *   /sync?key=<KEY>
 */
class SyncController extends Controller
{
    // ╔══════════════════════════════════════════════════════════╗
    // ║  PROJECT CONFIG — CUSTOMISE THESE FOR YOUR PROJECT      ║
    // ╚══════════════════════════════════════════════════════════╝

    /** Secret key for auth — reads from .env SYNC_SECRET_KEY */
    private function syncSecret(): string
    {
        return env('SYNC_SECRET_KEY', '');
    }

    /** Production URL — reads from .env SYNC_PROD_URL */
    private function prodUrl(): string
    {
        return rtrim(env('SYNC_PROD_URL', ''), '/');
    }

    /** Project name shown in the dashboard header */
    private function projectName(): string
    {
        return env('APP_NAME', 'Sync Panel');
    }

    /**
     * Tables to sync with "Targeted Sync" button.
     * ──────────────────────────────────────────────
     * CUSTOMISE: List the tables your project needs.
     * These are the most important tables that need
     * frequent dev ↔ prod synchronisation.
     */
    private function targetedTables(): array
    {
        return [
            'users',
            'user_profiles',
            'glucose_readings',
            'meals',
            'meal_items',
            'medications',
            'medication_events',
            'exercises',
            'doctors',
            'doctor_patient_links',
            'doctor_permissions',
            'consents',
        ];
    }

    /** Path to the production log file */
    private function logFilePath(): string
    {
        return storage_path('logs/laravel.log');
    }

    /** Directories to clear when "Clear Cache" is clicked */
    private function cacheDirs(): array
    {
        return [
            storage_path('framework/cache'),
            storage_path('framework/views'),
        ];
    }

    /** Where pulled logs are stored locally */
    private function logSaveDir(): string
    {
        return base_path('ServerLog');
    }

    /**
     * Is this a dev environment?
     * ──────────────────────────
     * CUSTOMISE: Adjust the host pattern to match your
     * local dev URL (e.g. .test, localhost, 127.0.0.1).
     */
    private function isDev(): bool
    {
        $host = request()->getHost();
        return str_contains($host, 'localhost')
            || str_contains($host, '.test')
            || str_contains($host, '127.0.0.1');
    }

    // ╔══════════════════════════════════════════════════════════╗
    // ║  END OF CONFIG — No changes needed below this line      ║
    // ╚══════════════════════════════════════════════════════════╝

    // ============================================================
    // CRYPTO HELPERS
    // ============================================================

    private function encrypt(string $data): string
    {
        $iv = random_bytes(16);
        return base64_encode($iv . openssl_encrypt($data, 'AES-256-CBC', $this->syncSecret(), OPENSSL_RAW_DATA, $iv));
    }

    private function decrypt(string $blob): string
    {
        $raw = base64_decode($blob);
        return openssl_decrypt(substr($raw, 16), 'AES-256-CBC', $this->syncSecret(), OPENSSL_RAW_DATA, substr($raw, 0, 16));
    }

    // ============================================================
    // HTTP HELPERS (dev -> prod)
    // ============================================================

    private function callProd(string $mode, array $params = [], int $timeout = 60): array
    {
        $url = $this->prodUrl() . '/api/sync/' . $mode;
        $sep = '?';
        foreach ($params as $k => $v) {
            $url .= $sep . urlencode($k) . '=' . urlencode($v);
            $sep = '&';
        }
        $ctx = stream_context_create([
            'http' => ['method' => 'GET', 'header' => 'X-Sync-Token: ' . $this->syncSecret(), 'timeout' => $timeout],
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $resp = @file_get_contents($url, false, $ctx);
        if ($resp === false) return ['_error' => 'Could not reach prod: ' . $url];
        $data = json_decode($resp, true);
        return is_array($data) ? $data : ['_error' => 'Invalid JSON from prod', '_raw' => substr($resp, 0, 300)];
    }

    private function postToProd(string $mode, string $body, int $timeout = 60): array
    {
        $url = $this->prodUrl() . '/api/sync/' . $mode;
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "X-Sync-Token: " . $this->syncSecret() . "\r\nContent-Type: application/json\r\n",
                'content' => $body,
                'timeout' => $timeout,
            ],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $resp = @file_get_contents($url, false, $ctx);
        if ($resp === false) return ['_error' => 'Could not reach prod: ' . $url];
        $data = json_decode($resp, true);
        return is_array($data) ? $data : ['_error' => 'Invalid JSON from prod', '_raw' => substr($resp, 0, 300)];
    }

    // ============================================================
    // UTILITY HELPERS
    // ============================================================

    private function importRows(\PDO $pdo, string $table, array $rows): string
    {
        if (empty($rows)) return 'empty';
        try {
            $pdo->exec("TRUNCATE TABLE `$table`");
            $cols = implode(', ', array_map(fn($c) => "`$c`", array_keys($rows[0])));
            $ph   = implode(', ', array_fill(0, count($rows[0]), '?'));
            $stmt = $pdo->prepare("INSERT INTO `$table` ($cols) VALUES ($ph)");
            foreach ($rows as $row) { $stmt->execute(array_values($row)); }
            return 'ok';
        } catch (\Exception $e) { return $e->getMessage(); }
    }

    private function clearDirRecursive(string $dir): int
    {
        $count = 0;
        if (!is_dir($dir)) return 0;
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir()) { @rmdir($item->getRealPath()); }
            else { @unlink($item->getRealPath()); $count++; }
        }
        return $count;
    }

    private function getRawPdo(): \PDO
    {
        return DB::connection()->getPdo();
    }

    // ============================================================
    // AUTH GATES
    // ============================================================

    private function requireProdToken(Request $request): void
    {
        $secret = $this->syncSecret();
        if ($secret === '' || !hash_equals($secret, $request->header('X-Sync-Token', ''))) {
            abort(401, json_encode(['error' => 'Invalid token.']));
        }
    }

    private function requireDevKey(Request $request): void
    {
        if (!$this->isDev()) abort(404, 'Not found.');
        $secret = $this->syncSecret();
        if ($secret === '' || !hash_equals($secret, $request->query('key', ''))) {
            abort(403, json_encode(['error' => 'Forbidden.']));
        }
    }

    // ============================================================
    // PROD ENDPOINTS (called from dev with X-Sync-Token header)
    // ============================================================

    public function prodEndpoint(Request $request, string $mode)
    {
        $this->requireProdToken($request);

        return match ($mode) {
            'listtables'  => $this->prodListTables(),
            'exporttable' => $this->prodExportTable($request),
            'export'      => $this->prodExportTargeted(),
            'logexport'   => $this->prodLogExport(),
            'logwipe'     => $this->prodLogWipe(),
            'filereceive' => $this->prodFileReceive($request),
            'cacheclear'  => $this->prodCacheClear(),
            'sqlquery'    => $this->prodSqlQuery($request),
            default       => response()->json(['error' => 'Unknown mode.']),
        };
    }

    private function prodListTables()
    {
        $tables = $this->getRawPdo()->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);
        return response()->json(['status' => 'ok', 'tables' => $tables]);
    }

    private function prodExportTable(Request $request)
    {
        set_time_limit(120);
        ini_set('memory_limit', '256M');
        $table = $request->query('table', '');
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return response()->json(['error' => 'Invalid table name.']);
        }
        $rows = $this->getRawPdo()->query("SELECT * FROM `$table`")->fetchAll(\PDO::FETCH_ASSOC);
        $rows = array_map(function ($row) {
            return array_map(function ($val) {
                if ($val !== null && !mb_check_encoding($val, 'UTF-8')) {
                    return '__b64__' . base64_encode($val);
                }
                return $val;
            }, $row);
        }, $rows);
        $json = json_encode($rows, JSON_INVALID_UTF8_SUBSTITUTE);
        return response()->json(['status' => 'ok', 'payload' => $this->encrypt($json)]);
    }

    private function prodExportTargeted()
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');
        $pdo = $this->getRawPdo();
        $export = [];
        foreach ($this->targetedTables() as $table) {
            try { $export[$table] = $pdo->query("SELECT * FROM `$table`")->fetchAll(\PDO::FETCH_ASSOC); }
            catch (\Exception $e) { $export[$table] = ['_error' => $e->getMessage()]; }
        }
        return response()->json(['status' => 'ok', 'payload' => $this->encrypt(json_encode($export))]);
    }

    private function prodLogExport()
    {
        $logFile = $this->logFilePath();
        if (!file_exists($logFile)) return response()->json(['error' => 'Log not found.']);
        return response()->json([
            'status'     => 'ok',
            'size_bytes' => filesize($logFile),
            'payload'    => $this->encrypt(file_get_contents($logFile)),
        ]);
    }

    private function prodLogWipe()
    {
        $logFile = $this->logFilePath();
        if (!file_exists($logFile)) return response()->json(['error' => 'Log not found.']);
        file_put_contents($logFile, '');
        return response()->json(['status' => 'ok', 'message' => 'Log cleared on production.']);
    }

    private function prodFileReceive(Request $request)
    {
        $input = json_decode($request->getContent(), true);
        if (!$input || !isset($input['payload'])) {
            return response()->json(['error' => 'No payload provided.']);
        }
        $decrypted = json_decode($this->decrypt($input['payload']), true);
        if (!$decrypted || !isset($decrypted['path']) || !isset($decrypted['content'])) {
            return response()->json(['error' => 'Invalid payload structure.']);
        }
        $relativePath = $decrypted['path'];
        $content      = $decrypted['content'];
        if (str_contains($relativePath, '..')) {
            return response()->json(['error' => 'Path traversal not allowed.']);
        }
        $fullPath = base_path(ltrim($relativePath, '/'));
        $dir = dirname($fullPath);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $backedUp = false;
        if (file_exists($fullPath)) {
            $backupDir = base_path('file_backups');
            if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
            $stamp = date('Ymd_His');
            $backupName = str_replace('/', '__', $relativePath) . '.' . $stamp . '.bak';
            copy($fullPath, $backupDir . '/' . $backupName);
            $backedUp = true;
        }
        file_put_contents($fullPath, $content);
        return response()->json(['status' => 'ok', 'path' => $relativePath, 'size' => strlen($content), 'backed_up' => $backedUp]);
    }

    private function prodCacheClear()
    {
        $totalDeleted = 0;
        $cleared = [];
        foreach ($this->cacheDirs() as $dir) {
            if (is_dir($dir)) {
                $count = $this->clearDirRecursive($dir);
                $totalDeleted += $count;
                $relative = str_replace(base_path() . '/', '', $dir);
                $cleared[] = $relative . " ($count files)";
            }
        }
        if (empty($cleared)) {
            return response()->json(['error' => 'No cache directories found.']);
        }
        return response()->json(['status' => 'ok', 'files_deleted' => $totalDeleted, 'cleared' => $cleared, 'message' => 'Cache cleared on production.']);
    }

    private function prodSqlQuery(Request $request)
    {
        $input = json_decode($request->getContent(), true);
        if (!$input || !isset($input['payload'])) {
            return response()->json(['error' => 'No payload provided.']);
        }
        $decrypted = json_decode($this->decrypt($input['payload']), true);
        if (!$decrypted || !isset($decrypted['sql'])) {
            return response()->json(['error' => 'Invalid payload structure.']);
        }
        $sql = trim($decrypted['sql']);
        if (empty($sql)) return response()->json(['error' => 'Empty query.']);
        try {
            $pdo  = $this->getRawPdo();
            $stmt = $pdo->query($sql);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $rowCount = count($rows);
            if ($rowCount > 1000) $rows = array_slice($rows, 0, 1000);
            $columns = $rowCount > 0 ? array_keys($rows[0]) : [];
            $payload = $this->encrypt(json_encode(['columns' => $columns, 'rows' => $rows, 'total' => $rowCount]));
            return response()->json(['status' => 'ok', 'payload' => $payload]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'error' => $e->getMessage()]);
        }
    }

    // ============================================================
    // DEV AJAX ENDPOINTS (called by JS dashboard)
    // ============================================================

    public function devEndpoint(Request $request, string $mode)
    {
        $this->requireDevKey($request);

        return match ($mode) {
            'gettables'  => $this->devGetTables(),
            'fetchtable' => $this->devFetchTable($request),
            'fetchbulk'  => $this->devFetchBulk(),
            'logpull'    => $this->devLogPull(),
            'logclear'   => $this->devLogClear(),
            'filepush'   => $this->devFilePush($request),
            'clearcache' => $this->devClearCache(),
            'runsql'     => $this->devRunSql($request),
            'backupdb'   => $this->devBackupDb($request),
            default      => response()->json(['error' => 'Unknown mode.']),
        };
    }

    private function devGetTables()
    {
        return response()->json($this->callProd('listtables'));
    }

    private function devFetchTable(Request $request)
    {
        set_time_limit(120);
        $table = $request->query('table', '');
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return response()->json(['error' => 'Invalid table name.']);
        }
        $data = $this->callProd('exporttable', ['table' => $table], 120);
        if (isset($data['_error'])) return response()->json(['status' => 'error', 'table' => $table, 'error' => $data['_error']]);
        if (!isset($data['payload'])) return response()->json(['status' => 'error', 'table' => $table, 'error' => 'No payload. Raw: ' . substr(json_encode($data), 0, 200)]);
        $rows = json_decode($this->decrypt($data['payload']), true);
        if ($rows === null) return response()->json(['status' => 'error', 'table' => $table, 'error' => 'Decrypt failed — likely binary BLOB data. Table skipped safely.']);
        $pdo = $this->getRawPdo();
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        $result = $this->importRows($pdo, $table, $rows);
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
        return response()->json([
            'status' => ($result === 'ok' || $result === 'empty') ? 'ok' : 'error',
            'table'  => $table,
            'rows'   => count($rows),
            'detail' => $result,
        ]);
    }

    private function devFetchBulk()
    {
        set_time_limit(120);
        $data = $this->callProd('export', [], 120);
        if (isset($data['_error'])) return response()->json(['status' => 'error', 'error' => $data['_error']]);
        if (!isset($data['payload'])) return response()->json(['status' => 'error', 'error' => 'No payload.']);
        $tables = json_decode($this->decrypt($data['payload']), true);
        if (!$tables) return response()->json(['status' => 'error', 'error' => 'Decrypt failed.']);
        $pdo = $this->getRawPdo();
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        $imported = []; $errors = [];
        foreach ($tables as $table => $rows) {
            if (isset($rows['_error'])) { $errors[$table] = $rows['_error']; continue; }
            $r = $this->importRows($pdo, $table, $rows);
            if ($r === 'ok' || $r === 'empty') $imported[$table] = count($rows);
            else $errors[$table] = $r;
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
        return response()->json(['status' => 'ok', 'imported' => $imported, 'errors' => $errors]);
    }

    private function devLogPull()
    {
        $data = $this->callProd('logexport', [], 60);
        if (isset($data['_error'])) return response()->json(['status' => 'error', 'error' => $data['_error']]);
        if (!isset($data['payload'])) return response()->json(['status' => 'error', 'error' => 'No payload.']);
        $contents = $this->decrypt($data['payload']);
        $logDir = $this->logSaveDir();
        if (!is_dir($logDir)) mkdir($logDir, 0755, true);
        $stamp = date('Ymd_His');
        $dest  = $logDir . '/prodlog_' . $stamp . '.log';
        file_put_contents($dest, $contents);
        return response()->json(['status' => 'ok', 'size_bytes' => strlen($contents), 'saved_to' => $dest, 'filename' => 'prodlog_' . $stamp . '.log']);
    }

    private function devLogClear()
    {
        $data = $this->callProd('logwipe');
        if (isset($data['_error'])) return response()->json(['status' => 'error', 'error' => $data['_error']]);
        return response()->json(['status' => 'ok', 'message' => 'Production log cleared.']);
    }

    private function devFilePush(Request $request)
    {
        $file = $request->query('file', '');
        if (empty($file)) return response()->json(['error' => 'No file specified. Use &file=path/to/file']);
        if (str_contains($file, '..')) return response()->json(['error' => 'Path traversal not allowed.']);
        $localPath = base_path(ltrim($file, '/'));
        if (!file_exists($localPath)) {
            return response()->json(['status' => 'error', 'error' => 'Local file not found: ' . $file]);
        }
        $content = file_get_contents($localPath);
        $payload = $this->encrypt(json_encode(['path' => $file, 'content' => $content]));
        $body    = json_encode(['payload' => $payload]);
        $data    = $this->postToProd('filereceive', $body, 30);
        if (isset($data['_error'])) {
            return response()->json(['status' => 'error', 'file' => $file, 'error' => $data['_error']]);
        }
        return response()->json(array_merge(['status' => 'ok', 'file' => $file], $data));
    }

    private function devClearCache()
    {
        $data = $this->callProd('cacheclear');
        if (isset($data['_error'])) return response()->json(['status' => 'error', 'error' => $data['_error']]);
        return response()->json($data);
    }

    private function devRunSql(Request $request)
    {
        $input = json_decode($request->getContent(), true);
        if (!$input || !isset($input['sql'])) {
            return response()->json(['error' => 'No SQL provided.']);
        }
        $sql = trim($input['sql']);
        $payload = $this->encrypt(json_encode(['sql' => $sql]));
        $body = json_encode(['payload' => $payload]);
        $data = $this->postToProd('sqlquery', $body, 30);
        if (isset($data['_error'])) return response()->json(['status' => 'error', 'error' => $data['_error']]);
        if (isset($data['error']))  return response()->json(['status' => 'error', 'error' => $data['error']]);
        if (!isset($data['payload'])) return response()->json(['status' => 'error', 'error' => 'No payload in response.']);
        $result = json_decode($this->decrypt($data['payload']), true);
        return response()->json(['status' => 'ok', 'columns' => $result['columns'], 'rows' => $result['rows'], 'total' => $result['total']]);
    }

    private function devBackupDb(Request $request)
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');
        $backupDir = base_path('ServerLog/DBBackups');
        if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
        $stamp    = date('Ymd_His');
        $type     = $request->query('type', 'sync');
        $filename = 'backup_' . $type . '_' . $stamp . '.sql';
        $dest     = $backupDir . '/' . $filename;
        try {
            $pdo = $this->getRawPdo();
            $fh  = fopen($dest, 'w');
            fwrite($fh, "-- Dev DB Backup\n-- Created: " . date('Y-m-d H:i:s') . " (pre-$type sync)\n\nSET FOREIGN_KEY_CHECKS=0;\n\n");
            $tables = $pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);
            foreach ($tables as $table) {
                $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(\PDO::FETCH_NUM);
                fwrite($fh, "DROP TABLE IF EXISTS `$table`;\n" . $create[1] . ";\n\n");
                $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(\PDO::FETCH_ASSOC);
                if (!empty($rows)) {
                    $cols = implode(', ', array_map(fn($c) => "`$c`", array_keys($rows[0])));
                    foreach ($rows as $row) {
                        $vals = implode(', ', array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), array_values($row)));
                        fwrite($fh, "INSERT INTO `$table` ($cols) VALUES ($vals);\n");
                    }
                }
                fwrite($fh, "\n");
            }
            fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
            fclose($fh);
            $size = round(filesize($dest) / 1024 / 1024, 2);
            return response()->json(['status' => 'ok', 'filename' => $filename, 'size_mb' => $size, 'path' => $dest]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'error' => $e->getMessage()]);
        }
    }

    // ============================================================
    // DEV DASHBOARD UI
    // ============================================================

    public function dashboard(Request $request)
    {
        $this->requireDevKey($request);

        $k = htmlspecialchars($request->query('key', ''));
        $projectName = htmlspecialchars($this->projectName());
        $targetedTablesJson = json_encode($this->targetedTables());

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $projectName ?> Sync Panel</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
  body { background: #f8f9fa; }
  .card { border-color: #dee2e6; background: #fff; }
  .card-header { border-color: #dee2e6; background: #f8f9fa; }
  .log-box { font-family: monospace; font-size: 0.78rem; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 10px; max-height: 180px; overflow-y: auto; color: #495057; }
  .log-box .ok   { color: #198754; }
  .log-box .err  { color: #dc3545; }
  .log-box .info { color: #0d6efd; }
  .badge-dev { background: #e7f1ff; color: #0d6efd; border: 1px solid #b6d4fe; font-size: 0.7rem; }
  .badge-safe { background: #d1e7dd; color: #146c43; border: 1px solid #a3cfbb; font-size: 0.68rem; }
  .badge-caution { background: #fff3cd; color: #997404; border: 1px solid #ffe69c; font-size: 0.68rem; }
  .badge-danger { background: #f8d7da; color: #b02a37; border: 1px solid #f1aeb5; font-size: 0.68rem; }
  .section-divider { border-top: 1px solid #dee2e6; margin: 2rem 0 1.5rem; }
  .card-safe { border-color: #a3cfbb; }
  .card-safe .card-header { border-bottom-color: #a3cfbb; background: #f0faf4; }
  .card-caution { border-color: #ffe69c; }
  .card-caution .card-header { border-bottom-color: #ffe69c; background: #fffcf0; }
  .card-danger { border-color: #f1aeb5; }
  .card-danger .card-header { border-bottom-color: #f1aeb5; background: #fef2f2; }
</style>
</head>
<body>
<div class="container py-5" style="max-width:1100px">
  <div class="d-flex align-items-center gap-3 mb-2">
    <h2 class="mb-0 fw-bold"><i class="bi bi-arrow-left-right me-2 text-primary"></i><?= $projectName ?> Sync Panel</h2>
    <span class="badge badge-dev rounded-pill px-3 py-1">DEV ONLY</span>
  </div>
  <p class="text-secondary mb-4">Sync production data to your local development environment. <span class="text-danger fw-semibold">Remove sync routes from production when done.</span></p>

  <div class="d-flex align-items-center gap-2 mb-3">
    <i class="bi bi-shield-check text-success fs-5"></i>
    <h5 class="mb-0 text-success fw-semibold">Pull from Server</h5>
    <span class="badge badge-safe rounded-pill px-2 py-1">SAFE — Read Only</span>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card h-100 shadow-sm card-safe">
        <div class="card-header d-flex align-items-center gap-2"><i class="bi bi-bullseye text-primary fs-5"></i><strong>Targeted Sync</strong></div>
        <div class="card-body">
          <p class="text-secondary small mb-3">Pulls key tables (configured in controller). Fastest option.</p>
          <div id="targeted-progress" class="mb-2 d-none"><div class="d-flex justify-content-between small text-secondary mb-1"><span id="targeted-label">Fetching…</span><span id="targeted-pct">0%</span></div><div class="progress" style="height:10px"><div id="targeted-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" style="width:0%"></div></div></div>
          <div id="targeted-log" class="log-box mb-3 d-none"></div>
          <button class="btn btn-primary w-100" id="btn-targeted" onclick="runTargeted()"><i class="bi bi-download me-1"></i> Run Targeted Sync</button>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100 shadow-sm card-safe">
        <div class="card-header d-flex align-items-center gap-2"><i class="bi bi-database-fill-down text-success fs-5"></i><strong>Full DB Sync</strong></div>
        <div class="card-body">
          <p class="text-secondary small mb-3">Fetches every table one-by-one from production. Safe from timeouts.</p>
          <div id="full-progress" class="mb-2 d-none"><div class="d-flex justify-content-between small text-secondary mb-1"><span id="full-label">Fetching table list…</span><span id="full-pct">0%</span></div><div class="progress" style="height:10px"><div id="full-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" style="width:0%"></div></div></div>
          <div id="full-log" class="log-box mb-3 d-none"></div>
          <button class="btn btn-success w-100" id="btn-full" onclick="runFull()"><i class="bi bi-download me-1"></i> Run Full Sync</button>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100 shadow-sm card-safe">
        <div class="card-header d-flex align-items-center gap-2"><i class="bi bi-file-earmark-text text-info fs-5"></i><strong>Pull Log File</strong></div>
        <div class="card-body">
          <p class="text-secondary small mb-3">Downloads the production log file into your local <code>ServerLog/</code> folder.</p>
          <div id="log-status" class="alert d-none mb-3 py-2 small"></div>
          <button class="btn btn-info w-100" id="btn-logpull" onclick="runLogPull()"><i class="bi bi-download me-1"></i> Pull Log File</button>
        </div>
      </div>
    </div>
  </div>

  <div class="section-divider"></div>
  <div class="d-flex align-items-center gap-2 mb-3">
    <i class="bi bi-exclamation-triangle text-danger fs-5"></i>
    <h5 class="mb-0 text-danger fw-semibold">Modify Production Server</h5>
    <span class="badge badge-danger rounded-pill px-2 py-1">CAUTION — Writes to Prod</span>
  </div>
  <div class="row g-3">
    <div class="col-md-4">
      <div class="card h-100 shadow-sm card-danger">
        <div class="card-header d-flex align-items-center gap-2"><i class="bi bi-cloud-upload text-danger fs-5"></i><strong>Push File to Prod</strong><span class="badge badge-danger rounded-pill ms-auto">HIGH RISK</span></div>
        <div class="card-body">
          <p class="text-secondary small mb-3">Uploads a local file to production. Original is auto-backed up.</p>
          <div class="input-group mb-3"><span class="input-group-text"><i class="bi bi-file-code"></i></span><input type="text" class="form-control" id="push-file-path" placeholder="app/Http/Controllers/File.php"></div>
          <div id="push-status" class="alert d-none mb-3 py-2 small"></div>
          <button class="btn btn-danger w-100" id="btn-filepush" onclick="runFilePush()"><i class="bi bi-cloud-upload me-1"></i> Push to Production</button>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100 shadow-sm card-caution">
        <div class="card-header d-flex align-items-center gap-2"><i class="bi bi-lightning-charge text-warning fs-5"></i><strong>Clear Prod Cache</strong><span class="badge badge-caution rounded-pill ms-auto">MODERATE</span></div>
        <div class="card-body">
          <p class="text-secondary small mb-3">Wipes configured cache directories on production. Cache regenerates automatically.</p>
          <div id="cache-status" class="alert d-none mb-3 py-2 small"></div>
          <button class="btn btn-warning w-100" id="btn-clearcache" onclick="confirmCacheClear()"><i class="bi bi-lightning-charge me-1"></i> Clear Production Cache</button>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100 shadow-sm card-caution">
        <div class="card-header d-flex align-items-center gap-2"><i class="bi bi-trash3 text-warning fs-5"></i><strong>Clear Production Log</strong><span class="badge badge-caution rounded-pill ms-auto">MODERATE</span></div>
        <div class="card-body">
          <p class="text-secondary small mb-3">Wipes the log file on the production server. <span class="text-danger fw-semibold">Irreversible.</span></p>
          <div id="clear-status" class="alert d-none mb-3 py-2 small"></div>
          <button class="btn btn-warning w-100" id="btn-logclear" onclick="confirmLogClear()"><i class="bi bi-trash3 me-1"></i> Clear Production Log</button>
        </div>
      </div>
    </div>
    <div class="col-12 mt-3">
      <div class="card shadow-sm card-danger">
        <div class="card-header d-flex align-items-center gap-2"><i class="bi bi-terminal text-danger fs-5"></i><strong>Run SQL on Production</strong><span class="badge badge-danger rounded-pill ms-auto">HIGH RISK</span></div>
        <div class="card-body">
          <p class="text-secondary small mb-2">Execute a SQL query directly on the production database. Results capped at 1,000 rows.</p>
          <textarea class="form-control font-monospace mb-3" id="sql-query" rows="4" placeholder="SELECT * FROM users LIMIT 10;" style="font-size: 0.85rem;"></textarea>
          <div class="d-flex gap-2 mb-3">
            <button class="btn btn-danger" id="btn-runsql" onclick="runSql()"><i class="bi bi-play-fill me-1"></i> Execute</button>
            <button class="btn btn-outline-secondary d-none" id="btn-export-csv" onclick="exportCsv()"><i class="bi bi-filetype-csv me-1"></i> Export CSV</button>
            <span id="sql-row-count" class="text-secondary small align-self-center ms-2 d-none"></span>
          </div>
          <div id="sql-status" class="alert d-none mb-3 py-2 small"></div>
          <div id="sql-results" class="d-none" style="max-height:400px; overflow:auto; border:1px solid #dee2e6; border-radius:6px;">
            <table class="table table-sm table-striped table-hover mb-0" style="font-size:0.78rem;"><thead id="sql-thead" class="sticky-top" style="z-index:1;"></thead><tbody id="sql-tbody"></tbody></table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="logClearModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header border-warning"><h5 class="modal-title text-warning"><i class="bi bi-exclamation-triangle-fill me-2"></i>Confirm Log Clear</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">Are you sure you want to clear the production log file?<br><span class="text-danger fw-semibold">This action cannot be undone.</span></div><div class="modal-footer border-warning"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-warning" onclick="runLogClear()"><i class="bi bi-trash3 me-1"></i> Yes, Clear Log</button></div></div></div></div>
<div class="modal fade" id="cacheClearModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header border-warning"><h5 class="modal-title text-warning"><i class="bi bi-exclamation-triangle-fill me-2"></i>Confirm Cache Clear</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">Clear all cached files on production?<br><span class="text-secondary">They will regenerate automatically.</span></div><div class="modal-footer border-warning"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-warning" onclick="runCacheClear()"><i class="bi bi-lightning-charge me-1"></i> Yes, Clear Cache</button></div></div></div></div>

<script>
const KEY = '<?= $k ?>';
const TARGETED_TABLES = <?= $targetedTablesJson ?>;

function apiUrl(mode, extra = '') { return `/sync/api/${mode}?key=${KEY}${extra}`; }
async function fetchJson(url) { const r = await fetch(url); return r.json(); }
function setProgress(prefix, pct, label) {
  document.getElementById(`${prefix}-progress`).classList.remove('d-none');
  document.getElementById(`${prefix}-bar`).style.width = pct + '%';
  document.getElementById(`${prefix}-pct`).textContent = Math.round(pct) + '%';
  document.getElementById(`${prefix}-label`).textContent = label;
}
function appendLog(prefix, msg, cls = '') {
  const box = document.getElementById(`${prefix}-log`); box.classList.remove('d-none');
  const line = document.createElement('div'); if (cls) line.className = cls;
  line.textContent = msg; box.appendChild(line); box.scrollTop = box.scrollHeight;
}
function setBtn(id, disabled, label) { const btn = document.getElementById(id); btn.disabled = disabled; btn.innerHTML = label; }

async function backupLocalDb(prefix, type) {
  appendLog(prefix, 'ℹ Creating local DB backup before sync…', 'info');
  try { const res = await fetchJson(apiUrl('backupdb', `&type=${type}`));
    if (res.status === 'ok') { appendLog(prefix, `✓ Backup saved: ${res.filename} (${res.size_mb} MB)`, 'ok'); return true; }
    else { appendLog(prefix, `⚠ Backup failed: ${res.error} — continuing anyway`, 'err'); return false; }
  } catch(e) { appendLog(prefix, '⚠ Backup request failed — continuing anyway', 'err'); return false; }
}

async function runTargeted() {
  setBtn('btn-targeted', true, '<span class="spinner-border spinner-border-sm me-1"></span> Backing up…');
  document.getElementById('targeted-log').innerHTML = '';
  document.getElementById('targeted-progress').classList.remove('d-none');
  document.getElementById('targeted-log').classList.remove('d-none');
  await backupLocalDb('targeted', 'targeted');
  setBtn('btn-targeted', true, '<span class="spinner-border spinner-border-sm me-1"></span> Syncing…');
  let done = 0, errors = 0;
  for (const table of TARGETED_TABLES) {
    setProgress('targeted', (done / TARGETED_TABLES.length) * 100, `Fetching: ${table}`);
    try { const res = await fetchJson(apiUrl('fetchtable', `&table=${table}`));
      if (res.status === 'ok') { appendLog('targeted', `✓ ${table} — ${res.rows} rows`, 'ok'); }
      else { appendLog('targeted', `✗ ${table}: ${res.error || res.detail}`, 'err'); errors++; }
    } catch (e) { appendLog('targeted', `✗ ${table}: network error`, 'err'); errors++; }
    done++;
  }
  setProgress('targeted', 100, errors ? `Done with ${errors} error(s)` : 'Completed successfully!');
  document.getElementById('targeted-bar').classList.remove('progress-bar-animated', 'progress-bar-striped');
  document.getElementById('targeted-bar').classList.add(errors ? 'bg-warning' : 'bg-primary');
  appendLog('targeted', `\n── Sync complete: ${done - errors}/${done} tables OK ──`, 'info');
  setBtn('btn-targeted', false, '<i class="bi bi-arrow-clockwise me-1"></i> Sync Again');
}

async function runFull() {
  setBtn('btn-full', true, '<span class="spinner-border spinner-border-sm me-1"></span> Backing up…');
  document.getElementById('full-log').innerHTML = '';
  document.getElementById('full-progress').classList.remove('d-none');
  document.getElementById('full-log').classList.remove('d-none');
  await backupLocalDb('full', 'full');
  setBtn('btn-full', true, '<span class="spinner-border spinner-border-sm me-1"></span> Loading tables…');
  setProgress('full', 0, 'Getting table list from production…');
  let tableList;
  try { const res = await fetchJson(apiUrl('gettables'));
    if (!res.tables) { appendLog('full', '✗ Failed to get table list: ' + (res._error || JSON.stringify(res)), 'err'); setBtn('btn-full', false, '<i class="bi bi-play-circle me-1"></i> Run Full Sync'); return; }
    tableList = res.tables;
    appendLog('full', `ℹ Found ${tableList.length} tables on production`, 'info');
  } catch(e) { appendLog('full', '✗ Network error fetching table list', 'err'); setBtn('btn-full', false, '<i class="bi bi-play-circle me-1"></i> Run Full Sync'); return; }
  setBtn('btn-full', true, '<span class="spinner-border spinner-border-sm me-1"></span> Syncing…');
  let done = 0, errors = 0;
  for (const table of tableList) {
    setProgress('full', (done / tableList.length) * 100, `Fetching: ${table} (${done+1}/${tableList.length})`);
    try { const res = await fetchJson(apiUrl('fetchtable', `&table=${table}`));
      if (res.status === 'ok') { appendLog('full', `✓ ${table} — ${res.rows} rows`, 'ok'); }
      else { appendLog('full', `✗ ${table}: ${res.error || res.detail}`, 'err'); errors++; }
    } catch(e) { appendLog('full', `✗ ${table}: network error`, 'err'); errors++; }
    done++;
  }
  setProgress('full', 100, errors ? `Done with ${errors} error(s)` : 'Full sync complete!');
  document.getElementById('full-bar').classList.remove('progress-bar-animated', 'progress-bar-striped');
  document.getElementById('full-bar').classList.add(errors ? 'bg-warning' : 'bg-success');
  appendLog('full', `\n── Sync complete: ${done - errors}/${done} tables OK ──`, 'info');
  setBtn('btn-full', false, '<i class="bi bi-arrow-clockwise me-1"></i> Sync Again');
}

async function runLogPull() {
  setBtn('btn-logpull', true, '<span class="spinner-border spinner-border-sm me-1"></span> Pulling…');
  const box = document.getElementById('log-status'); box.className = 'alert alert-secondary mb-3 py-2 small'; box.classList.remove('d-none'); box.textContent = 'Fetching log from production…';
  try { const res = await fetchJson(apiUrl('logpull'));
    if (res.status === 'ok') { box.className = 'alert alert-success mb-3 py-2 small'; box.textContent = `✓ Log saved as: ${res.filename}  (${(res.size_bytes / 1024).toFixed(1)} KB)`; }
    else { box.className = 'alert alert-danger mb-3 py-2 small'; box.textContent = '✗ Error: ' + (res.error || JSON.stringify(res)); }
  } catch(e) { box.className = 'alert alert-danger mb-3 py-2 small'; box.textContent = '✗ Network error.'; }
  setBtn('btn-logpull', false, '<i class="bi bi-download me-1"></i> Pull Log File');
}

function confirmLogClear() { new bootstrap.Modal(document.getElementById('logClearModal')).show(); }
async function runLogClear() {
  const m = bootstrap.Modal.getInstance(document.getElementById('logClearModal')); if (m) m.hide();
  setBtn('btn-logclear', true, '<span class="spinner-border spinner-border-sm me-1"></span> Clearing…');
  const box = document.getElementById('clear-status'); box.className = 'alert alert-secondary mb-3 py-2 small'; box.classList.remove('d-none'); box.textContent = 'Sending clear request…';
  try { const res = await fetchJson(apiUrl('logclear'));
    if (res.status === 'ok') { box.className = 'alert alert-success mb-3 py-2 small'; box.textContent = '✓ Production log cleared successfully.'; }
    else { box.className = 'alert alert-danger mb-3 py-2 small'; box.textContent = '✗ Error: ' + (res.error || JSON.stringify(res)); }
  } catch(e) { box.className = 'alert alert-danger mb-3 py-2 small'; box.textContent = '✗ Network error.'; }
  setBtn('btn-logclear', false, '<i class="bi bi-trash3 me-1"></i> Clear Production Log');
}

async function runFilePush() {
  const filePath = document.getElementById('push-file-path').value.trim();
  if (!filePath) { alert('Enter a file path first.'); return; }
  setBtn('btn-filepush', true, '<span class="spinner-border spinner-border-sm me-1"></span> Pushing…');
  const box = document.getElementById('push-status'); box.className = 'alert alert-secondary mb-3 py-2 small'; box.classList.remove('d-none'); box.textContent = `Pushing ${filePath} to production…`;
  try { const res = await fetchJson(apiUrl('filepush', `&file=${encodeURIComponent(filePath)}`));
    if (res.status === 'ok') { box.className = 'alert alert-success mb-3 py-2 small'; box.textContent = `✓ Pushed: ${res.path} (${(res.size / 1024).toFixed(1)} KB)` + (res.backed_up ? ' — original backed up' : ''); }
    else { box.className = 'alert alert-danger mb-3 py-2 small'; box.textContent = '✗ Error: ' + (res.error || JSON.stringify(res)); }
  } catch(e) { box.className = 'alert alert-danger mb-3 py-2 small'; box.textContent = '✗ Network error.'; }
  setBtn('btn-filepush', false, '<i class="bi bi-cloud-upload me-1"></i> Push to Production');
}

function confirmCacheClear() { new bootstrap.Modal(document.getElementById('cacheClearModal')).show(); }
async function runCacheClear() {
  const m = bootstrap.Modal.getInstance(document.getElementById('cacheClearModal')); if (m) m.hide();
  setBtn('btn-clearcache', true, '<span class="spinner-border spinner-border-sm me-1"></span> Clearing…');
  const box = document.getElementById('cache-status'); box.className = 'alert alert-secondary mb-3 py-2 small'; box.classList.remove('d-none'); box.textContent = 'Clearing production cache…';
  try { const res = await fetchJson(apiUrl('clearcache'));
    if (res.status === 'ok') { box.className = 'alert alert-success mb-3 py-2 small'; box.textContent = `✓ Cache cleared — ${res.files_deleted} files deleted.`; }
    else { box.className = 'alert alert-danger mb-3 py-2 small'; box.textContent = '✗ Error: ' + (res.error || JSON.stringify(res)); }
  } catch(e) { box.className = 'alert alert-danger mb-3 py-2 small'; box.textContent = '✗ Network error.'; }
  setBtn('btn-clearcache', false, '<i class="bi bi-lightning-charge me-1"></i> Clear Production Cache');
}

let _sqlData = null;
async function runSql() {
  const sql = document.getElementById('sql-query').value.trim();
  if (!sql) { alert('Enter a SQL query first.'); return; }
  setBtn('btn-runsql', true, '<span class="spinner-border spinner-border-sm me-1"></span> Executing…');
  const box = document.getElementById('sql-status'); const results = document.getElementById('sql-results');
  const csvBtn = document.getElementById('btn-export-csv'); const rowCount = document.getElementById('sql-row-count');
  results.classList.add('d-none'); csvBtn.classList.add('d-none'); rowCount.classList.add('d-none');
  box.className = 'alert alert-secondary mb-3 py-2 small'; box.classList.remove('d-none'); box.textContent = 'Executing query on production…';
  try { const r = await fetch(apiUrl('runsql'), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ sql }) });
    const res = await r.json();
    if (res.status === 'ok') { _sqlData = res; box.className = 'alert alert-success mb-3 py-2 small';
      const shown = res.rows.length; const total = res.total;
      box.textContent = `✓ Query executed — ${total} row(s) returned` + (total > 1000 ? ' (showing first 1,000)' : '');
      document.getElementById('sql-thead').innerHTML = '<tr>' + res.columns.map(c => `<th class="text-nowrap px-2" style="background:#f8f9fa; position:sticky; top:0; z-index:1;">${escHtml(c)}</th>`).join('') + '</tr>';
      document.getElementById('sql-tbody').innerHTML = res.rows.map(row => '<tr>' + res.columns.map(c => `<td class="text-nowrap px-2">${escHtml(String(row[c] ?? 'NULL'))}</td>`).join('') + '</tr>').join('');
      results.classList.remove('d-none'); csvBtn.classList.remove('d-none'); rowCount.classList.remove('d-none');
      rowCount.textContent = `${shown} row(s)` + (total > 1000 ? ` of ${total}` : '');
    } else { box.className = 'alert alert-danger mb-3 py-2 small'; box.textContent = '✗ Error: ' + (res.error || JSON.stringify(res)); _sqlData = null; }
  } catch(e) { box.className = 'alert alert-danger mb-3 py-2 small'; box.textContent = '✗ Network error.'; _sqlData = null; }
  setBtn('btn-runsql', false, '<i class="bi bi-play-fill me-1"></i> Execute');
}
function escHtml(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
function exportCsv() {
  if (!_sqlData || !_sqlData.rows.length) return;
  const esc = v => '"' + String(v ?? '').replace(/"/g, '""') + '"';
  const lines = [_sqlData.columns.map(esc).join(',')];
  for (const row of _sqlData.rows) { lines.push(_sqlData.columns.map(c => esc(row[c])).join(',')); }
  const blob = new Blob([lines.join('\n')], { type: 'text/csv' });
  const url = URL.createObjectURL(blob); const a = document.createElement('a'); a.href = url;
  a.download = 'query_results_' + new Date().toISOString().slice(0,19).replace(/[:-]/g,'') + '.csv';
  a.click(); URL.revokeObjectURL(url);
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
        return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }
}
