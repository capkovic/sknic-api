<?php

namespace sknic;

interface ISessionStorage {
	public function set($v);
	public function get();
	public function lock();
	public function unlock();
}

abstract class SessionStorage implements ISessionStorage {
	
}

class FileSessionStorage extends SessionStorage {
	
	protected $file;
	protected $fp;


	public function setFilePath($file) {
		$this->file = $file;
		if (!file_exists($this->file)) {
			file_put_contents($this->file, serialize(''));
		}
	}
	
	public function set($v) {
		$fp = $this->getFilePointer();
		$data = serialize($v);
		rewind($fp);
		fwrite($fp, $data, strlen($data));
		fflush($fp);
		ftruncate($fp, ftell($fp));
		return true;
	}
	
	public function get() {
		$fp = $this->getFilePointer();
		$data='';
		rewind($fp); 
		while(!feof($fp)) 
			$data.=fread($fp,100); 
		return @unserialize($data);
	}
	
	public function lock() {
		$fp = $this->getFilePointer();
		if (flock($fp, LOCK_EX | LOCK_NB)) {
			return true;
		}
		return false;
	}
	
	public function unlock() {
		$fp = $this->getFilePointer();
		return flock($fp, LOCK_UN); // always returns true
	}
	
	public function __destruct() {
        $this->unlock();
    }
	
	protected function getFilePointer() {
		if (!$this->fp) {
			if ($this->file == null) {
				throw new SessionStorageException('file path not set');
			}
			$this->fp = fopen($this->file, "r+");
			if ($this->fp === false) {
				throw new SessionStorageException('cannot open sknic session file');
			}
		}
		return $this->fp;
	}
}

class YiiCacheSessionStorage extends SessionStorage {
	
	protected $cache;
	protected $sessionCacheKey = 'sknic-session-cache';
	protected $sessionLockKey = 'sknic-session-lock';
	protected $locked = false;

	public function __construct($cacheComponent = null) {
		if ($cacheComponent) {
			$this->setCacheComponent($cacheComponent);
		}
	}

	public function setCacheComponent(\ICache $c) {
		$this->cache = $c;
	}
	
	public function set($v) {
		$this->cache->set($this->sessionCacheKey, $v);
	}

	public function get() {
		return $this->cache->get($this->sessionCacheKey);
	}
	
	public function lock() {
		if ($this->locked) {
			return true;
		}
		if (!$this->cache->get($this->sessionLockKey)) { // TODO: fix this (its not locking), or remove Yii caching entirely
			$this->cache->set($this->sessionLockKey, true, 120);
			$this->locked = true;
			return true;
		}
		return false;
	}
	
	public function unlock() {
		if ($this->locked) {
			$this->cache->set($this->sessionLockKey, false);
			$this->locked = false;
		}
		return true;
	}

	public function __destruct() {
		$this->unlock();
	}
}

class SessionStorageException extends \Exception {}