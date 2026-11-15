<?php
/**
 * Minimal S3-compatible server for tests (php -S ... this file). It VERIFIES the AWS
 * Signature V4 of every request with the same secret, then stores objects in a directory.
 * It proves the client signs and talks correctly; it is not a real S3 / R2 service.
 */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/core/Storage/StorageDriver.php';
require dirname(__DIR__, 2) . '/core/Storage/LocalStorage.php';
require dirname(__DIR__, 2) . '/core/Storage/S3Storage.php';
use Core\Storage\S3Storage;

$root = getenv('S3_MOCK_DIR') ?: sys_get_temp_dir() . '/s3mock';
$access = 'AKIAMOCK'; $secret = 'mock-secret-key';
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!preg_match('/Credential=([^\/]+)\/(\d{8})\/([^\/]+)\/s3\/aws4_request, SignedHeaders=([^,]+), Signature=([0-9a-f]{64})/', $auth, $m)) {
    http_response_code(403); echo 'missing signature'; return;
}
$names = explode(';', $m[4]);
$headers = [];
foreach ($names as $n) {
    $headers[$n] = $n === 'host' ? $_SERVER['HTTP_HOST'] : ($_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $n))] ?? ($n === 'content-type' ? ($_SERVER['CONTENT_TYPE'] ?? '') : ''));
}
$body = (string) file_get_contents('php://input');
$path = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$expected = S3Storage::authorization($_SERVER['REQUEST_METHOD'], $path, '', $headers, hash('sha256', $body), $access, $secret, $m[3], 's3', $_SERVER['HTTP_X_AMZ_DATE']);
if ($m[1] !== $access || !hash_equals($expected, $auth) || hash('sha256', $body) !== ($_SERVER['HTTP_X_AMZ_CONTENT_SHA256'] ?? '')) {
    http_response_code(403); echo 'SignatureDoesNotMatch'; return;
}
$file = $root . '/' . rawurldecode(ltrim($path, '/'));
switch ($_SERVER['REQUEST_METHOD']) {
    case 'PUT': @mkdir(dirname($file), 0775, true); file_put_contents($file, $body); http_response_code(200); break;
    case 'GET': if (is_file($file)) { echo file_get_contents($file); } else { http_response_code(404); } break;
    case 'HEAD': http_response_code(is_file($file) ? 200 : 404); break;
    case 'DELETE': @unlink($file); http_response_code(204); break;
}
