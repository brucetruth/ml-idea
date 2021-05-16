<?php
/**
 * Created by Bruce Mubangwa on 16 /May, 2021 @ 1:02
 */

namespace ML\IDEA\Utils;

class Math
{
    public function average($num1, $num2){
        return round(($num1/$num2)*100, 3) . "%";
    }
    public function logistic($x) {
        return 1/(1+ (2.7182 ** (-1 * ($x))));
    }

    public function euclidean($point1, $point2){
        $calc = 0;
        $countPoint1 = count($point1);
        $countPoint2 = count($point2);

        if($countPoint1 === $countPoint2) {
            for($x = 0; $x<$countPoint1; $x++) {
                $calc += ($point2[$x] - $point1[$x])*($point2[$x] - $point1[$x]);
            }
        }

        $calc = sqrt($calc);
        return $calc;
    }
}