<?php

/*
Bruce Mubangwa 
2016


This algorithm can be applied to both classification and regression problems.
Apparently, within the Data Science industry, it's more widely used to solve classification problems. 
It’s a simple algorithm that stores all available cases and classifies any new cases by taking a majority vote of its k neighbors. 
The case is then assigned to the class with which it has the most in common. 
A distance function performs this measurement.
*/


 include '../utils/Functions.php';

 class KNearestNeighbors {
	private $output = false;
	private $data = array();
	private $max = 0;
     /**
      *@param 
         *max: int:'number of sets of datasets.
         *output: boolean
     */
	function __construct($max, $output) {
        $this->max = $max;
        $this->output = $output;
    }
	
   
	function train($samples, $labels) {
		$countSamples = count($samples);
		$countLabels = count($labels);
		if($countSamples == $countLabels) {
			for($x = 0; $x<$countSamples; $x++) {
				$this->data[] = [$labels[$x], $samples[$x]];
			}
		}
	}
	
  /**
     * @return mixed
  */
	function predict($point) {
		$timer = new Timer();
		$timer->start();
		$d = array();
		$labels = array();
		$distance = new Distance();
		foreach($this->data as $value) {
			$d[rand(1000,9999) . '-' . $value[0]] = $distance->euclidean($point, [$value[1][0], $value[1][1]]);
			$labels[$value[0]] = 0;
		}
		asort($d);	
		$i = 0;
		foreach ($d as $key => $value) {
			$key = substr($key, 5);
			foreach($labels as $key2 => $value2) {
				if($i-2 <= $this->max) {
					if ($key2 == $key) {
						$labels[$key2] = $value2+1;
					}
					$i++;
				}
			}
		}
		arsort($labels);
		$this->data = $labels;
		$labels = key($labels);
		$predict = $labels;
		
		if ($this->output == true) {
			$average = new Functions();
			$x = 0;
			$y = 0;
			foreach($this->data as $key => $value) {
				if ($x == 0) {
					$temp = $value;
				}
				$y += $value;
				$x++;
			}
			$timer->finish();
			$out = array($predict, $average->average($temp,$y), $timer->runtime());
			return $out;
		} else {
			$timer->finish();
			return array($predict);
		}
	}
}
?>
