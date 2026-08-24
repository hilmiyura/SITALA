<?php
	function load_env($path){
		if(!is_readable($path)){
			return;
		}

		$lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		foreach($lines as $line){
			$line = trim($line);
			if($line === "" || $line[0] === "#"){
				continue;
			}

			$pos = strpos($line, "=");
			if($pos === false){
				continue;
			}

			$name = trim(substr($line, 0, $pos));
			$value = trim(substr($line, $pos + 1));

			if(strlen($value) > 1 && (
				($value[0] === '"' && substr($value, -1) === '"') ||
				($value[0] === "'" && substr($value, -1) === "'")
			)){
				$value = substr($value, 1, -1);
			}

			if($name === ""){
				continue;
			}

			putenv("$name=$value");
			$_ENV[$name] = $value;
		}
	}
?>
