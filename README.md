# Payid19 PHP API Library

Accept USDT and cryptocurrency payments on your website using [Payid19](https://payid19.com).

## Requirements

- PHP >= 7.4
- ext-curl
- ext-json

## Installation

Install via [Composer](http://getcomposer.org/):

```bash
composer require payid19/payid19-api-php
```

## Getting Started

1. Create an account at [payid19.com](https://payid19.com)
2. Go to **Settings** and get your **Public Key** and **Private Key**
3. Use the keys to create an instance of the client

```php
require_once 'vendor/autoload.php';

$payid19 = new \Payid19\ClientAPI('YOUR_PUBLIC_KEY', 'YOUR_PRIVATE_KEY');
```

## Usage

### Create an Invoice

Creates a new payment invoice and returns a payment page URL.

> Full parameter list: [payid19.com/dev/invoices/create_invoice](https://payid19.com/dev/invoices/create_invoice)

```php
$result = $payid19->create_invoice([
    'email'            => 'customer@example.com',
    'price_amount'     => 100,
    'price_currency'   => 'USD',
    'order_id'         => 42,
    'customer_id'      => 7,
    'merchant_id'      => 5,
    'title'            => 'Order #42',
    'description'      => 'Payment for Order #42',
    'add_fee_to_price' => 1,
    'success_url'      => 'https://yoursite.com/payment/success',
    'cancel_url'       => 'https://yoursite.com/payment/cancel',
    'callback_url'     => 'https://yoursite.com/payment/callback',
    'template'         => 'slate', // payment page design, see below
    // 'test'          => 1,  // uncomment to use test mode
]);

$response = json_decode($result);

if ($response->status === 'error') {
    echo 'Error: ' . $response->message[0];
} else {
    // Redirect customer to payment page
    header('Location: ' . $response->message);
}
```

#### Parameters

Only `price_amount` is required; everything else is optional.

| Parameter | Type | Notes |
|---|---|---|
| `price_amount` | decimal | **Required.** Price in `price_currency`. Minimum `0.0001`. |
| `price_currency` | string | Defaults to `USD`. EUR, GBP, TRY and 150+ local currencies are accepted — conversion to crypto happens when the customer opens the page. |
| `add_fee_to_price` | int | `1` passes the platform commission on to the customer, so you receive the full `price_amount`. Enabled automatically under ~0.20 USD. |
| `margin_ratio` | decimal | Underpayment tolerance in USDT. With `1` on a 5 USDT invoice, 4 USDT still completes the payment. Minimum `0.01`. Useful because wallets often deduct the network fee from the amount the customer types. |
| `order_id` | string | Your order reference, echoed back in the callback and searchable via `get_invoices()`. Max 100 chars. |
| `merchant_id` | string | Your merchant reference, echoed back in the callback. Max 150 chars. |
| `customer_id` | int | Your customer reference, echoed back in the callback. Max 11 digits. |
| `email` | string | Buyer's email. If omitted, the customer enters it on the payment page. |
| `title` | string | Shown at the top of the payment page. Max 150 chars. |
| `description` | string | Shown under the title. Up to 300 chars accepted, only the first 180 are stored and displayed. |
| `banned_coins` | JSON | Coins to hide, as a JSON array. `["BTC","ETH"]` hides them everywhere; `["USDT-ERC20"]` hides only that network. |
| `callback_url` | URL | Where the payment result is POSTed. Must be a public domain — no IPs, no localhost. Max 300 chars. |
| `success_url` | URL | Redirect after a successful payment. Cosmetic only — see the callback section. Max 300 chars. |
| `cancel_url` | URL | Redirect if the customer cancels. Max 300 chars. |
| `template` | string | Payment page design — see below. |
| `test` | int | `1` creates a test invoice that completes itself within seconds, callback included, with no real payment. |
| `white_label` | int | `1` returns a JSON coin list instead of a page URL, so you can build the checkout under your own brand. |
| `referral` | numeric | Your 10-digit referral ID. Earns you half of the Payid19 commission on every payment that invoice receives. |
| `expiration_date` | int | Accepted for backwards compatibility only. Invoices are currently valid for **24 hours** regardless of the value sent. |

### Payment Page Templates

The hosted payment page comes in four designs. Pass the one you want as
`template`; the returned URL points at that design, e.g.
`https://payid19.com/invoice/{alias}/paper`.

- `classic` — the default page, used when `template` is omitted
- `slate`
- `paper`
- `mint`

Every design supports the same coins, networks and underpayment handling —
only the look differs.

```php
$result = $payid19->create_invoice([
    'price_amount' => 100,
    'order_id'     => 42,
    'template'     => \Payid19\ClientAPI::TEMPLATE_MINT,
]);
```

Class constants are available so your editor can autocomplete them and typos
fail at compile time rather than at the API:

```php
\Payid19\ClientAPI::TEMPLATE_CLASSIC  // 'classic'
\Payid19\ClientAPI::TEMPLATE_SLATE    // 'slate'
\Payid19\ClientAPI::TEMPLATE_PAPER    // 'paper'
\Payid19\ClientAPI::TEMPLATE_MINT     // 'mint'

\Payid19\ClientAPI::TEMPLATES         // all of the above, as an array
```

### Get Invoices

Retrieve existing invoices by order ID.

> Full parameter list: [payid19.com/dev/invoices/get_invoices](https://payid19.com/dev/invoices/get_invoices)

```php
$result = $payid19->get_invoices([
    'order_id' => 42,
]);

$response = json_decode($result);
print_r($response);
```

### Coins and Estimates

```php
// Coins and networks available for payment
$coins = json_decode($payid19->get_coins());

// Convert between a fiat amount and a coin at the current rate
$estimate = json_decode($payid19->get_estimate([
    // see the docs below for the parameter list
]));
```

> Parameters: [get_coins](https://payid19.com/dev/tools/get_coins) &middot; [get_estimate](https://payid19.com/dev/tools/get_estimate)

### Withdrawals

```php
// Your account balance
$balance = json_decode($payid19->get_balance());

// Request a withdrawal
$withdraw = json_decode($payid19->create_withdraw([
    // see the docs below for the parameter list
]));
```

> Parameters: [get_balance](https://payid19.com/dev/withdraws/get_balance) &middot; [create_withdraw](https://payid19.com/dev/withdraws/create_withdraw)

## Payment Callback

When an invoice is **paid**, Payid19 POSTs a JSON body to your `callback_url`.
Callbacks are sent for completed payments only — receiving one means the
invoice is paid; pending and expired invoices produce nothing.

The payload carries `privatekey`, Payid19's invoice `id`, your `order_id` /
`merchant_id` / `customer_id` unchanged, the requested `price_amount` and
`price_currency`, the `amount` and `amount_currency` actually paid, and a full
snapshot of the invoice (`user_id`, `email`, `title`, `description`, `ip`,
`test`, `created_at` and the rest), so a follow-up API call is rarely needed.

```php
$data = json_decode(file_get_contents('php://input'));

if ($data->privatekey !== 'YOUR_PRIVATE_KEY') {
    http_response_code(403);
    exit;
}

// Payment confirmed — mark order $data->order_id as paid
http_response_code(200);
```

Four things worth getting right:

- **Verify `privatekey`** against your own copy before trusting a callback.
  Do *not* filter by sender IP — callbacks arrive from several addresses.
- **Respond with 2xx.** A non-2xx response is retried, up to 3 delivery
  attempts in total.
- **Be idempotent.** Because of those retries the same callback can arrive
  more than once; marking an already-paid order as paid again must be harmless.
- **Never treat `success_url` as proof of payment** — a customer can open that
  URL by hand. The callback is the single source of truth.

## Laravel Integration

**1. Add your keys to `.env`:**

```env
PAYID19_PUBLIC_KEY=your_public_key
PAYID19_PRIVATE_KEY=your_private_key
```

**2. Create a controller:**

```bash
php artisan make:controller PaymentController
```

```php
<?php

namespace App\Http\Controllers;

use Payid19\ClientAPI;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    private ClientAPI $payid19;

    public function __construct()
    {
        $this->payid19 = new ClientAPI(
            env('PAYID19_PUBLIC_KEY'),
            env('PAYID19_PRIVATE_KEY')
        );
    }

    public function createInvoice()
    {
        $result = $this->payid19->create_invoice([
            'email'          => auth()->user()->email,
            'price_amount'   => 100,
            'price_currency' => 'USD',
            'order_id'       => 123,
            'title'          => 'Order #123',
            'template'       => ClientAPI::TEMPLATE_SLATE,
            'success_url'    => route('payment.success'),
            'cancel_url'     => route('payment.cancel'),
            'callback_url'   => route('payment.callback'),
        ]);

        $response = json_decode($result);

        if ($response->status === 'error') {
            return back()->withErrors($response->message[0]);
        }

        return redirect($response->message);
    }

    public function callback(Request $request)
    {
        // Verify the callback really came from Payid19
        if ($request->input('privatekey') !== env('PAYID19_PRIVATE_KEY')) {
            return response('Forbidden', 403);
        }

        // Find order by $request->input('order_id') and mark it as paid.
        // Keep this idempotent — a callback can be delivered more than once.
        // ...

        return response('OK', 200);
    }
}
```

**3. Add routes to `routes/web.php`:**

```php
use App\Http\Controllers\PaymentController;

Route::get('/payment/create', [PaymentController::class, 'createInvoice']);
Route::get('/payment/success', fn() => 'Payment successful!')->name('payment.success');
Route::get('/payment/cancel', fn() => 'Payment cancelled.')->name('payment.cancel');
Route::post('/payment/callback', [PaymentController::class, 'callback'])->name('payment.callback');
```

**4. Exclude callback from CSRF protection (`bootstrap/app.php`):**

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        'payment/callback',
    ]);
})
```

## License

MIT
