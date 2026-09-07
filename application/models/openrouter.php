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
