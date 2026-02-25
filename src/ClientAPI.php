<?php

namespace Payid19;

class ClientAPI
{
    protected string $public_key  = '';
    protected string $private_key = '';
    public string $apiEndPoint    = 'https://payid19.com/api/v1';

    /** Connection and read timeout in seconds */
    private int $connectTimeout = 10;
    private int $timeout        = 30;

    public function __construct(string $public_key, string $private_key)
    {
        if (empty($public_key) || empty($private_key)) {
            throw new \InvalidArgumentException('Public key and Private key cannot be empty.');
        }

        $this->public_key  = $public_key;
        $this->private_key = $private_key;
    }

    protected function getApiUrl(string $commandUrl): string
    {
        return rtrim($this->apiEndPoint, '/') . '/' . ltrim($commandUrl, '/');
    }

    /**
     * Creates a new invoice.
     *
     * @param  array<string,mixed> $req
     * @return string JSON encoded response
     */
    public function create_invoice(array $req): string
    {
        return $this->apiCall('create_invoice', $req);
    }

    /**
     * Retrieves invoices.
     *
     * @param  array<string,mixed> $req
     * @return string JSON encoded response
     */
    public function get_invoices(array $req): string
    {
        return $this->apiCall('get_invoices', $req);
    }

    /**
     * Sends an API request and returns a JSON encoded response string.
     *
     * @param  array<string,mixed> $req
     */
    private function apiCall(string $cmd, array $req): string
    {
        $req['public_key']  = $this->public_key;
        $req['private_key'] = $this->private_key;

        $apiUrl = $this->getApiUrl($cmd);

        if (!function_exists('curl_init')) {
            return $this->errorResponse('cURL extension is not available in this PHP installation.');
        }

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $apiUrl,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($req),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);

        $result    = curl_exec($ch);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Network or SSL level cURL error
        if ($curlErrno !== 0) {
            return $this->errorResponse(
                sprintf('cURL error [%d]: %s', $curlErrno, $curlError)
            );
        }

        // Empty response body
        if (empty($result)) {
            return $this->errorResponse('API returned an empty response.');
        }

        // Non-2xx HTTP status code
        if ($httpCode < 200 || $httpCode >= 300) {
            return $this->errorResponse(
                sprintf('API returned an unexpected HTTP status code: %d', $httpCode)
            );
        }

        // Validate that the response is valid JSON before returning
        json_decode($result);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->errorResponse(
                'API returned invalid JSON: ' . json_last_error_msg()
            );
        }

        return $result;
    }

    /**
     * Builds a standard JSON encoded error response string.
     */
    private function errorResponse(string $message): string
    {
        return (string) json_encode([
            'status'  => 'error',
            'message' => [$message],
        ]);
    }
}
