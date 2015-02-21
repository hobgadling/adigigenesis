<?php
//externally supplied functions
function read6502($address){
	if($address < 0){
		$address = (SIZE ^ 2) - $address;
	}
	$readaddr = $address % (SIZE ^ 2);
	$i = floor($readaddr/SIZE);
	$j = $readaddr%SIZE;
	return $GLOBALS['grid'][$i][$j] & 0xFF;
}

function write6502($address, $value){
	if($address < 0){
		$address = (SIZE ^ 2) - $address;
	}
	$readaddr = $address % (SIZE ^ 2);
	$i = floor($readaddr/SIZE);
	$j = $readaddr%SIZE;
	$GLOBALS['grid'][$i][$j] = $value;
}

//6502 defines
const FLAG_CARRY     = 0x01;
const FLAG_ZERO      = 0x02;
const FLAG_INTERRUPT = 0x04;
const FLAG_DECIMAL   = 0x08;
const FLAG_BREAK     = 0x10;
const FLAG_CONSTANT  = 0x20;
const FLAG_OVERFLOW  = 0x40;
const FLAG_SIGN      = 0x80;

const BASE_STACK     = 0x100;

function saveaccum($n){
	$GLOBALS['a'] = $n & 0x00FF;
}


//flag modifier macros
function setcarry(){
	$GLOBALS['status'] |= FLAG_CARRY;
}

function clearcarry(){
	$GLOBALS['status'] &= (~FLAG_CARRY);
}

function setzero(){
	$GLOBALS['status'] |= FLAG_ZERO;
}

function clearzero(){
	$GLOBALS['status'] &= (~FLAG_ZERO);
}

function setinterrupt(){
	$GLOBALS['status'] |= FLAG_INTERRUPT;
}

function clearinterrupt(){
	$GLOBALS['status'] &= (~FLAG_INTERRUPT);
}

function setdecimal(){
	$GLOBALS['status'] |= FLAG_DECIMAL;
}

function cleardecimal(){
	$GLOBALS['status'] &= (~FLAG_DECIMAL);
}

function setoverflow(){
	$GLOBALS['status'] |= FLAG_OVERFLOW;
}

function clearoverflow(){
	$GLOBALS['status'] &= (~FLAG_OVERFLOW);
}

function setsign(){
	$GLOBALS['status'] |= FLAG_SIGN;
}

function clearsign(){
	$GLOBALS['status'] &= (~FLAG_SIGN);
}


//flag calculation macros
function zerocalc($n) {
    if ($n & 0x00FF){
	    clearzero();
	} else {
		setzero();
	}
}

function signcalc($n) {
    if ($n & 0x0080){
	    setsign();
	} else {
		clearsign();
	}
}

function carrycalc($n) {
    if ($n & 0xFF00){
	    setcarry();
	} else {
		clearcarry();
	}
}

function overflowcalc($n, $m, $o) { /* n = result, m = accumulator, o = memory */
    if (($n ^ ($m & 0xFFFF)) & ($n ^ $o) & 0x0080){
	    setoverflow();
	} else {
		clearoverflow();
	}
}


//6502 CPU registers
$GLOBALS['pc'] = 0;
$GLOBALS['sp'] = 0;
$GLOBALS['a'] = 0;
$GLOBALS['x'] = 0;
$GLOBALS['y'] = 0;
$GLOBALS['status'] = FLAG_CONSTANT;


//helper variables
$GLOBALS['instructions'] = 0; //keep track of total instructions executed
$GLOBALS['clockticks6502'] = 0;
$GLOBALS['clockgoal6502'] = 0;
$GLOBALS['oldpc'] = 0;
$GLOBALS['ea'] = 0;
$GLOBALS['reladdr'] = 0;
$GLOBALS['value'] = 0;
$GLOBALS['result'] = 0;
$GLOBALS['opcode'] = 0;
$GLOBALS['oldstatus'] = 0;

//a few general functions used by various other functions
function push16($pushval) {
    write6502(BASE_STACK + $GLOBALS['sp'], ($pushval >> 8) & 0xFF);
    write6502(BASE_STACK + (($GLOBALS['sp'] - 1) & 0xFF), $pushval & 0xFF);
    $GLOBALS['sp'] -= 2;
}

function push8($pushval) {
    write6502(BASE_STACK + $GLOBALS['sp']--, $pushval);
}

function pull16() {
    $temp16;
    $temp16 = read6502(BASE_STACK + (($GLOBALS['sp'] + 1) & 0xFF)) | (read6502(BASE_STACK + (($GLOBALS['sp'] + 2) & 0xFF)) << 8);
    $GLOBALS['sp'] += 2;
    return $temp16;
}

function pull8() {
    return (read6502(BASE_STACK + ++$GLOBALS['sp']));
}

function reset6502() {
    $GLOBALS['pc'] = read6502(0xFFFC) | (read6502(0xFFFD) << 8);
    $GLOBALS['a'] = 0;
    $GLOBALS['x'] = 0;
    $GLOBALS['y'] = 0;
    $GLOBALS['sp'] = 0xFD;
    $GLOBALS['status'] |= FLAG_CONSTANT;
}

$GLOBALS['penaltyop'] = 0;
$GLOBALS['penaltyaddr'] = 0;

//addressing mode functions, calculates effective addresses
function imp() { //implied
}

function acc() { //accumulator
}

function imm() { //immediate
    $GLOBALS['ea'] = $GLOBALS['pc']++;
}

function zp() { //zero-page
    $GLOBALS['ea'] = read6502($GLOBALS['pc']++ & 0xFFFF);
}

function zpx() { //zero-page,X
    $GLOBALS['ea'] = (read6502($GLOBALS['pc']++) + $GLOBALS['x']) & 0xFF; //zero-page wraparound
}

function zpy() { //zero-page,Y
    $GLOBALS['ea'] = (read6502($GLOBALS['pc']++) + $GLOBALS['y']) & 0xFF; //zero-page wraparound
}

function rel() { //relative for branch ops (8-bit immediate value, sign-extended)
    $GLOBALS['reladdr'] = read6502($GLOBALS['pc']++) & 0xFFFF;
    if ($GLOBALS['reladdr'] & 0x80) $GLOBALS['reladdr'] |= 0xFF00;
}

function abso() { //absolute
    $GLOBALS['ea'] = read6502($GLOBALS['pc']) | (read6502($GLOBALS['pc']+1) << 8);
    $GLOBALS['pc'] += 2;
}

function absx() { //absolute,X
    $GLOBALS['ea'] = (read6502($GLOBALS['pc']) | (read6502($GLOBALS['pc']+1) << 8));
    $startpage = $GLOBALS['ea'] & 0xFF00;
    $GLOBALS['ea'] += $GLOBALS['x'];

    if ($startpage != ($GLOBALS['ea'] & 0xFF00)) { //one cycle penlty for page-crossing on some opcodes
        $GLOBALS['penaltyaddr'] = 1;
    }

    $GLOBALS['pc'] += 2;
}

function absy() { //absolute,Y
    $GLOBALS['ea'] = (read6502($GLOBALS['pc']) | (read6502($GLOBALS['pc']+1) << 8));
    $startpage = $GLOBALS['ea'] & 0xFF00;
    $GLOBALS['ea'] += $GLOBALS['y'];

    if ($startpage != ($GLOBALS['ea'] & 0xFF00)) { //one cycle penlty for page-crossing on some opcodes
        $GLOBALS['penaltyaddr'] = 1;
    }

    $GLOBALS['pc'] += 2;
}

function ind() { //indirect
    $eahelp = read6502($GLOBALS['pc']) | (read6502($GLOBALS['pc']+1) << 8);
    $eahelp2 = ($eahelp & 0xFF00) | (($eahelp + 1) & 0x00FF); //replicate 6502 page-boundary wraparound bug
    $GLOBALS['ea'] = read6502($eahelp) | (read6502($eahelp2) << 8);
    $GLOBALS['pc'] += 2;
}

function indx() { // (indirect,X)
    $eahelp = ((read6502($GLOBALS['pc']++) + $GLOBALS['x']) & 0xFF); //zero-page wraparound for table pointer
    $GLOBALS['ea'] = read6502($eahelp & 0x00FF) | (read6502(($eahelp+1) & 0x00FF) << 8);
}

function indy() { // (indirect),Y
    $eahelp = read6502($GLOBALS['pc']++);
    $eahelp2 = ($eahelp & 0xFF00) | (($eahelp + 1) & 0x00FF); //zero-page wraparound
    $GLOBALS['ea'] = read6502($eahelp) | (read6502($eahelp2) << 8);
    $startpage = $GLOBALS['ea'] & 0xFF00;
    $GLOBALS['ea'] += $GLOBALS['y'];

    if ($startpage != ($GLOBALS['ea'] & 0xFF00)) { //one cycle penlty for page-crossing on some opcodes
        $GLOBALS['penaltyaddr'] = 1;
    }
}

function getvalue() {
    if ($GLOBALS['addrtable'][$GLOBALS['opcode']] == 'acc'){
	    return $GLOBALS['a'];
	} else{
		return read6502($GLOBALS['ea']);
	}
}

function putvalue($saveval) {
    if ($GLOBALS['addrtable'][$GLOBALS['opcode']] == 'acc'){
	    $GLOBALS['a'] = ($saveval & 0x00FF);
	} else{
		write6502($GLOBALS['ea'], ($saveval & 0x00FF));
	}
}


//instruction handler functions
function adc() {
    $GLOBALS['penaltyop'] = 1;
    $GLOBALS['value'] = getvalue();
    $GLOBALS['result'] = $GLOBALS['a'] + $GLOBALS['value'] + ($GLOBALS['status'] & FLAG_CARRY);

    carrycalc($GLOBALS['result']);
    zerocalc($GLOBALS['result']);
    overflowcalc($GLOBALS['result'], $GLOBALS['a'], $GLOBALS['value']);
    signcalc($GLOBALS['result']);

    if ($GLOBALS['status'] & FLAG_DECIMAL) {
        clearcarry();

        if (($GLOBALS['a'] & 0x0F) > 0x09) {
            $GLOBALS['a'] += 0x06;
        }
        if (($GLOBALS['a'] & 0xF0) > 0x90) {
            $GLOBALS['a'] += 0x60;
            setcarry();
        }

        $GLOBALS['clockticks6502']++;
    }

    saveaccum($GLOBALS['result']);
}

function aand() {
    $GLOBALS['penaltyop'] = 1;
    $GLOBALS['value'] = getvalue();
    $GLOBALS['result'] = $GLOBALS['a'] & $GLOBALS['value'];

    zerocalc($GLOBALS['result']);
    signcalc($GLOBALS['result']);

    saveaccum($GLOBALS['result']);
}

function asl() {
    $GLOBALS['value'] = getvalue();
    $GLOBALS['result'] = $GLOBALS['value'] << 1;

    carrycalc($GLOBALS['result']);
    zerocalc($GLOBALS['result']);
    signcalc($GLOBALS['result']);

    putvalue($GLOBALS['result']);
}

function bcc() {
    if (($GLOBALS['status'] & FLAG_CARRY) == 0) {
        $GLOBALS['oldpc'] = $GLOBALS['pc'];
        $GLOBALS['pc'] += $GLOBALS['reladdr'];
        if (($GLOBALS['oldpc'] & 0xFF00) != ($GLOBALS['pc'] & 0xFF00)){
	        $GLOBALS['clockticks6502'] += 2; //check if jump crossed a page boundary
	    } else {
		    $GLOBALS['clockticks6502']++;
		}
    }
}

function bcs() {
    if (($GLOBALS['status'] & FLAG_CARRY) == FLAG_CARRY) {
        $GLOBALS['oldpc'] = $GLOBALS['pc'];
        $GLOBALS['pc'] += $GLOBALS['reladdr'];
        if (($GLOBALS['oldpc'] & 0xFF00) != ($GLOBALS['pc'] & 0xFF00)){
	        $GLOBALS['clockticks6502'] += 2; //check if jump crossed a page boundary
	    } else {
		    $GLOBALS['clockticks6502']++;
		}
    }
}

function beq() {
    if (($GLOBALS['status'] & FLAG_ZERO) == FLAG_ZERO) {
        $GLOBALS['oldpc'] = $GLOBALS['pc'];
        $GLOBALS['pc'] += $GLOBALS['reladdr'];
        if (($GLOBALS['oldpc'] & 0xFF00) != ($GLOBALS['pc'] & 0xFF00)){
	        $GLOBALS['clockticks6502'] += 2; //check if jump crossed a page boundary
	    } else {
		    $GLOBALS['clockticks6502']++;
		}
    }
}

function bit() {
    $GLOBALS['value'] = getvalue();
    $GLOBALS['result'] = $GLOBALS['a'] & $GLOBALS['value'];

    zerocalc($GLOBALS['result']);
    $GLOBALS['status'] = ($GLOBALS['status'] & 0x3F) | ($GLOBALS['value'] & 0xC0);
}

function bmi() {
    if (($GLOBALS['status'] & FLAG_SIGN) == FLAG_SIGN) {
        $GLOBALS['oldpc'] = $GLOBALS['pc'];
        $GLOBALS['pc'] += $GLOBALS['reladdr'];
        if (($GLOBALS['oldpc'] & 0xFF00) != ($GLOBALS['pc'] & 0xFF00)){
	        $GLOBALS['clockticks6502'] += 2; //check if jump crossed a page boundary
	    } else {
		    $GLOBALS['clockticks6502']++;
		}
    }
}

function bne() {
    if (($GLOBALS['status'] & FLAG_ZERO) == 0) {
        $GLOBALS['oldpc'] = $GLOBALS['pc'];
        $GLOBALS['pc'] += $GLOBALS['reladdr'];
        if (($GLOBALS['oldpc'] & 0xFF00) != ($GLOBALS['pc'] & 0xFF00)){
	        $GLOBALS['clockticks6502'] += 2; //check if jump crossed a page boundary
	    } else {
		    $GLOBALS['clockticks6502']++;
		}
    }
}

function bpl() {
    if (($GLOBALS['status'] & FLAG_SIGN) == 0) {
        $GLOBALS['oldpc'] = $GLOBALS['pc'];
        $GLOBALS['pc'] += $GLOBALS['reladdr'];
        if (($GLOBALS['oldpc'] & 0xFF00) != ($GLOBALS['pc'] & 0xFF00)){
	        $GLOBALS['clockticks6502'] += 2; //check if jump crossed a page boundary
	    } else {
		    $GLOBALS['clockticks6502']++;
		}
    }
}

function brk() {
    $GLOBALS['pc']++;
    push16($GLOBALS['pc']); //push next instruction address onto stack
    push8($GLOBALS['status'] | FLAG_BREAK); //push CPU status to stack
    setinterrupt(); //set interrupt flag
    $GLOBALS['pc'] = read6502(0xFFFE) | (read6502(0xFFFF) << 8);
}

function bvc() {
    if (($GLOBALS['status'] & FLAG_OVERFLOW) == 0) {
        $GLOBALS['oldpc'] = $GLOBALS['pc'];
        $GLOBALS['pc'] += $GLOBALS['reladdr'];
        if (($GLOBALS['oldpc'] & 0xFF00) != ($GLOBALS['pc'] & 0xFF00)){
	        $GLOBALS['clockticks6502'] += 2; //check if jump crossed a page boundary
	    } else {
		    $GLOBALS['clockticks6502']++;
		}
    }
}

function bvs() {
    if (($GLOBALS['status'] & FLAG_OVERFLOW) == FLAG_OVERFLOW) {
        $GLOBALS['oldpc'] = $GLOBALS['pc'];
        $GLOBALS['pc'] += $GLOBALS['reladdr'];
        if (($GLOBALS['oldpc'] & 0xFF00) != ($GLOBALS['pc'] & 0xFF00)){
	        $GLOBALS['clockticks6502'] += 2; //check if jump crossed a page boundary
	    } else {
		    $GLOBALS['clockticks6502']++;
		}
    }
}

function clc() {
    clearcarry();
}

function cld() {
    cleardecimal();
}

function cli() {
    clearinterrupt();
}

function clv() {
    clearoverflow();
}

function cmp() {
    $GLOBALS['penaltyop'] = 1;
    $GLOBALS['value'] = getvalue();
    $GLOBALS['result'] = $GLOBALS['a'] - $GLOBALS['value'];

    if ($GLOBALS['a'] >= ($GLOBALS['value'] & 0x00FF)){
	    setcarry();
	} else {
		clearcarry();
	}
    if ($GLOBALS['a'] == ($GLOBALS['value'] & 0x00FF)){
	    setzero();
	} else {
		clearzero();
	}
    signcalc($GLOBALS['result']);
}

function cpx() {
    $GLOBALS['value'] = getvalue();
    $GLOBALS['result'] = $GLOBALS['x'] - $GLOBALS['value'];

    if ($GLOBALS['x'] >= ($GLOBALS['value'] & 0x00FF)){
	    setcarry();
	} else {
		clearcarry();
	}
    if ($GLOBALS['x'] == ($GLOBALS['value'] & 0x00FF)){
	    setzero();
	} else {
		clearzero();
	}
    signcalc($GLOBALS['result']);
}

function cpy() {
    $GLOBALS['value'] = getvalue();
    $GLOBALS['result'] = $GLOBALS['y'] - $GLOBALS['value'];

    if ($GLOBALS['y'] >= ($GLOBALS['value'] & 0x00FF)){
	    setcarry();
	} else {
		clearcarry();
	}
    if ($GLOBALS['y'] == ($GLOBALS['value'] & 0x00FF)){
	    setzero();
	} else {
		clearzero();
	}
    signcalc($GLOBALS['result']);
}

function dec() {
    $GLOBALS['value'] = getvalue();
    $GLOBALS['result'] = $GLOBALS['value'] - 1;

    zerocalc($GLOBALS['result']);
    signcalc($GLOBALS['result']);

    putvalue($GLOBALS['result']);
}

function dex() {
    $GLOBALS['x']--;

    zerocalc($GLOBALS['x']);
    signcalc($GLOBALS['x']);
}

function dey() {
    $GLOBALS['y']--;

    zerocalc($GLOBALS['y']);
    signcalc($GLOBALS['y']);
}

function eor() {
    $GLOBALS['penaltyop'] = 1;
    $GLOBALS['value'] = getvalue();
    $GLOBALS['result'] = $GLOBALS['a'] ^ $GLOBALS['value'];

    zerocalc($GLOBALS['result']);
    signcalc($GLOBALS['result']);

    saveaccum($GLOBALS['result']);
}

function inc() {
    $GLOBALS['value'] = getvalue();
    $GLOBALS['result'] = $GLOBALS['value'] + 1;

    zerocalc($GLOBALS['result']);
    signcalc($GLOBALS['result']);

    putvalue($GLOBALS['result']);
}

function inx() {
    $GLOBALS['x']++;

    zerocalc($GLOBALS['x']);
    signcalc($GLOBALS['x']);
}

function iny() {
    $GLOBALS['y']++;

    zerocalc($GLOBALS['y']);
    signcalc($GLOBALS['y']);
}

function jmp() {
    $GLOBALS['pc'] = $GLOBALS['ea'];
}

function jsr() {
    push16($GLOBALS['pc'] - 1);
    $GLOBALS['pc'] = $GLOBALS['ea'];
}

function lda() {
    $GLOBALS['penaltyop'] = 1;
    $GLOBALS['value'] = getvalue();
    $GLOBALS['a'] = ($GLOBALS['value'] & 0x00FF);

    zerocalc($GLOBALS['a']);
    signcalc($GLOBALS['a']);
}

function ldx() {
    $GLOBALS['penaltyop'] = 1;
    $GLOBALS['value'] = getvalue();
    $GLOBALS['x'] = ($GLOBALS['value'] & 0x00FF);

    zerocalc($GLOBALS['x']);
    signcalc($GLOBALS['x']);
}

function ldy() {
    $GLOBALS['penaltyop'] = 1;
    $GLOBALS['value'] = getvalue();
    $GLOBALS['y'] = ($GLOBALS['value'] & 0x00FF);

    zerocalc($GLOBALS['y']);
    signcalc($GLOBALS['y']);
}

function lsr() {
    $GLOBALS['value'] = getvalue();
    $GLOBALS['result'] = $GLOBALS['value'] >> 1;

    if ($GLOBALS['value'] & 1){
	    setcarry();
	} else {
		clearcarry();
	}
    zerocalc($GLOBALS['result']);
    signcalc($GLOBALS['result']);

    putvalue($GLOBALS['result']);
}

function nop() {
    switch ($GLOBALS['opcode']) {
        case 0x1C:
        case 0x3C:
        case 0x5C:
        case 0x7C:
        case 0xDC:
        case 0xFC:
            $GLOBALS['penaltyop'] = 1;
            break;
    }
}

function ora() {
    $GLOBALS['penaltyop'] = 1;
    $GLOBALS['value'] = getvalue();
    $GLOBALS['result'] = $GLOBALS['a'] | $GLOBALS['value'];

    zerocalc($GLOBALS['result']);
    signcalc($GLOBALS['result']);

    saveaccum($GLOBALS['result']);
}

function pha() {
    push8($GLOBALS['a']);
}

function php() {
    push8($GLOBALS['status'] | FLAG_BREAK);
}

function pla() {
    $GLOBALS['a'] = pull8();

    zerocalc($GLOBALS['a']);
    signcalc($GLOBALS['a']);
}

function plp() {
    $GLOBALS['status'] = pull8() | FLAG_CONSTANT;
}

function rol() {
    $GLOBALS['value'] = getvalue();
    $GLOBALS['result'] = ($GLOBALS['value'] << 1) | ($GLOBALS['status'] & FLAG_CARRY);

    carrycalc($GLOBALS['result']);
    zerocalc($GLOBALS['result']);
    signcalc($GLOBALS['result']);

    putvalue($GLOBALS['result']);
}

function ror() {
    $GLOBALS['value'] = getvalue();
    $GLOBALS['result'] = ($GLOBALS['value'] >> 1) | (($GLOBALS['status'] & FLAG_CARRY) << 7);

    if ($GLOBALS['value'] & 1){
	    setcarry();
	} else {
		clearcarry();
	}
    zerocalc($GLOBALS['result']);
    signcalc($GLOBALS['result']);

    putvalue($GLOBALS['result']);
}

function rti() {
    $GLOBALS['status'] = pull8();
    $GLOBALS['value'] = pull16();
    $GLOBALS['pc'] = $GLOBALS['value'];
}

function rts() {
    $GLOBALS['value'] = pull16();
    $GLOBALS['pc'] = $GLOBALS['value'] + 1;
}

function sbc() {
    $GLOBALS['penaltyop'] = 1;
    $GLOBALS['value'] = getvalue() ^ 0x00FF;
    $GLOBALS['result'] = $GLOBALS['a'] + $GLOBALS['value'] + ($GLOBALS['status'] & FLAG_CARRY);

    carrycalc($GLOBALS['result']);
    zerocalc($GLOBALS['result']);
    overflowcalc($GLOBALS['result'], $GLOBALS['a'], $GLOBALS['value']);
    signcalc($GLOBALS['result']);

    if ($GLOBALS['status'] & FLAG_DECIMAL) {
        clearcarry();

        $GLOBALS['a'] -= 0x66;
        if (($GLOBALS['a'] & 0x0F) > 0x09) {
            $GLOBALS['a'] += 0x06;
        }
        if (($GLOBALS['a'] & 0xF0) > 0x90) {
            $GLOBALS['a'] += 0x60;
            setcarry();
        }

        $GLOBALS['clockticks6502']++;
    }

    saveaccum($GLOBALS['result']);
}

function sec() {
    setcarry();
}

function sed() {
    setdecimal();
}

function sei() {
    setinterrupt();
}

function sta() {
    putvalue($GLOBALS['a']);
}

function stx() {
    putvalue($GLOBALS['x']);
}

function sty() {
    putvalue($GLOBALS['y']);
}

function tax() {
    $GLOBALS['x'] = $GLOBALS['a'];

    zerocalc($GLOBALS['x']);
    signcalc($GLOBALS['x']);
}

function tay() {
    $GLOBALS['y'] = $GLOBALS['a'];

    zerocalc($GLOBALS['y']);
    signcalc($GLOBALS['y']);
}

function tsx() {
    $GLOBALS['x'] = $GLOBALS['sp'];

    zerocalc($GLOBALS['x']);
    signcalc($GLOBALS['x']);
}

function txa() {
    $GLOBALS['a'] = $GLOBALS['x'];

    zerocalc($GLOBALS['a']);
    signcalc($GLOBALS['a']);
}

function txs() {
    $GLOBALS['sp'] = $GLOBALS['x'];
}

function tya() {
    $GLOBALS['a'] = $GLOBALS['y'];

    zerocalc($GLOBALS['a']);
    signcalc($GLOBALS['a']);
}

function lax() {
    lda();
    ldx();
}

function sax() {
    sta();
    stx();
    putvalue($GLOBALS['a'] & $GLOBALS['x']);
    if ($GLOBALS['penaltyop'] && $GLOBALS['penaltyaddr']){
	    $GLOBALS['clockticks6502']--;
	}
}

function dcp() {
    dec();
    cmp();
    if ($GLOBALS['penaltyop'] && $GLOBALS['penaltyaddr']){
	    $GLOBALS['clockticks6502']--;
	}
}

function isb() {
    inc();
    sbc();
    if ($GLOBALS['penaltyop'] && $GLOBALS['penaltyaddr']){
	    $GLOBALS['clockticks6502']--;
	}
}

function slo() {
    asl();
    ora();
    if ($GLOBALS['penaltyop'] && $GLOBALS['penaltyaddr']){
	    $GLOBALS['clockticks6502']--;
	}
}

function rla() {
    rol();
    aand();
    if ($GLOBALS['penaltyop'] && $GLOBALS['penaltyaddr']){
	    $GLOBALS['clockticks6502']--;
	}
}

function sre() {
    lsr();
    eor();
    if ($GLOBALS['penaltyop'] && $GLOBALS['penaltyaddr']){
	    $GLOBALS['clockticks6502']--;
	}
}

function rra() {
    ror();
    adc();
    if ($GLOBALS['penaltyop'] && $GLOBALS['penaltyaddr']){
	    $GLOBALS['clockticks6502']--;
	}
}



$GLOBALS['addrtable'] = array(
/*        |  0  |  1  |  2  |  3  |  4  |  5  |  6  |  7  |  8  |  9  |  A  |  B  |  C  |  D  |  E  |  F  |     */
/* 0 */     'imp', 'indx',  'imp', 'indx',   'zp',   'zp',   'zp',   'zp',  'imp',  'imm',  'acc',  'imm', 'abso', 'abso', 'abso', 'abso',
/* 1 */     'rel', 'indy',  'imp', 'indy',  'zpx',  'zpx',  'zpx',  'zpx',  'imp', 'absy',  'imp', 'absy', 'absx', 'absx', 'absx', 'absx',
/* 2 */    'abso', 'indx',  'imp', 'indx',   'zp',   'zp',   'zp',   'zp',  'imp',  'imm',  'acc',  'imm', 'abso', 'abso', 'abso', 'abso',
/* 3 */     'rel', 'indy',  'imp', 'indy',  'zpx',  'zpx',  'zpx',  'zpx',  'imp', 'absy',  'imp', 'absy', 'absx', 'absx', 'absx', 'absx',
/* 4 */     'imp', 'indx',  'imp', 'indx',   'zp',   'zp',   'zp',   'zp',  'imp',  'imm',  'acc',  'imm', 'abso', 'abso', 'abso', 'abso',
/* 5 */     'rel', 'indy',  'imp', 'indy',  'zpx',  'zpx',  'zpx',  'zpx',  'imp', 'absy',  'imp', 'absy', 'absx', 'absx', 'absx', 'absx',
/* 6 */     'imp', 'indx',  'imp', 'indx',   'zp',   'zp',   'zp',   'zp',  'imp',  'imm',  'acc',  'imm',  'ind', 'abso', 'abso', 'abso',
/* 7 */     'rel', 'indy',  'imp', 'indy',  'zpx',  'zpx',  'zpx',  'zpx',  'imp', 'absy',  'imp', 'absy', 'absx', 'absx', 'absx', 'absx',
/* 8 */     'imm', 'indx',  'imm', 'indx',   'zp',   'zp',   'zp',   'zp',  'imp',  'imm',  'imp',  'imm', 'abso', 'abso', 'abso', 'abso',
/* 9 */     'rel', 'indy',  'imp', 'indy',  'zpx',  'zpx',  'zpy',  'zpy',  'imp', 'absy',  'imp', 'absy', 'absx', 'absx', 'absy', 'absy',
/* A */     'imm', 'indx',  'imm', 'indx',   'zp',   'zp',   'zp',   'zp',  'imp',  'imm',  'imp',  'imm', 'abso', 'abso', 'abso', 'abso',
/* B */     'rel', 'indy',  'imp', 'indy',  'zpx',  'zpx',  'zpy',  'zpy',  'imp', 'absy',  'imp', 'absy', 'absx', 'absx', 'absy', 'absy',
/* C */     'imm', 'indx',  'imm', 'indx',   'zp',   'zp',   'zp',   'zp',  'imp',  'imm',  'imp',  'imm', 'abso', 'abso', 'abso', 'abso',
/* D */     'rel', 'indy',  'imp', 'indy',  'zpx',  'zpx',  'zpx',  'zpx',  'imp', 'absy',  'imp', 'absy', 'absx', 'absx', 'absx', 'absx',
/* E */     'imm', 'indx',  'imm', 'indx',   'zp',   'zp',   'zp',   'zp',  'imp',  'imm',  'imp',  'imm', 'abso', 'abso', 'abso', 'abso',
/* F */     'rel', 'indy',  'imp', 'indy',  'zpx',  'zpx',  'zpx',  'zpx',  'imp', 'absy',  'imp', 'absy', 'absx', 'absx', 'absx', 'absx'
);

$GLOBALS['optable'] = array(
/*        |  0  |  1  |  2  |  3  |  4  |  5  |  6  |  7  |  8  |  9  |  A  |  B  |  C  |  D  |  E  |  F  |      */
/* 0 */      'brk',  'ora',  'nop',  'slo',  'nop',  'ora',  'asl',  'slo',  'php',  'ora',  'asl',  'nop',  'nop',  'ora',  'asl',  'slo',
/* 1 */      'bpl',  'ora',  'nop',  'slo',  'nop',  'ora',  'asl',  'slo',  'clc',  'ora',  'nop',  'slo',  'nop',  'ora',  'asl',  'slo',
/* 2 */      'jsr',  'aand', 'nop',  'rla',  'bit',  'aand', 'rol',  'rla',  'plp',  'aand', 'rol',  'nop',  'bit',  'aand', 'rol',  'rla',
/* 3 */      'bmi',  'aand', 'nop',  'rla',  'nop',  'aand', 'rol',  'rla',  'sec',  'aand', 'nop',  'rla',  'nop',  'aand', 'rol',  'rla',
/* 4 */      'rti',  'eor',  'nop',  'sre',  'nop',  'eor',  'lsr',  'sre',  'pha',  'eor',  'lsr',  'nop',  'jmp',  'eor',  'lsr',  'sre',
/* 5 */      'bvc',  'eor',  'nop',  'sre',  'nop',  'eor',  'lsr',  'sre',  'cli',  'eor',  'nop',  'sre',  'nop',  'eor',  'lsr',  'sre',
/* 6 */      'rts',  'adc',  'nop',  'rra',  'nop',  'adc',  'ror',  'rra',  'pla',  'adc',  'ror',  'nop',  'jmp',  'adc',  'ror',  'rra',
/* 7 */      'bvs',  'adc',  'nop',  'rra',  'nop',  'adc',  'ror',  'rra',  'sei',  'adc',  'nop',  'rra',  'nop',  'adc',  'ror',  'rra',
/* 8 */      'nop',  'sta',  'nop',  'sax',  'sty',  'sta',  'stx',  'sax',  'dey',  'nop',  'txa',  'nop',  'sty',  'sta',  'stx',  'sax',
/* 9 */      'bcc',  'sta',  'nop',  'nop',  'sty',  'sta',  'stx',  'sax',  'tya',  'sta',  'txs',  'nop',  'nop',  'sta',  'nop',  'nop',
/* A */      'ldy',  'lda',  'ldx',  'lax',  'ldy',  'lda',  'ldx',  'lax',  'tay',  'lda',  'tax',  'nop',  'ldy',  'lda',  'ldx',  'lax',
/* B */      'bcs',  'lda',  'nop',  'lax',  'ldy',  'lda',  'ldx',  'lax',  'clv',  'lda',  'tsx',  'lax',  'ldy',  'lda',  'ldx',  'lax',
/* C */      'cpy',  'cmp',  'nop',  'dcp',  'cpy',  'cmp',  'dec',  'dcp',  'iny',  'cmp',  'dex',  'nop',  'cpy',  'cmp',  'dec',  'dcp',
/* D */      'bne',  'cmp',  'nop',  'dcp',  'nop',  'cmp',  'dec',  'dcp',  'cld',  'cmp',  'nop',  'dcp',  'nop',  'cmp',  'dec',  'dcp',
/* E */      'cpx',  'sbc',  'nop',  'isb',  'cpx',  'sbc',  'inc',  'isb',  'inx',  'sbc',  'nop',  'sbc',  'cpx',  'sbc',  'inc',  'isb',
/* F */      'beq',  'sbc',  'nop',  'isb',  'nop',  'sbc',  'inc',  'isb',  'sed',  'sbc',  'nop',  'isb',  'nop',  'sbc',  'inc',  'isb'
);

$GLOBALS['ticktable'] = array(
/*        |  0  |  1  |  2  |  3  |  4  |  5  |  6  |  7  |  8  |  9  |  A  |  B  |  C  |  D  |  E  |  F  |     */
/* 0 */      7,    6,    2,    8,    3,    3,    5,    5,    3,    2,    2,    2,    4,    4,    6,    6,  /* 0 */
/* 1 */      2,    5,    2,    8,    4,    4,    6,    6,    2,    4,    2,    7,    4,    4,    7,    7,  /* 1 */
/* 2 */      6,    6,    2,    8,    3,    3,    5,    5,    4,    2,    2,    2,    4,    4,    6,    6,  /* 2 */
/* 3 */      2,    5,    2,    8,    4,    4,    6,    6,    2,    4,    2,    7,    4,    4,    7,    7,  /* 3 */
/* 4 */      6,    6,    2,    8,    3,    3,    5,    5,    3,    2,    2,    2,    3,    4,    6,    6,  /* 4 */
/* 5 */      2,    5,    2,    8,    4,    4,    6,    6,    2,    4,    2,    7,    4,    4,    7,    7,  /* 5 */
/* 6 */      6,    6,    2,    8,    3,    3,    5,    5,    4,    2,    2,    2,    5,    4,    6,    6,  /* 6 */
/* 7 */      2,    5,    2,    8,    4,    4,    6,    6,    2,    4,    2,    7,    4,    4,    7,    7,  /* 7 */
/* 8 */      2,    6,    2,    6,    3,    3,    3,    3,    2,    2,    2,    2,    4,    4,    4,    4,  /* 8 */
/* 9 */      2,    6,    2,    6,    4,    4,    4,    4,    2,    5,    2,    5,    5,    5,    5,    5,  /* 9 */
/* A */      2,    6,    2,    6,    3,    3,    3,    3,    2,    2,    2,    2,    4,    4,    4,    4,  /* A */
/* B */      2,    5,    2,    5,    4,    4,    4,    4,    2,    4,    2,    4,    4,    4,    4,    4,  /* B */
/* C */      2,    6,    2,    8,    3,    3,    5,    5,    2,    2,    2,    2,    4,    4,    6,    6,  /* C */
/* D */      2,    5,    2,    8,    4,    4,    6,    6,    2,    4,    2,    7,    4,    4,    7,    7,  /* D */
/* E */      2,    6,    2,    8,    3,    3,    5,    5,    2,    2,    2,    2,    4,    4,    6,    6,  /* E */
/* F */      2,    5,    2,    8,    4,    4,    6,    6,    2,    4,    2,    7,    4,    4,    7,    7   /* F */
);


function nmi6502() {
    push16($GLOBALS['pc']);
    push8($GLOBALS['status']);
    $GLOBALS['status'] |= FLAG_INTERRUPT;
    $GLOBALS['pc'] = read6502(0xFFFA) | (read6502(0xFFFB) << 8);
}

function irq6502() {
    push16($GLOBALS['pc']);
    push8($GLOBALS['status']);
    $GLOBALS['status'] |= FLAG_INTERRUPT;
    $GLOBALS['pc'] = read6502(0xFFFE) | (read6502(0xFFFF) << 8);
}

$GLOBALS['callexternal'] = 0;

function exec6502($tickcount) {
    $GLOBALS['clockgoal6502'] += $tickcount;

    while ($GLOBALS['clockticks6502'] < $GLOBALS['clockgoal6502']) {
        $GLOBALS['opcode'] = read6502($GLOBALS['pc']++);

        $GLOBALS['penaltyop'] = 0;
        $GLOBALS['penaltyaddr'] = 0;

        call_user_func($GLOBALS['addrtable'][$GLOBALS['opcode']]);
        call_user_func($GLOBALS['optable'][$GLOBALS['opcode']]);
        $GLOBALS['clockticks6502'] += $GLOBALS['ticktable'][$GLOBALS['opcode']];
        if ($GLOBALS['penaltyop'] && $GLOBALS['penaltyaddr']){
	        $GLOBALS['clockticks6502']++;
	    }

        $GLOBALS['instructions']++;
    }

}

function step6502() {
    $GLOBALS['opcode'] = read6502($GLOBALS['pc']++);

    $GLOBALS['penaltyop'] = 0;
    $GLOBALS['penaltyaddr'] = 0;

    call_user_func($GLOBALS['addrtable'][$GLOBALS['opcode']]);
    call_user_func($GLOBALS['optable'][$GLOBALS['opcode']]);
    $GLOBALS['clockticks6502'] += $GLOBALS['ticktable'][$GLOBALS['opcode']];
    if ($GLOBALS['penaltyop'] && $GLOBALS['penaltyaddr']){
	    $GLOBALS['clockticks6502']++;
	}
    $GLOBALS['clockgoal6502'] = $GLOBALS['clockticks6502'];

    $GLOBALS['instructions']++;
    
    $GLOBALS['pc'] = $GLOBALS['pc'] % (SIZE ^ 2);
}
?>