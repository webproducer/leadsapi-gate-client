# leadsapi-gate-client
PHP client for leadsapi.org

## Installation

```
composer require leadsapiorg/gate-client
```

## Examples

### Send SMS

```php
use Leadsapi\Gate\Client;
use Leadsapi\Gate\Exception as GateException;

$client = new Client('my_username', 'my_token'); // Request credentials from your provider
$client->setGate('test'); // Request list of available gates from your provider
try {
    // Sending one SMS:
    $res = $client->sendSms('13212022278', 'Hello!');
    printf("SMS sent: sending id is: %d\n\n", $res->id);

    // Sending bulk of messages:
    $res = $client->sendSmsBulk([
        ['13212022278', "First hello!"],
        ['12064572648', "Second\nhello!"],
        ['13212022368', "Third hello!"],
        ['xxxxxxxxxxx', ":("]
    ]);
    printf("Bulk sent: %d messages accepted; bulk id is: %d\n", $res->enqueued, $res->id);
    if (!empty($res->errors)) {
        print("But there's some errors:\n");
        foreach ($res->errors as $msg) {
            print("- {$msg}\n");
        }
    }
} catch (GateException $e) {
    printf("Got error: %s\n", $e->getMessage());
}
```
