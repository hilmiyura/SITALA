<?php
	class _pdfController extends Front {

		public function init(){
			$this->view->setFolder('be');

		}

		public function index(){
      // ini_set("display_errors",TRUE);
			$this->_generatePDF("template-kosong","test_pdf.html");
		}

		private function _generatePDF($name="",$target="",$kertas="A4",$orientasi="P",$ukuran_hurup=8,$jenis_hurup="Arial"){
			$html = $this->view->fetch($target);
			include('mpdf_new/mpdf.php');
			$mpdf=  new mPDF('c',    // mode - default ''
	                 $kertas, // format - A4, for example, default ''
	                 $ukuran_hurup,     // font size - default 0
	                 $jenis_hurup,    // default font family
	                 10,    // margin_left
	                 10,    // margin right
	                 10,     // margin top
	                 10,    // margin bottom
	                 0,     // margin header
	                 0,     // margin footer
	                 $orientasi);  // L - landscape, P - portrait
			$mpdf->WriteHTML($html);
      // die($html);
			$fileName = $name.'.pdf';
			$mpdf->Output();
		}
	}

?>
