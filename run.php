<?php

include 'Game.php';

function std_dev($arr) {
    $num_of_elements = count($arr);

    $variance = 0.0;

    $average = array_sum($arr)/$num_of_elements;

    foreach($arr as $i) {
        $variance += pow(($i - $average), 2);
    }

    return (float)sqrt($variance/$num_of_elements);
}

$size = 40;
$states = [0,1];
$board = [];

for($i = 0; $i < $size; $i++){
    $board[$i] = [];
    for($j = 0; $j < $size; $j++){
        $board[$i][$j] = $states[array_rand($states)];
    }
}

$func = function($my_col,$my_row,$center_col,$center_row,$my_val,$center_val){
    $distance = floor(sqrt(($center_col - $my_col) ** 2 + ($center_row - $my_row) ** 2));
    return floor($my_val/($distance ** 2));
};

$max_sum = 16 * max($states);

$neighborhood = [];

for($i = -$size/2; $i <= $size/2; $i++){
    for($j = -$size/2; $j <= $size/2; $j++){
        if($i != 0 || $j != 0){
            $neighborhood[] = ['column_offset' => $i, 'row_offset' => $j, 'calc' => $func];
        }
    }
}

$rules = [];

$arr = range(0,$max_sum);
$devi = std_dev($arr);
$center = $max_sum/2;

foreach($states as $state){
    $rules[$state] = [];
    for($i = 0; $i <= $max_sum; $i++){
        $devs = floor(abs($center - $i) / $devi);
        $rules[$state][$i] = $states[count($states) - 1 - $devs];
    }
}

$game = new Game();
$game->init($board,$states,$neighborhood,$rules);
$game->start(10);
