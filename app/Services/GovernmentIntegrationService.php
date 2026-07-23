<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GovSyncLog;
use Core\Database\QueryBuilder;
use Core\Security\Crypto;

/**
 * Future-ready government-integration layer. Provides a modular *adapter
 * registry* (UDISE+, APAAR, ABC, DigiLocker, NAD, EMIS, Scholarship) whose
 * credentials are stored encrypted at rest and toggled from the Admin panel.
 *
 * IMPORTANT: no external API endpoints are hardcoded here. This service manages
 * configuration, enable/disable, masking, and a synchronization log (with
 * status + retry). When an official integration is connected, its adapter reads
 * the decrypted config via get() and records attempts via logSync()/markResult().
 */
final class GovernmentIntegrationService
{
    /**
     * @var array<string,array{label:string,fields:array<int,string>,secret:array<int,string>}>
     */
    private const PROVIDERS = [
        'udise'       => ['label' => 'UDISE+',              'fields' => ['school_code', 'api_key'],                 'secret' => ['api_key'],       'direction' => 'bidirectional'],
        'apaar'       => ['label' => 'APAAR',               'fields' => ['client_id', 'client_secret', 'base_url'], 'secret' => ['client_secret'], 'direction' => 'bidirectional'],
        'abc'         => ['label' => 'Academic Bank of Credits', 'fields' => ['client_id', 'client_secret', 'base_url'], 'secret' => ['client_secret'], 'direction' => 'push'],
        'digilocker'  => ['label' => 'DigiLocker',          'fields' => ['client_id', 'client_secret', 'redirect_uri'], 'secret' => ['client_secret'], 'direction' => 'pull'],
        'nad'         => ['label' => 'NAD',                 'fields' => ['client_id', 'client_secret'],             'secret' => ['client_secret'], 'direction' => 'push'],
        'emis'        => ['label' => 'State EMIS',          'fields' => ['state_code', 'api_key', 'base_url'],      'secret' => ['api_key'],       'direction' => 'bidirectional'],
        'scholarship' => ['label' => 'Scholarship Portal',  'fields' => ['portal_id', 'api_key', 'base_url'],       'secret' => ['api_key'],       'direction' => 'pull'],
    ];

    /** Supported operation direction for a provider: pull | push | bidirectional. */
    public function direction(string $provider): string
    {
        return self::PROVIDERS[$provider]['direction'] ?? 'pull';
    }

    /** Whether a provider allows reading from the government source. */
    public function supportsPull(string $provider): bool
    {
        return in_array($this->direction($provider), ['pull', 'bidirectional'], true);
    }

    /** Whether a provider allows writing to the government source. */
    public function supportsPush(string $provider): bool
    {
        return in_array($this->direction($provider), ['push', 'bidirectional'], true);
    }

    /** @return array<string,array{label:string,fields:array<int,string>,secret:array<int,string>}> */
    public static function providers(): array
    {
        return self::PROVIDERS;
    }

    public static function isProvider(string $p): bool
    {
        return isset(self::PROVIDERS[$p]);
    }

    /**
     * Full decrypted config for a provider (INTERNAL — contains secrets).
     * @return array<string,mixed>
     */
    public function get(string $provider): array
    {
        if (!self::isProvider($provider)) {
            return [];
        }
        $row = QueryBuilder::table('settings')->where('key', 'integration.' . $provider)->first();
        $decoded = $row ? json_decode((string) $row['value'], true) : [];

        return $this->normalize($provider, is_array($decoded) ? $decoded : []);
    }

    /**
     * @param array<string,mixed> $raw
     * @return array<string,mixed>
     */
    private function normalize(string $provider, array $raw): array
    {
        $meta = self::PROVIDERS[$provider];
        $mode = $raw['mode'] ?? 'sandbox';
        if (!in_array($mode, ['sandbox', 'live'], true)) {
            $mode = 'sandbox';
        }
        $config = [
            'provider' => $provider,
            'label'    => $meta['label'],
            'enabled'  => (bool) ($raw['enabled'] ?? false),
            'mode'     => $mode,
        ];
        foreach ($meta['fields'] as $field) {
            $value = (string) ($raw[$field] ?? '');
            if ($value !== '' && in_array($field, $meta['secret'], true)) {
                $value = (string) (Crypto::decrypt($value) ?? '');
            }
            $config[$field] = $value;
        }
        return $config;
    }

    /**
     * Persist a provider config, encrypting secret fields. Blank secret fields
     * keep the previously stored value (masked in the UI).
     *
     * @param array<string,mixed> $input
     */
    public function save(string $provider, array $input): void
    {
        if (!self::isProvider($provider)) {
            return;
        }
        $meta = self::PROVIDERS[$provider];
        $existing = $this->get($provider);
        $store = [
            'enabled' => !empty($input['enabled']),
            'mode'    => (($input['mode'] ?? 'sandbox') === 'live') ? 'live' : 'sandbox',
        ];
        foreach ($meta['fields'] as $field) {
            $new = trim((string) ($input[$field] ?? ''));
            $plain = ($new === '' && in_array($field, $meta['secret'], true))
                ? (string) ($existing[$field] ?? '')
                : $new;
            $store[$field] = (in_array($field, $meta['secret'], true) && $plain !== '')
                ? (string) Crypto::encrypt($plain)
                : $plain;
        }
        $payload = json_encode($store, JSON_UNESCAPED_SLASHES);
        $exists = QueryBuilder::table('settings')->where('key', 'integration.' . $provider)->first();
        if ($exists) {
            QueryBuilder::table('settings')->where('key', 'integration.' . $provider)
                ->update(['value' => $payload, 'updated_at' => date('Y-m-d H:i:s')]);
        } else {
            QueryBuilder::table('settings')->insert([
                'group' => 'integration', 'key' => 'integration.' . $provider, 'value' => $payload,
                'type' => 'json', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
        AuditLogger::log('integration.configured', 'settings', null, 'Configured ' . $provider . ' integration');
    }

    public function isEnabled(string $provider): bool
    {
        return (bool) ($this->get($provider)['enabled'] ?? false);
    }

    /**
     * Config safe for the admin UI: secrets masked, never returned in clear.
     * @return array<string,mixed>
     */
    public function publicConfig(string $provider): array
    {
        $config = $this->get($provider);
        if ($config === []) {
            return [];
        }
        foreach (self::PROVIDERS[$provider]['secret'] as $field) {
            $set = (string) ($config[$field] ?? '') !== '';
            $config[$field] = $set ? '••••••••' : '';
            $config[$field . '_set'] = $set;
        }
        return $config;
    }

    /**
     * Enabled providers for use by adapters/UI — no secrets.
     * @return array<int,array{provider:string,label:string,mode:string}>
     */
    public function enabled(): array
    {
        $out = [];
        foreach (array_keys(self::PROVIDERS) as $p) {
            $c = $this->get($p);
            if (!empty($c['enabled'])) {
                $out[] = ['provider' => $p, 'label' => (string) $c['label'], 'mode' => (string) $c['mode']];
            }
        }
        return $out;
    }

    // -- Sync ledger --------------------------------------------------------

    /** Record a (pending) sync attempt for a provider. Returns the log id. */
    public function logSync(string $provider, string $action, ?int $studentId = null, string $status = 'pending', ?string $requestRef = null, ?string $message = null): int
    {
        return GovSyncLog::create([
            'provider'    => $provider,
            'student_id'  => $studentId,
            'action'      => $action,
            'status'      => in_array($status, ['pending', 'success', 'failed', 'retry'], true) ? $status : 'pending',
            'request_ref' => $requestRef,
            'message'     => $message !== null ? substr($message, 0, 500) : null,
        ]);
    }

    /** Advance a sync attempt's result (called by an adapter after an API call). */
    public function markResult(int $logId, string $status, ?string $message = null): void
    {
        if (!in_array($status, ['pending', 'success', 'failed', 'retry'], true)) {
            $status = 'failed';
        }
        GovSyncLog::updateById($logId, [
            'status'  => $status,
            'message' => $message !== null ? substr($message, 0, 500) : null,
        ]);
        AuditLogger::log('integration.sync_' . $status, 'government_sync_log', $logId, 'Sync ' . $status);
    }
}
