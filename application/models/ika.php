<?php
	class ika extends Database{
		public function __construct(){
			$this->init();
			$this->table 	= "";
			$this->primary 	= "";
		}

    public function parameterIndeks2024(){
      $parameterSql = "
        if(a.kategori = 2, ABS(a.ph-(b.ph_max+b.ph_min_gambut)/2) / (b.ph_max-(b.ph_max+b.ph_min_gambut)/2), ABS(a.ph-(b.ph_max+b.ph_min)/2) / (b.ph_max-(b.ph_max+b.ph_min)/2)) AS ph,

        ABS(a.bod/b.bod) AS bod,
        ABS(a.cod/b.cod) AS cod,
        ABS(a.tss/b.tss) AS tss,
        ABS(a.do_p/b.do) AS do,
        ABS(((a.do_max_p-a.do_p)/(a.do_max_p-b.do))/b.do) AS do_max_p,
        ABS(a.no3_n/b.no3_n) AS no3_n,
        ABS(a.total_phosphat/b.total_phosphat_danau) AS total_phosphat_danau,
        ABS(a.total_phosphat/b.total_phosphat) AS total_phosphat,
        ABS(a.fecal_coliform/b.fecal_coliform) AS fecal_coliform,
        ABS(a.kecerahan/b.kecerahan) AS kecerahan,
        ABS(a.klorofil_a/b.klorofil_a) AS klorofil_a,
        ABS(a.total_nitrogen/b.total_nitrogen_danau) AS total_nitrogen_danau,
        ABS(a.total_nitrogen/b.total_nitrogen) AS total_nitrogen,

        if(a.kategori = 2,
          if((ABS(a.ph-(b.ph_max+b.ph_min_gambut)/2) / (b.ph_max-(b.ph_max+b.ph_min_gambut)/2)) > 1, (1+(5* LOG(10,ABS(a.ph-(b.ph_max+b.ph_min_gambut)/2) / (b.ph_max-(b.ph_max+b.ph_min_gambut)/2))))  ,(ABS(a.ph-(b.ph_max+b.ph_min_gambut)/2) / (b.ph_max-(b.ph_max+b.ph_min_gambut)/2))),
            if((ABS(a.ph-(b.ph_max+b.ph_min)/2) / (b.ph_max-(b.ph_max+b.ph_min)/2)) > 1, (1+(5* LOG(10,ABS(a.ph-(b.ph_max+b.ph_min)/2) / (b.ph_max-(b.ph_max+b.ph_min)/2))))  ,(ABS(a.ph-(b.ph_max+b.ph_min)/2) / (b.ph_max-(b.ph_max+b.ph_min)/2))
          )
        ) AS ph_l,

        if((ABS(a.bod/b.bod)) > 1, (1+(5* LOG(10,ABS(a.bod/b.bod))))  ,(ABS(a.bod/b.bod))) AS bod_l,

        if((ABS(a.cod/b.cod)) > 1, (1+(5* LOG(10,ABS(a.cod/b.cod))))  ,(ABS(a.cod/b.cod))) AS cod_l,

        if((ABS(a.tss/b.tss)) > 1, (1+(5* LOG(10,ABS(a.tss/b.tss))))  ,(ABS(a.tss/b.tss))) AS tss_l,

        if((ABS(((a.do_max_p-a.do_p)/(a.do_max_p-b.do))/b.do)) > 1, (1+(5* LOG(10,ABS(((a.do_max_p-a.do_p)/(a.do_max_p-b.do))/b.do))))  ,(ABS(((a.do_max_p-a.do_p)/(a.do_max_p-b.do))/b.do))) AS do_l,

        if((ABS(a.no3_n/b.no3_n)) > 1, (1+(5* LOG(10,ABS(a.no3_n/b.no3_n))))  ,ABS(a.no3_n/b.no3_n)) AS no3_n_l,

        if((ABS(a.total_phosphat/b.total_phosphat_danau)) > 1, (1+(5* LOG(10,ABS(a.total_phosphat/b.total_phosphat_danau))))  ,(ABS(a.total_phosphat/b.total_phosphat_danau))) AS total_phosphat_danau_l,

        if((ABS(a.total_phosphat/b.total_phosphat)) > 1, (1+(5* LOG(10,ABS(a.total_phosphat/b.total_phosphat))))  ,(ABS(a.total_phosphat/b.total_phosphat))) AS total_phosphat_l,

        if((ABS(a.fecal_coliform/b.fecal_coliform)) > 1, (1+(5* LOG(10,ABS(a.fecal_coliform/b.fecal_coliform))))  ,(ABS(a.fecal_coliform/b.fecal_coliform))) AS fecal_coliform_l,

        if((ABS(a.kecerahan/b.kecerahan)) > 1, (1+(5* LOG(10,ABS(a.kecerahan/b.kecerahan))))  ,(ABS(a.kecerahan/b.kecerahan))) AS kecerahan_l,

        if((ABS(a.klorofil_a/b.klorofil_a)) > 1, (1+(5* LOG(10,ABS(a.klorofil_a/b.klorofil_a))))  ,(ABS(a.klorofil_a/b.klorofil_a))) AS klorofil_a_l,

        if((ABS(a.total_nitrogen/b.total_nitrogen_danau)) > 1, (1+(5* LOG(10,ABS(a.total_nitrogen/b.total_nitrogen_danau))))  ,(ABS(a.total_nitrogen/b.total_nitrogen_danau))) AS total_nitrogen_danau_l,

        if((ABS(a.total_nitrogen/b.total_nitrogen)) > 1, (1+(5* LOG(10,ABS(a.total_nitrogen/b.total_nitrogen))))  ,(ABS(a.total_nitrogen/b.total_nitrogen))) AS total_nitrogen_l

      ";

      return $parameterSql;
    }

    public function cnIndeks2024($cnIndeks){
      $cnIndeks['jumlahTitik']['berat'] = 0;
      $cnIndeks['jumlahTitik']['sedang'] = 0;
      $cnIndeks['jumlahTitik']['ringan'] = 0;
      $cnIndeks['jumlahTitik']['memenuhi'] = 0;

      $tmpMemenuhi = [];

      foreach ($cnIndeks['data'] as $key => $value) {
					$tmpCn['countParams'][$key]['ph_l'] = $value['ph_l'];
					$tmpCn['countParams'][$key]['bod_l'] = $value['bod_l'];
					$tmpCn['countParams'][$key]['cod_l'] = $value['cod_l'];
					$tmpCn['countParams'][$key]['tss_l'] = $value['tss_l'];
					$tmpCn['countParams'][$key]['do_l'] = $value['do_l'];
					$tmpCn['countParams'][$key]['no3_n_l'] = $value['no3_n_l'];
					$tmpCn['countParams'][$key]['total_phosphat_l'] = ($value['kategori'] == 3 ? $value['total_phosphat_danau_l'] : $value['total_phosphat_l']);
					$tmpCn['countParams'][$key]['fecal_coliform_l'] = $value['fecal_coliform_l'];
					$tmpCn['countParams'][$key]['kecerahan_l'] = $value['kecerahan_l'];
					$tmpCn['countParams'][$key]['klorofil_a_l'] = $value['klorofil_a_l'];
					$tmpCn['countParams'][$key]['total_nitrogen_l'] = ($value['kategori'] == 3 ? $value['total_nitrogen_danau_l'] : $value['total_nitrogen_l']);

          if ($value['kategori'] == 3) {
              unset($tmpCn['countParams'][$key]['no3_n_l']);
          } else {
              unset($tmpCn['countParams'][$key]['kecerahan_l']);
              unset($tmpCn['countParams'][$key]['klorofil_a_l']);
              unset($tmpCn['countParams'][$key]['total_nitrogen_l']);
          }

          //nilai
          $cnIndeks['data'][$key]['nilai_rataan'] = array_sum($tmpCn['countParams'][$key]) / count($tmpCn['countParams'][$key]);
          $cnIndeks['data'][$key]['nilai_maksimum'] = max($tmpCn['countParams'][$key]);
          $cnIndeks['data'][$key]['nilai_pij'] = sqrt((pow($cnIndeks['data'][$key]['nilai_rataan'], 2) + pow($cnIndeks['data'][$key]['nilai_maksimum'], 2)) / 2);

          if ($cnIndeks['data'][$key]['nilai_pij'] > 10) {
              $cnIndeks['jumlahTitik']['berat']++;
              $cnIndeks['data'][$key]['status_mutu'] = "CEMAR BERAT";
          } elseif ($cnIndeks['data'][$key]['nilai_pij'] > 5 && $cnIndeks['data'][$key]['nilai_pij'] <= 10) {
              $cnIndeks['jumlahTitik']['sedang']++;
              $cnIndeks['data'][$key]['status_mutu'] = "CEMAR SEDANG";
          } elseif ($cnIndeks['data'][$key]['nilai_pij'] > 1 && $cnIndeks['data'][$key]['nilai_pij'] <= 5) {
              $cnIndeks['jumlahTitik']['ringan']++;
              $cnIndeks['data'][$key]['status_mutu'] = "CEMAR RINGAN";
          } elseif ($cnIndeks['data'][$key]['nilai_pij'] >= 0 && $cnIndeks['data'][$key]['nilai_pij'] <= 1) {
              $cnIndeks['jumlahTitik']['memenuhi']++;
              $cnIndeks['data'][$key]['status_mutu'] = "MEMENUHI";

              $tmpMemenuhi[] = $cnIndeks['data'][$key];
          }

          $cnIndeks['nilaiIndeksPerMutu']['memenuhi'] = ($cnIndeks['jumlahTitik']['memenuhi'] / array_sum($cnIndeks['jumlahTitik'])) * 70;
          $cnIndeks['nilaiIndeksPerMutu']['ringan'] = ($cnIndeks['jumlahTitik']['ringan'] / array_sum($cnIndeks['jumlahTitik'])) * 50;
          $cnIndeks['nilaiIndeksPerMutu']['sedang'] = ($cnIndeks['jumlahTitik']['sedang'] / array_sum($cnIndeks['jumlahTitik'])) * 30;
          $cnIndeks['nilaiIndeksPerMutu']['berat'] = ($cnIndeks['jumlahTitik']['berat'] / array_sum($cnIndeks['jumlahTitik'])) * 10;
          //status
      }

      return array(
        "memenuhi" => $tmpMemenuhi,
        "cnIndeks" => $cnIndeks
      );
    }

    public function parameterIndeks2025(){
      $parameterSql = "
				a.uid_lokasi_pemantauan,

				if(a.kategori = 2,
					if(a.ph <= 4.21 , (-0.0375 * (POW((a.ph + 2.79),5))) + (0.5379 * (POW((a.ph + 2.79),4))) - (1.8352 * (POW((a.ph + 2.79),3))) + (0.1667 * (POW((a.ph + 2.79),2))) + (7.8273 * (a.ph + 2.79)) - 9.5327 ,
						if(a.ph <= 7 , (1.4337 * a.ph) + 77.9642 ,
							if(a.ph <= 8 , (-4 * a.ph) + 116 ,
								if(a.ph <= 13 , (-0.463 * (POW(a.ph,3))) + (19.155 * (POW(a.ph,2))) - (263.07 * a.ph) + 1200.4 ,
									0
								)
							)
						)
					)
					,
					if(a.ph <= 1, 0 ,
					 if(a.ph <= 7, (-0.0375 * (POW(a.ph,5))) + (0.5379 * (POW(a.ph,4))) - (1.8352 * (POW(a.ph,3))) + (0.1667 * (POW(a.ph,2))) + (7.8273 * a.ph) - 6.7143 ,
					   if(a.ph <= 8, (-4 * a.ph) + 116 ,
					     if(a.ph <= 13, (-0.463 * (POW(a.ph,3))) + (19.155 * (POW(a.ph,2))) - (263.07 * a.ph) + 1200.4 , 0)
					   )
					 )
					)
				) as ph_l

        ,if(a.bod <= 7, (-0.25 * (POW(a.bod,3))) + (4.0952 * (POW(a.bod,2))) - (26.726 * a.bod) + 118.14 ,
          if(a.bod <= 32, (6 * ((POW(10,(-5))) * (POW(a.bod,4)))) - (0.0067 * (POW(a.bod,3))) + (0.3286 * (POW(a.bod,2))) - (8.3016 * a.bod) + 90.378, 0)
        ) AS bod_l

        ,if(a.cod <= 20, (0.0204 * (POW(a.cod,2))) - (1.4479 * a.cod) + 99.614,
          if(a.cod <= 25, (-2.9803 * a.cod) + 138.43,
            if(a.cod <= 50, (-0.9054 * a.cod) + 86.555,
              if(a.cod <= 100, (-0.0055 * (POW(a.cod,2))) + (0.2907 * a.cod) + 40.428,
                if(a.cod <= 150, (0.0088 * (POW(a.cod,2))) - (2.4487 * a.cod) + 171.57, 0)
              )
            )
          )
        ) as cod_l

        ,if(a.tss <= 50, (-0.06 * a.tss) + 90,
          if(a.tss <= 60, 87,
            if(a.tss <= 100, (-4 * ((POW(10,(-16))) * (POW(a.tss,2)))) - (0.1 * a.tss) + 93,
              if(a.tss <= 150, (-0.08 * a.tss) + 91,
                if(a.tss <= 450, (-3 * ((POW(10,(-5))) * (POW(a.tss,2)))) - (0.1145 * a.tss) + 96.81,
                  if(a.tss <= 500, (-0.18 * a.tss) + 121,
                    if(a.tss <= 501, (-11 * a.tss) + 5531,
                      if(a.tss <= 2000, 20, 0)
                    )
                  )
                )
              )
            )
          )
        ) AS tss_l

        ,if(a.do_p <= 2, (-0.6574 * (POW(a.do_p,2))) + (10.157 * a.do_p) + (7 * (POW(10,(-15)))),
          if(a.do_p <= 7, (-0.023 * (POW(a.do_p,3))) - (0.9933 * (POW(a.do_p,2))) + (26.124 * a.do_p) - 30.173,
            if(a.do_p <= 8.5, (1.2438 * a.do_p) + 87.428,
              if(a.do_p <= 9, 98,
                if(a.do_p <= 11, (8.0809 * (POW(a.do_p,3))) - (227.43 * (POW(a.do_p,2))) + (2101.2 * a.do_p) - 6300.1, 0)
              )
            )
          )
        ) AS do_l

        ,if(a.no3_n <= 1, -a.no3_n + 97,
          if(a.no3_n <= 6, (0.6989 * (POW(a.no3_n,2))) - (12.05 * a.no3_n) +107.32,
            if(a.no3_n <= 15, (0.0714 * (POW(a.no3_n,2))) - (3.4111 * a.no3_n) + 78.091,
              if(a.no3_n <= 40, (-1 * (POW(10,(-16)))) * (POW(a.no3_n,3)) + (0.0071 * (POW(a.no3_n,2))) - (1.3929 * a.no3_n) + 62.214,
                if(a.no3_n <= 50, (4 * (POW(10,(-16)))) * (POW(a.no3_n,2)) - (0.8 * a.no3_n) + 50,
                  if(a.no3_n <= 60, (0.02 * (POW(a.no3_n,2))) - (2.5 * a.no3_n) + 85,
                    if(a.no3_n <= 100, (0.0029 * (POW(a.no3_n,2))) - (0.5571 * a.no3_n) + 30.114,
                      if(a.no3_n <= 101, (-2 * a.no3_n) + 203,
                        if(a.no3_n <= 200, 1, 0)
                      )
                    )
                  )
                )
              )
            )
          )
        ) AS no3_n_l

        ,if(a.total_phosphat <= 0.1, (-80 * a.total_phosphat) + 100,
          if(a.total_phosphat <= 0.8, (246.13 * (POW(a.total_phosphat,3))) - (304.86 * (POW(a.total_phosphat,2))) + (30.477 * a.total_phosphat) + 91.909,
            if(a.total_phosphat <= 5, (0.0924 * (POW(a.total_phosphat,6))) - (1.8787 * (POW(a.total_phosphat,5))) + (15.365 * (POW(a.total_phosphat,4))) - (64.708 * (POW(a.total_phosphat,3))) + (148.85 * (POW(a.total_phosphat,2))) - (184.6 * a.total_phosphat) + 126.81,
              if(a.total_phosphat <= 10, (-0.0463 * (POW(a.total_phosphat,3))) + (1.4524 * (POW(a.total_phosphat,2))) - (14.882 * a.total_phosphat) + 56.921,
                if(a.total_phosphat <= 12, (2.5 * (POW(a.total_phosphat,2))) - (57.5 * a.total_phosphat) + 332, 0)
              )
            )
          )
        ) AS total_phosphat_l

        ,if(a.fecal_coliform <= 30, (-0.004 * (POW(a.fecal_coliform,3))) + (0.2471 * (POW(a.fecal_coliform,2))) - (5.2535 * a.fecal_coliform) + 102.14,
          if(a.fecal_coliform <= 500, (3 * ((POW(10,(-9))) * (POW(a.fecal_coliform,4)))) - (4 * (POW(10,(-6))) * (POW(a.fecal_coliform,3))) + (0.0019 * (POW(a.fecal_coliform,2))) - (0.3953 * a.fecal_coliform) + 67.962,
            if(a.fecal_coliform <= 1000, (-0.014 * a.fecal_coliform) + 36,
              if(a.fecal_coliform <= 5000, (-0.002 * a.fecal_coliform) + 24,
                if(a.fecal_coliform <= 10000, (-0.0008 * a.fecal_coliform) + 18,
                  if(a.fecal_coliform <= 20000, (-0.0002 * a.fecal_coliform) + 12,
                    if(a.fecal_coliform <= 40000, (5 * ((POW(10,(-23))) * (POW(a.fecal_coliform,2)))) - (0.0001 * a.fecal_coliform) + 10,
                      if(a.fecal_coliform <= 50000, 6, 0)
                    )
                  )
                )
              )
            )
          )
        ) AS fecal_coliform_l
      ";

      return $parameterSql;
    }

    public function cnIndeks2025($cnIndeks){
      $cnIndeks['jumlahTitik']['sangat_kurang'] = 0;
      $cnIndeks['jumlahTitik']['kurang'] = 0;
      $cnIndeks['jumlahTitik']['sedang'] = 0;
      $cnIndeks['jumlahTitik']['baik'] = 0;
      $cnIndeks['jumlahTitik']['sangat_baik'] = 0;

      $tmpMemenuhi = [];

      $bobotParameter = array("ph"=>0.137, "bod"=>0.132, "cod"=>0.140, "tss"=>0.086, "do"=>0.167, "no3_n"=>0.081, "total_phosphat"=>0.100, "fecal_coliform"=>0.157);

      foreach ($cnIndeks['data'] as $key => $value) {
          $tmpCn['countParams'][$key]['ph_l'] = $value['ph_l'] * $bobotParameter['ph'];
          $tmpCn['countParams'][$key]['bod_l'] = $value['bod_l'] * $bobotParameter['bod'];
          $tmpCn['countParams'][$key]['cod_l'] = $value['cod_l'] * $bobotParameter['cod'];
          $tmpCn['countParams'][$key]['tss_l'] = $value['tss_l'] * $bobotParameter['tss'];
          $tmpCn['countParams'][$key]['do_l'] = $value['do_l'] * $bobotParameter['do'];
          $tmpCn['countParams'][$key]['no3_n_l'] = $value['no3_n_l'] * $bobotParameter['no3_n'];
          $tmpCn['countParams'][$key]['total_phosphat_l'] = $value['total_phosphat_l'] * $bobotParameter['total_phosphat'];
          $tmpCn['countParams'][$key]['fecal_coliform_l'] = $value['fecal_coliform_l'] * $bobotParameter['fecal_coliform'];

          //nilai
          // $cnIndeks['data'][$key]['nilai_rataan'] = array_sum($tmpCn['countParams'][$key]) / count($tmpCn['countParams'][$key]);
          // $cnIndeks['data'][$key]['nilai_maksimum'] = max($tmpCn['countParams'][$key]);
          $cnIndeks['data'][$key]['nilai_pij'] = array_sum($tmpCn['countParams'][$key]); //nilai ina per rows

          if ($cnIndeks['data'][$key]['nilai_pij'] >= 0 && $cnIndeks['data'][$key]['nilai_pij'] < 25) {
              $cnIndeks['jumlahTitik']['sangat_kurang']++;
              $cnIndeks['data'][$key]['status_mutu'] = "SANGAT KURANG";
          } elseif ($cnIndeks['data'][$key]['nilai_pij'] >= 25 && $cnIndeks['data'][$key]['nilai_pij'] < 50) {
              $cnIndeks['jumlahTitik']['berat']++;
              $cnIndeks['data'][$key]['status_mutu'] = "KURANG";
          } elseif ($cnIndeks['data'][$key]['nilai_pij'] >= 50 && $cnIndeks['data'][$key]['nilai_pij'] < 70) {
              $cnIndeks['jumlahTitik']['sedang']++;
              $cnIndeks['data'][$key]['status_mutu'] = "SEDANG";
          } elseif ($cnIndeks['data'][$key]['nilai_pij'] >= 70 && $cnIndeks['data'][$key]['nilai_pij'] < 90) {
            $cnIndeks['jumlahTitik']['baik']++;
            $cnIndeks['data'][$key]['status_mutu'] = "BAIK";
          } elseif ($cnIndeks['data'][$key]['nilai_pij'] >= 90 && $cnIndeks['data'][$key]['nilai_pij'] <= 100) {
              $cnIndeks['jumlahTitik']['sangat_baik']++;
              $cnIndeks['data'][$key]['status_mutu'] = "SANGAT BAIK";
              $tmpMemenuhi[] = $cnIndeks['data'][$key];
          }
          //status

					$tmpIkaIna[] = $cnIndeks['data'][$key]['nilai_pij'];

					if($cnIndeks['data'][$key]['nilai_pij']){
						$tmpPijByLokasi[$value['uid_lokasi_pemantauan']][] = $cnIndeks['data'][$key]['nilai_pij'];
					}
					if(count($tmpPijByLokasi[$value['uid_lokasi_pemantauan']])){
						$tmpPijByKabkota[$value['uid_kabkota']][$value['uid_lokasi_pemantauan']] = array_sum($tmpPijByLokasi[$value['uid_lokasi_pemantauan']])/count($tmpPijByLokasi[$value['uid_lokasi_pemantauan']]);
					}

					// $tmpPijByKabkota[$value['uid_kabkota']][] = $cnIndeks['data'][$key]['nilai_pij'];
      }

			// $tmpPijByKabkota[$value['uid_kabkota']] = array_values($tmpPijByKabkota[$value['uid_kabkota']]);
			$cnIndeks["tmpPijByLokasi"] = $tmpPijByLokasi;
			$cnIndeks["tmpPijByKabkota"] = $tmpPijByKabkota;

			$tmpIkaIna = [];

			// echo "<pre>";
			// print_r($tmpPijByKabkota);
			// die();
			foreach ($tmpPijByKabkota as $key => $value) {
				$tmpPijByKabkota[$key] = array_values($tmpPijByKabkota[$key]);
				$tmpIkaIna[] = round(array_sum($value) / count($value), 2);
			}

			$cnIndeks['ika_ina'] = (count($tmpIkaIna) > 0 ? array_sum($tmpIkaIna) / count($tmpIkaIna) : 0 );


			if ($cnIndeks['ika_ina'] >= 0 && $cnIndeks['ika_ina'] < 25) {
					$cnIndeks['ika_ina_status'] =  "SANGAT KURANG";
			} elseif ($cnIndeks['ika_ina'] >= 25 && $cnIndeks['ika_ina'] < 50) {
					$cnIndeks['ika_ina_status'] =  "KURANG";
			} elseif ($cnIndeks['ika_ina'] >= 50 && $cnIndeks['ika_ina'] < 70) {
					$cnIndeks['ika_ina_status'] =  "SEDANG";
			} elseif ($cnIndeks['ika_ina'] >= 70 && $cnIndeks['ika_ina'] < 90) {
					$cnIndeks['ika_ina_status'] =  "BAIK";
			} elseif ($cnIndeks['ika_ina'] >= 90 && $cnIndeks['ika_ina'] <= 100) {
					$cnIndeks['ika_ina_status'] =  "SANGAT BAIK";
			}

      return array(
        "memenuhi" => $tmpMemenuhi,
        "cnIndeks" => $cnIndeks
      );
    }

    public function parameterIndeks2024StatusMutu(){
      $parameterSql = "
        if(a.kategori = 2, ABS(a.ph-(b.ph_max+b.ph_min_gambut)/2) / (b.ph_max-(b.ph_max+b.ph_min_gambut)/2), ABS(a.ph-(b.ph_max+b.ph_min)/2) / (b.ph_max-(b.ph_max+b.ph_min)/2)) AS ph,

        a.temperatur_air AS temperatur_air,
        a.temperatur_udara AS temperatur_udara,
        ABS(a.temperatur_air/a.temperatur_udara) AS temperatur,
        ABS(a.tds/b.tds) AS tds,
        ABS(a.do_p/b.do) AS do,
        ABS(a.tss/b.tss) AS tss,
        ABS(a.bod/b.bod) AS bod,
        ABS(a.cod/b.cod) AS cod,
        ABS(a.nitrit/b.nitrit) AS nitrit,
        ABS(a.no3_n/b.no3_n) AS no3_n,
        ABS(a.amoniak/b.amoniak) AS amoniak,
        ABS(a.total_phosphat/b.total_phosphat) AS total_phosphat,
        ABS(a.klorin_bebas/b.klorin_bebas) AS klorin_bebas,
        ABS(a.fenol/b.fenol) AS fenol,
        ABS(a.minyak_lemak/b.minyak_lemak) AS minyak_lemak,
        ABS(a.detergen_total/b.detergen_total) AS detergen_total,
        ABS(a.fecal_coliform/b.fecal_coliform) AS fecal_coliform,
        ABS(a.total_coliform/b.total_coliform) AS total_coliform,
        ABS(a.sianida/b.sianida) AS sianida,
        ABS(a.sulfat/b.sulfat) AS sulfat,
        ABS(a.pb/b.pb) AS pb,
        ABS(a.cd/b.cd) AS cd,

        if(a.kategori = 2,
          if((ABS(a.ph-(b.ph_max+b.ph_min_gambut)/2) / (b.ph_max-(b.ph_max+b.ph_min_gambut)/2)) > 1, (1+(5* LOG(10,ABS(a.ph-(b.ph_max+b.ph_min_gambut)/2) / (b.ph_max-(b.ph_max+b.ph_min_gambut)/2))))  ,(ABS(a.ph-(b.ph_max+b.ph_min_gambut)/2) / (b.ph_max-(b.ph_max+b.ph_min_gambut)/2))),
            if((ABS(a.ph-(b.ph_max+b.ph_min)/2) / (b.ph_max-(b.ph_max+b.ph_min)/2)) > 1, (1+(5* LOG(10,ABS(a.ph-(b.ph_max+b.ph_min)/2) / (b.ph_max-(b.ph_max+b.ph_min)/2))))  ,(ABS(a.ph-(b.ph_max+b.ph_min)/2) / (b.ph_max-(b.ph_max+b.ph_min)/2))
          )
        ) AS ph_l,

        if((ABS(a.temperatur_air/a.temperatur_udara)) > 1, (1+(5* LOG(10,ABS(a.temperatur_air/a.temperatur_udara))))  ,(ABS(a.temperatur_air/a.temperatur_udara))) AS temperatur_l,

        if((ABS(a.tds/b.tds)) > 1, (1+(5* LOG(10,ABS(a.tds/b.tds))))  ,(ABS(a.tds/b.tds))) AS tds_l,

        if((ABS(((a.do_max_p-a.do_p)/(a.do_max_p-b.do))/b.do)) > 1, (1+(5* LOG(10,ABS(((a.do_max_p-a.do_p)/(a.do_max_p-b.do))/b.do))))  ,(ABS(((a.do_max_p-a.do_p)/(a.do_max_p-b.do))/b.do))) AS do_l,

        if((ABS(a.tss/b.tss)) > 1, (1+(5* LOG(10,ABS(a.tss/b.tss))))  ,(ABS(a.tss/b.tss))) AS tss_l,

        if((ABS(a.bod/b.bod)) > 1, (1+(5* LOG(10,ABS(a.bod/b.bod))))  ,(ABS(a.bod/b.bod))) AS bod_l,

        if((ABS(a.cod/b.cod)) > 1, (1+(5* LOG(10,ABS(a.cod/b.cod))))  ,(ABS(a.cod/b.cod))) AS cod_l,

        if((ABS(a.nitrit/b.nitrit)) > 1, (1+(5* LOG(10,ABS(a.nitrit/b.nitrit))))  ,ABS(a.nitrit/b.nitrit)) AS nitrit_l,

        if((ABS(a.no3_n/b.no3_n)) > 1, (1+(5* LOG(10,ABS(a.no3_n/b.no3_n))))  ,ABS(a.no3_n/b.no3_n)) AS no3_n_l,

        if((ABS(a.amoniak/b.amoniak)) > 1, (1+(5* LOG(10,ABS(a.amoniak/b.amoniak))))  ,ABS(a.amoniak/b.amoniak)) AS amoniak_l,

        if((ABS(a.total_phosphat/b.total_phosphat)) > 1, (1+(5* LOG(10,ABS(a.total_phosphat/b.total_phosphat))))  ,(ABS(a.total_phosphat/b.total_phosphat))) AS total_phosphat_l,

        if((ABS(a.klorin_bebas/b.klorin_bebas)) > 1, (1+(5* LOG(10,ABS(a.klorin_bebas/b.klorin_bebas))))  ,(ABS(a.klorin_bebas/b.klorin_bebas))) AS klorin_bebas_l,

        if((ABS(a.fenol/b.fenol)) > 1, (1+(5* LOG(10,ABS(a.fenol/b.fenol))))  ,(ABS(a.fenol/b.fenol))) AS fenol_l,

        if((ABS(a.minyak_lemak/b.minyak_lemak)) > 1, (1+(5* LOG(10,ABS(a.minyak_lemak/b.minyak_lemak))))  ,(ABS(a.minyak_lemak/b.minyak_lemak))) AS minyak_lemak_l,

        if((ABS(a.detergen_total/b.detergen_total)) > 1, (1+(5* LOG(10,ABS(a.detergen_total/b.detergen_total))))  ,(ABS(a.detergen_total/b.detergen_total))) AS detergen_total_l,

        if((ABS(a.fecal_coliform/b.fecal_coliform)) > 1, (1+(5* LOG(10,ABS(a.fecal_coliform/b.fecal_coliform))))  ,(ABS(a.fecal_coliform/b.fecal_coliform))) AS fecal_coliform_l,

        if((ABS(a.total_coliform/b.total_coliform)) > 1, (1+(5* LOG(10,ABS(a.total_coliform/b.total_coliform))))  ,(ABS(a.total_coliform/b.total_coliform))) AS total_coliform_l,

        if((ABS(a.sianida/b.sianida)) > 1, (1+(5* LOG(10,ABS(a.sianida/b.sianida))))  ,(ABS(a.sianida/b.sianida))) AS sianida_l,

        if((ABS(a.sulfat/b.sulfat)) > 1, (1+(5* LOG(10,ABS(a.sulfat/b.sulfat))))  ,(ABS(a.sulfat/b.sulfat))) AS sulfat_l,

        if((ABS(a.pb/b.pb)) > 1, (1+(5* LOG(10,ABS(a.pb/b.pb))))  ,(ABS(a.pb/b.pb))) AS pb_l,

        if((ABS(a.cd/b.cd)) > 1, (1+(5* LOG(10,ABS(a.cd/b.cd))))  ,(ABS(a.cd/b.cd))) AS cd_l

      ";

      return $parameterSql;
    }
	}
?>
