<?php
define('IDS_ROOT', dirname(__FILE__).'/'.);
require_once(IDS_ROOT.'FSObject.php');
abstract class ImageDataStore extends FSObject implements IDataStore {
	public static function from($file) {
		$info = getimagesize($file);
		switch ($info[2]) {
			case IMAGETYPE_GIF: 
				require_once(IDS_ROOT.'GifDataStore.php');
				return new GifDataStore($file);
			case IMAGETYPE_PNG: 
				require_once(IDS_ROOT.'PngDataStore.php');
				return new PngDataStore($file);
			case IMAGETYPE_JPEG: 
				require_once(IDS_ROOT.'JpegDataStore.php');
				return new JpegDataStore($file);
			default:
				throw new Exception($file.": Files of this type are not supported");
		}
	}
}
