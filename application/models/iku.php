<?php
	class iku extends Database{
		public function __construct(){
			$this->init();
			$this->table 	= "";
			$this->primary 	= "";
		}

    public function cnIndeks2025($tahun, $provinsi, $kabkota,$simulasi = false){
      $wPelaporan = "deleted = 0 AND tanggal BETWEEN '{$tahun}-01-01' AND '{$tahun}-12-31'";
			if($provinsi > 0){
				$wPelaporan .= " AND uid_provinsi = {$provinsi}";
			}
			if($kabkota > 0){
				$wPelaporan .= " AND uid_kabkota = {$kabkota}";
			}
			$wPelaporan .= " AND v_pusat = 1 ";
			// $wPelaporan .= " AND v_pusat < 2 "; //jika uji coba
			if(!$simulasi){
				$wPelaporan .= " AND IF(role_user = 2, v_regional = 1, v_pusat = 1) AND IF(role_user = 3, v_provinsi = 1, v_pusat = 1)";
			}

      $sql = "SELECT uid_lokasi_pemantauan, kode_lokasi, uid_provinsi, uid_kabkota,  YEAR(tanggal) AS tahun,

                SUM(CASE WHEN no2_uid_metode_pemantauan = 1 THEN no2_durasi_pemantauan END) AS no2_durasi_manual_aktif,
								AVG(CASE WHEN no2_uid_metode_pemantauan = 1 THEN no2 END) AS no2_avg_manual_aktif,
								GROUP_CONCAT(CASE WHEN no2_uid_metode_pemantauan = 1 THEN no2 END) AS no2_group_manual_aktif,
                COUNT(CASE WHEN no2_uid_metode_pemantauan = 1 AND no2 > 0 THEN no2 END) AS no2_count_manual_aktif,
								SUM(CASE WHEN no2_uid_metode_pemantauan != 1 THEN no2 END) AS no2_sum_non_manual_aktif,
                COUNT(CASE WHEN no2_uid_metode_pemantauan != 1 AND no2 > 0 THEN uid_pelaporan_iku END) AS no2_count_non_manual_aktif,

                SUM(CASE WHEN so2_uid_metode_pemantauan = 1 THEN so2_durasi_pemantauan END) AS so2_durasi_manual_aktif,
                AVG(CASE WHEN so2_uid_metode_pemantauan = 1 THEN so2 END) AS so2_avg_manual_aktif,
								GROUP_CONCAT(CASE WHEN so2_uid_metode_pemantauan = 1 THEN so2 END) AS so2_group_manual_aktif,
								COUNT(CASE WHEN so2_uid_metode_pemantauan = 1 AND so2 > 0 THEN so2 END) AS so2_count_manual_aktif,
                SUM(CASE WHEN so2_uid_metode_pemantauan != 1 THEN so2 END) AS so2_sum_non_manual_aktif,
								COUNT(CASE WHEN so2_uid_metode_pemantauan != 1 AND so2 > 0 THEN uid_pelaporan_iku END) AS so2_count_non_manual_aktif,

                SUM(CASE WHEN pm25_uid_metode_pemantauan = 1 THEN pm25_durasi_pemantauan END) AS pm25_durasi_manual_aktif,
                AVG(CASE WHEN pm25_uid_metode_pemantauan = 1 THEN pm25 END) AS pm25_avg_manual_aktif,
								GROUP_CONCAT(CASE WHEN pm25_uid_metode_pemantauan = 1 THEN pm25 END) AS pm25_group_manual_aktif,
								COUNT(CASE WHEN pm25_uid_metode_pemantauan = 1 AND pm25 > 0 THEN pm25 END) AS pm25_count_manual_aktif,
								SUM(CASE WHEN pm25_uid_metode_pemantauan != 1 THEN CASE WHEN pm25 = 0 AND pm25_np_satelit != 0 THEN pm25_np_satelit WHEN pm25 != 0 THEN pm25 ELSE NULL END END) AS pm25_sum_non_manual_aktif,
								COUNT(CASE WHEN pm25_uid_metode_pemantauan != 1 AND (pm25 > 0 OR (pm25 = 0 AND pm25_np_satelit > 0)) THEN uid_pelaporan_iku END) AS pm25_count_non_manual_aktif


              FROM v_pelaporan_iku WHERE {$wPelaporan} GROUP BY uid_lokasi_pemantauan ORDER BY uid_lokasi_pemantauan, uid_rf_peruntukan ASC";

      $dataPelaporanGroup = $this->query($sql);
			$dataSql = $dataPelaporanGroup;
      $_dataPelaporanGroup = [];
      $parameters = ["no2", "so2", "pm25"];
      foreach ($dataPelaporanGroup["data"] as $key => $value) {
				$uid_provinsi = $value["uid_provinsi"];
				$uid_kabkota = $value["uid_kabkota"];

				$_dataPelaporanGroup[$key]["uid_provinsi"] = $value["uid_provinsi"];
				$_dataPelaporanGroup[$key]["uid_kabkota"] = $value["uid_kabkota"];
				$_dataPelaporanGroup[$key]["uid_lokasi_pemantauan"] = $value["uid_lokasi_pemantauan"];
        $_dataPelaporanGroup[$key]["kode_lokasi"] = $value["kode_lokasi"];
        $_dataPelaporanGroup[$key]["tahun"] = $value["tahun"];
        foreach ($parameters as $ki => $vi) {

					//jika nilai manual aktif dalam 1 titik lebih dari 1 nilai
					$manualAktifGroup = [];
					$manualAktifKoreksiGroup = [];
					if($value["{$vi}_count_manual_aktif"] > 0){
						$manualAktifGroup = explode(",", $value["{$vi}_group_manual_aktif"]);
						foreach ($manualAktifGroup as $kj => $vj) {
							$manualAktifKoreksiGroup[] = $this->_faktorKoreksiManualAktif($vi, $vj, $value["{$vi}_durasi_manual_aktif"]);
						}
					}
					//end

          $_dataPelaporanGroup[$key]["{$vi}"] = array(
						"metode_manual_aktif_group" => $manualAktifGroup,
						"metode_manual_aktif_koreksi_group" => $manualAktifKoreksiGroup,
            "metode_manual_aktif" => $value["{$vi}_avg_manual_aktif"] ?? "",
            "metode_manual_aktif_durasi" => $value["{$vi}_durasi_manual_aktif"] ?? "",
            "metode_manual_aktif_koreksi" => $this->_faktorKoreksiManualAktif($vi, $value["{$vi}_avg_manual_aktif"], $value["{$vi}_durasi_manual_aktif"]),
						"metode_non_manual_aktif" => $value["{$vi}_sum_non_manual_aktif"] ?? "",
            "metode_non_manual_aktif_count" => $value["{$vi}_count_non_manual_aktif"] ?? 0,
            "rataan_konsentrasi" => 0,
          );

          // =IF(COUNT(H2:H3;K2:K3)<1;"";AVERAGE(H2:H3;K2:K3))
					$nilai_group = $_dataPelaporanGroup[$key]["{$vi}"]["metode_manual_aktif_koreksi_group"];
          $nilai_group[] = $_dataPelaporanGroup[$key]["{$vi}"]["metode_non_manual_aktif"];
          $values = array_filter($nilai_group, function ($v) {return is_numeric($v) && $v > 0;});


          // $manual_aktif = $_dataPelaporanGroup[$key]["{$vi}"]["metode_manual_aktif_koreksi"];
          // $non_manual_aktif = $_dataPelaporanGroup[$key]["{$vi}"]["metode_non_manual_aktif"];
          // $values = array_filter([$manual_aktif, $non_manual_aktif], function ($v) {return is_numeric($v) && $v > 0;});


          if (count($values) < 1) {
              $_dataPelaporanGroup[$key]["{$vi}"]["rataan_konsentrasi"] = "";
          } else {

							//titik
							$countData = count($values) + ($value["{$vi}_count_non_manual_aktif"] > 0 ? $value["{$vi}_count_non_manual_aktif"] - 1 : 0 );
							$_dataPelaporanGroup[$key]["{$vi}"]["rataan_konsentrasi"] = array_sum($values) / $countData;

							//kabkota
							$_dataPelaporanGroupKabkota["{$vi}"]["rataan_konsentrasi_list"][] = $_dataPelaporanGroup[$key]["{$vi}"]["rataan_konsentrasi"];
							$_dataPelaporanGroupKabkota["{$vi}"]["rataan_konsentrasi"] = array_sum($_dataPelaporanGroupKabkota["{$vi}"]["rataan_konsentrasi_list"])/count($_dataPelaporanGroupKabkota["{$vi}"]["rataan_konsentrasi_list"]);

							//provinsi
							if($_dataPelaporanGroupKabkota["{$vi}"]["rataan_konsentrasi"] > 0){
								$_dataPelaporanGroupProvinsi["{$vi}"]["rataan_konsentrasi_list_by_kabkota"][$uid_kabkota][] = $_dataPelaporanGroup[$key]["{$vi}"]["rataan_konsentrasi"];
								$_dataPelaporanGroupProvinsi["{$vi}"]["rataan_konsentrasi_list"][$uid_kabkota] = array_sum($_dataPelaporanGroupProvinsi["{$vi}"]["rataan_konsentrasi_list_by_kabkota"][$uid_kabkota])/count($_dataPelaporanGroupProvinsi["{$vi}"]["rataan_konsentrasi_list_by_kabkota"][$uid_kabkota]);
								$_dataPelaporanGroupProvinsi["{$vi}"]["rataan_konsentrasi"] = array_sum($_dataPelaporanGroupProvinsi["{$vi}"]["rataan_konsentrasi_list"])/count($_dataPelaporanGroupProvinsi["{$vi}"]["rataan_konsentrasi_list"]);
							}

							// //nasional
							// if($_dataPelaporanGroupProvinsi["{$vi}"]["rataan_konsentrasi"] > 0){
							// 	$_dataPelaporanGroupNasional["{$vi}"]["rataan_konsentrasi_list"][$uid_provinsi] = $_dataPelaporanGroupProvinsi["{$vi}"]["rataan_konsentrasi"];
							// 	$_dataPelaporanGroupNasional["{$vi}"]["rataan_konsentrasi"] = array_sum($_dataPelaporanGroupNasional["{$vi}"]["rataan_konsentrasi_list"])/count($_dataPelaporanGroupNasional["{$vi}"]["rataan_konsentrasi_list"]);
							// }

          }

          // =IF(AND(ISNUMBER(N2);N2>0);(N2/50);"")

					$bobotParameter = ($vi == "no2" ? 50 : ($vi == "so2" ? 45 : ($vi == "pm25" ? 15 : 0)));
					//titik
          $rataan_konsentrasi = $_dataPelaporanGroup[$key]["{$vi}"]["rataan_konsentrasi"];
          if (is_numeric($rataan_konsentrasi) && $rataan_konsentrasi > 0) {
              $_dataPelaporanGroup[$key]["{$vi}"]["indeks_konsentrasi"] = $rataan_konsentrasi / $bobotParameter;
          } else {
              $_dataPelaporanGroup[$key]["{$vi}"]["indeks_konsentrasi"] = "";
          }

					//kabakota
          $rataan_konsentrasi_kabkota = $_dataPelaporanGroupKabkota["{$vi}"]["rataan_konsentrasi"];
          if (is_numeric($rataan_konsentrasi_kabkota) && $rataan_konsentrasi_kabkota > 0) {
              $_dataPelaporanGroupKabkota["{$vi}"]["indeks_konsentrasi"] = $rataan_konsentrasi_kabkota / $bobotParameter;
          } else {
              $_dataPelaporanGroupKabkota["{$vi}"]["indeks_konsentrasi"] = "";
          }

					//provinsi
          $rataan_konsentrasi_provinsi = $_dataPelaporanGroupProvinsi["{$vi}"]["rataan_konsentrasi"];
          if (is_numeric($rataan_konsentrasi_provinsi) && $rataan_konsentrasi_provinsi > 0) {
              $_dataPelaporanGroupProvinsi["{$vi}"]["indeks_konsentrasi"] = $rataan_konsentrasi_provinsi / $bobotParameter;
          } else {
              $_dataPelaporanGroupProvinsi["{$vi}"]["indeks_konsentrasi"] = "";
          }

					// //nasional
          // $rataan_konsentrasi_nasional = $_dataPelaporanGroupNasional["{$vi}"]["rataan_konsentrasi"];
          // if (is_numeric($rataan_konsentrasi_nasional) && $rataan_konsentrasi_nasional > 0) {
          //     $_dataPelaporanGroupNasional["{$vi}"]["indeks_konsentrasi"] = $rataan_konsentrasi_nasional / $bobotParameter;
          // } else {
          //     $_dataPelaporanGroupNasional["{$vi}"]["indeks_konsentrasi"] = "";
          // }

        }

        // =IF(OR(Q2="";R2="";S2="");"";AVERAGE(Q2;R2;S2))

				//titik
        $no2 = $_dataPelaporanGroup[$key]["no2"]["indeks_konsentrasi"];
        $so2 = $_dataPelaporanGroup[$key]["so2"]["indeks_konsentrasi"];
        $pm25 = $_dataPelaporanGroup[$key]["pm25"]["indeks_konsentrasi"];
        if ($no2 === "" || $so2 === "" || $pm25 === "") {
            $_dataPelaporanGroup[$key]["indeks_rataan"] = "";
        } else {
            $_dataPelaporanGroup[$key]["indeks_rataan"] = ($no2 + $so2 + $pm25) / 3;
        }

				//kabkota
				$no2 = $_dataPelaporanGroupKabkota["no2"]["indeks_konsentrasi"];
        $so2 = $_dataPelaporanGroupKabkota["so2"]["indeks_konsentrasi"];
        $pm25 = $_dataPelaporanGroupKabkota["pm25"]["indeks_konsentrasi"];
        if ($no2 === "" || $so2 === "" || $pm25 === "") {
            $_dataPelaporanGroupKabkota["indeks_rataan"] = "";
        } else {
            $_dataPelaporanGroupKabkota["indeks_rataan"] = ($no2 + $so2 + $pm25) / 3;
        }

				//provinsi
				$no2 = $_dataPelaporanGroupProvinsi["no2"]["indeks_konsentrasi"];
        $so2 = $_dataPelaporanGroupProvinsi["so2"]["indeks_konsentrasi"];
        $pm25 = $_dataPelaporanGroupProvinsi["pm25"]["indeks_konsentrasi"];
        if ($no2 === "" || $so2 === "" || $pm25 === "") {
            $_dataPelaporanGroupProvinsi["indeks_rataan"] = "";
        } else {
            $_dataPelaporanGroupProvinsi["indeks_rataan"] = ($no2 + $so2 + $pm25) / 3;
        }
				//
				// //nasional
				// $no2 = $_dataPelaporanGroupNasional["no2"]["indeks_konsentrasi"];
        // $so2 = $_dataPelaporanGroupNasional["so2"]["indeks_konsentrasi"];
        // $pm25 = $_dataPelaporanGroupNasional["pm25"]["indeks_konsentrasi"];
        // if ($no2 === "" || $so2 === "" || $pm25 === "") {
        //     $_dataPelaporanGroupNasional["indeks_rataan"] = "";
        // } else {
        //     $_dataPelaporanGroupNasional["indeks_rataan"] = ($no2 + $so2 + $pm25) / 3;
        // }


        // =IF(T2="";"";100-((50/0,99)*(T2-0,01)))
        // =IF(U2<=60;"BURUK";IF(U2<=85;"SEDANG";IF(U2<=100;"BAIK";"")))

				//titik
        $indeks_rataan = $_dataPelaporanGroup[$key]["indeks_rataan"];
        if ($indeks_rataan === "" || $indeks_rataan === null) {
          $_dataPelaporanGroup[$key]["iku"] = "";
          $_dataPelaporanGroup[$key]["iku_kategori"] = "";
        } else {
            $result = 100 - ((50 / 0.99) * ($indeks_rataan - 0.01));
						$_dataPelaporanGroup[$key]["iku"] = $result;
            $_dataPelaporanGroup[$key]["iku_kategori"] = ($result <= 60 ? "BURUK" : ($result <= 85 ? "SEDANG" : ($result <= 100 ? "BAIK" : "")));

						$_dataPelaporanGroupAll[$key] = $result;
        }

				//kabkota
				$indeks_rataan = $_dataPelaporanGroupKabkota["indeks_rataan"];
        if ($indeks_rataan === "" || $indeks_rataan === null) {
          $_dataPelaporanGroupKabkota["iku"] = "";
          $_dataPelaporanGroupKabkota["iku_kategori"] = "";
        } else {
            $result = 100 - ((50 / 0.99) * ($indeks_rataan - 0.01));
            $_dataPelaporanGroupKabkota["iku"] = $result;
            $_dataPelaporanGroupKabkota["iku_kategori"] = ($result <= 60 ? "BURUK" : ($result <= 85 ? "SEDANG" : ($result <= 100 ? "BAIK" : "")));
        }

				//provinsi
				$indeks_rataan = $_dataPelaporanGroupProvinsi["indeks_rataan"];
        if ($indeks_rataan === "" || $indeks_rataan === null) {
          $_dataPelaporanGroupProvinsi["iku"] = "";
          $_dataPelaporanGroupProvinsi["iku_kategori"] = "";
        } else {
            $result = 100 - ((50 / 0.99) * ($indeks_rataan - 0.01));
            $_dataPelaporanGroupProvinsi["iku"] = $result;
            $_dataPelaporanGroupProvinsi["iku_kategori"] = ($result <= 60 ? "BURUK" : ($result <= 85 ? "SEDANG" : ($result <= 100 ? "BAIK" : "")));
        }
				// $result = array_sum($_dataPelaporanGroupAll)/count($_dataPelaporanGroupAll);
				// $_dataPelaporanGroupProvinsi["iku"] = $result;
				// $_dataPelaporanGroupProvinsi["iku_kategori"] = ($result <= 60 ? "BURUK" : ($result <= 85 ? "SEDANG" : ($result <= 100 ? "BAIK" : "")));

				//nasional
				// $indeks_rataan = $_dataPelaporanGroupNasional["indeks_rataan"];
        // if ($indeks_rataan === "" || $indeks_rataan === null) {
        //   $_dataPelaporanGroupNasional["iku"] = "";
        //   $_dataPelaporanGroupNasional["iku_kategori"] = "";
        // } else {
        //     $result = 100 - ((50 / 0.99) * ($indeks_rataan - 0.01));
        //     $_dataPelaporanGroupNasional["iku"] = $result;
        //     $_dataPelaporanGroupNasional["iku_kategori"] = ($result <= 60 ? "BURUK" : ($result <= 85 ? "SEDANG" : ($result <= 100 ? "BAIK" : "")));
        // }
				$result = array_sum($_dataPelaporanGroupAll)/count($_dataPelaporanGroupAll);
				$_dataPelaporanGroupNasional["iku"] = $result;
				$_dataPelaporanGroupNasional["iku_kategori"] = ($result <= 60 ? "BURUK" : ($result <= 85 ? "SEDANG" : ($result <= 100 ? "BAIK" : "")));

      }
			// return $_dataPelaporanGroup;
			if($kabkota > 0 && $simulasi == false){
				$this->insertTitik($_dataPelaporanGroup, $kabkota);
			}


      return array(
				"sql"=>$dataSql,
				"titik"=> $_dataPelaporanGroup,
				"kabkota" => $_dataPelaporanGroupKabkota,
				"provinsi" => $_dataPelaporanGroupProvinsi,
				"nasional" => $_dataPelaporanGroupNasional,
			);
    }

		private function insertTitik($data, $kabkota){
			$this->query("UPDATE indeks_iku_lokasi SET deleted = 1 WHERE uid_kabkota = {$kabkota}");

			$dataSubmit = [];
			foreach ($data as $key => $value) {
				$dataSubmit[$key]["crdate"] = time();
				$dataSubmit[$key]["chdate"] = time();

				$dataSubmit[$key]["deleted"] = 0;
				$dataSubmit[$key]["uid_provinsi"] = $value["uid_provinsi"];
				$dataSubmit[$key]["uid_kabkota"] = $value["uid_kabkota"];

				$dataSubmit[$key]["uid_lokasi_pemantauan"] = $value["uid_lokasi_pemantauan"];
				$dataSubmit[$key]["tahun"] = $value["tahun"];
				$dataSubmit[$key]["iku"] = $value["iku"];
				$dataSubmit[$key]["iku_kategori"] = $value["iku_kategori"];
				$dataSubmit[$key]["indeks_rataan"] = $value["indeks_rataan"];

				$dataSubmit[$key]["rataan_konsentrasi_no2"] = $value["no2"]["rataan_konsentrasi"];
				$dataSubmit[$key]["rataan_konsentrasi_so2"] = $value["so2"]["rataan_konsentrasi"];
				$dataSubmit[$key]["rataan_konsentrasi_pm25"] = $value["pm25"]["rataan_konsentrasi"];
				$dataSubmit[$key]["indeks_so2"] = $value["no2"]["indeks_konsentrasi"];
				$dataSubmit[$key]["indeks_no2"] = $value["so2"]["indeks_konsentrasi"];
				$dataSubmit[$key]["indeks_pm25"] = $value["pm25"]["indeks_konsentrasi"];
			}
			$tmpSql = $this->generate_sql_insert("indeks_iku_lokasi",$dataSubmit,"uid_indeks_iku_lokasi");
			$this->query($tmpSql);
		}

    private function _faktorKoreksiManualAktif($parameter, $nilai, $durasi){
			$durasi = $durasi > 24 ? 24 : $durasi;
      //PM25 =ROUND(IFS($G2=1;J2*1,1337;$G2=2;J2*1,1318;$G2=4;J2*1,1282;$G2=6;J2*1,1246;$G2=12;J2*1,1144;$G2=24;J2*1);2)
      // NO2,SO2 =ROUND(IFS($G2=1;H2*2,48;$G2=2;H2*2;$G2=4;H2*1,92;$G2=6;H2*1,76;$G2=12;H2*1,48;$G2=24;H2*1);2)
      if($durasi == 1){
        $result = $nilai * ($parameter != "pm25" ? 2.48 : 1.1337 );
      }elseif ($durasi == 2) {
        $result = $nilai * ($parameter != "pm25" ? 2 : 1.1318 );
      }elseif ($durasi == 4) {
        $result = $nilai * ($parameter != "pm25" ? 1.92 : 1.1282 );
      }elseif ($durasi == 6) {
        $result = $nilai * ($parameter != "pm25" ? 1.76 : 1.1246 );
      }elseif ($durasi == 12) {
        $result = $nilai * ($parameter != "pm25" ? 1.48 : 1.1144 );
      }elseif ($durasi == 24) {
        $result = $nilai * ($parameter != "pm25" ? 1 : 1 );
      }else{
        $result = 0;
      }

			$result = round($result, 2);
      return $result;
    }

		private function generate_sql_insert($table, $data, $primaryKey = 'id') {
				if (empty($data)) return '';

				$fields = array_keys($data[0]);
				$columns = '`' . implode('`, `', $fields) . '`';
				$values = [];
				foreach ($data as $row) {
						$escaped_values = array_map(function($val) {
								if (is_null($val)) return 'NULL';
								return "'" . addslashes($val) . "'";
						}, array_values($row));

						$values[] = '(' . implode(', ', $escaped_values) . ')';
				}
				$update_fields = array_filter($fields, fn($field) => $field !== $primaryKey);
				$update_sql = implode(', ', array_map(fn($field) => "`$field`=VALUES(`$field`)", $update_fields));
				$sql = "INSERT INTO `$table` ($columns) VALUES \n" . implode(",\n", $values) . "\n";
				$sql .= "ON DUPLICATE KEY UPDATE $update_sql;";
				return $sql;
		}


  }
?>
