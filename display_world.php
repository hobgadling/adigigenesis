<?
	define('REVISION', '0.0.1');
	define('SIZE', 32);
	define('DIMS', 2);
	$mysqli = new mysqli('localhost','maximus','ltiS950+','adigi');
	
	$query = "SELECT * FROM world WHERE id = " . $_GET['id'];
	$result = $mysqli->query($query);
	$world = $result->fetch_array();
	
	$grid = array();
	for($i = 0; $i < SIZE; $i++){
		for($j = 0; $j < SIZE; $j++){
			$query = "SELECT * FROM grid WHERE world_id = " . $world['id'] . " AND x = " . $i . " AND y = " . $j;
			$result = $mysqli->query($query);
			$row = $result->fetch_assoc();
			$inst = strtoupper(dechex($row['inst']));
			while(strlen($inst) < 4){
				$inst = '0' . $inst;
			}
			$grid[$i][$j] = array('inst' => $inst, 'energy' => $row['energy'], 'id' => $row['id']);
		}
	}
?>
<!DOCTYPE html>
<html>
<head>
	<link type="text/css" rel="stylesheet" href="/css/default.css" />
</head>
<body>
	<? foreach($grid as $row){?>
		<? foreach($row as $cell){?>
			<div class="cell" style="background-color: #00<?=$cell['inst']?>" id="<?=$cell['id']?>">
			</div>
		<? }?>
		<div class="clear"></div>
	<? }?>
	<script src="//ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
	<script type="text/javascript" src="/js/default.js"></script>
</body>
</html>