<?php

declare(strict_types=1);

namespace Payid19\Tests;

use InvalidArgumentException;
use Payid19\ClientAPI;
use PHPUnit\Framework\TestCase;

final class ClientAPITest extends TestCase
{
    private function client(): ClientAPI
    {
        $client = new ClientAPI('test-public-key', 'test-private-key');
        $client->apiEndPoint = TEST_SERVER_URL;

        return $client;
    }

    /** @return array<string,mixed> */
    private function decode(string $json): array
    {
        $decoded = json_decode($json, true);
        self::assertIsArray($decoded, "Response was not a JSON object: {$json}");

        return $decoded;
    }

    public function testConstructorRejectsAnEmptyPublicKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ClientAPI('', 'private');
    }

    public function testConstructorRejectsAnEmptyPrivateKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ClientAPI('public', '');
    }

    public function testCreateInvoiceReturnsThePaymentPageUrl(): void
    {
        $response = $this->decode($this->client()->create_invoice([
            'scenario'     => 'url',
            'price_amount' => 100,
        ]));

        self::assertSame('success', $response['status']);
        self::assertSame('https://payid19.com/invoice/Xy3kP9', $response['message']);
    }

    public function testCredentialsAreAddedToEveryRequest(): void
    {
        $response = $this->decode($this->client()->create_invoice(['price_amount' => 100]));
        $fields   = $response['message']['fields'];

        self::assertSame('test-public-key', $fields['public_key']);
        self::assertSame('test-private-key', $fields['private_key']);
    }

    public function testParametersArePassedThroughUnchanged(): void
    {
        $response = $this->decode($this->client()->create_invoice([
            'price_amount' => 100,
            'order_id'     => 42,
            'template'     => ClientAPI::TEMPLATE_MINT,
            'banned_coins' => '["BTC","ETH"]',
        ]));
        $fields = $response['message']['fields'];

        self::assertSame('100', $fields['price_amount']);
        self::assertSame('42', $fields['order_id']);
        self::assertSame('mint', $fields['template']);
        self::assertSame('["BTC","ETH"]', $fields['banned_coins']);
    }

    public function testRequestsArePostedToTheCommandPath(): void
    {
        $response = $this->decode($this->client()->create_invoice(['price_amount' => 1]));

        self::assertSame('POST', $response['message']['method']);
        self::assertSame('/create_invoice', $response['message']['path']);
    }

    public function testEveryEndpointPostsToItsOwnPath(): void
    {
        $client = $this->client();

        $calls = [
            '/create_invoice'  => static fn (ClientAPI $c): string => $c->create_invoice(['price_amount' => 1]),
            '/get_invoices'    => static fn (ClientAPI $c): string => $c->get_invoices(['order_id' => 1]),
            '/get_coins'       => static fn (ClientAPI $c): string => $c->get_coins(),
            '/get_estimate'    => static fn (ClientAPI $c): string => $c->get_estimate([]),
            '/get_balance'     => static fn (ClientAPI $c): string => $c->get_balance(),
            '/create_withdraw' => static fn (ClientAPI $c): string => $c->create_withdraw([]),
        ];

        foreach ($calls as $expectedPath => $call) {
            $response = $this->decode($call($client));
            self::assertSame($expectedPath, $response['message']['path']);
        }
    }

    public function testAnApiErrorIsReturnedAsTheApiSentIt(): void
    {
        $response = $this->decode($this->client()->create_invoice([
            'scenario'     => 'api_error',
            'price_amount' => 100,
        ]));

        self::assertSame('error', $response['status']);
        // The reason the API gave, not the status code it gave it with: an
        // API error arrives as HTTP 421, and reporting the code instead would
        // discard every message the API sends.
        self::assertSame(['Wrong public or private key.'], $response['message']);
    }

    public function testANonSuccessStatusCodeIsReported(): void
    {
        $response = $this->decode($this->client()->create_invoice([
            'scenario'     => 'http_error',
            'price_amount' => 100,
        ]));

        self::assertSame('error', $response['status']);
        self::assertStringContainsString('502', $response['message'][0]);
    }

    public function testAnEmptyBodyIsReported(): void
    {
        $response = $this->decode($this->client()->get_coins(['scenario' => 'empty']));

        self::assertSame('error', $response['status']);
        self::assertStringContainsString('empty', strtolower($response['message'][0]));
    }

    public function testABodyThatIsNotJsonIsReported(): void
    {
        $response = $this->decode($this->client()->get_coins(['scenario' => 'invalid_json']));

        self::assertSame('error', $response['status']);
        self::assertStringContainsString('invalid json', strtolower($response['message'][0]));
    }

    public function testAnUnreachableHostIsReportedAsACurlError(): void
    {
        $client = new ClientAPI('public', 'private');
        // Port 1 on loopback refuses connections, which is the closest thing
        // to a dead endpoint that does not need the network.
        $client->apiEndPoint = 'http://127.0.0.1:1';

        $response = $this->decode($client->create_invoice(['price_amount' => 1]));

        self::assertSame('error', $response['status']);
        self::assertStringContainsString('curl error', strtolower($response['message'][0]));
    }

    public function testATrailingSlashOnTheEndpointDoesNotDoubleUp(): void
    {
        $client = new ClientAPI('public', 'private');
        $client->apiEndPoint = TEST_SERVER_URL . '/';

        $response = $this->decode($client->create_invoice(['price_amount' => 1]));

        self::assertSame('/create_invoice', $response['message']['path']);
    }

    public function testTemplateConstantsCoverTheDocumentedDesigns(): void
    {
        self::assertSame(
            ['classic', 'slate', 'paper', 'mint'],
            ClientAPI::TEMPLATES
        );
        self::assertSame('classic', ClientAPI::TEMPLATE_CLASSIC);
        self::assertSame('slate', ClientAPI::TEMPLATE_SLATE);
        self::assertSame('paper', ClientAPI::TEMPLATE_PAPER);
        self::assertSame('mint', ClientAPI::TEMPLATE_MINT);
    }
}
