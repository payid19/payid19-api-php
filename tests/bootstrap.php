<?php

/**
 * Starts a local HTTP server for the test suite.
 *
 * ClientAPI talks to the network with cURL directly, so a stubbed transport
 * would mean changing the class to suit its tests. A real server on loopback
 * keeps the production code path intact — the tests exercise the same cURL
 * call, response handling and error branches that a live request would.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$probe = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
if ($probe === false) {
    fwrite(STDERR, "Could not reserve a port for the test server: {$errstr}\n");
    exit(1);
}
$name = stream_socket_get_name($probe, false);
$port = (int) substr((string) $name, (int) strrpos((string) $name, ':') + 1);
fclose($probe);

$php    = escapeshellarg(PHP_BINARY);
$router = escapeshellarg(__DIR__ . '/server/router.php');
$pid    = (int) shell_exec(sprintf('%s -S 127.0.0.1:%d %s >/dev/null 2>&1 & echo $!', $php, $port, $router));

// The server needs a moment before it accepts connections; without this the
// first test races it and fails for reasons that have nothing to do with the
// client.
$ready = false;
for ($attempt = 0; $attempt < 100; $attempt++) {
    $client = @stream_socket_client("tcp://127.0.0.1:{$port}", $e, $es, 0.1);
    if ($client !== false) {
        fclose($client);
        $ready = true;
        break;
    }
    usleep(50000);
}

if (!$ready) {
    fwrite(STDERR, "Test server on port {$port} never came up.\n");
    if ($pid > 0) {
        exec("kill {$pid} 2>/dev/null");
    }
    exit(1);
}

define('TEST_SERVER_URL', "http://127.0.0.1:{$port}");

register_shutdown_function(static function () use ($pid): void {
    if ($pid > 0) {
        exec("kill {$pid} 2>/dev/null");
    }
});
