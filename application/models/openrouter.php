<?php
	/*
	 * MODEL FOR OCR DOCUMENT EXTRACTION VIA OPENROUTER (GEMINI FLASH)
	 */
	class openrouter{

		public function chatCompletion($messages, $extra = array()){
			$payload = array_merge(array(
				"model" => OPENROUTER_OCR_MODEL,
				"messages" => $messages,
				"response_format" => array("type" => "json_object"),
				//Meminta OpenRouter menyertakan RINCIAN pemakaian beserta biayanya.
				//Tanpa flag ini responsnya tetap memuat cacah token, tapi TIDAK memuat
				//`cost` -- sehingga biaya harus dihitung sendiri dari daftar harga per
				//model. Itu rapuh: harga bisa berubah tanpa pemberitahuan, berbeda antara
				//token masukan dan keluaran, dan bisa berbeda lagi tergantung provider
				//mana yang kebetulan melayani permintaan. Angka dari OpenRouter adalah
				//yang benar-benar ditagihkan.
				"usage" => array("include" => true),
			), $extra);

			$headers = array(
				"Content-Type: application/json",
				"Authorization: Bearer " . OPENROUTER_API_KEY,
				"HTTP-Referer: " . APP_IKLH,
				"X-Title: SITALA IKLH",
			);

			$curlSession = curl_init();
			curl_setopt($curlSession, CURLOPT_URL, OPENROUTER_API_URL);
			curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curlSession, CURLOPT_POST, true);
			curl_setopt($curlSession, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($curlSession, CURLOPT_POSTFIELDS, json_encode($payload));
			curl_setopt($curlSession, CURLOPT_TIMEOUT, 90);
			$response = curl_exec($curlSession);
			$curlError = curl_error($curlSession);
			curl_close($curlSession);

			if ($curlError) {
				return array("error" => $curlError);
			}

			$json = json_decode($response, true);
			if ($json === null) {
				return array("error" => "Response OpenRouter tidak valid", "raw" => $response);
			}
			return $json;
		}

		//Loads a system prompt from application/prompts/{name}.md.
		//One markdown file per document/indicator type (iku, and later ikl, ikal, ika, ...)
		//so prompts can be reviewed/edited without touching PHP code.
		private function loadPrompt($name){
			$path = dirname(__DIR__) . "/prompts/" . basename($name) . ".md";
			if (!is_file($path)) {
				throw new Exception("Prompt file tidak ditemukan: " . $path);
			}
			return trim(file_get_contents($path));
		}

		//Extract structured pelaporan IKU fields from a SHU document (image or pdf)
		public function extractIku($filePath, $mimeType){
			return $this->extractDocument("iku", "Ekstrak data dari dokumen SHU/LHP kualitas udara ambien berikut sesuai instruksi.", $filePath, $mimeType);
		}

		//Extract structured pelaporan IKA fields from a SHU/LHP/LHU document (image or pdf)
		public function extractIka($filePath, $mimeType){
			return $this->extractDocument("ika", "Ekstrak data dari dokumen SHU/LHP/LHU kualitas air permukaan berikut sesuai instruksi.", $filePath, $mimeType);
		}

		//Extract structured pelaporan IKAL fields from a SHU/LHP/LHU document (image or pdf)
		public function extractIkal($filePath, $mimeType){
			return $this->extractDocument("ikal", "Ekstrak data dari dokumen SHU/LHP/LHU kualitas air laut berikut sesuai instruksi.", $filePath, $mimeType);
		}

		//Extract structured pelaporan IKU fields from a laporan bulanan AQMS (bukan SHU) --
		//dokumen pemantauan kontinu tiap 30 menit sepanjang tahun.
		//
		//Yang diminta dari model HANYA angka ringkasan yang tercetak: dua angka footer di
		//tiap halaman bulanan, plus halaman rekap tahunan. Isi tabel per 30 menit TIDAK
		//dibaca sama sekali.
		//
		//Batasan itu hasil pengukuran, bukan penyederhanaan. Meminta model menyalin matriks
		//48x31 menghasilkan angka karangan: dua model berbeda hanya sepakat pada 44% sel,
		//dan keduanya mengulang baris yang sama berkali-kali. Sebaliknya angka footer
		//terbukti akurat -- jumlah 11 footer bulanan cocok SAMPAI DIGIT TERAKHIR dengan
		//halaman rekap tahunan yang dibaca terpisah, untuk NO2 maupun SO2.
		//
		//Perhitungan turunannya (hari valid, data valid, persentase) dikerjakan
		//ocrController::aqmsHitung().
		public function extractIkuAqms($filePath, $mimeType){
			return $this->extractDocument("iku_aqms", "Baca angka ringkasan laporan bulanan pemantauan otomatis (AQMS) kualitas udara ambien berikut sesuai instruksi. Jangan membaca isi tabel data per 30 menit.", $filePath, $mimeType);
		}

		//Timeout satu panggilan baris harian AQMS. Diukur, bukan ditebak: satu halaman
		//bulanan memakan 50-60 detik pada dokumen uji, jauh di atas panggilan SHU biasa.
		const TIMEOUT_HARIAN = 240;

		//Batas atas panggilan harian yang berjalan berbarengan.
		const MULTI_CONCURRENCY_MAX = 12;

		//Anggaran memori untuk seluruh handle yang hidup bersamaan, dalam MB.
		//
		//Yang membatasi paralelisme di sini MEMORI, bukan jaringan: tiap handle curl
		//membawa salinan dokumen ber-base64 di body-nya. Dokumen 5,9 MB jadi ~7,9 MB per
		//handle; unggahan 10 MB (batas ocrController::readUploadedFile) jadi ~13,3 MB.
		//Dengan anggaran 120 MB keduanya masih jauh di bawah memory_limit 512M sekalipun
		//curl menyimpan salinannya sendiri.
		const MULTI_MEMORY_BUDGET_MB = 120;

		/**
		 * Mengambil baris ringkasan harian AQMS, satu panggilan per parameter-bulan,
		 * dijalankan berbarengan.
		 *
		 * Dipecah per bulan karena batas ketelitian, bukan batas token: satu panggilan
		 * berisi 11 bulan sekaligus (~1.400 angka) terbukti BERGESER sekitar dua hari
		 * mulai hari ke-11 dan kehilangan min/mean/max pada hari 23-30. Strukturnya tetap
		 * tampak sehat, jadi kerusakannya tidak terlihat tanpa dibandingkan ke pembacaan
		 * lain. Satu halaman per panggilan tetap runut.
		 *
		 * Ditulis mandiri, TIDAK memakai ulang extractDocument(): jalur satu-panggilan itu
		 * sudah terbukti melayani SHU IKU/IKA/IKAL di produksi, dan memecahnya jadi
		 * potongan yang bisa dipakai bersama pernah menyebabkan regresi yang mematikan
		 * ketiganya. Sedikit duplikasi di sini ditukar dengan jalur lama yang tidak
		 * tersentuh sama sekali.
		 *
		 * @param  array $jobs daftar array("parameter" => "no2", "bulan" => 1)
		 * @param  float|null $deadline microtime(true) batas MEMULAI panggilan baru
		 * @return array kunci sama dengan $jobs, tiap nilai {success, data|error, usage, job, dilewati}
		 */
		public function extractIkuAqmsHarian($filePath, $mimeType, $jobs, $deadline = NULL){
			if (!count($jobs)) {
				return array();
			}

			//Sekali encode untuk seluruh pekerjaan. PHP menyalin string secara
			//copy-on-write, jadi menaruh string yang sama di puluhan payload tidak
			//menggandakan memorinya selama tidak ada yang mengubahnya.
			$fileData = base64_encode(file_get_contents($filePath));
			$prompt   = $this->loadPrompt("iku_aqms_harian");

			$payloads = array();
			foreach ($jobs as $i => $job) {
				$userText = "Ekstrak baris ringkasan harian (Min, Mean, Max, Jumlah Data) dan dua angka footer"
					. " untuk parameter " . strtoupper($job['parameter'])
					. " bulan " . $this->namaBulan($job['bulan'])
					. " (bulan ke-" . (int) $job['bulan'] . ") sesuai instruksi."
					. " Keluarkan HANYA halaman itu.";

				$payloads[$i] = array(
					"model" => OPENROUTER_OCR_MODEL,
					"messages" => array(
						array("role" => "system", "content" => $prompt),
						array("role" => "user", "content" => array(
							array("type" => "text", "text" => $userText),
							$this->buildFilePart($fileData, $mimeType),
						)),
					),
					"response_format" => array("type" => "json_object"),
					"usage" => array("include" => true),
				);

				if ($mimeType == "application/pdf") {
					$payloads[$i]["plugins"] = array(
						array("id" => "file-parser", "pdf" => array("engine" => "native")),
					);
				}
			}

			$raw = $this->executeMany(
				$payloads,
				self::TIMEOUT_HARIAN,
				$this->concurrencyFor(strlen($fileData)),
				$deadline
			);

			$out = array();
			foreach ($jobs as $i => $job) {
				$mentah  = isset($raw[$i]) ? $raw[$i] : array("error" => "Tidak ada respons untuk permintaan ini");
				$out[$i] = $this->interpretHarian($mentah);
				$out[$i]['job'] = $job;
				//Ditandai supaya pemanggil bisa membedakan "gagal dibaca" dari "tidak
				//sempat dijalankan" -- yang kedua tidak menghabiskan biaya.
				$out[$i]['dilewati'] = !empty($mentah['dilewati']);
			}

			return $out;
		}

		//Menerjemahkan satu respons jadi {success, data|error, usage}. Sejajar dengan
		//bagian akhir extractDocument(), tapi terpisah supaya jalur SHU tidak tersentuh.
		private function interpretHarian($result){
			$usage = $this->normalizeUsage($result);

			if (isset($result['error'])) {
				return array("success" => false, "error" => is_array($result['error']) ? json_encode($result['error']) : $result['error'], "usage" => $usage);
			}
			if (!isset($result['choices'][0]['message']['content'])) {
				return array("success" => false, "error" => "Response OpenRouter tidak berisi konten", "usage" => $usage);
			}

			$data = $this->extractJson($result['choices'][0]['message']['content']);
			if ($data === null) {
				return array("success" => false, "error" => "Gagal parsing JSON dari hasil OCR", "usage" => $usage);
			}

			return array("success" => true, "data" => $data, "usage" => $usage);
		}

		/**
		 * Menjalankan banyak permintaan sekaligus lewat curl_multi, dengan jendela
		 * bergulir: begitu satu handle selesai, satu pekerjaan berikutnya langsung masuk
		 * menggantikan -- bukan menunggu seluruh gelombang beres. Ini penting karena lama
		 * tiap panggilan sangat bervariasi.
		 *
		 * $deadline adalah batas untuk MEMULAI pekerjaan baru, bukan menyelesaikannya.
		 * Lewat batas itu sisa pekerjaan tidak dijalankan dan ditandai dilewati, sehingga
		 * pemanggil mendapat hasil sebagian beserta keterangannya -- jauh lebih berguna
		 * daripada dibunuh max_execution_time tanpa keluaran apa pun. Pekerjaan yang
		 * TERLANJUR berjalan tetap ditunggu: tokennya sudah ditagih.
		 */
		private function executeMany($payloads, $timeout, $concurrency = NULL, $deadline = NULL){
			$keys        = array_keys($payloads);
			$next        = 0;
			$total       = count($keys);
			$results     = array();
			$running     = array();
			$concurrency = $concurrency ? (int) $concurrency : self::MULTI_CONCURRENCY_MAX;
			$kehabisan   = FALSE;

			$mh = curl_multi_init();

			do {
				//Isi jendela sampai penuh, atau sampai pekerjaan habis.
				while (count($running) < $concurrency && $next < $total) {
					if ($deadline !== NULL && microtime(TRUE) >= $deadline) {
						$kehabisan = TRUE;
						break;
					}
					$key = $keys[$next++];
					$ch  = $this->newHandle($payloads[$key], $timeout);
					curl_multi_add_handle($mh, $ch);
					$running[$this->handleId($ch)] = array("key" => $key, "handle" => $ch);
				}

				do {
					$status = curl_multi_exec($mh, $active);
				} while ($status === CURLM_CALL_MULTI_PERFORM);

				while ($info = curl_multi_info_read($mh)) {
					$ch = $info['handle'];
					$id = $this->handleId($ch);
					if (!isset($running[$id])) {
						continue;
					}

					$body = curl_multi_getcontent($ch);
					//$info['result'] adalah kode galat curl; curl_error() pada handle yang
					//sudah selesai tetap memuat pesannya.
					$err  = $info['result'] === CURLE_OK ? "" : (curl_error($ch) ?: "cURL error " . $info['result']);

					if ($err) {
						$results[$running[$id]['key']] = array("error" => $err);
					} else {
						$json = json_decode($body, true);
						$results[$running[$id]['key']] = ($json === null)
							? array("error" => "Response OpenRouter tidak valid", "raw" => $body)
							: $json;
					}

					curl_multi_remove_handle($mh, $ch);
					curl_close($ch);
					unset($running[$id]);
				}

				//Menunggu ada aktivitas soket sebelum memutar lagi, supaya loop ini tidak
				//memakan CPU penuh selama menit-menit penantian. Nilai negatif berarti
				//select() gagal di beberapa platform -- di situ tidur singkat sudah cukup.
				if (count($running) && curl_multi_select($mh, 1.0) === -1) {
					usleep(100000);
				}
			} while (count($running) || ($next < $total && !$kehabisan));

			curl_multi_close($mh);

			//Pekerjaan yang tidak sempat dijalankan diberi keterangan sendiri, bukan
			//dibiarkan hilang. Bedakan dari kegagalan jaringan: ini bukan permintaan yang
			//gagal, melainkan permintaan yang tidak pernah dikirim -- dan karena itu juga
			//tidak ditagih.
			while ($next < $total) {
				$results[$keys[$next++]] = array(
					"error" => "Dilewati: anggaran waktu habis sebelum permintaan ini dijalankan",
					"dilewati" => TRUE,
				);
			}

			return $results;
		}

		private function newHandle($payload, $timeout){
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, OPENROUTER_API_URL);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				"Content-Type: application/json",
				"Authorization: Bearer " . OPENROUTER_API_KEY,
				"HTTP-Referer: " . APP_IKLH,
				"X-Title: SITALA IKLH",
			));
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
			curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

			return $ch;
		}

		//Berapa handle boleh hidup bersamaan untuk payload sebesar $payloadBytes.
		//Dihitung dari anggaran memori, lalu dijepit antara 2 dan MULTI_CONCURRENCY_MAX.
		private function concurrencyFor($payloadBytes){
			if ($payloadBytes <= 0) {
				return self::MULTI_CONCURRENCY_MAX;
			}

			$muat = (int) floor((self::MULTI_MEMORY_BUDGET_MB * 1048576) / $payloadBytes);

			return max(2, min(self::MULTI_CONCURRENCY_MAX, $muat));
		}

		//Handle curl adalah resource di PHP 7 dan objek di PHP 8. Repo ini terkunci di
		//PHP 7.4 (lihat Dockerfile), tapi pengenal ini dibuat tahan keduanya supaya loop
		//di executeMany() tidak diam-diam rusak bila versinya kelak naik.
		private function handleId($ch){
			return is_object($ch) ? spl_object_id($ch) : (int) $ch;
		}

		private function namaBulan($bulan){
			$nama = array(1 => "JANUARI", "FEBRUARI", "MARET", "APRIL", "MEI", "JUNI",
						  "JULI", "AGUSTUS", "SEPTEMBER", "OKTOBER", "NOVEMBER", "DESEMBER");
			$bulan = (int) $bulan;

			return isset($nama[$bulan]) ? $nama[$bulan] : (string) $bulan;
		}

		private function extractDocument($promptName, $userText, $filePath, $mimeType){
			$fileData = base64_encode(file_get_contents($filePath));

			$messages = array(
				array(
					"role" => "system",
					"content" => $this->loadPrompt($promptName),
				),
				array(
					"role" => "user",
					"content" => array(
						array("type" => "text", "text" => $userText),
						$this->buildFilePart($fileData, $mimeType),
					),
				),
			);

			$extra = array();
			if ($mimeType == "application/pdf") {
				$extra["plugins"] = array(
					array("id" => "file-parser", "pdf" => array("engine" => "native")),
				);
			}

			$result = $this->chatCompletion($messages, $extra);

			//Pemakaian diambil SEBELUM percabangan galat, dan ikut dikembalikan di semua
			//cabang. Panggilan yang hasilnya tidak terpakai -- konten kosong, JSON gagal
			//di-parse -- tetap ditagih penuh. Bila hanya jalur sukses yang melaporkannya,
			//justru percobaan yang paling boros (dokumen sulit, model mengarang, diulang
			//berkali-kali) yang biayanya tidak terlihat sama sekali.
			$usage = $this->normalizeUsage($result);

			if (isset($result['error'])) {
				return array("success" => false, "error" => is_array($result['error']) ? json_encode($result['error']) : $result['error'], "usage" => $usage, "raw" => $result);
			}
			if (!isset($result['choices'][0]['message']['content'])) {
				return array("success" => false, "error" => "Response OpenRouter tidak berisi konten", "usage" => $usage, "raw" => $result);
			}

			$content = $result['choices'][0]['message']['content'];
			$data = $this->extractJson($content);

			if ($data === null) {
				return array("success" => false, "error" => "Gagal parsing JSON dari hasil OCR", "usage" => $usage, "raw" => $content);
			}

			return array("success" => true, "data" => $data, "usage" => $usage, "raw" => $content);
		}

		/**
		 * Meratakan blok `usage` OpenRouter jadi satu bentuk tetap.
		 *
		 * Bentuk aslinya bersarang dan tidak seragam: cached_tokens ada di
		 * prompt_tokens_details, reasoning_tokens di completion_tokens_details, dan
		 * kunci-kunci itu hanya muncul bila providernya melaporkannya. Diratakan di
		 * sini supaya sisi pemanggil tidak perlu menjaga isset() bertingkat, dan supaya
		 * bentuk yang dijanjikan ke frontend tidak ikut berubah kalau provider di balik
		 * layar berganti.
		 *
		 * Kuncinya SELALU lengkap, bernilai null bila tidak dilaporkan. Null di sini
		 * berarti "tidak diketahui", BUKAN nol -- membedakan keduanya penting saat
		 * angka-angka ini dijumlahkan jadi laporan biaya.
		 *
		 * model dan generation_id ikut dibawa karena keduanya yang membuat angka biaya
		 * bisa ditelusuri: model yang benar-benar melayani bisa berbeda dari yang diminta
		 * (OpenRouter merutekan ulang), dan generation_id bisa dicari di dasbor OpenRouter
		 * bila suatu tagihan dipertanyakan.
		 *
		 * @return array|null null bila responsnya memang tidak memuat usage sama sekali
		 */
		private function normalizeUsage($result){
			if (!is_array($result) || !isset($result['usage']) || !is_array($result['usage'])) {
				return null;
			}

			$u = $result['usage'];

			return array(
				"prompt_tokens" 	=> isset($u['prompt_tokens']) ? (int) $u['prompt_tokens'] : null,
				"completion_tokens" => isset($u['completion_tokens']) ? (int) $u['completion_tokens'] : null,
				"total_tokens" 		=> isset($u['total_tokens']) ? (int) $u['total_tokens'] : null,
				//Token masukan yang dilayani dari cache. Ditagih lebih murah, jadi kalau
				//angka ini besar, biaya tidak naik sebanding dengan prompt_tokens.
				"cached_tokens" 	=> isset($u['prompt_tokens_details']['cached_tokens']) ? (int) $u['prompt_tokens_details']['cached_tokens'] : null,
				//Selalu 0 untuk model non-reasoning. Disertakan supaya lonjakan biaya
				//masih bisa dijelaskan bila model OCR-nya kelak diganti.
				"reasoning_tokens" 	=> isset($u['completion_tokens_details']['reasoning_tokens']) ? (int) $u['completion_tokens_details']['reasoning_tokens'] : null,
				//Biaya dalam USD, langsung dari OpenRouter. Angkanya sangat kecil
				//(orde 1e-4), jadi json_encode menuliskannya dalam notasi eksponen
				//seperti 7.2e-5 -- itu JSON yang sah dan JSON.parse membacanya sebagai
				//number biasa. JANGAN dibulatkan di sini: pembulatan dua desimal
				//menjadikan seluruh biaya per dokumen bernilai 0.
				"cost" 				=> isset($u['cost']) ? (float) $u['cost'] : null,
				"model" 			=> isset($result['model']) ? $result['model'] : null,
				"generation_id" 	=> isset($result['id']) ? $result['id'] : null,
			);
		}

		private function buildFilePart($base64Data, $mimeType){
			if (strpos($mimeType, "image/") === 0) {
				return array(
					"type" => "image_url",
					"image_url" => array("url" => "data:" . $mimeType . ";base64," . $base64Data),
				);
			}
			return array(
				"type" => "file",
				"file" => array(
					"filename" => "document.pdf",
					"file_data" => "data:application/pdf;base64," . $base64Data,
				),
			);
		}

		private function extractJson($text){
			$text = trim($text);
			$text = preg_replace('/^```(json)?/i', '', $text);
			$text = preg_replace('/```$/', '', $text);
			$text = trim($text);

			$json = json_decode($text, true);
			if ($json !== null) {
				return $json;
			}

			if (preg_match('/\{.*\}/s', $text, $m)) {
				$json = json_decode($m[0], true);
				if ($json !== null) {
					return $json;
				}
			}
			return null;
		}
	}
?>
