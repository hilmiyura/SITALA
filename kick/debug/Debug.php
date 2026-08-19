<?php
	class Debug{
		public function __construct(){
			
		}
		public function show($expression="",$printArray=TRUE, $die=TRUE){
			echo "<pre>";
			if($printArray){
				print_r($expression);	
			}else{
				var_dump($expression);	
			}
			if($die) die();
		}
	}
?>