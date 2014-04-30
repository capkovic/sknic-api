sk-nic php api
==============


Installation
------------

add this to composer.json

```
    "repositories": [
        {
            "url": "https://github.com/capkovic/sknic-api.git",
            "type": "git"
        }
    ],
    "require": {
        "capkovic/sknic-api": "dev-master"
    }
```

Domain info
-------------

```php
$api = new \sknic\SknicDomainInfo();
$api->setUsername('login');
$api->setPassword('pass');
$api->setDomain('sk-nic.sk');
$api->loadDomainInfo();
echo $api->getRegistrar();
echo $api->getOwner();
echo $api->getState();
echo $api->getExpirationDate();
```

Register domain
---------------

```php
$api = new \sknic\SknicRegister();
$api->setUsername('login');
$api->setPassword('pass');
$api->waitForLock(); // aquire lock, so other proccesses can't use this session
try {
    $api->registerDomain('newdomain', 'ABCD-0001');
} catch (Exception $e) {
    var_dump($e); // error via exception
}
$api->unlock();
```

Pay domain - collective invoice
----------

```php
$api = new \sknic\SknicPay();
$api->setUsername('login');
$api->setPassword('pass');
$api->waitForLock();
try {
    $api->payDomain('newdomain');
} catch (Exception $e) {
    var_dump($e);
}
$api->unlock();
```

Pay domain - variables from Proforma for external processing
----------

```php
$api = new \sknic\SknicPay();
$api->setUsername('login');
$api->setPassword('pass');
$api->waitForLock();
try {
	echo $api->getDomainPrice('domain');
	echo $api->getProformaPrice('domain');
	echo $api->getProformaVariableSymbol('domain');
} catch (Exception $e) {
    var_dump($e);
}
$api->unlock();
```


NS change
---------

```php
$api = new \sknic\SknicNsChange();
$api->setUsername('login');
$api->setPassword('pass');
$api->waitForLock();
try {
    $api->change('newdomain', array('nserver1'=>'ns1.mynameserver.sk'));
} catch (Exception $e) {
    var_dump($e);
}
$api->unlock();
```

Owner change
------------

```php
$api = new \sknic\SknicOwnerChange();
$api->setUsername('login');
$api->setPassword('pass');
$api->waitForLock();
try {
    $api->change('mydomain', 'ABCD-0001');
    $pdf = $api->getPdf(); // form to sign
} catch (Exception $e) {
    var_dump($e);
}
$api->unlock();
```

Transfer (registrator change)
----------------------------

```php
$api = new \sknic\SknicTransfer();
$api->setUsername('login');
$api->setPassword('pass');
$api->waitForLock();
try {
    $api->change('mydomain');
    $pdf = $api->getPdf(); // form to sign
} catch (Exception $e) {
    var_dump($e);
}
$api->unlock();
```

Using multiple sessions and locks
---------------------------------

Add these lines after you instantiate Sknic... class:

```php
$storage = new \sknic\FileSessionStorage();
$storage->setFilePath('/tmp/sknic_session_2');
$api->setSessionStorage($storage);
```

default session storage and lockfile is in `/tmp/sknic_sessions`

