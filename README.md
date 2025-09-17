# Sdk for frontpad.ru

### Create order

```php
$frontpad = new \Kolirt\Frontpad\Frontpad('secret');

$order = new \Kolirt\Frontpad\Order;
$order->setName('Oleh');
$order->setPhone('phonenumber');
$order->setMail('qwe@qwe.qwe');
$order->setStreet('street name');
$order->setHome('19');
$order->setPod('1');
$order->setEt('9');
$order->setApart('920');
$order->setDescr('Comment');
$order->setSaleAmount(100);
$order->addHookStatus(10);
$order->setHookUrl('https://example.com/webhook'); // optional
$order->setAffiliate(20);
$order->setPoint(30);
$order->setPerson(4);
$order->setDateTime('2016-08-15 15:30:00');
$order->setCertificate('CERT-12345'); // номер сертификата


$order->adTag(10);
$order->adTag(11);
$order->adTag(12);


$product1 = new \Kolirt\Frontpad\Product();
$product1->setIndex($order->getNewProductIndex());
$product1->setId(100);
$product1->setQuantity(2);
$product1->setPrice(100);
$order->addProduct($product1);

$product2 = new \Kolirt\Frontpad\Product();
$product2->setIndex($order->getNewProductIndex());
$product2->setModIndex($product1->getIndex());
$product2->setId(101);
$product2->setQuantity(2);
$product2->setPrice(30);
$order->addProduct($product2);


$frontpad->newOrder($order);
```

### Get client

```php
$frontpad = new \Kolirt\Frontpad\Frontpad('secret');

$frontpad->getClient('client_phone');
```

### Get certificate

```php
$frontpad = new \Kolirt\Frontpad\Frontpad('secret');

$frontpad->getCertificate('certificate');
```

### Get products

```php
$frontpad = new \Kolirt\Frontpad\Frontpad('secret');

$frontpad->getProducts();
```
