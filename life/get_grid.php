<?php
	$key = ftok(getcwd() . '/phpinfo.php', 'a');
	$shm_segment = shm_attach($key);
	$grid_json = shm_get_var($shm_segment, 1);
	shm_detach($shm_segment);
	echo $grid_json;	
?>