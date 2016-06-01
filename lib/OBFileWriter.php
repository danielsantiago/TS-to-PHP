<?php
namespace phptojs\util;
class OBFileWriter {
	private $_filename;
	private $_fp = null;

	public function __construct($filename) {
		$this->setFilename($filename);
	}

	public function __destruct() {
		if ($this->_fp)
			$this->end();
	}

	public function setFilename($filename) {
		$this->_filename = $filename;
	}

	public function getFilename() {
		return $this->_filename;
	}

	public function start() {
		$this->_fp = @fopen($this->_filename, 'w');

		if (!$this->_fp)
			throw new Exception('Cannot open ' . $this->_filename . ' for writing!');

		ob_start(array($this, 'outputHandler'));
	}

	public function end() {
		@ob_end_flush();
		if ($this->_fp)
			fclose($this->_fp);

		$this->_fp = null;
	}

	public function outputHandler($buffer) {
		fwrite($this->_fp, $buffer);
	}
	
	public function isEnd(){
		if ($this->_fp==null){
			return true;
		}
		return false;
	}
}