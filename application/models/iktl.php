<?php
	class iktl extends Database{
		public function __construct(){
			$this->init();
			$this->table 	= "";
			$this->primary 	= "";

      $this->field_use_2025 = ["hutan_lahan_kering_primer","hutan_lahan_kering_sekunder","hutan_mangrove_primer","hutan_rawa_primer","hutan_tanaman","belukar","perkebunan","permukiman","lahan_terbuka","savanna","tubuh_air","hutan_mangrove_sekunder","hutan_rawa_sekunder","belukar_rawa","pertanian_lahan_kering","pertanian_lahan_kering_campur_semak","sawah","tambak","bandara","transmigrasi","pertambangan","rawa"];
		}

    public function cnIndeks2025($tahun, $provinsi, $kabkota,$simulasi = false){
			$wPelaporan = "input_.deleted = 0 AND input_.tanggal BETWEEN '{$tahun}-01-01' AND '{$tahun}-12-31'";
			if($provinsi > 0){
				$wPelaporan .= " AND input_.uid_provinsi = {$provinsi}";
			}
			if($kabkota > 0){
				$wPelaporan .= " AND input_.uid_kabkota = {$kabkota}";
			}
			if(!$simulasi){
				$wPelaporan .= " AND input_.v_provinsi =1 AND input_.v_pusat =1 ";
			}

      $koefisien = $this->query("SELECT * FROM rf_koefisien_ikl WHERE deleted = 0 AND tahun = 2025")["data"][0];
      $fieldAs = ["input_","revisi_rth_","revisi_rhl_"];

      $fieldAsArr = [];
      foreach ($fieldAs as $key => $value) {
        foreach ($this->field_use_2025 as $ki => $vi) {
          $fieldAsArr[] = "SUM( if({$value}.{$vi},{$value}.{$vi},0) ) AS {$value}{$vi}";
        }
      }

      foreach ($fieldAs as $key => $value) {
        foreach ($this->field_use_2025 as $ki => $vi) {
          if($value == "input_"){
            $fieldAsArr[] = "SUM( if({$value}.{$vi},{$value}.{$vi}*{$koefisien[$vi]},0) ) AS {$value}{$vi}_x_koefisien";
          }elseif ($value == "revisi_rth_") {
            $fieldAsArr[] = "SUM( if({$value}.{$vi},{$value}.{$vi}*0.6,0)) AS {$value}{$vi}_x_koefisien";
          }elseif ($value == "revisi_rhl_") {
            $fieldAsArr[] = "SUM( if({$value}.{$vi},{$value}.{$vi}*0.6,0)) AS {$value}{$vi}_x_koefisien";
          }
          if($value == "input_"){
						$fieldAsArr[] = "SUM(((input_.{$vi} - if(revisi_rth_.{$vi},revisi_rth_.{$vi},0) - if(revisi_rhl_.{$vi},revisi_rhl_.{$vi},0) ) )) AS {$vi}_min_input";
            $fieldAsArr[] = "SUM(((input_.{$vi} + if(revisi_rth_.{$vi},revisi_rth_.{$vi},0) + if(revisi_rhl_.{$vi},revisi_rhl_.{$vi},0) ) )) AS {$vi}_plus_input";

            $fieldAsArr[] = "SUM(((input_.{$vi} - if(revisi_rth_.{$vi},revisi_rth_.{$vi},0) - if(revisi_rhl_.{$vi},revisi_rhl_.{$vi},0) ) * {$koefisien[$vi]})) AS {$vi}_min_input_x_koefisien";
            $fieldAsArr[] = "SUM(((input_.{$vi} + if(revisi_rth_.{$vi},revisi_rth_.{$vi},0) + if(revisi_rhl_.{$vi},revisi_rhl_.{$vi},0) ) * {$koefisien[$vi]})) AS {$vi}_plus_input_x_koefisien";
          }
        }
      }

      $fieldAsArr = implode(",",$fieldAsArr);
      $sql = "SELECT {$fieldAsArr}
              FROM pelaporan_iktl input_
              LEFT JOIN pelaporan_iktl_revisi_pusat revisi_rth_ ON input_.uid_pelaporan_iktl = revisi_rth_.uid_pelaporan_iktl AND revisi_rth_.jenis_data = 'RTH' AND revisi_rth_.deleted = 0
              LEFT JOIN pelaporan_iktl_revisi_pusat revisi_rhl_ ON input_.uid_pelaporan_iktl = revisi_rhl_.uid_pelaporan_iktl AND revisi_rhl_.jenis_data = 'RHL' AND revisi_rhl_.deleted = 0
              WHERE {$wPelaporan}";
      $data = $this->query($sql)["data"][0];
      $dataReturn = null;
      foreach ($fieldAs as $key => $value) {
        foreach ($this->field_use_2025 as $ki => $vi) {
          $dataReturn["origin"][$value][$vi] = $data["{$value}{$vi}"];
          $dataReturn["koefisien"][$value][$vi] = $data["{$value}{$vi}_x_koefisien"];

					if($key == (count($fieldAs)-1)){
						$dataReturn["origin"]["input_min_rth_rhl"][$vi] = $data["{$vi}_min_input"];
            $dataReturn["origin"]["input_plus_rth_rhl"][$vi] = $data["{$vi}_plus_input"];

						$dataReturn["koefisien"]["input_min_rth_rhl"][$vi] = $data["{$vi}_min_input_x_koefisien"];
            $dataReturn["koefisien"]["input_plus_rth_rhl"][$vi] = $data["{$vi}_plus_input_x_koefisien"];
					}

        }

        $dataReturn["origin_sum"][$value] = array_sum($dataReturn["origin"][$value]);
        $dataReturn["koefisien_sum"][$value] = array_sum($dataReturn["koefisien"][$value]);

				if($key == (count($fieldAs)-1)){
					$dataReturn["origin_sum"]["input_min_rth_rhl"] = array_sum($dataReturn["origin"]["input_min_rth_rhl"]);
					$dataReturn["origin_sum"]["input_plus_rth_rhl"] = array_sum($dataReturn["origin"]["input_plus_rth_rhl"]);

					$dataReturn["koefisien_sum"]["input_min_rth_rhl"] = array_sum($dataReturn["koefisien"]["input_min_rth_rhl"]);
					$dataReturn["koefisien_sum"]["input_plus_rth_rhl"] = array_sum($dataReturn["koefisien"]["input_plus_rth_rhl"]);
				}
      }

      $dataReturn["itl"] = ($dataReturn["koefisien_sum"]["revisi_rth_"] + $dataReturn["koefisien_sum"]["revisi_rhl_"] + $dataReturn["koefisien_sum"]["input_min_rth_rhl"])/$dataReturn["origin_sum"]["input_"];
      $dataReturn["iktl"] = ($dataReturn["itl"] > 0 ? 100-((84.3-($dataReturn["itl"]*100))*(50/54.3)) : 0);
      $dataReturn["iktl"] = ($dataReturn["iktl"] > 100 ? 100 : $dataReturn["iktl"]);
      // return $dataReturn["origin_sum"]["input_"];

      return $dataReturn;
    }
  }
?>
