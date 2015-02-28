<?php
	$key = ftok(getcwd() . '/get_segments.php','a');
	$shm_segment = shm_attach($key);
	$segment_json = shm_get_var($shm_segment, 1);
	shm_detach($shm_segment);
	echo $segment_json;	
?>