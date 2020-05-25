# leadsapi-gate-client
PHP client for gate.leadsapi.org

## Installation

```
composer require leadsapiorg/gate-client
```

## Examples

### Send SMS

```php
use Leadsapi\Gate\Client;
use Leadsapi\Gate\Exception as GateException;

///////// Preparing client

$client = new Client('my_username', 'my_token'); // Request credentials from your provider

// If you need to change the channel:
$client->setGate('test');

// If you need to set sender:
$client->setSender('Main sender');

try {
    
    ///////// Single message mode

    $res = $client->sendSms('+13212022278', 'Hello!');
    printf("SMS sent: sending id is: %d\n\n", $res->id);
    
    // Setting sender on single message level:
    $res = $client->sendSms('+13212022278', 'Hello!', 'Special sender');

    ///////// Bulk mode

    $messages = [
        ['+13212022278', "First hello!"],
        ['+12064572648', "Second\nhello!"],
        ['+13212022368', "Third hello!"]
    ];

    $res = $client->sendSmsBulk($messages);
    printf("Bulk sent: %d messages accepted; bulk id is: %d\n", $res->enqueued, $res->id);
    if (!empty($res->errors)) {
        print("But there's some errors:\n");
        foreach ($res->errors as $msg) {
            print("- {$msg}\n");
        }
    }

    // Setting sender on bulk level:
    $res = $client->sendSmsBulk($messages, 'Special sender');

} catch (GateException $e) {
    printf("Got error: %s\n", $e->getMessage());
}
```
