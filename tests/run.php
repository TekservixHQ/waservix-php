<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';

use Tekservix\WAservix\WAservix;
use Tekservix\WAservix\WAservixException;

$fails = 0;
function check(string $name, bool $ok): void { global $fails; echo ($ok ? 'ok   ' : 'FAIL ') . $name . PHP_EOL; if (!$ok) $fails++; }

$last = null;
$reply = [200, [], '{"success":true,"data":{"msgId":1,"status":"in_progress"}}'];
$handler = function ($m, $u, $h, $b) use (&$last, &$reply) { $last = [$m, $u, $h, $b === null ? null : json_decode($b, true)]; return $reply; };
$c = new WAservix('wpk_test', ['baseUrl' => 'https://api.test', 'handler' => $handler]);

check('send text', $c->sendText('+1555', 'hi')['msgId'] === 1);
check('send path + body', $last[0] === 'POST' && $last[1] === 'https://api.test/send-message' && $last[3] === ['to' => '+1555', 'text' => 'hi']);
check('bearer header', $last[2]['Authorization'] === 'Bearer wpk_test');

$c->sendImage('+1', 'https://x/y.jpg');
check('image body', $last[3] === ['to' => '+1', 'imageUrl' => 'https://x/y.jpg']);
$c->sendDocument('+1', 'https://x/a.pdf', 'a.pdf', 'c');
check('document body', $last[3] === ['to' => '+1', 'documentUrl' => 'https://x/a.pdf', 'fileName' => 'a.pdf', 'text' => 'c']);

$c->getMessageInfo(5); check('info path', $last[1] === 'https://api.test/messages/5/info');
$c->editMessage(5, 't'); check('edit', $last[0] === 'PUT' && $last[3] === ['text' => 't']);
$c->onWhatsApp('+1555'); check('on-whatsapp encodes', $last[1] === 'https://api.test/on-whatsapp/%2B1555');
$c->getGroupMetadata('1203@g.us'); check('group encodes', $last[1] === 'https://api.test/groups/1203%40g.us/metadata');
$c->saveContact('+1', 'A'); check('save contact', $last[0] === 'PUT' && $last[3] === ['phone' => '+1', 'name' => 'A']);
$c->connectSession(3); check('connect default qr', $last[3] === ['method' => 'qr']);

$reply = [503, [], '{"success":false,"message":"Device reconnecting"}'];
try { $c->getStatus(); check('503 throws', false); }
catch (WAservixException $e) { check('503 details', $e->status === 503 && $e->getMessage() === 'Device reconnecting'); }

check('webhook valid', WAservix::verifyWebhook('abc', 'abc'));
check('webhook invalid', !WAservix::verifyWebhook('abd', 'abc'));
check('webhook missing', !WAservix::verifyWebhook(null, 'abc'));
try { $c->sendMessage('', ['text' => 'x']); check('empty to throws', false); } catch (InvalidArgumentException $e) { check('empty to throws', true); }

echo $fails ? "$fails failed\n" : "all passed\n";
exit($fails ? 1 : 0);
