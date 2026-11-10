<?php

declare(strict_types=1);

namespace Tekservix\WAservix;

/**
 * Client for the WAservix WhatsApp API Gateway.
 *
 * Docs: https://tekservix.com/waservix/docs/
 */
class WAservix
{
    public const VERSION = '1.0.1';
    public const DEFAULT_BASE_URL = 'https://tekservix.com/waservix/api';

    private string $apiKey;
    private string $baseUrl;
    private int $timeout;
    /** @var callable|null */
    private $handler;

    /**
     * @param string $apiKey Device API key (wpk_...) or a personal access token for session management.
     * @param array{baseUrl?: string, timeout?: int, handler?: callable} $options
     *        handler: optional fn(string $method, string $url, array $headers, ?string $body): array{0:int,1:array,2:string}
     */
    public function __construct(string $apiKey, array $options = [])
    {
        if ($apiKey === '') {
            throw new \InvalidArgumentException('WAservix: apiKey is required (Devices -> API key in the WAservix dashboard)');
        }
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($options['baseUrl'] ?? self::DEFAULT_BASE_URL, '/');
        $this->timeout = (int) ($options['timeout'] ?? 30);
        $this->handler = $options['handler'] ?? null;
    }

    /**
     * Call any documented endpoint, e.g. request('GET', '/groups'). Returns the `data` field.
     *
     * @return mixed
     * @throws WAservixException
     */
    public function request(string $method, string $path, ?array $body = null)
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
            'User-Agent' => 'waservix-php/' . self::VERSION,
        ];
        $payload = null;
        if ($body !== null) {
            $payload = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $headers['Content-Type'] = 'application/json';
        }

        [$status, $resHeaders, $text] = $this->handler
            ? ($this->handler)($method, $this->baseUrl . $path, $headers, $payload)
            : $this->send($method, $this->baseUrl . $path, $headers, $payload);

        $json = $text !== '' ? json_decode($text, true) : null;
        if ($status >= 400 || (is_array($json) && ($json['success'] ?? null) === false)) {
            $retry = $resHeaders['retry-after'] ?? null;
            throw new WAservixException(
                (is_array($json) && isset($json['message'])) ? (string) $json['message'] : 'HTTP ' . $status,
                $status,
                is_array($json) ? ($json['errors'] ?? null) : null,
                $json ?? $text,
                $retry !== null ? (float) $retry : null
            );
        }
        return (is_array($json) && array_key_exists('data', $json)) ? $json['data'] : $json;
    }

    /**
     * Send any message type to a number or group JID (...@g.us).
     *
     * $fields use the API names: text, imageUrl, videoUrl, audioUrl, documentUrl, fileName,
     * stickerUrl, contact, location, poll, replyTo, mentions, viewOnce.
     * Returns ['msgId' => ..., 'jid' => ..., 'status' => 'in_progress'].
     */
    public function sendMessage(string $to, array $fields = [])
    {
        if ($to === '') {
            throw new \InvalidArgumentException('sendMessage: "to" is required (international number or group JID)');
        }
        $body = ['to' => $to] + array_filter($fields, static fn ($v) => $v !== null);
        return $this->request('POST', '/send-message', $body);
    }

    public function sendText(string $to, string $text)
    {
        return $this->sendMessage($to, ['text' => $text]);
    }

    public function sendImage(string $to, string $imageUrl, ?string $caption = null)
    {
        return $this->sendMessage($to, ['imageUrl' => $imageUrl, 'text' => $caption]);
    }

    public function sendDocument(string $to, string $documentUrl, ?string $fileName = null, ?string $caption = null)
    {
        return $this->sendMessage($to, ['documentUrl' => $documentUrl, 'fileName' => $fileName, 'text' => $caption]);
    }

    /** Status: queued, sent, delivered, read, played or failed. */
    public function getMessageInfo($id)
    {
        return $this->request('GET', '/messages/' . rawurlencode((string) $id) . '/info');
    }

    public function editMessage($id, string $text)
    {
        return $this->request('PUT', '/messages/' . rawurlencode((string) $id), ['text' => $text]);
    }

    public function deleteMessage($id)
    {
        return $this->request('DELETE', '/messages/' . rawurlencode((string) $id));
    }

    public function resendMessage($id)
    {
        return $this->request('POST', '/messages/' . rawurlencode((string) $id) . '/resend');
    }

    /** Is this number on WhatsApp? Returns ['exists' => bool, 'jid' => ...]. */
    public function onWhatsApp(string $phone)
    {
        return $this->request('GET', '/on-whatsapp/' . rawurlencode($phone));
    }

    /** connected, connecting, need_scan, disconnected, logged_out or expired. */
    public function getStatus()
    {
        return $this->request('GET', '/status');
    }

    public function getUser()
    {
        return $this->request('GET', '/user');
    }

    public function listContacts()
    {
        return $this->request('GET', '/contacts');
    }

    public function getContact(string $phone)
    {
        return $this->request('GET', '/contacts/' . rawurlencode($phone));
    }

    public function saveContact(string $phone, string $name)
    {
        return $this->request('PUT', '/contacts', ['phone' => $phone, 'name' => $name]);
    }

    public function listGroups()
    {
        return $this->request('GET', '/groups');
    }

    public function getGroupMetadata(string $groupJid)
    {
        return $this->request('GET', '/groups/' . rawurlencode($groupJid) . '/metadata');
    }

    /** Session management (needs a personal access token, not a device key). */
    public function listSessions()
    {
        return $this->request('GET', '/whatsapp-sessions');
    }

    public function getSession($id)
    {
        return $this->request('GET', '/whatsapp-sessions/' . rawurlencode((string) $id));
    }

    public function createSession(array $params)
    {
        return $this->request('POST', '/whatsapp-sessions', $params);
    }

    /** $params: ['method' => 'qr'] or ['method' => 'code', 'phone' => '+1555...'] */
    public function connectSession($id, array $params = ['method' => 'qr'])
    {
        return $this->request('POST', '/whatsapp-sessions/' . rawurlencode((string) $id) . '/connect', $params);
    }

    public function getSessionQrCode($id)
    {
        return $this->request('GET', '/whatsapp-sessions/' . rawurlencode((string) $id) . '/qrcode');
    }

    /** Constant-time check of the X-Webhook-Signature header against your device's webhook secret. */
    public static function verifyWebhook(?string $signature, string $secret): bool
    {
        if (!$signature || $secret === '') {
            return false;
        }
        return hash_equals($secret, $signature);
    }

    /** @return array{0:int,1:array<string,string>,2:string} */
    private function send(string $method, string $url, array $headers, ?string $body): array
    {
        $lines = [];
        foreach ($headers as $k => $v) {
            $lines[] = $k . ': ' . $v;
        }
        $resHeaders = [];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HEADERFUNCTION => static function ($ch, $line) use (&$resHeaders) {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $resHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($line);
            },
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $text = curl_exec($ch);
        if ($text === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new WAservixException('WAservix: request failed: ' . $error);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return [$status, $resHeaders, (string) $text];
    }
}
