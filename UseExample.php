<?php
require_once('ImageDataStore.php');
$ds = new ImageDataStore('gallery-image.jpg');
$ds->writeData((object)array(
	'comment'=>'You know I like the way this image looks',
	'taken'=>mktime(),
	'seeAlso'=>array('another-gallery-image.jpg'),
	'author'=>'Awesome User',
	'copyright'=>2011
));

var_dump($ds->readData());