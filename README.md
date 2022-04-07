## Payid19 Crypto Payment Gateway PHP API library

Accept USDT Payment with Payid19

To start accept cryptocurrencies on your site you need to create an account on <https://payid19.com> and get Public and Private keys on Settings page.

This secret key will be used for creating instance of the Payid19Client.

### Installation

**Composer**

You can install library via [Composer](http://getcomposer.org/). Run the following command in your terminal:

```bash
composer require payid19/payid19-api-php
```

Usage example:

New invoice:  (<https://payid19.com/dev/invoices/create_invoice>)

```
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$public_key  = 'xxxxx';
$private_key = 'xxxxx';

$payid19 = new \Payid19\ClientAPI($public_key,$private_key);

$request = [
                    'email' => 'email@email.com',
                    'price_amount' => 725,
                    'price_currency' => 'USD',
                    'merchant_id' => 5,
                    'order_id' => 11,
                    'customer_id' => 12,
                    // 'test' => 1,
                    'title' => 'title',
                    'description' => 'description',
                    'add_fee_to_price' => 1,
                    'cancel_url' => 'https://yourcancelurl',
                    'success_url' => 'https://yoursuccessurl',
                    'callback_url' => 'http://yourcallbackurl',
                    'expiration_date' => 48
];

$result= $payid19->create_invoice($request);

if(json_decode($result)->status=='error'){
		//error
		echo json_decode($result)->message[0];
}else{
		//success echo url
		echo json_decode($result)->message;
}
```

Get invoices: (<https://payid19.com/dev/invoices/get_invoices>)
```
$public_key  = 'xxxxx';
$private_key = 'xxxxx';

$payid19 = new \Payid19\ClientAPI($public_key,$private_key);
    
$request= [
		'order_id' 			=> '11'
];
    
$result= $payid19->get_invoices($request);
print_r($result);
```
