<?php

class SknicTest extends CDbTestCase {
	
	public $pass = false; // please do not commit this password

	public function testFileCacheStorage() {
		
		require_once 'sknic/SessionStorage.php';
		
		if (file_exists('/tmp/test_FileSessionStorage'))
			unlink('/tmp/test_FileSessionStorage');
		
		$cache = new \sknic\FileSessionStorage();
		$cache->setFilePath('/tmp/test_FileSessionStorage');
		$cache->set('jh589asfsdfgsdog');
		$this->assertEquals('jh589asfsdfgsdog', $cache->get());
		
		$cache = new \sknic\FileSessionStorage();
		$cache->setFilePath('/tmp/test_FileSessionStorage');
		$this->assertEquals('jh589asfsdfgsdog', $cache->get());
		
		$cache->set('aaaaaaaa');
		$this->assertEquals('aaaaaaaa', $cache->get());
		
		unlink('/tmp/test_FileSessionStorage');
	}
	
	public function testFileCacheStorageLocking() {
		
		require_once 'sknic/SessionStorage.php';
		$sessionFile = '/tmp/test_FileSessionStorage';
		if (file_exists($sessionFile))
			unlink($sessionFile);
		
		$cache = new \sknic\FileSessionStorage();
		$cache->setFilePath($sessionFile);

		$cache2 = new \sknic\FileSessionStorage();
		$cache2->setFilePath($sessionFile);
		
		
		$this->assertTrue($cache->lock());
		$this->assertTrue($cache->lock());
		$this->assertFalse($cache2->lock());
		
		$cache->unlock();
		$this->assertTrue($cache2->lock());
		
		$cache->unlock();
		$this->assertFalse($cache->lock());
		
		
		unlink($sessionFile);
	}

	public function testYiiCacheStorage() {
		
		require_once 'sknic/SessionStorage.php';
		
		$cache = new \sknic\YiiCacheSessionStorage(Yii::app()->cache);
		$cache->set('hsdkagjaslkf');
		$this->assertEquals('hsdkagjaslkf', $cache->get());
		
		$cache = new \sknic\YiiCacheSessionStorage(Yii::app()->cache);
		$this->assertEquals('hsdkagjaslkf', $cache->get());
	}
	
	
	public function testYiiCacheStorageLocking() {
		require_once 'sknic/SessionStorage.php';
		
		$cache = new \sknic\YiiCacheSessionStorage(Yii::app()->cache);
		$cache2 = new \sknic\YiiCacheSessionStorage(Yii::app()->cache);
		
		
		$this->assertTrue($cache->lock());
		$this->assertTrue($cache->lock());
		$this->assertFalse($cache2->lock());
		
		$cache->unlock();
		$this->assertTrue($cache2->lock());
		
		$cache->unlock();
		$this->assertFalse($cache->lock());
	}

	public function testLogin() {
		
		if (!$this->pass) {
			return;
		}
		
		require_once 'sknic/SknicApi.php';
		
		$api = new \sknic\SknicApi();
		$api->setUsername('WEBS-0001');
		$api->setPassword($this->pass);
		
		$this->assertTrue($api->login());
	}

	public function testInfo() {
		
		if (!$this->pass) {
			return;
		}
		
		require_once 'sknic/SknicDomainInfo.php';
		
		$api = new \sknic\SknicDomainInfo();
		$api->setUsername('WEBS-0001');
		$api->setPassword($this->pass);
		
		$api->setDomain('websupport.sk');
		$this->assertTrue($api->loadDomainInfo());
		$this->assertEquals('WEBS-0001', $api->getRegistrar());
		$this->assertEquals('WEBS-0001', $api->getOwner());
		$this->assertEquals('DOM_OK', $api->getState());

	}
	
	public function testRegister() {
		
		if (!$this->pass) {
			return;
		}
		
		require_once 'sknic/SknicRegister.php';
		
		$api = new \sknic\SknicRegister();
		$api->setUsername('WEBS-0001');
		$api->setPassword($this->pass);
		

		$testDomain = 'a'.md5(rand());
		$this->assertTrue($api->registerDomain($testDomain, 'WEBS-0001', false, array('nserver1'=>'ns1.websupport.sk')));
		
	}
	
	
	public function testPay() {
		
		if (!$this->pass) {
			return;
		}
		
		require_once 'sknic/SknicPay.php';
		
		$api = new \sknic\SknicPay();
		$api->setUsername('WEBS-0001');
		$api->setPassword($this->pass);
		

//		$this->assertTrue($api->payDomain('cute'));
		
	}
	
	public function testNsChange() {
		
		if (!$this->pass) {
			return;
		}
		
		require_once 'sknic/SknicNsChange.php';
		
		$api = new \sknic\SknicNsChange();
		$api->setUsername('WEBS-0001');
		$api->setPassword($this->pass);
		
//		$this->assertTrue($api->change('dfgsdfgasdfgasds', array('nserver1'=>'ns1.websupport.sk')));
	}
}
