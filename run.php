<?php

include 'Game.php';

$size = 5;
$board = [];
for($i = 0; $i < $size; $i++){
    $board[] = array_fill(0,$size,0);
}

foreach($board as $column_id => $row){
    foreach($row as $row_id => $cell){
      $board[$column_id][$row_id] = rand(0,1);
    }
}

$states = [0,1];

$func = function($me,$center){
    return $me;
};

$neighborhood = [
    ['column_offset' => -1,'row_offset' => -1,'calc' => $func],
    ['column_offset' => -1,'row_offset' => 0, 'calc' => $func],
    ['column_offset' => -1,'row_offset' => 1, 'calc' => $func],
    ['column_offset' => 0, 'row_offset' => -1,'calc' => $func],
    ['column_offset' => 0, 'row_offset' => 1, 'calc' => $func],
    ['column_offset' => 1, 'row_offset' => -1,'calc' => $func],
    ['column_offset' => 1, 'row_offset' => 0, 'calc' => $func],
    ['column_offset' => 1, 'row_offset' => 1, 'calc' => $func]
];

$rules = [0 => [
                  0 => 0,
                  1 => 0,
                  2 => 0,
                  3 => 1,
                  4 => 0,
                  5 => 0,
                  6 => 0,
                  7 => 0,
                  8 => 0
              ],
          1 => [
                  0 => 0,
                  1 => 0,
                  2 => 1,
                  3 => 1,
                  4 => 0,
                  5 => 0,
                  6 => 0,
                  7 => 0,
                  8 => 0
              ]
        ];

$game = new Game();
$game->init($board,$states,$neighborhood,$rules);
$game->start(10);
