<?php
	/**
	 * 
	 */
	class alert{
		private $lang;
		private $messageError = array(
									'in' => array(
											1	=> '<strong>ERROR : </strong> Login tidak valid!',
											2	=> '<strong>SUCCESS : </strong> Simpan data berhasil.',
											3	=> '<strong>ERROR : </strong> Simpan data gagal.',											
											4	=> '<strong>SUCCESS : </strong> Hapus data berhasil.',
											5	=> '<strong>ERROR : </strong> Hapus data gagal.',
											6	=> '<strong>WARNING : </strong> Data tidak ditemukan.',
											7	=> '<strong>ERROR : </strong> ACCESS FORBIDEN.',
											8	=> '<strong>ERROR : </strong> Kata Sandi yang dimasukan tidak sama dengan ulangi kata sandi.',
											9	=> '<strong>ERROR : </strong> Isian kurang lengkap.',
											10	=> '<strong>ERROR : </strong> Kata Sandi yang dimasukan harus 6 hurup.',
											11	=> '<strong>ERROR : </strong> Email tidak valid!',
											12	=> '<strong>SUCCESS : </strong> Registrasi berhasil, silakan login menggunakan Email dan Kata Sandi yang telah didaftarkan.',
											13	=> '<strong>ERROR : </strong> Registrasi gagal, silakan ulangi lagi.',
											14	=> '<strong>ERROR : </strong> Email yang dimasukan sudah terdaftar, silakan ulangi lagi.',
										)
							);
		
		public function __construct($lang='in') {
			$this->lang = $lang;
		}
		public function message($errorNo){
			return $this->messageError[$this->lang][$errorNo];
		}
	}
?>