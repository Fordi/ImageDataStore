<?php
require_once('ImageDataStore.php');
class PngDataStore extends ImageDataStore {
	private function isPNG() {
		$this->seek();
		if ($this->read(8)!="\x89\x50\x4e\x47\x0d\x0a\x1a\x0a") return false;
		return true;
	}
	public static function crcMsg($byte) {
		static $msg = array();
		if (isset($msg[$byte])) return $msg[$byte];
		$c = array(0x00, 0x00, 0x00, $byte);
		for ($k=0; $k<8; $k++) {
			$c1 = array(
				$c[0] >> 1, 
				(($c[0] & 1) << 7) | ($c[1]>>1), 
				(($c[1] & 1) << 7) | ($c[2]>>1), 
				(($c[2] & 1) << 7) | ($c[3]>>1)
			);
			if ($c[3] & 1) 
				$c = array(
					0xed ^ $c1[0],
					0xb8 ^ $c1[1],
					0x83 ^ $c1[2],
					0x20 ^ $c1[3]
				);
			else 
				$c = $c1;
		}
		return $msg[$byte]=$c;
	}
	private function calcCRC($data, $crc=null) {
		if ($crc === null) $crc = array(0xff, 0xff, 0xff, 0xff);
		if (is_string($crc)) $crc = unpack('C*', $crc);
		for ($n=0; $n<strlen($data); $n++) {
			$msg = $this->crcMsg($crc[3] ^ ord($data[$n]));
			$crc = array($msg[0], $msg[1] ^ $crc[0], $msg[2] ^ $crc[1], $msg[3] ^$crc[2]);
		}
		return pack('C*', $crc[0]^0xFF, $crc[1]^0xFF, $crc[2]^0xFF, $crc[3]^0xFF);
	}
	private function chunk($data=false) {
		$pos = $this->tell();
		$chunkLen = $this->readInt(true);
		$chunkType = $this->read(4);
		if ($data) {
			$chunkData = $this->read($chunkLen);
			$crc = $this->read(4);
			$check = $this->calcCRC($chunkType.$chunkData);
			return (object)array(
				'type'=>$chunkType,
				'pos'=>$pos,
				'length'=>$chunkLen,
				'data'=>$chunkData,
				'crc'=>$crc == $check
			);
		}
		$this->seek($chunkLen + 4, SEEK_CUR);
		return (object)array(
			'type'=>$chunkType,
			'pos'=>$pos,
			'length'=>$chunkLen
		);
	}
	public function readData() {
		$this->open();
		if (!$this->isPNG()) throw new Exception($this->file." is not a PNG file");
		while (!feof($this->handle)) {
			$chunk = $this->chunk();

			if ($chunk->type == 'IEND') break;
			if ($chunk->type == 'tEXt') {
				$this->seek($chunk->pos);
				$chunk = $this->chunk(true);
				if (substr($chunk->data, 0, 5) == "DATA\x00") {
					$this->close();
					return $this->unpackData(substr($chunk->data,5));
				}
			}
		}
		$this->close();
		return null;
	}
	public function copyChunk($toHandle) {
		$chunk = $this->chunk();
		$this->seek($chunk->pos);
		$this->copy($toHandle, $chunk->length+12);
	}
	public function writeData($data) {
		$this->open();
		$enc = "DATA\x00".$this->packData($data);
		if ($data === null) $dataChunk = '';
		else $dataChunk = pack("N", strlen($enc)).'tEXt'.$enc.$this->calcCRC('tEXt'.$enc);
		
		$fn = tempnam('.', 'png_');
		$tgt = fopen($fn, 'w');
		
		if (!$this->isPNG()) throw new Exception($this->file." is not a PNG file");
		$this->seek(0);
		$this->copy($tgt, 8);
		$passedHeader = false;
		$wroteData = false;
		
		while (!feof($this->handle)) {
			if ($passedHeader && !$wroteData) {
				fwrite($tgt, $dataChunk);
				$wroteData = true;
			}
			$chunk = $this->chunk();
			if ($chunk->type == 'tEXt') continue;
			if ($chunk->type == 'IHDR') $passedHeader = true;
			$this->seek($chunk->pos);
			$this->copyChunk($tgt);
			if ($chunk->type == 'IEND') break;
		}
		$this->close();
		fclose($tgt);
		unlink($this->file);
		rename($fn, $this->file);
	}
}