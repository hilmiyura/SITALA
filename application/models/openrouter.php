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

		//Extract structured pelaporan IKU fields from a laporan bulanan AQMS (bukan SHU) — dokumen
		//kontinu per 30 menit ditutup ringkasan tahunan per parameter, lihat prompts/iku_aqms.md
		public function extractIkuAqms($filePath, $mimeType){
			return $this->extractDocument("iku_aqms", "Ekstrak data dari laporan bulanan pemantauan otomatis (AQMS) kualitas udara ambien berikut sesuai instruksi.", $filePath, $mimeType);
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

			if (isset($result['error'])) {
				return array("success" => false, "error" => is_array($result['error']) ? json_encode($result['error']) : $result['error'], "raw" => $result);
			}
			if (!isset($result['choices'][0]['message']['content'])) {
				return array("success" => false, "error" => "Response OpenRouter tidak berisi konten", "raw" => $result);
			}

			$content = $result['choices'][0]['message']['content'];
			$data = $this->extractJson($content);

			if ($data === null) {
				return array("success" => false, "error" => "Gagal parsing JSON dari hasil OCR", "raw" => $content);
			}

			return array("success" => true, "data" => $data, "raw" => $content);
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
