<?php
namespace Leadsapi\Gate;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\RequestException as HttpRequestException;


class Client {

	const DEF_ENDPOINT = 'https://gate.leadsapi.org';

	private $user;
	private $token;
	private $endpoint;

	private $cli;

	public function __construct(string $user, string $token, string $endpoint = self::DEF_ENDPOINT)
	{
		$this->user = $user;
		$this->token = $token;
		$this->endpoint = rtrim($endpoint, '/');
	}

	/**
	 * @param string $phone
	 * @param string $text
	 * @return Result
	 * @throws Exception
	 */
	public function sendSms(string $phone, string $text): Result
	{
	    $result = $this->send(
	        new Request(
	            'POST',
	            '/send/sms',
	            ['Content-Type' => 'application/json'],
	            json_encode(['target' => $phone, 'text' => $text])
	        )
	    );
	    if (!isset($result['id'])) {
	    	throw new Exception("No id in result");
	    }
	    return new Result($result['id']);
	}

	/**
	 * @param iterable $messages
	 * @return BulkResult
	 * @throws Exception
	 */
	public function sendSmsBulk(iterable $messages): BulkResult {
	    $body = fopen('php://temp', 'r+');
	    foreach ($messages as [$phone, $text]) {
	        fprintf($body, "%s\t%s\n", $phone, addcslashes($text, "\t\n\r"));
	    }
	    rewind($body);
	    try {
	        $resp = $this->send(
	            new Request('POST', '/send/sms', ['Content-Type' => 'text/tab-separated-values'], $body)
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

	protected function getHttpClient(): HttpClient
	{
		if (!$this->cli) {
			$this->cli = new HttpClient([
		        'base_uri' => $this->endpoint,
		        'auth' => [$this->user, $this->token]
	    	]);
		}
	    return $this->cli;
	}

	protected function send(Request $request): array {
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
	    }
	}

}