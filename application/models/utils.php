<?php
	/**
	 * desc : utilitas lintas modul yang butuh akses database
	 */
	class utils extends Database{
		//Cache dalam-proses untuk isi config_parameters. Tanpa ini setiap pemanggilan
		//validateLocationPelaporan menambah satu query; dengan ini seluruh parameter
		//dibaca sekali saja per request. Sengaja TIDAK memakai cache lintas-request
		//supaya perubahan nilai oleh operator langsung berlaku tanpa menunggu
		//kedaluwarsa -- itulah gunanya memindahkan ambang ini ke database.
		private $configCache = null;

		public function __construct(){
			$this->init();
			$this->table 	= "lokasi_pemantauan";
			$this->primary 	= "uid_lokasi_pemantauan";
		}

		/**
		 * Memeriksa seberapa jauh koordinat yang dilaporkan bergeser dari koordinat
		 * master lokasi_pemantauan, lalu menggolongkannya ke tiga tingkat.
		 *
		 * Dipakai untuk memvalidasi hasil pembacaan OCR: model bisa saja mencocokkan
		 * dokumen ke lokasi tertentu, tapi koordinat yang tertera di dokumen itu
		 * ternyata jauh dari titik pantau yang terdaftar -- pertanda salah cocok,
		 * salah ketik, atau titik yang memang berpindah.
		 *
		 * Tingkatnya diambil dari tabel config_parameters:
		 *   shift <= LOCATION_SHIFT_OK_M                -> "ok"
		 *   LOCATION_SHIFT_OK_M < shift <= ..._WARN_M   -> "warn"
		 *   shift > LOCATION_SHIFT_WARN_M               -> "invalid"
		 *
		 * Kembaliannya memakai amplop {statusCode, message, data} seperti endpoint JSON
		 * lain di aplikasi ini. Isi 'data' SELALU memuat 'status' dan 'shift_m',
		 * termasuk pada jalur galat, supaya pemanggil tidak perlu bercabang menurut
		 * bentuk respons. Pada jalur galat 'shift_m' bernilai null -- berbeda dari 0,
		 * yang berarti koordinatnya benar-benar berimpit.
		 *
		 * @param  mixed $uid        uid_lokasi_pemantauan yang dijadikan acuan
		 * @param  mixed $latitude   lintang yang dilaporkan
		 * @param  mixed $longitude  bujur yang dilaporkan
		 * @return array
		 */
		public function validateLocationPelaporan($uid, $latitude, $longitude){
			$uid = (int) $uid;
			if($uid <= 0){
				return $this->invalidLocation(400, "uid_lokasi_pemantauan wajib diisi dan harus angka lebih besar dari 0");
			}

			//is_numeric dipakai, bukan sekadar empty(), supaya "0" tidak ikut tertolak
			//dan supaya string seperti "-6,16" (koma desimal) tertangkap sebagai galat
			//alih-alih diam-diam dibaca (float) menjadi -6.
			if(!is_numeric($latitude) || !is_numeric($longitude)){
				return $this->invalidLocation(400, "latitude dan longitude wajib diisi dan harus angka dengan titik sebagai pemisah desimal");
			}

			$latitude 	= (float) $latitude;
			$longitude 	= (float) $longitude;
			if($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180){
				return $this->invalidLocation(400, "latitude harus di rentang -90..90 dan longitude di rentang -180..180");
			}

			$row = $this->query("SELECT uid_lokasi_pemantauan, kode_lokasi, latitude, longitude
									FROM lokasi_pemantauan
									WHERE deleted = 0 AND uid_lokasi_pemantauan = " . $uid);
			$row = isset($row['data'][0]) ? $row['data'][0] : null;
			if(!$row){
				return $this->invalidLocation(404, "Lokasi pemantauan uid " . $uid . " tidak ditemukan atau sudah dihapus");
			}

			//Sebagian kecil baris master memang belum berkoordinat. Nilai 0/0 ikut
			//dianggap kosong: titik itu di lepas pantai Teluk Guinea, jadi mustahil
			//sebagai lokasi pemantauan di Indonesia dan pasti berarti data belum diisi.
			if(!is_numeric($row['latitude']) || !is_numeric($row['longitude'])
				|| ((float) $row['latitude'] == 0 && (float) $row['longitude'] == 0)){
				return $this->invalidLocation(422, "Koordinat master lokasi " . $row['kode_lokasi'] . " belum diisi, pergeseran tidak bisa dihitung");
			}

			list($okM, $warnM) = $this->shiftThresholds();

			$shift = $this->distanceMeters($latitude, $longitude, (float) $row['latitude'], (float) $row['longitude']);
			$shift = round($shift, 2);

			if($shift <= $okM){
				$status = "ok";
				$message = "Pergeseran " . $shift . " m dari titik " . $row['kode_lokasi'] . ", masih dalam ambang wajar " . $okM . " m";
			}elseif($shift <= $warnM){
				$status = "warn";
				$message = "Pergeseran " . $shift . " m dari titik " . $row['kode_lokasi'] . ", melebihi ambang wajar " . $okM . " m tapi masih di bawah batas " . $warnM . " m";
			}else{
				$status = "invalid";
				$message = "Pergeseran " . $shift . " m dari titik " . $row['kode_lokasi'] . ", melebihi batas " . $warnM . " m";
			}

			return array(
				'statusCode' 	=> 200,
				'message' 		=> $message,
				'data' 			=> array(
					'status' 		=> $status,
					'shift_m' 		=> $shift,
					'shift_ok_m' 	=> $okM,
					'shift_warn_m' 	=> $warnM
				)
			);
		}

		/**
		 * Jarak lingkaran besar antara dua koordinat, dalam meter, dengan rumus haversine.
		 *
		 * Haversine menganggap bumi bulat sempurna sehingga meleset sampai sekitar 0,5%
		 * terhadap elipsoid WGS84. Pada jarak puluhan meter selisihnya jauh di bawah
		 * ketelitian GPS ponsel, jadi tidak perlu rumus Vincenty yang lebih mahal.
		 *
		 * @return float
		 */
		public function distanceMeters($lat1, $lon1, $lat2, $lon2){
			//jari-jari rata-rata bumi menurut IUGG, dalam meter
			$radius = 6371008.8;

			$dLat = deg2rad($lat2 - $lat1);
			$dLon = deg2rad($lon2 - $lon1);

			$a = sin($dLat / 2) * sin($dLat / 2)
				+ cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);

			//min(1.0, ...) menjaga argumen asin tetap di domainnya. Untuk dua titik yang
			//nyaris berimpit, pembulatan float bisa membuat sqrt($a) sedikit melebihi 1
			//dan asin() mengembalikan NAN.
			$c = 2 * asin(min(1.0, sqrt($a)));

			return $radius * $c;
		}

		/**
		 * Nilai integer sebuah parameter di config_parameters.
		 *
		 * $default dipakai bila barisnya belum ada atau value_int-nya NULL -- misalnya
		 * di environment yang migrasi config_parameters-nya belum dijalankan. Sengaja
		 * tidak melempar galat: endpoint validasi harus tetap bisa menjawab meski tabel
		 * config belum tersedia.
		 *
		 * @return int|null
		 */
		public function configInt($key, $default = null){
			$config = $this->loadConfig();
			if(isset($config[$key]) && $config[$key]['value_int'] !== null && $config[$key]['value_int'] !== ''){
				return (int) $config[$key]['value_int'];
			}
			return $default;
		}

		/**
		 * Nilai teks sebuah parameter di config_parameters. Pasangan configInt()
		 * untuk kolom value_text.
		 *
		 * @return string|null
		 */
		public function configText($key, $default = null){
			$config = $this->loadConfig();
			if(isset($config[$key]) && $config[$key]['value_text'] !== null && $config[$key]['value_text'] !== ''){
				return $config[$key]['value_text'];
			}
			return $default;
		}

		/**
		 * Ambang pergeseran yang berlaku, sebagai array($okM, $warnM).
		 *
		 * Nilai bawaan 50 dan 100 di sini adalah CADANGAN bila tabel config_parameters
		 * belum ada; nilai yang sebenarnya berlaku ada di tabel itu. Keduanya dijaga
		 * agar tidak terbalik: bila operator keliru menyetel WARN lebih kecil dari OK,
		 * pita "warn" akan kosong dan pergeseran di antara keduanya langsung dianggap
		 * "invalid" -- jadi WARN dinaikkan menyamai OK.
		 *
		 * @return array
		 */
		private function shiftThresholds(){
			$okM 	= $this->configInt('LOCATION_SHIFT_OK_M', 50);
			$warnM 	= $this->configInt('LOCATION_SHIFT_WARN_M', 100);

			if($warnM < $okM){
				$warnM = $okM;
			}

			return array($okM, $warnM);
		}

		/**
		 * Membaca seluruh isi config_parameters sekali lalu menyimpannya di memori
		 * untuk sisa request ini.
		 *
		 * @return array dipetakan sebagai key => baris
		 */
		private function loadConfig(){
			if($this->configCache !== null){
				return $this->configCache;
			}

			$this->configCache = array();

			//`key` adalah kata cadangan MySQL, wajib ditulis dalam backtick
			$rows = $this->query("SELECT `key`, `value_int`, `value_text` FROM `config_parameters`");
			if(isset($rows['data'])){
				foreach($rows['data'] as $r){
					$this->configCache[$r['key']] = $r;
				}
			}

			return $this->configCache;
		}

		private function invalidLocation($statusCode, $message){
			list($okM, $warnM) = $this->shiftThresholds();

			return array(
				'statusCode' 	=> $statusCode,
				'message' 		=> $message,
				'data' 			=> array(
					'status' 		=> "invalid",
					'shift_m' 		=> NULL,
					'shift_ok_m' 	=> $okM,
					'shift_warn_m' 	=> $warnM
				)
			);
		}
	}
?>
