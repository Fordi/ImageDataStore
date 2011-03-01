<?php
abstract class ImageDataStore extends FSObject implements IDataStore {
	public static function from($file) {
		$info = getimagesize($file);
		switch ($info[2]) {
			case IMAGETYPE_GIF: 
				require_once('GifDataStore.php');
				return new GifDataStore($file);
			case IMAGETYPE_PNG: 
				require_once('PngDataStore.php');
				return new PngDataStore($file);
			case IMAGETYPE_JPEG: 
				require_once('JpegDataStore.php');
				return new JpegDataStore($file);
			default:
				throw new Exception($file.": Files of this type are not supported");
		}
	}
}
