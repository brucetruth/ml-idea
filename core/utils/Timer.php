<?php

namespace ML\IDEA\Utils;

class Timer {
	private $start;
	private $finish;

	public function start(){
		$this->start = microtime(true);
	}

	public function finish(){
		$this->finish = microtime(true);
	}

	public function runtime() {
		return ($this->finish-$this->start)*10;
	}
}

?>
