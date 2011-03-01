<?php
class JpegDataStore extends ImageDataStore {
	private function block($dataNeeded=false, $large=false) {
		$data = $this->readInt()-2;
		if ($dataNeeded) {
			$data = $this->read($data);
		} else {
			$this->seek($data, SEEK_CUR);
		}
		return $data;
	}
	
	private function findComment() {
		$comments = array();
		while (!feof($this->handle)) {
			switch ($bt = $this->read(2)) {
				case "\xFF\xFE":
					$comment = (object)array(
						'pos' => $this->tell()-2,
						'len' => $this->readInt()+2
					);
					if ($this->read(5)=='DATA:') $comments[]=$comment;
					$this->seek($comment->pos+$comment->len);
					break;
				case "\xFF\xD9": case "\xFF\xD8":  break;
				case "\xFF\xC0": case "\xFF\xC2": case "\xFF\xC4": case "\xFF\xDB": case "\xFF\xDB": case "\xFF\xDA": 
					$this->block();
					break;
				case "\xFF\xDD":
					$this->readInt();
					break;
				default:
					if (strlen($bt)<2) break;
					if ($bt[0]!="\xff") break;
					$val = ord($bt[1]);
					if (($val>=0xD0 && $val <= 0xD7) || $val >= 0xE0 && $val <= 0xEF) {
						$this->block();
						break;
					}
			}
		}
		if (empty($comments))
			return array();
		return $comments;
	}
	function readData() {
		$this->open();
		$comment = $this->findComment();
		$data = array();
		forEach($comment as $section) {
			$this->seek($section->pos+9);
			$data[]=$this->read(max(0,$section->len-9));
		}
		
		$this->close();
		return $this->unpackData(join('', $data));
	}
	function writeData($data) {
		$this->open();
		
		$dataBlock = array();
		
		if (!empty($data)) {
			$dec = $this->packData($data);
			$len = strlen($dec);
			for ($i=0; $i<$len; $i+=65528) {
				$ct = min(65528, $len-$i);
				$dataBlock[]="\xFF\xFE".pack("n*", $ct+7).'DATA:'.substr($dec,$i, $ct);
			}
		}

		$dataBlock = join('', $dataBlock);
		$comment = $this->findComment();
		$fn = tempnam('.', 'jfif_');
		$newFile = fopen($fn, 'w');
		$len = $this->size();
		$this->seek();
		$this->copy($newFile, 2);
		//Insert comment at head of file
		fwrite($newFile, $dataBlock);
		
		//Strip existing comments
		for ($i=0; $i<count($comment); $i++) {
			$this->copy($newFile, $comment[$i]->pos - $this->tell());
			$this->seek($comment[$i]->len, SEEK_CUR);
		}
		
		//Copy the remainder of the file
		$this->copy($newFile, $len - $this->tell());
		
		fclose($newFile);
		$this->close();
		unlink($this->file);
		rename($fn, $this->file);
	}
}