<?php

class Game {
    private $_board; //2D Array of ints;
    private $_states; //Possible states of each cell (array of ints)
    private $_neighborhood; //Array of relative addresses, plus a weight calculator
    private $_rules; //3D array: each possible current state => all possible neighborhood totals => new state

    public function init($board,$states,$neighborhood,$rules){
        $this->_board = $board;
        $this->_states = $states;
        $this->_neighborhood = $neighborhood;
        $this->_rules = $rules;
    }

    public function start($steps = 0) {
        for($i = 0; $i < $steps; $i++){
            $this->tick();
        }
    }

    public function stop(){

    }

    private function tick() {
        $new_board = $this->_board;
        foreach($this->_board as $column_id => $row){
            foreach($row as $row_id => $cell){
                $change = 0;
                foreach($this->_neighborhood as $neighbor){
                    $new_col = $column_id + $neighbor['column_offset'];
                    $new_row = $row_id + $neighbor['row_offset'];
                    if($new_col >= 0
                      && $new_col < count($this->_board)
                      && $new_row >= 0
                      && $new_row < count($row)){
                          $change += $neighbor['calc']($new_col,$new_row,$column_id,$row_id,$this->_board[$new_col][$new_row],$this->_board[$column_id][$row_id]);
                      }
                }

                $new_board[$column_id][$row_id] = $this->_rules[$this->_board[$column_id][$row_id]][$change];
            }
        }

        $this->_board = $new_board;
        file_put_contents('board.txt',serialize($this->_board));
        $this->printBoard();
    }

    private function printBoard() {
        foreach($this->_board as $row){
            foreach($row as $cell){
                echo $cell . ' ';
            }
            echo "\n";
        }
        echo "\n\n\n";
    }
}
