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

$client = new Client('my_username', 'my_token');
try {
    // Sending one SMS:
    $res = $client->sendSms('13212022278', 'Hello!');
    printf("SMS sent: sending id is: %d", $res->id);

    // Sending bulk of messages:
    $client->sendSmsBulk([
        ['13212022278', "First hello!"],
        ['12064572648', "Second\nhello!"],
        ['13212022368', "Third hello!"],
        ['xxxxxxxxxxx', ":("]
    ]);
    printf("Bulk sent: %d messages accepted; bulk id is: %d", $res->enqueued, $res->id);
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