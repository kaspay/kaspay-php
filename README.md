kaspay-php
==========

Kaspay API client for PHP

# Installation
Just copy the content of `dist` to your project directory and include `kaspay_api.combined.php` file.

# Getting merchant keys
Login to [Kaspay](https://www.kaspay.com) with your merchant account and directly go to [Profile Setting Page](https://www.kaspay.com/profile-setting) to acquire both **encryption key** and **MAC key**. There should be _API Keys_ section in that page.

>Trouble getting merchant keys? _API Keys_ section does not exist?
Please [contact us](http://localhost/kaspay-dev/contact).

# Usage
List of available services:

1. Payment API

### Payment API
#### Creating payment
```PHP
///Constructing the client
$client = new Kaspay_payment_api_client(
	<your uaccount>, <your enc key>, <your MAC key>
);
$attempt = new stdClass();
$attempt->merchant_uaccount = <your uaccount>;
$attempt->approve_url = <your site approve URL>;
$attempt->reject_url = <your site reject URL>;
$product_1 = new Product();
$product_1->id = "product-1";
$product_1->name = "product-1";
$product_1->price = 100;
$product_1->quantity = 1;
$attempt->transaction_no = <your generated transaction ID>;
$attempt->timestamp = time();
$attempt->products = array(
	$product_1,
	clone $product_1,
	clone $product_1,
);
$attempt->total = 10000;

///Calling the API
///You can comment the next line in production mode, for sandbox only
$client->set_as_development();
$client->create($attempt);

///Getting the response
$response = json_encode($client->get_response());
print_r($response);
```
#### Executing payment
```PHP
///Constructing the client
$client = new Kaspay_payment_api_client(
	<your uaccount>, <your enc key>, <your MAC key>
);

///Calling the API.
///You can comment the next line in production mode, for sandbox only
$client->set_as_development();
$client->execute($id);

///Getting the response
$response = json_encode($client->get_response());
print_r($response);
```
#### Refunding payment
_Applicable only for executed payments_
```PHP
///Constructing the client
$client = new Kaspay_payment_api_client(
	<your uaccount>, <your enc key>, <your MAC key>
);

///Calling the API.
///You can comment the next line in production mode, for sandbox only
$client->set_as_development();
///$reference_no is acquired from execute() call response
$client->refund($reference_no);

///Getting the response
$response = json_encode($client->get_response());
print_r($response);
```
