<?php

namespace Leadsapi\Gate\Tests;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use GuzzleHttp\Exception\TransferException as GuzzleTransferException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Leadsapi\Gate\Client;
use Leadsapi\Gate\Exception;
use Leadsapi\Gate\Exception as GateException;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    /** @var null|Request */
    private $request = null;
    /** @var string */
    private $requestBodyContent = '';

    /**
     * @dataProvider setGateDataProvider
     * @param string $gate
     * @param string $expectedUri
     * @throws GateException
     * @throws \ReflectionException
     */
    public function testSetGate(string $gate, string $expectedUri)
    {
        $client = $this->makeClient($this->getHttpClientSendCallback(new Response(200, [], '{"sending_id":1}')));
        $client->setGate($gate);
        $client->sendSms('1', '2');
        $this->assertEquals($expectedUri, "{$this->request->getUri()}");
    }

    public function setGateDataProvider(): array
    {
        return [
            ['', '/send/sms'],
            ['gate_name', '/send/sms/gate_name'],
        ];
    }

    /**
     * @dataProvider sendSmsDataProvider
     * @param array $message
     * @param string $sender
     * @param string $expectedRequestBody
     * @throws GateException
     * @throws \ReflectionException
     */
    public function testSendSms(array $message, string $sender, string $expectedRequestBody)
    {
        $client = $this->makeClient($this->getHttpClientSendCallback(new Response(200, [], '{"sending_id":1}')));
        $client->setSender($sender);
        $client->sendSms($message['phone'], $message['body'], $message['sender'] ?? null);
        $this->assertEquals($expectedRequestBody, $this->requestBodyContent);
    }

    public function sendSmsDataProvider(): array
    {
        return [
            [
                ['phone' => '12345678900', 'body' => 'Message body'],
                '',
                '{"target":"12345678900","body":"Message body","sender":""}',
            ],
            [
                ['phone' => '12345678900', 'body' => 'Message body'],
                'main_sender',
                '{"target":"12345678900","body":"Message body","sender":"main_sender"}',
            ],
            [
                ['phone' => '12345678900', 'body' => 'Message body', 'sender' => 'Message sender'],
                'main_sender',
                '{"target":"12345678900","body":"Message body","sender":"Message sender"}',
            ],
        ];
    }

    /**
     * @dataProvider sendSmsWrongResponseDataProvider
     * @param callable $httpClientSendCallback
     * @param string $exceptionClass
     * @param string $exceptionMessage
     * @throws GateException
     * @throws \ReflectionException
     */
    public function testSendSmsWrongResponse(
        callable $httpClientSendCallback,
        string $exceptionClass,
        string $exceptionMessage
    ) {
        $client = $this->makeClient($httpClientSendCallback);
        $this->expectException($exceptionClass);
        $this->expectExceptionMessage($exceptionMessage);
        $client->sendSms('', '');
    }

    public function sendSmsWrongResponseDataProvider(): array
    {
        return [
            [
                $this->getHttpClientSendCallback(new Response(400, [], '')),
                Exception::class,
                'Error parsing response: Syntax error',
            ],
            [
                $this->getHttpClientSendCallback(new Response(400, [], '{}')),
                Exception::class,
                'No sending id in result',
            ],
            [
                function () {
                    throw new GuzzleRequestException('Stub message', new Request('GET', '/'));
                },
                Exception::class,
                'Error sending: Stub message',
            ],
            [
                function () {
                    throw new GuzzleTransferException('Stub message');
                },
                Exception::class,
                'Error sending: Stub message',
            ],
        ];
    }

    /**
     * @dataProvider sendSmsBulkDataProvider
     * @param iterable $messages
     * @param string $mainSender
     * @param string $bulkSender
     * @param string $expectedRequestBody
     * @throws GateException
     * @throws \ReflectionException
     */
    public function testSendSmsBulk(
        iterable $messages,
        string $mainSender,
        string $expectedRequestBody,
        string $bulkSender = null
    ) {
        $client = $this->makeClient($this->getHttpClientSendCallback(new Response(200, [], '{"bulk_id":1}')));
        $client->setSender($mainSender);
        $client->sendSmsBulk($messages, $bulkSender);
        $this->assertEquals($expectedRequestBody, $this->requestBodyContent);
    }

    public function sendSmsBulkDataProvider(): array
    {
        return [
            [
                [], // Messages
                '', // Main sender
                '', // Expected request body
                null, // Bulk sender
            ],
            [
                [
                    ['12345678901', 'Message body 1'],
                    ['12345678902', 'Message body 2'],
                    ['12345678903', 'Message body 3'],
                ],
                '', // Main sender
                <<<EOD
target	body	sender
12345678901	Message body 1	
12345678902	Message body 2	
12345678903	Message body 3	

EOD
                ,
                null, // Bulk sender
            ],
            [
                [
                    ['12345678901', 'Message body 1'],
                    ['12345678902', 'Message body 2'],
                    ['12345678903', 'Message body 3'],
                ],
                'Main sender',
                <<<EOD
target	body	sender
12345678901	Message body 1	Main sender
12345678902	Message body 2	Main sender
12345678903	Message body 3	Main sender

EOD
                ,
                null, // Bulk sender

            ],
            [
                new \IteratorIterator(call_user_func(function (): \Generator {
                    yield ['12345678901', 'Message body 1'];
                    yield ['12345678902', 'Message body 2'];
                    yield ['12345678903', 'Message body 3'];
                })),
                'Main sender',
                <<<EOD
target	body	sender
12345678901	Message body 1	Main sender
12345678902	Message body 2	Main sender
12345678903	Message body 3	Main sender

EOD
                ,
                null, // Bulk sender
            ],
            [
                [
                    ['12345678901', 'Message body 1'],
                    ['12345678902', 'Message body 2'],
                    ['12345678903', 'Message body 3'],
                ],
                'Main sender',
                <<<EOD
target	body	sender
12345678901	Message body 1	
12345678902	Message body 2	
12345678903	Message body 3	

EOD
                ,
                '', // Bulk sender

            ],
            [
                new \IteratorIterator(call_user_func(function (): \Generator {
                    yield ['12345678901', 'Message body 1'];
                    yield ['12345678902', 'Message body 2'];
                    yield ['12345678903', 'Message body 3'];
                })),
                'Main sender',
                <<<EOD
target	body	sender
12345678901	Message body 1	
12345678902	Message body 2	
12345678903	Message body 3	

EOD
                ,
                '', // Bulk sender
            ],
            [
                [
                    ['12345678901', 'Message body 1'],
                    ['12345678902', 'Message body 2'],
                    ['12345678903', 'Message body 3'],
                ],
                'Main sender',
                <<<EOD
target	body	sender
12345678901	Message body 1	Bulk sender
12345678902	Message body 2	Bulk sender
12345678903	Message body 3	Bulk sender

EOD
                ,
                'Bulk sender',
            ],
            [
                new \IteratorIterator(call_user_func(function (): \Generator {
                    yield ['12345678901', 'Message body 1'];
                    yield ['12345678902', 'Message body 2'];
                    yield ['12345678903', 'Message body 3'];
                })),
                'Main sender',
                <<<EOD
target	body	sender
12345678901	Message body 1	Bulk sender
12345678902	Message body 2	Bulk sender
12345678903	Message body 3	Bulk sender

EOD
                ,
                'Bulk sender',
            ],
        ];
    }

    /**
     * @dataProvider sendSmsBulkWrongResponseDataProvider
     * @param callable $httpClientSendCallback
     * @param string $exceptionClass
     * @param string $exceptionMessage
     * @throws GateException
     * @throws \ReflectionException
     */
    public function testSendSmsBulkWrongResponse(
        callable $httpClientSendCallback,
        string $exceptionClass,
        string $exceptionMessage
    ) {
        $client = $this->makeClient($httpClientSendCallback);
        $this->expectException($exceptionClass);
        $this->expectExceptionMessage($exceptionMessage);
        $client->sendSmsBulk([]);
    }

    public function sendSmsBulkWrongResponseDataProvider(): array
    {
        return [
            [
                $this->getHttpClientSendCallback(new Response(400, [], '')),
                Exception::class,
                'Error parsing response: Syntax error',
            ],
            [
                $this->getHttpClientSendCallback(new Response(400, [], '{}')),
                Exception::class,
                'No id in result',
            ],
            [
                function () {
                    throw new GuzzleRequestException('Stub message', new Request('GET', '/'));
                },
                Exception::class,
                'Error sending: Stub message',
            ],
            [
                function () {
                    throw new GuzzleTransferException('Stub message');
                },
                Exception::class,
                'Error sending: Stub message',
            ],
        ];
    }

    /**
     * @param callable $httpClientSendCallback
     * @return Client
     * @throws \ReflectionException
     */
    private function makeClient(callable $httpClientSendCallback): Client
    {
        $client = new Client('user', 'token');
        $httpClient = $this->getHttpClientMock($httpClientSendCallback);
        return $this->replaceHttpClient($client, $httpClient);
    }

    private function getHttpClientMock(callable $httpClientSendCallback)
    {
        $httpClientMock = $this->createMock(HttpClient::class);
        $httpClientMock->method('send')->willReturnCallback($httpClientSendCallback);
        return $httpClientMock;
    }

    private function getHttpClientSendCallback(Response $response = null, \Throwable $throwable = null)
    {
        if (is_null($response)) {
            $response = new Response();
        }
        return function (Request $request) use ($response, $throwable) {
            if ($throwable) {
                throw $throwable;
            }
            $this->request = $request;
            $this->requestBodyContent = $request->getBody()->getContents();
            return $response;
        };
    }

    /**
     * @param Client $client
     * @param HttpClient $httpClient
     * @return Client
     * @throws \ReflectionException
     */
    private function replaceHttpClient(Client $client, HttpClient $httpClient)
    {
        $reflection = new \ReflectionClass($client);
        $httpClientProperty = $reflection->getProperty('cli');
        $httpClientProperty->setAccessible(true);
        $httpClientProperty->setValue($client, $httpClient);
        return $client;
    }
}
