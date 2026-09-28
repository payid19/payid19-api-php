<?php

namespace Payid19;

class ClientAPI
{
    /**
     * Designs available for the hosted payment page.
     *
     * Pass one as the 'template' parameter of create_invoice(); the returned
     * payment URL then points at that design, e.g.
     * https://payid19.com/invoice/{alias}/paper
     */
    public const TEMPLATE_CLASSIC = 'classic';
    public const TEMPLATE_SLATE   = 'slate';
    public const TEMPLATE_PAPER   = 'paper';
    public const TEMPLATE_MINT    = 'mint';

    /** @var string[] All templates known to this library version. */
    public const TEMPLATES = [
        self::TEMPLATE_CLASSIC,
        self::TEMPLATE_SLATE,
        self::TEMPLATE_PAPER,
        self::TEMPLATE_MINT,
    ];

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
     * Optionally accepts a 'template' key to pick the payment page design —
     * see the TEMPLATE_* constants. Omitting it keeps the classic page.
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
     * Lists the coins and networks that can be used for payment.
     *
     * @see    https://payid19.com/dev/tools/get_coins
     * @param  array<string,mixed> $req
     * @return string JSON encoded response
     */
    public function get_coins(array $req = []): string
    {
        return $this->apiCall('get_coins', $req);
    }

    /**
     * Converts an amount between a fiat currency and a coin at the current rate.
     *
     * @see    https://payid19.com/dev/tools/get_estimate
     * @param  array<string,mixed> $req
     * @return string JSON encoded response
     */
    public function get_estimate(array $req): string
    {
        return $this->apiCall('get_estimate', $req);
    }

    /**
     * Returns the balance of your account.
     *
     * @see    https://payid19.com/dev/withdraws/get_balance
     * @param  array<string,mixed> $req
     * @return string JSON encoded response
     */
    public function get_balance(array $req = []): string
    {
        return $this->apiCall('get_balance', $req);
    }

    /**
     * Creates a withdrawal request.
     *
     * @see    https://payid19.com/dev/withdraws/create_withdraw
     * @param  array<string,mixed> $req
     * @return string JSON encoded response
     */
    public function create_withdraw(array $req): string
    {
        return $this->apiCall('create_withdraw', $req);
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
