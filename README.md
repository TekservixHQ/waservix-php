# WAservix PHP client

PHP client for the [WAservix WhatsApp API Gateway](https://tekservix.com/waservix/).

WAservix links your own WhatsApp number by QR code (the same way WhatsApp Web does) and gives you a REST API to send text, images, video, audio, documents, locations, polls and more, with webhooks for incoming messages and status updates.

- PHP 7.4 to 8.4, no dependencies beyond the curl and json extensions
- Works in Laravel, Symfony, WordPress plugins and plain PHP
- Webhook check built in

## Install

```bash
composer require tekservix/waservix
```

## Quick start

Copy your device's API key from the WAservix dashboard (Devices -> API key) and keep it on your server.

```php
use Tekservix\WAservix\WAservix;

$wa = new WAservix(getenv('WASERVIX_KEY'));

$result = $wa->sendText('+15551234567', 'Hello Aline, your order is ready');
echo $result['msgId'], ' ', $result['status']; // 58213 in_progress
```

## Sending

Messages are queued and sent one at a time per number, so each call returns right away with `status: "in_progress"` and a `msgId`.

```php
// Image with caption
$wa->sendImage('+15551234567', 'https://example.com/product.jpg', 'Back in stock');

// Document
$wa->sendDocument('+15551234567', 'https://example.com/inv-1042.pdf', 'Invoice-1042.pdf', 'Your invoice');

// Anything the API supports, including groups (JID ending in @g.us)
$wa->sendMessage('120363040000000000@g.us', ['text' => 'Team meeting at 3pm']);
$wa->sendMessage('+15551234567', ['location' => ['latitude' => -1.9441, 'longitude' => 30.0619, 'name' => 'Our shop']]);
```

## Status, contacts and groups

```php
$info  = $wa->getMessageInfo(58213);      // queued, sent, delivered, read, played or failed
$check = $wa->onWhatsApp('+15551234567'); // ['exists' => true, 'jid' => '...']
$conn  = $wa->getStatus();                // ['status' => 'connected']

$wa->editMessage(58213, 'Corrected text'); // own text messages, about 15 minutes
$groups = $wa->listGroups();
```

Anything else in the [API reference](https://tekservix.com/waservix/docs/) is one call away:

```php
$wa->request('POST', '/messages/read', ['msgId' => '3EB0X123456789']);
```

## Webhooks

Set a webhook URL and events for the number in the dashboard. Each request carries `X-Webhook-Signature`, which equals the device's webhook secret:

```php
use Tekservix\WAservix\WAservix;

if (!WAservix::verifyWebhook($_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? null, getenv('WASERVIX_WEBHOOK_SECRET'))) {
    http_response_code(401);
    exit;
}
http_response_code(200); // acknowledge fast; queue slow work

$payload = json_decode(file_get_contents('php://input'), true);
if ($payload['event'] === 'messages.received') {
    $m = $payload['data']['messages'];
    error_log($m['key']['cleanedSenderPn'] . ': ' . $m['messageBody']);
}
```

## Errors

Failed calls throw `Tekservix\WAservix\WAservixException` with `status`, `getMessage()`, field `errors` and, on 429, `retryAfter` in seconds. A 503 means the device is reconnecting: retry shortly.

Never put the key in JavaScript or a mobile app: anyone could read it. Call the API from your server.

## WordPress and WooCommerce

No code needed: the [WAservix WordPress plugin](https://tekservix.com/waservix/docs/wordpress-plugin) sends WooCommerce order messages out of the box.

## Migrating from WaSender

WAservix accepts the same `/send-message` body and webhook fields as WaSender, so most integrations switch by changing the base URL and key. See the [compatibility notes](https://tekservix.com/waservix/docs/).

## Use it responsibly

WAservix is not Meta's official WhatsApp Business Platform. There's no business verification and no per-conversation fee, but WhatsApp's spam rules apply to your number directly. Message people who expect to hear from you, warm up new numbers slowly and keep the built-in account protection on.

## More from Tekservix

- [SMS Gora SMS Gateway](https://tekservix.com/smsgora/): send SMS through your own Android phones (`composer require tekservix/smsgora`)
- [AI Sales Agent](https://tekservix.com/ai-sales-agent): answers customers on WhatsApp and SMS using your website's content
- [Website to App](https://tekservix.com/mobile-apps): turn your website into Android and iOS apps
- Also for Node.js (`npm install waservix`) and Python (`pip install waservix`)

## License

MIT
