<p> Classification algorithms examples </p>
<b>1. KNearestNegbours</b>

  <!-- language: php -->
  ```php
  
include 'Classification/KNearestNegbours.php';
$samples = [[1, 3], [1, 4], [2, 4], [3, 1], [4, 1], [4, 2]];
$labels = ['a', 'a', 'b', 'b', 'c', 'c'];
$classifier = new KNearestNeighbors(6, true);
$classifier->train($samples, $labels);
$data = $classifier->predict([2, 1]);
echo "<pre>";
print_r($data);
echo "</pre>";

```
