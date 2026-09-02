<?php

    /**
     * created at 	: 30/09/2020
     * created by 	: dasendria team
     * desc		  	: controller Ajax IKLHK aaa
     *
     */
    class ajaxController extends Front
    {
        public function init()
        {
            ($this->session->get('memberIKLH') ?: $this->redirect("login"));

            //SET CUSTOM VIEWS FOLDER
            $this->view->setFolder('be');

            //LOAD MODELS
            $this->loadModel("tables");
            $this->loadModel("ref");

            //GLOBAL VAR
            $this->me 			= $this->session->get('memberIKLH');
            $this->ctrl 		= $this->uri->getController();
            $this->act 			= $this->uri->getAction();
            $this->url			= $this->ctrl . '/' . $this->act;

            //ASSIGN VAR
            $this->view->assign("now", $this->now = date('Y-m-d'));
            $this->view->assign("me", $this->me);
            $this->view->assign("baseUrl", BASEURL);
            $this->view->assign("ctrl", $this->ctrl);
            $this->view->assign("act", $this->act);
            $this->view->assign("format", $this->format);
            $this->view->assign("time", time());
            $this->view->assign("thisYear", date('Y'));
            $this->view->assign("assets", ASSETS);
        }

        public function provinsiByIdprov()
        {
            header("Content-Type: application/json; charset=UTF-8");
            $wProv = ($this->me['role_user'] == 2 ? " AND kd_propinsi=".$this->me['uid_provinsi'] : "");
            $w = ($this->params('tahun') < 2023 ? " AND kd_propinsi < 34 " : '');
            $this->tables->set("rf_provinsi", "kd_propinsi");
            $rf = $this->tables->fetch("kd_regional=".$this->params("x").$wProv.$w);
            echo json_encode($rf);
            // $this->debug->show($rf);
        }

        public function kabkotaByIdprov()
        {
            header("Content-Type: application/json; charset=UTF-8");
            $wKabkota = ($this->me['role_user'] == 3 ? " AND kd_kota=".$this->me['uid_kabkota'] : "");
            $w = ($this->params('tahun') < 2023 ? "kd_provinsi=".$this->params("x") : "deleted = 0 AND kd_provinsi=".$this->params("x"));
            $this->tables->set("rf_kabkota", "kd_kota");
            $rf = $this->tables->fetch($w.$wKabkota);
            echo json_encode($rf);
            // $this->debug->show($rf);
        }

        public function pemantauanByIdprov()
        {
            header("Content-Type: application/json; charset=UTF-8");
            $this->tables->set("lokasi_pemantauan", "uid_lokasi_pemantauan");
            $rf = $this->tables->fetch("uid_provinsi=".$this->params("x"));
            echo json_encode($rf);
            // $this->debug->show($rf);
        }

        public function pemantauanIkuByYear()
        {
            header("Content-Type: application/json; charset=UTF-8");
            if ($this -> me['role_user'] == 3) {
                $w = " AND uid_kabkota = " . $this -> me['uid_kabkota'];
            } elseif ($this -> me['role_user'] == 2) {
                $w = " AND uid_provinsi = " . $this -> me['uid_provinsi'];
            } elseif ($this -> me['role_user'] == 4 || $this -> me['role_user'] == 5) {
                $w = " AND uid_rf_pelaksana = 2";
            }
            // $uid_pelaksana = $this->me['role_user'];
            // if ($uid_pelaksana == 1) {
            //   $uid_pelaksana = 1;
            // } elseif ($uid_pelaksana == 2) {
            //   $uid_pelaksana = 3;
            // } elseif ($uid_pelaksana == 3) {
            //   $uid_pelaksana = 4;
            // } else {
            //   $uid_pelaksana = 2;
            // }
            $this->tables->set("lokasi_pemantauan", "uid_lokasi_pemantauan");
            $data = $this->tables->fetch("deleted = 0 AND uid_rf_component = 1 AND tahun LIKE '%".$this->params("x")."%'".$w);
            // $this->debug->show($data);
            echo json_encode($data);
        }

        public function pemantauanIkaByYear()
        {
            header("Content-Type: application/json; charset=UTF-8");
            if ($this -> me['role_user'] == 3) {
                $w = " AND uid_kabkota = " . $this -> me['uid_kabkota'];
            } elseif ($this -> me['role_user'] == 2) {
                $w = " AND uid_provinsi = " . $this -> me['uid_provinsi'];
            } elseif ($this -> me['role_user'] == 4 || $this -> me['role_user'] == 5) {
                $w = " AND uid_rf_pelaksana = 2";
            }
            // $uid_pelaksana = $this->me['role_user'];
            // if ($uid_pelaksana == 1) {
            //   $uid_pelaksana = 1;
            // } elseif ($uid_pelaksana == 2) {
            //   $uid_pelaksana = 3;
            // } elseif ($uid_pelaksana == 3) {
            //   $uid_pelaksana = 4;
            // } else {
            //   $uid_pelaksana = 2;
            // }
            $this->tables->set("lokasi_pemantauan", "uid_lokasi_pemantauan");
            $data = $this->tables->fetch("deleted = 0 AND uid_rf_component = 2 AND tahun LIKE '%".$this->params("x")."%'".$w);
            // $data = $this->tables->fetch("deleted = 0 AND uid_rf_component = 2 AND uid_rf_pelaksana = ".$uid_pelaksana." AND tahun LIKE '%".$this->params("x")."%'".$w);
            // $this->debug->show($data);
            echo json_encode($data);
        }

        public function pemantauanIkalByYear()
        {
            header("Content-Type: application/json; charset=UTF-8");
            if ($this -> me['role_user'] == 3) {
                $w = " AND uid_kabkota = " . $this -> me['uid_kabkota'];
            } elseif ($this -> me['role_user'] == 2) {
                $w = " AND uid_provinsi = " . $this -> me['uid_provinsi'];
            } elseif ($this -> me['role_user'] == 4 || $this -> me['role_user'] == 5) {
                $w = " AND uid_rf_pelaksana = 2";
            }
            // $uid_pelaksana = $this->me['role_user'];
            // if ($uid_pelaksana == 1) {
            //   $uid_pelaksana = 1;
            // } elseif ($uid_pelaksana == 2) {
            //   $uid_pelaksana = 3;
            // } elseif ($uid_pelaksana == 3) {
            //   $uid_pelaksana = 4;
            // } else {
            //   $uid_pelaksana = 2;
            // }
            $this->tables->set("lokasi_pemantauan", "uid_lokasi_pemantauan");
            $data = $this->tables->fetch("deleted = 0 AND uid_rf_component = 5 AND tahun LIKE '%".$this->params("x")."%'".$w);
            echo json_encode($data);
            // $this->debug->show($data);
        }

        public function lokasiById()
        {
            header("Content-Type: application/json; charset=UTF-8");

            $this->tables->set("lokasi_pemantauan", "uid_lokasi_pemantauan");
            $rf = $this->tables->fetch("deleted = 0 AND uid_lokasi_pemantauan=".$this->params("x"));
            // $this->debug->show($rf);
            echo json_encode($rf);
        }

        //Nilai PM2.5 satelit (rf_nilai_pelengkap_iklh, diisi lewat menu Target Nilai IKLH) untuk
        //provinsi+kabkota+tahun tertentu — dipakai form IKU untuk mengisi PM2.5 saat metode
        //pemantauan Manual Pasif (alat pasif tidak bisa mengukur PM2.5 langsung). Pola pencocokan
        //sama dengan join np di view v_pelaporan_iku.
        public function pm25Satelit()
        {
            header("Content-Type: application/json; charset=UTF-8");

            $this->tables->set("rf_nilai_pelengkap_iklh", "uid_nilai_pelengkap_iklh");
            $rf = $this->tables->fetch(
                "deleted = 0 AND uid_provinsi=".(int) $this->params("x").
                " AND uid_kabkota=".(int) $this->params("y").
                " AND tahun=".(int) $this->params("z")
            );
            echo json_encode($rf);
        }

        public function sumberPencemarByKomponen()
        {
            header("Content-Type: application/json; charset=UTF-8");

            $this->tables->set("rf_sumber_pencemar", "uid");
            $rf = $this->tables->fetch("deleted = 0 AND uid_rf_component =".$this->params("x"));
            // $this->debug->show($rf);
            echo json_encode($rf);
        }

        public function detailIkal()
        {
            header("Content-Type: application/json; charset=UTF-8");

            $uid_provinsi = $this->params("x");
            $tahun = $this->params("t");
            $sql = "SELECT a.*, b.alamat, b.alamat_detail, b.uid_kabkota, c.nama_kabkot FROM indeks_ikal a
                  LEFT JOIN lokasi_pemantauan b ON b.uid_lokasi_pemantauan = a.uid_lokasi
                  LEFT JOIN rf_kabkota c ON c.kd_kota = b.uid_kabkota
                  WHERE b.deleted = 0 AND a.deleted = 0 AND a.uid_lokasi > 0 AND a.uid_provinsi = ".$uid_provinsi." AND a.tahun=".$tahun."
                  ORDER BY b.uid_lokasi_pemantauan ASC
                  ";
            $data = $this->tables->query($sql);
            // $this->debug->show($data);
            echo json_encode($data);
        }

        public function das(){
          header("Content-Type: application/json; charset=UTF-8");

          $nama = $_GET['q'];
          $uid = $_GET['i'];
          $where = " deleted = 0 ";
          $where .= ($nama? " AND (a.nama LIKE '%{$nama}%' OR a.kode LIKE '%{$nama}%') "  : "" );
          // $where .= ($uid? " OR a.uid = {$uid}"  : "" );
          $data1["data"] = [];
          if((int)$uid > 0){
            $data1 = $this->db->fetch("SELECT a.uid AS id, a.nama AS text, a.kode FROM rf_das a WHERE uid={$uid} LIMIT 1");
          }
          $data2 = $this->db->fetch("SELECT a.uid AS id, a.nama AS text, a.kode FROM rf_das a WHERE {$where} ORDER BY uid DESC LIMIT 0,50");

          $dataReturn = array_merge($data1["data"],$data2["data"]);

          echo json_encode($dataReturn);
        }

        public function sungai(){
          header("Content-Type: application/json; charset=UTF-8");

          $nama = $_GET['q'];
          $uid = $_GET['i'];
          $where = " deleted = 0 ";
          $where .= ($nama? " AND (a.nama LIKE '%{$nama}%' OR a.kode_das LIKE '%{$nama}%' OR a.nama_das LIKE '%{$nama}%' OR a.nama_provinsi LIKE '%{$nama}%' OR a.nama_kabkota LIKE '%{$nama}%') "  : "" );
          // $where .= ($uid? " OR a.uid = {$uid}"  : "" );
          $data1["data"] = [];
          if((int)$uid > 0){
            $data1 = $this->db->fetch("SELECT a.uid AS id, a.nama AS text, a.kode_das, a.nama_das, a.nama_provinsi, a.nama_kabkota FROM v_sungai a WHERE uid={$uid} LIMIT 1");
          }
          $data2 = $this->db->fetch("SELECT a.uid AS id, a.nama AS text, a.kode_das, a.nama_das, a.nama_provinsi, a.nama_kabkota FROM v_sungai a WHERE {$where} ORDER BY uid DESC LIMIT 0,50");

          $dataReturn = array_merge($data1["data"],$data2["data"]);

          echo json_encode($dataReturn);
        }
    }
