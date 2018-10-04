<?php

include 'Timer.php';

class Distance {
	function euclidean($point1, $point2){
		$calc = 0;
		$countPoint1 = count($point1);
		$countPoint2 = count($point2);

		if($countPoint1 == $countPoint2) {
			for($x = 0; $x<$countPoint1; $x++) {
				$calc += ($point2[$x] - $point1[$x])*($point2[$x] - $point1[$x]);
			}
		}

		$calc = sqrt($calc);
		return $calc;
	}
}

class Functions {
	function average($num1, $num2){
		$avg = round(($num1/$num2)*100, 3) . "%";
		return $avg;
	}
	function logistic($x) {
		$fx = 1/(1+pow(2.7182, -1*($x)));
		return $fx;
	}
}




?>
