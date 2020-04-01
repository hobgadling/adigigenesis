<?php

include 'Game.php';

$size = 40;
$states = [0,1,2,3];
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

$center = $max_sum/2;

foreach($states as $state){
    $rules[$state] = [];
    for($i = 0; $i <= $max_sum; $i++){
        $dist = abs($center - $i);
        $div = ($max_sum / count($states)) / 2;
        $nth = floor($dist/$div);
        $rules[$state][$i] = count($states) - 1 - $nth > 0 ? $states[count($states) - 1 - $nth] : $states[0];
    }
}

$game = new Game();
$game->init($board,$states,$neighborhood,$rules);
$game->start(10);
