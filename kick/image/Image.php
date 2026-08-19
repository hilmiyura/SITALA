<?php
	require_once "image/ImageWorkshop.php";
	class Image{
		private $layer;		
		public function __construct() {
			//$this->layer = new ImageWorkshop();
		}
		public function resize($imgPath, $width, $height, $saveFolder, $imageName, $type='pixel'){
			$params = array(
							'imageFromPath'=>$imgPath
						);
			$this->layer = new ImageWorkshop($params);
			if($type=='pixel'){
				$this->layer->resizeInPixel($width, $height, TRUE);	
				$this->layer->save($saveFolder, $imageName, FALSE);
			}else{
				die('Type not supported');
			}
		}
	
		public function watermark_text($oldimage_name, $new_image_name, $font_size, $water_mark_text_2, $font_path){
			global $font_path, $font_size, $water_mark_text_2;
			list($owidth,$oheight) = getimagesize($oldimage_name);
			$width = $height = 300;
			$image = imagecreatetruecolor($width, $height);
			$image_src = imagecreatefromjpeg($oldimage_name);
			imagecopyresampled($image, $image_src, 0, 0, 0, 0, $width, $height, $owidth, $oheight);
			$blue = imagecolorallocate($image, 79, 166, 185);
			imagettftext($image, $font_size, 0, 68, 190, $blue, $font_path, $water_mark_text_2);
			imagejpeg($image, $new_image_name, 100);
			imagedestroy($image);
			unlink($oldimage_name);
			return true;
		}
		// Function to add image watermark over images
		function addImageWatermark($SourceFile, $WaterMark, $DestinationFile=NULL, $opacity) {
			$main_img = $SourceFile;
			$watermark_img = $WaterMark;
			$padding = 5;
			$opacity = $opacity;
			// create watermark
			$watermark = imagecreatefrompng($watermark_img);
			$image = imagecreatefromjpeg($main_img);
			if(!$image || !$watermark) die("Error: main image or watermark image could not be loaded!");
			$watermark_size = getimagesize($watermark_img);
			$watermark_width = $watermark_size[0];
			$watermark_height = $watermark_size[1];
			$image_size = getimagesize($main_img);
			$dest_x = $image_size[0] - $watermark_width - $padding;
			$dest_y = $image_size[1] - $watermark_height - $padding;
			imagecopymerge($image, $watermark, $dest_x, $dest_y, 0, 0, $watermark_width, $watermark_height, $opacity);
			if ($DestinationFile<>'') {
				imagejpeg($image, $DestinationFile, 100);
			} else {
				header('Content-Type: image/jpeg');
				imagejpeg($image);
			}
			imagedestroy($image);
			imagedestroy($watermark);
		}
	}
?>