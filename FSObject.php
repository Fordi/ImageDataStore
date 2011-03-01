<?php
class FSObject {
	protected $handle;
	protected $file;

	function __construct($file) {
		if (!file_exists($file)) 
			throw new Exception('File "'.$file.'" does not exist!');
		$this->file = $file;
	}
	protected function open() {
		$this->handle = fopen($this->file, 'r');
	}
	protected function close() {
		fclose($this->handle);		
		$this->handle = null;		
	}
	protected function seek($pos=0, $mode=SEEK_SET) {
		return fseek($this->handle, $pos, $mode);
	}
	protected function tell() {
		return ftell($this->handle);
	}
	protected function read($len, $debug=false) {
		if ($debug) echo 'Reading: '.$len."\r\n";
		if ($len==0) return '';
		return fread($this->handle, $len);
	}
	protected function readInt($large = false, $signed=false, $little=false, $array=false) {
		$chars=array('n','N','s','l','v','V','i','I');
		$len = $large?4:2;
		if ($array) return unpack('C*', $this->read($len));
		
		$char = $chars[ ($large?1:0) + ($signed?2:0) + ($little?4:0) ];
		$data = $this->read($len);
		$data = unpack($char.'*', $data);
		$data = array_shift($data);
		
		
		
		return $data;
	}
	function copy($toHandle, $bytes) {
		$copied = 0;
		while ($copied < $bytes && !feof($this->handle)) {
			$copy = min(10240, $bytes-$copied);
			$buf = fread($this->handle, $copy);
			fwrite($toHandle, $buf);
			$copied+=$copy;
		}
	}
	function size() {
		$pos = $this->tell();
		$this->seek(0, SEEK_END);
		$ret = $this->tell();
		$this->seek($pos);
		return $ret;
	}
	function compress($data) {
		return gzcompress($data, 9);
	}
	function decompress($data) {
		return gzuncompress($data);
	}
	function serialize($obj) {
		return serialize($obj);
	}
	function unserialize($obj) {
		return unserialize($obj);
	}
	function packData($obj) {
		return $this->compress($this->serialize($obj));
	}
	function unpackData($data) {
		return $this->unserialize($this->decompress($data));
	}
	
}