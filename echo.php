<?php
/**
 * echo.php — QUERY-aware HTTP method echo endpoint.
 *
 * Part of the repro for whatwg/html#12594.
 * Host this on any PHP-capable server. It:
 *   - Handles CORS preflight and explicitly allows the QUERY method
 *     (so cross-origin `fetch(url, {method:'QUERY'})` works against it).
 *   - Renders a nice HTML result page when opened via a form submission
 *     (top-level navigation), and returns JSON when called via fetch().
 *
 * Pass a hidden `__intended` field/param (e.g. "QUERY") and the page will
 * compare what you intended to send vs. what actually arrived on the wire.
 */

// ---- CORS: allow any origin and, crucially, the QUERY method ----
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: $origin");
header('Vary: Origin');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, HEAD, OPTIONS, QUERY');
header('Access-Control-Allow-Headers: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 3600');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Preflight: answer and stop.
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ---- Collect what arrived ----
$rawBody = file_get_contents('php://input');
$contentType = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? null);

$query = $_GET;
$bodyParams = [];
if ($rawBody !== '' && stripos((string)$contentType, 'application/json') !== false) {
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) $bodyParams = $decoded;
} elseif ($rawBody !== '') {
    parse_str($rawBody, $bodyParams);
}

// Intent marker (sent as a hidden form field / param), then strip markers.
$intended = $query['__intended'] ?? ($bodyParams['__intended'] ?? null);
foreach (['__intended', '__via'] as $m) {
    unset($query[$m], $bodyParams[$m]);
}

$secFetchMode = $_SERVER['HTTP_SEC_FETCH_MODE'] ?? '';
$accept = $_SERVER['HTTP_ACCEPT'] ?? '';

// Decide format: fetch() (cors/no navigation) or explicit ?format=json → JSON; else HTML.
$wantsJson = $secFetchMode === 'cors'
    || (isset($_GET['format']) && $_GET['format'] === 'json')
    || ($secFetchMode !== 'navigate' && stripos($accept, 'application/json') !== false);

$payload = [
    'method'      => $method,
    'intended'    => $intended,
    'matched'     => $intended ? ($method === strtoupper($intended)) : null,
    'query'       => $query,
    'body'        => $bodyParams,
    'rawBody'     => $rawBody,
    'contentType' => $contentType,
];

if ($wantsJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// ---- Nice HTML result page (for form / direct navigation) ----
$actual = htmlspecialchars($method);
$intendedSafe = htmlspecialchars((string)($intended ?? '—'));
$fellBack = $intended && strtoupper($intended) === 'QUERY' && $method !== 'QUERY';
$isQueryOk = strtoupper((string)$intended) === 'QUERY' && $method === 'QUERY';

$verdict = $fellBack
    ? '<p class="warn">⚠️ The browser did <b>not</b> send QUERY — it fell back to <b>' . $actual . '</b>, '
        . 'and any request body was dropped. This confirms HTML forms don\'t support QUERY yet.</p>'
    : ($isQueryOk
        ? '<p class="ok">✅ QUERY arrived on the wire (this request was made via fetch(), not a form).</p>'
        : '<p class="ok">✅ Method <b>' . $actual . '</b> as expected.</p>');

$dataJson = htmlspecialchars(json_encode(
    ['query' => $query, 'body' => $bodyParams],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
));
$ctSafe = htmlspecialchars((string)($contentType ?? '(none)'));
$modeSafe = htmlspecialchars($secFetchMode ?: '(none)');
$back = htmlspecialchars($_SERVER['HTTP_REFERER'] ?? './');
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Echo — method received: <?= $actual ?></title>
<style>
  :root { color-scheme: dark; }
  * { box-sizing: border-box; }
  body { margin:0; min-height:100vh; display:grid; place-items:center;
    font:15px/1.6 ui-monospace,Menlo,Consolas,monospace; background:#0d1117; color:#e6edf3; padding:24px; }
  .card { width:min(660px,100%); background:#161b22; border:1px solid #30363d; border-radius:14px;
    padding:30px; box-shadow:0 10px 40px rgba(0,0,0,.45); }
  h1 { font-size:18px; margin:0 0 20px; color:#8b949e; font-weight:500; }
  .method { font-size:44px; font-weight:700; letter-spacing:1px; margin:0 0 4px;
    background:linear-gradient(90deg,#58a6ff,#3fb950); -webkit-background-clip:text;
    background-clip:text; color:transparent; }
  .row { display:flex; justify-content:space-between; gap:12px; padding:8px 0;
    border-bottom:1px solid #21262d; font-size:13px; }
  .row .k { color:#8b949e; } .row .v { color:#e6edf3; text-align:right; word-break:break-all; }
  .ok{color:#3fb950}.warn{color:#d29922}
  .verdict { margin:18px 0; font-size:14px; }
  pre { background:#0d1117; border:1px solid #30363d; border-radius:8px; padding:14px;
    overflow-x:auto; font-size:13px; margin:10px 0 0; }
  a.btn { display:inline-block; margin-top:22px; padding:10px 16px; background:#21262d;
    color:#79c0ff; border:1px solid #30363d; border-radius:8px; text-decoration:none; }
  a.btn:hover { background:#30363d; }
  .label { font-size:12px; color:#8b949e; margin:18px 0 4px; }
</style></head>
<body><div class="card">
  <h1>HTTP method that reached the server</h1>
  <p class="method"><?= $actual ?></p>
  <div class="verdict"><?= $verdict ?></div>

  <div class="row"><span class="k">intended (form method / param)</span><span class="v"><?= $intendedSafe ?></span></div>
  <div class="row"><span class="k">actual method on the wire</span><span class="v"><b><?= $actual ?></b></span></div>
  <div class="row"><span class="k">content-type received</span><span class="v"><?= $ctSafe ?></span></div>
  <div class="row"><span class="k">Sec-Fetch-Mode</span><span class="v"><?= $modeSafe ?></span></div>

  <p class="label">data received (query + body):</p>
  <pre><?= $dataJson ?></pre>

  <a class="btn" href="<?= $back ?>">← back to the demo</a>
</div></body></html>
