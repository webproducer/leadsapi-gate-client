<?php
namespace Leadsapi\Gate;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException as HttpRequestException;
use GuzzleHttp\Psr7\Request;


class Client
{
    private const PHONE_CLEAN_RE = '/[^\d+]/';
    private const PHONE_VALIDATE_RE = '/^\+\d{4,25}$/';

    const DEF_ENDPOINT = 'https://gate.leadsapi.org';
    const FIELD_TARGET = 'target';
    const FIELD_BODY = 'body';
    const FIELD_SENDER = 'sender';

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
     * @param string $body
     * @param string $sender
     * @return Result
     * @throws Exception
     */
    public function sendSms(string $phone, string $body, string $sender = null): Result
    {
        $result = $this->send(
            new Request(
                'POST',
                $this->buildSendUrl('sms'),
                ['Content-Type' => 'application/json'],
                json_encode([
                    self::FIELD_TARGET => $this->normalizePhoneNumber($phone),
                    self::FIELD_BODY => $body,
                    self::FIELD_SENDER => $sender ?? $this->sender
                ])
            )
        );
        if (!isset($result['sending_id'])) {
            throw new Exception("No sending id in result");
        }
        return new Result($result['sending_id']);
    }

    /**
     * @param iterable $messages
     * @param string|null $sender
     * @return BulkResult
     * @throws Exception
     */
    public function sendSmsBulk(iterable $messages, string $sender = null): BulkResult
    {
        $requestBody = fopen('php://temp', 'r+');
        fwrite($requestBody, $this->makeRow([self::FIELD_TARGET, self::FIELD_BODY, self::FIELD_SENDER]));
        $rowsProcessed = 0;
        try {
            foreach ($messages as [$phone, $messageBody]) {
                fwrite($requestBody, $this->makeRow([
                    $this->normalizePhoneNumber($phone),
                    $messageBody,
                    $sender ?? $this->sender
                ]));
                $rowsProcessed++;
            }
            if ($rowsProcessed === 0) {
                throw new Exception("Empty target list");
            }
            rewind($requestBody);
            $resp = $this->send(
                new Request(
                    'POST',
                    $this->buildSendUrl('sms'),
                    ['Content-Type' => 'text/tab-separated-values'],
                    $requestBody
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
            @fclose($requestBody);
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
        return sprintf('/send/%s%s', $type, $this->gate ? "/{$this->gate}" : '');
    }

    private function makeRow(...$chunks): string
    {
        return sprintf("%s\n", implode("\t", array_map(function ($chunk) {
            return addcslashes($chunk, "\t\n\r");
        }, ...$chunks)));
    }

    /**
     * @param string $phone
     * @return string
     * @throws Exception
     */
    private function normalizePhoneNumber(string $phone): string {
        $phone = preg_replace(self::PHONE_CLEAN_RE, '', $phone);
        if (!preg_match(self::PHONE_VALIDATE_RE, $phone)) {
            throw new Exception("Error validating (phone number should be passed in international format): {$phone}");
        }
        return $phone;
    }
}
