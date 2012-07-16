PHP PayPal API Class
====================
Class for handling Express Checkout and Direct Payment Using PayPal's NVP API.

License: MIT Copyright (c) 2011 Kelly Lauren Summer Becker<br />
Version: 1.1

Country and State Codes are listing in arrays at the bottom of the Class these are in the proper format for PayPal already.

## Express Checkout USAGE
Call PayPal and goto their site for checkout.

```php
<?php
$paypal->express_checkout(array(
	'RETURN' => 'http://my.return/handler.php',
 	'CANCEL' => 'http://my.cancel/handler.php',
 	'INVNUM' => 'Invoice Number',
 	'ITEMS' => array(
 		'1' => array(
 			'NAME' => 'Item Name',
 			'QTY' => 'Item Quantity',
 			'AMT' => 'Item Price'
 		)
 	)
 ), 'checkout');
 ```

 Return to our site and process/finalize the order
 
 ```php
 <?php
 $paypal->express_checkout(array(
 	'IPN_URL' => 'http://my.ipn/handler.php'
 ), 'process');
 ```

## Direct Payments Usage
 (untested due to paypal not having website payments pro test site easily available)

 ```php
 <?php
 $paypal->direct_payment(array(
 	'IPN_URL' => 'http://my.ipn/handler.php',
 	'INVNUM' => 'Invoice Number',
	'CREDITCARD_TYPE' => 'Master, Visa, Discover, Amex',
 	'CREDITCARD_NUM' => 'Credit Card Number',
 	'CVV' => 'Credit Card CVV Code (3-4 digits)',
 	'EXPIRY' => 'Credit Card Expiry Date MMYYYY',
 	'FNAME' => 'First Name',
 	'LNAME' => 'Last Name',
 	'STREET' => 'Street Address',
 	'CITY' => 'City',
 	'STATE' => 'State Code',
 	'COUNTRY' => 'Country'
 	'ITEMS' => array(
 		'1' => array(
 			'NAME' => 'Item Name',
 			'QTY' => 'Item Quantity',
 			'AMT' => 'Item Price'
 		)
 	)
);
```

## IPN Processor Usage

```php
<?php
$this->process_ipn();
```