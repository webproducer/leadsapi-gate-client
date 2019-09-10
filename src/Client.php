<?php
namespace Leadsapi\Gate;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\RequestException as HttpRequestException;
use GuzzleHttp\Exception\GuzzleException;

class Client
{
    const DEF_ENDPOINT = 'https://gate.leadsapi.org';
    const HEADER_TARGET = 'target';
    const HEADER_TEXT = 'text';
    const HEADER_SENDER = 'sender';

    private $user;
    private $token;
    private $endpoint;
    private $sender = '';
    private $gate = '';
    private $cli;

    public function __construct(string $user, string $token, string $endpoint = self::DEF_ENDPOINT)
    {
        $this->user = $user;
        $this->token = $token;
        $this->endpoint = rtrim($endpoint, '/');
    }

    /**
     * @param string $sender
     * @return Client
     */
    public function setSender(string $sender): self
    {
        $this->sender = $sender;
        return $this;
    }

    /**
     * @param string $gate
     * @return Client
     */
    public function setGate(string $gate): self
    {
        $this->gate = $gate;
        return $this;
    }

    /**
     * @param string $phone
     * @param string $text
     * @param string $sender
     * @return Result
     * @throws Exception
     */
    public function sendSms(string $phone, string $text, string $sender = null): Result
    {
        $result = $this->send(
            new Request(
                'POST',
                $this->buildSendUrl('sms'),
                ['Content-Type' => 'application/json'],
                json_encode(['target' => $phone, 'text' => $text, 'sender' => $sender ?? $this->sender])
            )
        );
        if (!isset($result['sending_id'])) {
            throw new Exception("No sending id in result");
        }
        return new Result($result['sending_id']);
    }

    /**
     * @param iterable $messages
     * @return BulkResult
     * @throws Exception
     */
    public function sendSmsBulk(iterable $messages): BulkResult
    {
        $body = fopen('php://temp', 'r+');
        foreach ($this->getRows($messages) as $row) {
            fwrite($body, $row);
        }
        rewind($body);
        try {
            $resp = $this->send(
                new Request(
                    'POST',
                    $this->buildSendUrl('sms'),
                    ['Content-Type' => 'text/tab-separated-values'],
                    $body
                )
            );
            if (!isset($resp['bulk_id'])) {
                throw new Exception("No id in result");
            }
            $result = new BulkResult($resp['bulk_id']);
            $result->enqueued = $resp['enqueued'] ?? 0;
            $result->errors = $resp['errors'] ?? [];
            return $result;
        } finally {
            @fclose($body);
        }
    }

    private function getHttpClient(): HttpClient
    {
        if (!$this->cli) {
            $this->cli = new HttpClient([
                'base_uri' => $this->endpoint,
                'auth' => [$this->user, $this->token]
            ]);
        }
        return $this->cli;
    }

    /**
     * @param Request $request
     * @return array
     * @throws Exception
     */
    private function send(Request $request): array
    {
        try {
            $response = $this->getHttpClient()->send($request);
            $data = json_decode($response->getBody()->getContents(), true);
            if ($data === null) {
                throw new Exception("Error parsing response: " . json_last_error_msg());
            }
            return $data;
        } catch (HttpRequestException $e) {
            throw new Exception(sprintf(
                "Error sending: %s\n",
                ($response = $e->getResponse()) ? $response->getBody()->getContents() : $e->getMessage()
            ));
        } catch (GuzzleException $e) {
            throw new Exception(sprintf("Error sending: %s\n", $e->getMessage()));
        }
    }

    private function buildSendUrl(string $type): string
    {
        return sprintf(
            '/send/%s%s%s',
            $type,
            $this->gate ? "/{$this->gate}" : '',
            $this->sender ? "?sender={$this->sender}" : ''
        );
    }

    private function getRows(iterable $messages): \Generator
    {
        if (is_array($messages)) {
            $messages = new \ArrayIterator($messages);
        } elseif ($messages instanceof \Traversable) {
            $messages = new \IteratorIterator($messages);
        }
        $messages->rewind();
        if (!$messages->valid()) {
            return;
        }
        $firstRow = $messages->current();
        $headers = [self::HEADER_TARGET, self::HEADER_TEXT];
        if (isset($firstRow[2])) { // Sender exists
            $headers[] = self::HEADER_SENDER;
        }
        yield $this->makeRow($headers);
        yield $this->makeRow($firstRow);
        $messages->next();
        while ($messages->valid()) {
            yield $this->makeRow($messages->current());
            $messages->next();
        }
    }

    private function makeRow(...$chunks): string
    {
        return sprintf("%s\n", implode("\t", array_map(function ($chunk) {
            return addcslashes($chunk, "\t\n\r");
        }, ...$chunks)));
    }
}
