<?php

declare(strict_types=1);

namespace Core\Storage;

/**
 * S3-compatible object storage (AWS S3, Cloudflare R2, MinIO, Backblaze B2,
 * DigitalOcean Spaces, ...) over HTTPS with AWS Signature Version 4, using
 * only ext-curl. No SDK dependency.
 *
 * Objects are private. They are only ever read by the application and served
 * through authorised controller actions; no public or pre-signed URLs are issued.
 */
final class S3Storage implements StorageDriver
{
    public function __construct(
        private readonly string $endpoint,     // https://s3.amazonaws.com or https://<account>.r2.cloudflarestorage.com
        private readonly string $region,       // "auto" for R2
        private readonly string $bucket,
        private readonly string $accessKey,
        private readonly string $secretKey,
        private readonly bool $pathStyle = true,
        private readonly string $prefix = '',
        private readonly bool $verifyTls = true,
    ) {
        if (!extension_loaded('curl')) {
            throw new \RuntimeException('The curl extension is required for S3/R2 storage.');
        }
        if ($endpoint === '' || $bucket === '' || $accessKey === '' || $secretKey === '') {
            throw new \InvalidArgumentException('S3 storage needs endpoint, bucket, access key and secret key.');
        }
        if (!preg_match('#^https?://#i', $endpoint)) {
            throw new \InvalidArgumentException('S3 endpoint must start with https:// (or http:// for local testing).');
        }
    }

    public function name(): string
    {
        return 's3';
    }

    public function put(string $key, string $contents, ?string $mime = null): void
    {
        $r = $this->request('PUT', $key, $contents, ['content-type' => $mime ?: 'application/octet-stream']);
        if ($r['status'] < 200 || $r['status'] >= 300) {
            throw new \RuntimeException('Object storage rejected the upload (HTTP ' . $r['status'] . ').');
        }
    }

    public function get(string $key): string
    {
        $r = $this->request('GET', $key);
        if ($r['status'] !== 200) {
            throw new \RuntimeException('File not found.');
        }
        return $r['body'];
    }

    public function exists(string $key): bool
    {
        return $this->request('HEAD', $key)['status'] === 200;
    }

    public function delete(string $key): void
    {
        $this->request('DELETE', $key);
    }

    // ------------------------------------------------------------------ SigV4

    /**
     * Build the Authorization header. Public + parameterised so it can be
     * checked against AWS's published test vectors.
     *
     * @param array<string,string> $headers lower-case header name => value (must include host and x-amz-date)
     */
    public static function authorization(
        string $method, string $canonicalPath, string $canonicalQuery, array $headers, string $payloadHash,
        string $accessKey, string $secretKey, string $region, string $service, string $amzDate
    ): string {
        ksort($headers);
        $canonHeaders = '';
        foreach ($headers as $k => $v) {
            $canonHeaders .= $k . ':' . trim((string) preg_replace('/\s+/', ' ', $v)) . "\n";
        }
        $signed = implode(';', array_keys($headers));
        $canonical = implode("\n", [$method, $canonicalPath, $canonicalQuery, $canonHeaders, $signed, $payloadHash]);
        $date = substr($amzDate, 0, 8);
        $scope = "{$date}/{$region}/{$service}/aws4_request";
        $toSign = implode("\n", ['AWS4-HMAC-SHA256', $amzDate, $scope, hash('sha256', $canonical)]);
        $k = hash_hmac('sha256', 'aws4_request', hash_hmac('sha256', $service, hash_hmac('sha256', $region, hash_hmac('sha256', $date, 'AWS4' . $secretKey, true), true), true), true);
        $sig = hash_hmac('sha256', $toSign, $k);
        return "AWS4-HMAC-SHA256 Credential={$accessKey}/{$scope}, SignedHeaders={$signed}, Signature={$sig}";
    }

    /** @return array{status:int,body:string} */
    private function request(string $method, string $key, string $body = '', array $extra = []): array
    {
        $key = LocalStorage::cleanKey(($this->prefix !== '' ? trim($this->prefix, '/') . '/' : '') . $key);
        $u = parse_url($this->endpoint);
        $host = (string) $u['host'] . (isset($u['port']) ? ':' . $u['port'] : '');
        $encKey = implode('/', array_map('rawurlencode', explode('/', $key)));
        if ($this->pathStyle) {
            $path = '/' . rawurlencode($this->bucket) . '/' . $encKey;
        } else {
            $host = $this->bucket . '.' . $host;
            $path = '/' . $encKey;
        }
        $amzDate = gmdate('Ymd\THis\Z');
        $payloadHash = hash('sha256', $body);
        $headers = ['host' => $host, 'x-amz-content-sha256' => $payloadHash, 'x-amz-date' => $amzDate] + array_change_key_case($extra, CASE_LOWER);
        $auth = self::authorization($method, $path, '', $headers, $payloadHash, $this->accessKey, $this->secretKey, $this->region, 's3', $amzDate);

        $h = [];
        foreach ($headers as $k => $v) {
            if ($k !== 'host') {
                $h[] = "{$k}: {$v}";
            }
        }
        $h[] = 'Authorization: ' . $auth;
        $ch = curl_init(($u['scheme'] ?? 'https') . '://' . $host . $path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $h, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_NOBODY => $method === 'HEAD',
            CURLOPT_SSL_VERIFYPEER => $this->verifyTls, CURLOPT_SSL_VERIFYHOST => $this->verifyTls ? 2 : 0,
        ]);
        if ($body !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $resp = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($resp === false) {
            curl_close($ch);
            throw new \RuntimeException('Could not reach the object storage endpoint.');
        }
        curl_close($ch);
        return ['status' => $status, 'body' => (string) $resp];
    }
}
