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

> Full parameter list: https://payid19.com/dev/invoices/create_invoice

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
    'expiration_date'  => 48, // hours
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

### Get Invoices

Retrieve existing invoices by order ID.

> Full parameter list: https://payid19.com/dev/invoices/get_invoices

```php
$result = $payid19->get_invoices([
    'order_id' => 42,
]);

$response = json_decode($result);
print_r($response);
```

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
        // Handle payment notification from Payid19
        $data = $request->all();

        // Find order by $data['order_id'] and update payment status
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
