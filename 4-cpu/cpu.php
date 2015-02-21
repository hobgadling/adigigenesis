<?php
//externally supplied functions
function read6502($address){
	$readaddr = $address % (SIZE ^ 2);
	$i = floor($readaddr/SIZE);
	$j = $readaddr%SIZE;
	return $grid[$i][$j] & 0xFF;
}

function write6502($address, $value){
	$readaddr = $address % (SIZE ^ 2);
	$i = floor($readaddr/SIZE);
	$j = $readaddr%SIZE;
	$grid[$i][$j] = $value;
}

//6502 defines
const FLAG_CARRY     0x01
const FLAG_ZERO      0x02
const FLAG_INTERRUPT 0x04
const FLAG_DECIMAL   0x08
const FLAG_BREAK     0x10
const FLAG_CONSTANT  0x20
const FLAG_OVERFLOW  0x40
const FLAG_SIGN      0x80

const BASE_STACK     0x100

function saveaccum(n){
	$a = (char)($n & 0x00FF)
}


//flag modifier macros
function setcarry(){
	$status |= FLAG_CARRY;
}

function clearcarry(){
	$status &= (~FLAG_CARRY);
}

function setzero(){
	$status |= FLAG_ZERO;
}

function clearzero(){
	$status &= (~FLAG_ZERO);
}

function setinterrupt(){
	$status |= FLAG_INTERRUPT;
}

function clearinterrupt(){
	$status &= (~FLAG_INTERRUPT);
}

function setdecimal(){
	$status |= FLAG_DECIMAL;
}

function cleardecimal(){
	$status &= (~FLAG_DECIMAL);
}

function setoverflow(){
	$status |= FLAG_OVERFLOW;
}

function clearoverflow(){
	$status &= (~FLAG_OVERFLOW);
}

function setsign(){
	$status |= FLAG_SIGN;
}

function clearsign(){
	$status &= (~FLAG_SIGN);
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
var $pc, $sp, $a, $x, $y, $status = FLAG_CONSTANT;


//helper variables
var $instructions = 0; //keep track of total instructions executed
var $clockticks6502 = 0, $clockgoal6502 = 0;
var $oldpc, $ea, $reladdr, $value, $result;
var $opcode, $oldstatus;

//a few general functions used by various other functions
function push16($pushval) {
    write6502(BASE_STACK + $sp, ($pushval >> 8) & 0xFF);
    write6502(BASE_STACK + (($sp - 1) & 0xFF), $pushval & 0xFF);
    $sp -= 2;
}

function push8($pushval) {
    write6502(BASE_STACK + $sp--, $pushval);
}

function pull16() {
    $temp16;
    $temp16 = read6502(BASE_STACK + (($sp + 1) & 0xFF)) | (read6502(BASE_STACK + (($sp + 2) & 0xFF)) << 8);
    $sp += 2;
    return $temp16;
}

function pull8() {
    return (read6502(BASE_STACK + ++$sp));
}

function reset6502() {
    $pc = read6502(0xFFFC) | (read6502(0xFFFD) << 8);
    $a = 0;
    $x = 0;
    $y = 0;
    $sp = 0xFD;
    $status |= FLAG_CONSTANT;
}

var $penaltyop, $penaltyaddr;

//addressing mode functions, calculates effective addresses
function imp() { //implied
}

function acc() { //accumulator
}

function imm() { //immediate
    $ea = $pc++;
}

function zp() { //zero-page
    $ea = read6502($pc++ & 0xFFFF);
}

function zpx() { //zero-page,X
    $ea = (read6502($pc++) + $x) & 0xFF; //zero-page wraparound
}

function zpy() { //zero-page,Y
    $ea = (read6502($pc++) + $y) & 0xFF; //zero-page wraparound
}

function rel() { //relative for branch ops (8-bit immediate value, sign-extended)
    $reladdr = read6502($pc++) & 0xFFFF;
    if ($reladdr & 0x80) $reladdr |= 0xFF00;
}

static void abso() { //absolute
    $ea = read6502($pc) | (read6502($pc+1) << 8);
    $pc += 2;
}

static void absx() { //absolute,X
    $ea = (read6502($pc) | (read6502($pc+1) << 8));
    $startpage = $ea & 0xFF00;
    $ea += $x;

    if ($startpage != ($ea & 0xFF00)) { //one cycle penlty for page-crossing on some opcodes
        $penaltyaddr = 1;
    }

    $pc += 2;
}

function absy() { //absolute,Y
    $ea = (read6502($pc) | (read6502($pc+1) << 8));
    $startpage = $ea & 0xFF00;
    $ea += $y;

    if ($startpage != ($ea & 0xFF00)) { //one cycle penlty for page-crossing on some opcodes
        $penaltyaddr = 1;
    }

    $pc += 2;
}

function ind() { //indirect
    $eahelp = read6502($pc) | (read6502($pc+1) << 8);
    $eahelp2 = ($eahelp & 0xFF00) | (($eahelp + 1) & 0x00FF); //replicate 6502 page-boundary wraparound bug
    $ea = read6502($eahelp) | (read6502($eahelp2) << 8);
    $pc += 2;
}

function indx() { // (indirect,X)
    $eahelp = ((read6502($pc++) + $x) & 0xFF); //zero-page wraparound for table pointer
    $ea = read6502($eahelp & 0x00FF) | (read6502(($eahelp+1) & 0x00FF) << 8);
}

function indy() { // (indirect),Y
    $eahelp = read6502($pc++);
    $eahelp2 = ($eahelp & 0xFF00) | (($eahelp + 1) & 0x00FF); //zero-page wraparound
    $ea = read6502($eahelp) | (read6502($eahelp2) << 8);
    $startpage = $ea & 0xFF00;
    $ea += $y;

    if ($startpage != ($ea & 0xFF00)) { //one cycle penlty for page-crossing on some opcodes
        $penaltyaddr = 1;
    }
}

function getvalue() {
    if ($addrtable[$opcode] == 'acc'){
	    return $a;
	} else{
		return read6502($ea);
	}
}

function putvalue($saveval) {
    if ($addrtable[$opcode] == 'acc'){
	    $a = ($saveval & 0x00FF);
	} else{
		write6502($ea, ($saveval & 0x00FF));
	}
}


//instruction handler functions
function adc() {
    $penaltyop = 1;
    $value = getvalue();
    $result = $a + $value + ($status & FLAG_CARRY);

    carrycalc($result);
    zerocalc($result);
    overflowcalc($result, $a, $value);
    signcalc($result);

    if ($status & FLAG_DECIMAL) {
        clearcarry();

        if (($a & 0x0F) > 0x09) {
            $a += 0x06;
        }
        if (($a & 0xF0) > 0x90) {
            $a += 0x60;
            setcarry();
        }

        $clockticks6502++;
    }

    saveaccum($result);
}

function and() {
    $penaltyop = 1;
    $value = getvalue();
    $result = $a & $value;

    zerocalc($result);
    signcalc($result);

    saveaccum($result);
}

function asl() {
    $value = getvalue();
    $result = $value << 1;

    carrycalc($result);
    zerocalc($result);
    signcalc($result);

    putvalue($result);
}

function bcc() {
    if (($status & FLAG_CARRY) == 0) {
        $oldpc = $pc;
        $pc += $reladdr;
        if (($oldpc & 0xFF00) != ($pc & 0xFF00)){
	        $clockticks6502 += 2; //check if jump crossed a page boundary
	    } else {
		    $clockticks6502++;
		}
    }
}

function bcs() {
    if (($status & FLAG_CARRY) == FLAG_CARRY) {
        $oldpc = $pc;
        $pc += $reladdr;
        if (($oldpc & 0xFF00) != ($pc & 0xFF00)){
	        $clockticks6502 += 2; //check if jump crossed a page boundary
	    } else {
		    $clockticks6502++;
		}
    }
}

function beq() {
    if (($status & FLAG_ZERO) == FLAG_ZERO) {
        $oldpc = $pc;
        $pc += $reladdr;
        if (($oldpc & 0xFF00) != ($pc & 0xFF00)){
	        $clockticks6502 += 2; //check if jump crossed a page boundary
	    } else {
		    $clockticks6502++;
		}
    }
}

function bit() {
    $value = getvalue();
    $result = $a & $value;

    zerocalc($result);
    $status = ($status & 0x3F) | ($value & 0xC0);
}

function bmi() {
    if (($status & FLAG_SIGN) == FLAG_SIGN) {
        $oldpc = $pc;
        $pc += $reladdr;
        if (($oldpc & 0xFF00) != ($pc & 0xFF00)){
	        $clockticks6502 += 2; //check if jump crossed a page boundary
	    } else {
		    $clockticks6502++;
		}
    }
}

function bne() {
    if (($status & FLAG_ZERO) == 0) {
        $oldpc = $pc;
        $pc += $reladdr;
        if (($oldpc & 0xFF00) != ($pc & 0xFF00)){
	        $clockticks6502 += 2; //check if jump crossed a page boundary
	    } else {
		    $clockticks6502++;
		}
    }
}

function bpl() {
    if (($status & FLAG_SIGN) == 0) {
        $oldpc = $pc;
        $pc += $reladdr;
        if (($oldpc & 0xFF00) != ($pc & 0xFF00)){
	        $clockticks6502 += 2; //check if jump crossed a page boundary
	    } else {
		    $clockticks6502++;
		}
    }
}

function brk() {
    $pc++;
    push16($pc); //push next instruction address onto stack
    push8($status | FLAG_BREAK); //push CPU status to stack
    setinterrupt(); //set interrupt flag
    $pc = read6502(0xFFFE) | (read6502(0xFFFF) << 8);
}

function bvc() {
    if (($status & FLAG_OVERFLOW) == 0) {
        $oldpc = $pc;
        $pc += $reladdr;
        if (($oldpc & 0xFF00) != ($pc & 0xFF00)){
	        clockticks6502 += 2; //check if jump crossed a page boundary
	    } else {
		    clockticks6502++;
		}
    }
}

function bvs() {
    if (($status & FLAG_OVERFLOW) == FLAG_OVERFLOW) {
        $oldpc = $pc;
        $pc += $reladdr;
        if (($oldpc & 0xFF00) != ($pc & 0xFF00)){
	        $clockticks6502 += 2; //check if jump crossed a page boundary
	    } else {
		    $clockticks6502++;
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
    $penaltyop = 1;
    $value = getvalue();
    $result = $a - $value;

    if ($a >= ($value & 0x00FF)){
	    setcarry();
	} else {
		clearcarry();
	}
    if ($a == ($value & 0x00FF)){
	    setzero();
	} else {
		clearzero();
	}
    signcalc($result);
}

function cpx() {
    $value = getvalue();
    $result = $x - $value;

    if ($x >= ($value & 0x00FF)){
	    setcarry();
	} else {
		clearcarry();
	}
    if ($x == ($value & 0x00FF)){
	    setzero();
	} else {
		clearzero();
	}
    signcalc($result);
}

function cpy() {
    $value = getvalue();
    $result = $y - $value;

    if ($y >= ($value & 0x00FF)){
	    setcarry();
	} else {
		clearcarry();
	}
    if ($y == ($value & 0x00FF)){
	    setzero();
	} else {
		clearzero();
	}
    signcalc($result);
}

function dec() {
    $value = getvalue();
    $result = $value - 1;

    zerocalc($result);
    signcalc($result);

    putvalue($result);
}

function dex() {
    $x--;

    zerocalc($x);
    signcalc($x);
}

function dey() {
    $y--;

    zerocalc($y);
    signcalc($y);
}

function eor() {
    $penaltyop = 1;
    $value = getvalue();
    $result = $a ^ $value;

    zerocalc($result);
    signcalc($result);

    saveaccum($result);
}

function inc() {
    $value = getvalue();
    $result = $value + 1;

    zerocalc($result);
    signcalc($result);

    putvalue($result);
}

function inx() {
    $x++;

    zerocalc($x);
    signcalc($x);
}

function iny() {
    $y++;

    zerocalc($y);
    signcalc($y);
}

function jmp() {
    $pc = $ea;
}

function jsr() {
    push16($pc - 1);
    $pc = $ea;
}

function lda() {
    $penaltyop = 1;
    $value = getvalue();
    $a = ($value & 0x00FF);

    zerocalc($a);
    signcalc($a);
}

function ldx() {
    $penaltyop = 1;
    $value = getvalue();
    $x = ($value & 0x00FF);

    zerocalc($x);
    signcalc($x);
}

function ldy() {
    $penaltyop = 1;
    $value = getvalue();
    $y = ($value & 0x00FF);

    zerocalc($y);
    signcalc($y);
}

function lsr() {
    $value = getvalue();
    $result = $value >> 1;

    if ($value & 1){
	    setcarry();
	} else {
		clearcarry();
	}
    zerocalc($result);
    signcalc($result);

    putvalue($result);
}

function nop() {
    switch ($opcode) {
        case 0x1C:
        case 0x3C:
        case 0x5C:
        case 0x7C:
        case 0xDC:
        case 0xFC:
            $penaltyop = 1;
            break;
    }
}

function ora() {
    $penaltyop = 1;
    $value = getvalue();
    $result = $a | $value;

    zerocalc($result);
    signcalc($result);

    saveaccum($result);
}

function pha() {
    push8($a);
}

function php() {
    push8($status | FLAG_BREAK);
}

function pla() {
    $a = pull8();

    zerocalc($a);
    signcalc($a);
}

function plp() {
    $status = pull8() | FLAG_CONSTANT;
}

function rol() {
    $value = getvalue();
    $result = ($value << 1) | ($status & FLAG_CARRY);

    carrycalc($result);
    zerocalc($result);
    signcalc($result);

    putvalue($result);
}

function ror() {
    $value = getvalue();
    $result = ($value >> 1) | (($status & FLAG_CARRY) << 7);

    if ($value & 1){
	    setcarry();
	} else {
		clearcarry();
	}
    zerocalc($result);
    signcalc($result);

    putvalue($result);
}

function rti() {
    $status = pull8();
    $value = pull16();
    $pc = $value;
}

function rts() {
    $value = pull16();
    $pc = $value + 1;
}

function sbc() {
    $penaltyop = 1;
    $value = getvalue() ^ 0x00FF;
    $result = $a + $value + ($status & FLAG_CARRY);

    carrycalc($result);
    zerocalc($result);
    overflowcalc($result, $a, $value);
    signcalc($result);

    if ($status & FLAG_DECIMAL) {
        clearcarry();

        $a -= 0x66;
        if (($a & 0x0F) > 0x09) {
            $a += 0x06;
        }
        if (($a & 0xF0) > 0x90) {
            $a += 0x60;
            setcarry();
        }

        $clockticks6502++;
    }

    saveaccum($result);
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
    putvalue(a);
}

function stx() {
    putvalue(x);
}

function sty() {
    putvalue(y);
}

function tax() {
    $x = $a;

    zerocalc($x);
    signcalc($x);
}

function tay() {
    $y = $a;

    zerocalc($y);
    signcalc($y);
}

function tsx() {
    $x = $sp;

    zerocalc($x);
    signcalc($x);
}

function txa() {
    $a = $x;

    zerocalc($a);
    signcalc($a);
}

function txs() {
    $sp = $x;
}

function tya() {
    $a = $y;

    zerocalc($a);
    signcalc($a);
}

function lax() {
    lda();
    ldx();
}

function sax() {
    sta();
    stx();
    putvalue($a & $x);
    if ($penaltyop && $penaltyaddr){
	    $clockticks6502--;
	}
}

function dcp() {
    dec();
    cmp();
    if ($penaltyop && $penaltyaddr){
	    $clockticks6502--;
	}
}

function isb() {
    inc();
    sbc();
    if ($penaltyop && $penaltyaddr){
	    $clockticks6502--;
	}
}

function slo() {
    asl();
    ora();
    if ($penaltyop && $penaltyaddr){
	    $clockticks6502--;
	}
}

function rla() {
    rol();
    and();
    if ($penaltyop && $penaltyaddr){
	    $clockticks6502--;
	}
}

function sre() {
    lsr();
    eor();
    if ($penaltyop && $penaltyaddr){
	    $clockticks6502--;
	}
}

function rra() {
    ror();
    adc();
    if ($penaltyop && $penaltyaddr){
	    $clockticks6502--;
	}
}



$addrtable = array()
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

$optable = array(
/*        |  0  |  1  |  2  |  3  |  4  |  5  |  6  |  7  |  8  |  9  |  A  |  B  |  C  |  D  |  E  |  F  |      */
/* 0 */      'brk',  'ora',  'nop',  'slo',  'nop',  'ora',  'asl',  'slo',  'php',  'ora',  'asl',  'nop',  'nop',  'ora',  'asl',  'slo',
/* 1 */      'bpl',  'ora',  'nop',  'slo',  'nop',  'ora',  'asl',  'slo',  'clc',  'ora',  'nop',  'slo',  'nop',  'ora',  'asl',  'slo',
/* 2 */      'jsr',  'and',  'nop',  'rla',  'bit',  'and',  'rol',  'rla',  'plp',  'and',  'rol',  'nop',  'bit',  'and',  'rol',  'rla',
/* 3 */      'bmi',  'and',  'nop',  'rla',  'nop',  'and',  'rol',  'rla',  'sec',  'and',  'nop',  'rla',  'nop',  'and',  'rol',  'rla',
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

$ticktable = array(
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
    push16($pc);
    push8($status);
    $status |= FLAG_INTERRUPT;
    $pc = read6502(0xFFFA) | (read6502(0xFFFB) << 8);
}

function irq6502() {
    push16($pc);
    push8($status);
    $status |= FLAG_INTERRUPT;
    $pc = read6502(0xFFFE) | (read6502(0xFFFF) << 8);
}

$callexternal = 0;

function exec6502($tickcount) {
    $clockgoal6502 += $tickcount;

    while ($clockticks6502 < $clockgoal6502) {
        $opcode = read6502(pc++);

        $penaltyop = 0;
        $penaltyaddr = 0;

        call_user_func($addrtable[$opcode]);
        call_user_func($optable[$opcode]);
        $clockticks6502 += $ticktable[$opcode];
        if ($penaltyop && $penaltyaddr){
	        $clockticks6502++;
	    }

        $instructions++;
    }

}

function step6502() {
    $opcode = read6502($pc++);

    $penaltyop = 0;
    $penaltyaddr = 0;

    call_user_func($addrtable[$opcode]);
    call_user_func($optable[$opcode]);
    $clockticks6502 += $ticktable[opcode];
    if ($penaltyop && $penaltyaddr){
	    $clockticks6502++;
	}
    $clockgoal6502 = $clockticks6502;

    $instructions++;
}
?>