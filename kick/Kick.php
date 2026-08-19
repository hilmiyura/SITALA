<?php	    
    require_once "globalsetting.php";
	require_once "front/Front.php";
	
	class Kick{
		public static function run(){
			Kick::loadController();			
		}
		public static function loadController(){
			$uri 		= new Uri();			
			$ctrl		= $uri->getController();
			$ctrl_file 	= $ctrl . "Controller.php";
		    $ctrl_name 	= $ctrl . "Controller";
			
		    if(Kick::checkFile(CONTROLLERS, $ctrl_file)){
		        require_once CONTROLLERS . $ctrl_file;
					
		        $module = new $ctrl_name;
				$action = $uri->getAction();
				
				if(method_exists($module,"init")){
					$module->init();
				}
				if(method_exists($module,$action)){
					$module->$action();
				}else{
					die("Action " . $action . " not found in " . $ctrl_file);	
				}			    
		    }else{
		    	if(defined('USENICENAME')){		    		
		    		$ctrl_file 	= NICENAMECONTROLLER . "Controller.php";
					$ctrl_name 	= NICENAMECONTROLLER . "Controller";
					
		    		if(Kick::checkFile(CONTROLLERS, $ctrl_file)){
		    			require_once CONTROLLERS . $ctrl_file;
		    			
		    			$module = new $ctrl_name;
		    			if(method_exists($module,"init")){
							$module->init();
						}
						$module->index();
					}else{
						die("NICENAME USED : File " . $ctrl_file . " not found in controllers folder");
					}
		    	}else{
		    		die("File " . $ctrl_file . " not found in controllers folder");	
		    	}
		    }
		}
		public static function checkFile($folder, $file){
            $dest = DOCROOT . $folder . $file ;
			
           	if(file_exists($dest)){
                return true;
            }
            else{
                return false;                
            }
        }		
	}
?>