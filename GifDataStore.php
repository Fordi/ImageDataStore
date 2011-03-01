<?php
require_once('ImageDataStore.php');
class GifDataStore extends ImageDataStore {
	private function isGIF() {
		$this->seek(0);
		return $this->read(3)=='GIF';
	}
	private function is89a() {
		$this->seek(3);
		return $this->read(3)=='89a';
	}
	private function skipFileDesc() {
		$this->seek(6);
		$screen = $this->read(7);
		$flags =ord($screen[4]);
		$cTableLen = (($flags & 0x80)>>7) * pow(2, (($flags & 0x07)+1))*3;
		$this->seek($cTableLen, SEEK_CUR);
	}
	private function isComment() {
		$intro = ord($this->read(1));
		switch($intro) {
			case 0x2C: //image descriptor
				$this->seek(9);
				$flags = ord($this->read(1));
				$cTableLen = (($flags & 0x80)>>7) * pow(2, (($flags & 0x07)+1))*3;
				$this->seek($cTableLen, SEEK_CUR);
				$size = -1;
				while ($size != 0) {
					$this->seek($size = ord($this->read(1)), SEEK_CUR);
				}
				return null;
			case 0x21: //extension introducer
				$label = ord($this->read(1));
				switch ($label) {
					case 0xFE: 
						$size = -1;
						$comment = array();
						while ($size != 0) {
							$comment[] = $this->read($size = ord($this->read(1)));
						}
						return join('', $comment);
					default: 
						$size = -1;
						while ($size != 0) {
							$this->seek($size = ord($this->read(1)), SEEK_CUR);
						}
						break;
				}
			case 0xEB: //EOF!
				return false;
		}
		return null;
	}
	private function copyBlock($toHandle) {
		$start = $this->tell();
		$intro = ord($this->read(1));
		switch($intro) {
			case 0x21: //extension introducer
				$label = ord($this->read(1));
				$size = -1;
				while ($size != 0) 
					$this->seek($size = ord($this->read(1)), SEEK_CUR);
				if ($label == 0xFE) return false;
			case 0x3b:
				fwrite($toHandle, "\x3b");
				return true;
			default: //image descriptor
				$this->seek(9, SEEK_CUR);
				$flags = ord($this->read(1));
				$cTableLen = (($flags & 0x80)>>7) * pow(2, (($flags & 0x07)+1))*3;
				$this->seek($cTableLen, SEEK_CUR);
				$size = -1;
				while ($size != 0) {
					$this->seek($size = ord($this->read(1)), SEEK_CUR);
				}
		}
		$len = $this->tell() - $start;
		$this->seek($start);
		$this->copy($toHandle, $len);
		return false;
	}
	public function readData() {
		$this->open();
		if (!$this->isGIF()) throw new Exception($this->file.' is not a GIF file!');
		if (!$this->is89a()) return null;
		$this->skipFileDesc();
		$comment = null;
		while(null === $comment) {
			$comment = $this->isComment();
			if ($comment === false) return null;
			if (substr($comment, 0, 5) !== 'DATA:') $comment = null;
			else {
				return $this->unpackData(substr($comment, 5));
			}
		}
		$this->close();
	}
	public function writeData($data) {
		$this->open();
		$fn = 'Clouds1.gif';//tempnam('.', 'gif89_');
		$tgt = fopen($fn, 'w');
		
		if (!$this->isGIF()) throw new Exception($this->file.' is not a GIF file!');
		fwrite($tgt, 'GIF89a');
		$this->skipFileDesc();
		$len = $this->tell()-6;
		$this->seek(6);
		$this->copy($tgt, $len);
		if ($data !== null) {
			$enc = 'DATA:'.$this->packData($data);
			$ct = strlen($enc);
			$dataBlock = array("!\xFE");
			for ($i=0; $i<$ct; $i+=255) {
				$len = min($ct-$i, 255);
				$dataBlock[]=chr($len).substr($enc, $i, $len);
			}
			$dataBlock[]="\x00";
			$dataBlock = join('', $dataBlock);
			fwrite($tgt, $dataBlock);
		}		
		while (true) {
			if ($this->copyBlock($tgt)) break;
		}
		
		$this->close();
		fclose($tgt);
		
		
		unlink($this->file);
		rename($fn, $this->file);
		
	}
}