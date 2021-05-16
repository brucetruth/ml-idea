<?php

namespace ML\IDEA\Classifiers;

use ML\IDEA\Utils\Timer;

class SVC {
	private $data = array();
	private $output = false;
	
	public function __construct( $output ) {
        $this->output = $output;
    }
	public function train( $samples, $labels ) {
		$countSamples = count($samples);
		$countLabels = count($labels);
		if($countSamples === $countLabels) {
			for($x = 0; $x<$countSamples; $x++) {
				$this->data[] = [$labels[$x], $samples[$x]];
			}
		}
	}
	public function predict( $point ) {
		$timer = new Timer();
		$timer->start();
		$slopef = 0;
		$bf = 0;
		$list = array();
		
		foreach($this->data as $value) {
			if(!in_array($value[0], $list, true)) {
				$list[] = $value[0];
			}
		}
		$list = array_unique($list);
		foreach($list as $sample) {
			$count = 0;
			$ysum = 0;
			$xsum = 0;
			$xx = 0;
			$yy = 0;
			foreach($this->data as $value) {
				if($sample[0] !== $value[0]) {
					$count++;
					$ysum += $value[1][0];
					$xsum += $value[1][1];
				}
			}
			$ymean = $ysum/$count;
			$xmean = $xsum/$count;
			foreach($this->data as $value) {
				if($sample[0] !== $value[0]) {
					$xx += ($value[1][1]-$xmean)*($value[1][0]-$ymean);
					$yy += ($value[1][1]-$xmean)*($value[1][1]-$xmean);
				}
			}
			$slope = $xx/$yy;
			$b = $ymean-($slope*$xmean);
			foreach ($list as $xValue) {
				if($sample[0] === $xValue[0]) {
					$list[] = [$slope, $b];
				}
			}
			$slopef += $slope;
			$bf += $b;
		}	
		$slopef /= 2; 
		$bf /= 2; 
		
		$s1 = ($slopef*($point[0]))+$bf;
		$s1 = ($point[1])-($s1);
		if($s1 < 0) {
			if($list[2][1] < $list[3][1]) {
				if($this->output === true) {
					$timer->finish();
					return array($list[0], $timer->runtime());
				}

				$timer->finish();
				return array($list[0]);
			}

			if($this->output === true) {
				$timer->finish();
				return array($list[1], $timer->runtime());
			}

			$timer->finish();
			return array($list[1]);
		}

		if($list[2][1] > $list[3][1]) {
			if($this->output === true) {
				$timer->finish();
				return array($list[0], $timer->runtime());
			}

			$timer->finish();
			return array($list[0]);
		}

		if($this->output === true) {
			$timer->finish();
			return array($list[1], $timer->runtime());
		}

		$timer->finish();
		return array($list[1]);
	}

}

?>
