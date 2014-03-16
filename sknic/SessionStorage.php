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
			chmod($this->file, 0600);
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

class SessionStorageException extends \Exception {}
