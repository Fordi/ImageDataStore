<?php
interface IDataStore {
	public function readData();
	public function writeData($data);
}
