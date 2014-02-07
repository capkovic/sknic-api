sk-nic php api
==============

Domain info
-------------

```php
require_once 'sknic/SknicDomainInfo.php';
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
require_once 'sknic/SknicRegister.php';
$api = new \sknic\SknicPay();
$api->setUsername('login');
$api->setPassword('pass');
var_dump($api->registerDomain('newdomain', 'ABCD-0001'));
```

Pay domain
----------

```php
require_once 'sknic/SknicPay.php';
$api = new \sknic\SknicPay();
$api->setUsername('login');
$api->setPassword('pass');
var_dump($api->payDomain('newdomain'));
```

NS change
---------

```php
require_once 'sknic/SknicNsChange.php';
$api = new \sknic\SknicNsChange();
$api->setUsername('login');
$api->setPassword('pass');
var_dump($api->change('newdomain', array('nserver1'=>'ns1.mynameserver.sk')));
```
