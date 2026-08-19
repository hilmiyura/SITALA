<?php
	class Message{
		private $message;
		public function __construct() {
			$this->message = array(
									1	=> '<strong>ERROR : </strong> Login fail!',
									2	=> '<strong>SUCCESS : </strong> Saving data success.',
									3	=> '<strong>ERROR : </strong> Saving data fail.',
									4	=> '<strong>SUCCESS : </strong> Delete data success.',
									5	=> '<strong>ERROR : </strong> Delete data fail.',
									6	=> '<strong>WARNING : </strong> Data not found.',
									7	=> '<strong>ERROR : </strong> ACCESS FORBIDEN.',
									8	=> '<strong>ERROR : </strong> New password not same with old password.'
							);
		}
		public function login(){
			return $this->message[1];
		}
		public function save($type='success'){
			if($type=='success'){
				return $this->message[2];
			}else{
				return $this->message[3];
			}

		}
		public function delete($type='success'){
			if($type=='success'){
				return $this->message[4];
			}else{
				return $this->message[5];
			}
		}
		public function search(){
			return $this->message[6];
		}
		public function access(){
			return $this->message[7];
		}
		public function changePassword(){
			return $this->message[8];
		}
	}
?>
