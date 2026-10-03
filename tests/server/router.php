<?php

/**
 * Request router for the test HTTP server.
 *
 * The tests drive it with a `scenario` field, so one server covers both the
 * happy path and every failure the client is expected to recognise. The
 * `echo` scenario returns the request itself, which is how the tests assert
 * what the client actually sent.
 */

declare(strict_types=1);

$raw = file_get_contents('php://input');
parse_str($raw ?: '', $fields);

$scenario = $fields['scenario'] ?? 'echo';

switch ($scenario) {
    case 'url':
        header('Content-Type: application/json');
        echo json_encode([
            'status'  => 'success',
            'message' => 'https://payid19.com/invoice/Xy3kP9',
        ]);
        break;

    case 'api_error':
        http_response_code(421);
        header('Content-Type: application/json');
        echo json_encode([
            'status'  => 'error',
            'message' => ['Wrong public or private key.'],
        ]);
        break;

    case 'http_error':
        http_response_code(502);
        header('Content-Type: text/html');
        echo '<html>Bad Gateway</html>';
        break;

    case 'empty':
        header('Content-Type: application/json');
        break;

    case 'invalid_json':
        header('Content-Type: application/json');
        echo 'not json at all';
        break;

    case 'echo':
    default:
        header('Content-Type: application/json');
        echo json_encode([
            'status'  => 'success',
            'message' => [
                'path'   => parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH),
                'method' => $_SERVER['REQUEST_METHOD'] ?? '',
                'fields' => $fields,
            ],
        ]);
        break;
}
