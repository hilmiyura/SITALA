<?php
	require_once "view/smarty/Smarty.class.php";	
    require_once "view/smarty/SmartyPaginate.class.php";
	
	class View extends Smarty{
		public function __construct() {
			//VIEW SETTING						
    		$this->setFolder();
			parent::__construct();
    		$this->compile_dir = COMPILEFOLDER;
    		$this->compile_check = TRUE;
			$this->force_compile = TRUE;
			$this->caching = FALSE;
    		$this->debugging = FALSE;
		}
		public function setFolder($folder="fe"){
			if(Kick::checkFile(VIEWS, $folder)){
				$this->template_dir = VIEWS . $folder;	
			}else{
				die("Folder " . $folder . " not found in views folder");
			}
		}
		/**
         * Function to generate pagination with Smarty Pagination
         * 
         * @param $totalRow = jumlah semua baris dari query yg dihasilkan
         * @param $offset = offset dari query
         * @param $limit = limit query
         * @param $id = digunakan jika dalam satu page ada lebih dari 1 paging, maka identifier-nya berupa variable $id
         * @param $urlVar = digunakan jika ada parameter URL yang akan disertakan pada setiap page, misal untuk pencarian
         *                  contoh $urlVar = "/admin/users/index/search/redwhite/";
        */
        public function pagination($view, $totalRow, $offset, $limit=20, $urlVar='', $id='default'){
        	$smartypaginate = new SmartyPaginate;
			
            $smartypaginate->reset($id);
            $smartypaginate->connect($id);
                    
            $smartypaginate->setLimit($limit,$id);
            $smartypaginate->setCurrentItem($offset);                
			
           	$smartypaginate->setTotal($totalRow,$id);
            $smartypaginate->setUrl($urlVar,$id);   
            $smartypaginate->setPrevText('<',$id);
            $smartypaginate->setNextText('>',$id);
            $smartypaginate->setLastText('>>',$id);
            $smartypaginate->setFirstText('<<',$id);
            $smartypaginate->assign($view,"paging_" . $id, $id); 			
        }
	}
?>