<?php
/**
 * created at 	: 29/09/2020
 * created by 	: dasendria team
 * desc		  	: controller index IKLHK
 *
 */
class evaluasiIndeksResponController extends Front
{
    public $IRType = array(
      1 => 'Langit Biru',
      2 => 'Pantai Lestari',
      3 => 'Kali Bersih',
      4 => 'Indonesia Hijau',
      5 => 'Gambut Lestari'
    );

    public function init()
    {
      // if($_SERVER['REMOTE_ADDR']=='103.144.175.182' && $_SERVER['REMOTE_ADDR']=='182.0.214.67') die('sedang development');
      // ini_set("display_errors",TRUE);
        ($this -> session -> get('memberIKLH') ?: $this -> redirect("login"));

        //SET CUSTOM VIEWS FOLDER
        $this -> view -> setFolder('be');

        //LOAD MODELS
        $this -> loadModel("tables");
        $this -> loadModel("ref");

        //GLOBAL VAR
        $this -> me = $this -> session -> get('memberIKLH');
        $this -> ctrl = $this -> uri -> getController();
        $this -> act = $this -> uri -> getAction();
        $this -> url = $this -> ctrl . '/' . $this -> act;
        $this->properties 	= array();
        $this->where		= 'deleted=0 AND hidden=0';
        $this->msg			= '';

        //ASSIGN VAR
        $this -> view -> assign("now", $this -> now = date('Y-m-d'));
        $this -> view -> assign("me", $this -> me);
        $this -> view -> assign("baseUrl", BASEURL);
        $this -> view -> assign("ctrl", $this -> ctrl);
        $this -> view -> assign("act", $this -> act);
        $this -> view -> assign("format", $this -> format);
        $this -> view -> assign("time", time());
        $this -> view -> assign("thisYear", date('Y'));
        $this -> view -> assign("assets", ASSETS);
        $this -> roleDataView = (is_numeric($this->params("user")) ? $this->tables->query("SELECT * FROM users WHERE uid_users=".$this->params("user"))['data'][0]['role_user'] : 0);
    }

    //INDEX FUNCTION IS A DEFAULT ACTION
    public function index()
    {
        $this->rfData();
        $this->dataTable();
        $this -> view -> assign("indeksResponActive", "active");
        $this -> view -> assign("show", $show);
        $this -> view -> assign("message", $message);
        $this -> view -> assign("title", 'EVALUASI INDEKS RESPON');
        $this -> view -> assign("icons", '<i class="ft-bar-chart"></i>');
        $this -> view -> display("index.html");
    }

    private function dataTable()
    {
      $urlVar = BASEURL . $this -> url . '/';
      $w = ' a.deleted=0 AND a.hidden=0 ';
      // if ($this -> me['role_user'] == 3 || $this->me['role_user'] == 2) {
      //     $w .= " AND users_uid =" . $this -> me['uid_users'];
      // } elseif ($this->me['role_user'] == 4) {
      //   $w .=" AND c.kd_regional =".$this->me['uid_regional'];
      // }

      if ($this -> me['role_user'] == 3) {
          $w .= " AND b.uid_kabkota =" . $this -> me['uid_kabkota'];
      } elseif ($this -> me['role_user'] == 2) {
          $w .= " AND b.uid_provinsi =" . $this -> me['uid_provinsi'];
      } elseif ($this -> me['role_user'] == 4 || $this -> me['role_user'] == 5) {
          $w .= " AND c.kd_regional =" . $this -> me['uid_regional'];
      }
      // $this->debug->show($this->me['uid_regional']);
      // $o = $this -> primaryKey . " DESC";
      $post = $this -> post();
      if ($this -> params('search')) {
          $post['search'] = true;
          $post['form'] = json_decode(urldecode($this -> params('search')), 1);
      }
      if (isset($post['search'])) {
          // if ($post['form']['keyword']) {
          //     if ($properties['total']) {
          //         $w .= " AND ";
          //         $w .= "(";
          //         for ($i = 5; $i < $properties['total']; $i++) {
          //             $w .= $properties['data'][$i] . " LIKE '%" . $post['form']['keyword'] . "%' OR ";
          //         }
          //         $w .= $properties['data'][$properties['total'] - 1] . " LIKE '%" . $post['form']['keyword'] . "%' ";
          //         $w .= ")";
          //     }
          // }
          if ($post['form']['tahun']) {
              $w .= " AND a.tahun =" . $post['form']['tahun'];
          }
          if ($post['form']['src_prop']) {
              $w .= " AND b.uid_provinsi = " . $post['form']['src_prop'];
              $wProv  = " AND kd_propinsi = " . $post['form']['src_prop'];
              $wKabk .= " AND kd_provinsi = " . $post['form']['src_prop'];
          }
          if ($post['form']['src_kabkota2']) {
              $w .= " AND b.uid_kabkota = " . $post['form']['src_kabkota2'];
              $wKabk = " AND kd_kota = " . $post['form']['src_kabkota2'];
          }
          if ($post['form']['src_reg']) {
              $w .= " AND c.kd_regional = " . $post['form']['src_reg'];
              $wProv .= " AND kd_regional = " . $post['form']['src_reg'];
          }
          $urlVar .= 'search/' . urlencode(json_encode($post['form'])) . '/';
          $this -> view -> assign("search", $post['form']);
      } else {
          $w .= " AND a.tahun = ". ACTIVE_YEAR;
          $post['form']['tahun'] = ACTIVE_YEAR;

          $urlVar .= 'search/' . urlencode(json_encode($post['form'])) . '/';
          $this -> view -> assign("search", $post['form']);
      }
      $this->yearActive = $post['form']['tahun'];
      $search_json = urlencode(json_encode($post['form']));
      $this->view->assign("search_json", $search_json);
      //PAGING
      $offset = (isset($_REQUEST['page']) && $_REQUEST['page'] > 1 ? $_REQUEST['page'] - 1 : 0);
      $limit = 100;
      // DATA
      $sql = 'SELECT MIN(a.indeks_uid) as min_index, GROUP_CONCAT(a.indeks_uid SEPARATOR ",") indeks_gabung, a.users_uid, a.tahun, MIN(a.tipe) as min_tipe, GROUP_CONCAT(a.tipe SEPARATOR ",") AS tipe_gabung,
                     b.name, b.uid_provinsi, b.uid_kabkota, c.kd_regional, c.nama_propinsi, d.nama_kabkot
              FROM ir_parent a
              LEFT JOIN users b ON a.users_uid = b.uid_users
              LEFT JOIN rf_provinsi c ON b.uid_provinsi = c.kd_propinsi
              LEFT JOIN rf_kabkota d ON b.uid_kabkota = d.kd_kota
              LEFT JOIN rf_regional e ON c.kd_regional = e.kd_regional
              WHERE '. $w .' GROUP BY a.users_uid ORDER BY a.tahun DESC';
      $data = $this->tables->query($sql);
      foreach ($data['data'] as $key => $value) {
        $data['data'][$key]['parent'] = explode(',',$value['indeks_gabung']);
        $data['data'][$key]['tipe'] = explode(',',$value['tipe_gabung']);
      }
      $sql2 = 'SELECT COUNT(a.indeks_uid)
              FROM ir_parent a
              LEFT JOIN users b ON a.users_uid = b.uid_users
              LEFT JOIN rf_provinsi c ON b.uid_provinsi = c.kd_propinsi
              LEFT JOIN rf_kabkota d ON b.uid_kabkota = d.kd_kota
              WHERE '. $w .' GROUP BY a.users_uid ORDER BY a.tahun DESC';

      $All = $this -> db -> query($sql2);
      $totalRow = ($All -> _numOfRows ? $All -> _numOfRows : 0);

        $isian_per_tipe = array(1, 2, 3, 4, 5);
        foreach($data['data'] as $key=>$val){
          $sql = 'SELECT DISTINCT(tipe) as tipe, SUM(nilai) jumlah_mengisi
                  FROM indeks_respon_evaluasi
                  WHERE deleted=0 AND hidden=0 AND parent_uid IN (' . $val['indeks_gabung'] . ')
                  GROUP BY tipe LIMIT 7';
          $child = $this->tables->query($sql);
          $isian = array();
          foreach($child['data'] as $ke=>$va){
            $total_isian = $isian_per_tipe[$va['tipe']];
            $isian[$va['tipe']] = array (
                                    'tipe'            => $va['tipe'],
                                    'jumlah_mengisi'  => ROUND($va['jumlah_mengisi'],2)
                                  );
          }
          // $data['data'][$key]['isian'] = $child['data'];
          $data['data'][$key]['isian'] = $isian;
          $data['data'][$key]['isian_sql'] = $sql;
          $dataIR[$val['uid_kabkota']] = $data['data'][$key];
          if($val['uid_kabkota'] == 0){
            $dataIR['p-'.$val['uid_provinsi']] = $data['data'][$key];
          }
        }
        $cont_prov = $this->db->query('SELECT COUNT(kd_propinsi) as total FROM rf_provinsi WHERE '.$wProv);
      // if($offset < 100){
        $data_prov = $this->db->query('SELECT * FROM rf_provinsi WHERE 1 '.$wProv.' LIMIT '. $offset .','. $limit);
        foreach ($data_prov as $key => $value) {
          $data_final['p-'.$key] = $dataIR['p-'.$value['kd_propinsi']] ? $dataIR['p-'.$value['kd_propinsi']] : $value ;
        }
      // }

      $cont_kabk = $this->db->query('SELECT COUNT(kd_kota) as total FROM rf_kabkota WHERE deleted =0'.$wKabk);
      $data_kabk = $this->db->query('SELECT * FROM rf_kabkota WHERE deleted =0 '.$wKabk.' LIMIT '. $offset .','. $limit);
      foreach ($data_kabk as $key => $value) {
        $data_final[$key] = $dataIR[$value['kd_kota']] ? $dataIR[$value['kd_kota']] : $value ;
      }

      $totalRow = $cont_kabk -> fields['total'];
      // $this->debug->show($data);

      $this -> view -> pagination($this -> view, $totalRow, $offset + 1, $limit, $urlVar);
      $listExport = $this->_getListExport($totalRow);
  		$this->view->assign("listExport", $listExport);
      $this -> view -> assign("urlVar", $urlVar);
      $this -> view -> assign("totalRow", $totalRow);
      $this -> view -> assign("limit", $limit);
      $this -> view -> assign("page", $offset);
      $this -> view -> assign("view", $data_final);
    }

    private function _getListExport($totalRow)
  	{
  			$numList 	= round($totalRow/LIMIT_DOWNLOAD_EXCEL);
  			$numListRes = $numList * LIMIT_DOWNLOAD_EXCEL;
  			if ($totalRow >= $numListRes) {
  					$numList += 1;
  			}

  			$itemCount	= 1;
  			$limitCount	= LIMIT_DOWNLOAD_EXCEL;
  			for ($itemCount; $itemCount<=$numList; $itemCount++) {
  					if ($itemCount == 1) {
  							$listExport[$itemCount]['offset_start'] = 0;
  					} else {
  							$listExport[$itemCount]['offset_start'] = ($limitCount - LIMIT_DOWNLOAD_EXCEL) + 1;
  					}

  					if ($limitCount >= $totalRow) {
  							$listExport[$itemCount]['offset_end'] = $totalRow;
  					} else {
  							$listExport[$itemCount]['offset_end'] = $limitCount;
  					}

  					$limitCount += LIMIT_DOWNLOAD_EXCEL;
  			}

  			return $listExport;
  	}

    public function dataExcel($w, $offset)
  	{
  			$offset = $this->params('offset');
        $urlVar = BASEURL . $this -> url . '/';
        $w = ' a.deleted=0 AND a.hidden=0 ';
        // if ($this -> me['role_user'] == 3 || $this->me['role_user'] == 2) {
        //     $w .= " AND users_uid =" . $this -> me['uid_users'];
        // } elseif ($this->me['role_user'] == 4) {
        //   $w .=" AND c.kd_regional =".$this->me['uid_regional'];
        // }

        if ($this -> me['role_user'] == 3) {
            $w .= " AND b.uid_kabkota =" . $this -> me['uid_kabkota'];
        } elseif ($this -> me['role_user'] == 2) {
            $w .= " AND b.uid_provinsi =" . $this -> me['uid_provinsi'];
        } elseif ($this -> me['role_user'] == 4 || $this -> me['role_user'] == 5) {
            $w .= " AND c.kd_regional =" . $this -> me['uid_regional'];
        }
        // $this->debug->show($this->me['uid_regional']);
        // $o = $this -> primaryKey . " DESC";
        $post = $this -> post();
        if ($this -> params('search')) {
            $post['search'] = true;
            $post['form'] = json_decode(urldecode($this -> params('search')), 1);
        }
        if (isset($post['search'])) {
            // if ($post['form']['keyword']) {
            //     if ($properties['total']) {
            //         $w .= " AND ";
            //         $w .= "(";
            //         for ($i = 5; $i < $properties['total']; $i++) {
            //             $w .= $properties['data'][$i] . " LIKE '%" . $post['form']['keyword'] . "%' OR ";
            //         }
            //         $w .= $properties['data'][$properties['total'] - 1] . " LIKE '%" . $post['form']['keyword'] . "%' ";
            //         $w .= ")";
            //     }
            // }
            if ($post['form']['tahun']) {
                $w .= " AND a.tahun =" . $post['form']['tahun'];
            }
            if ($post['form']['src_prop']) {
                $w .= " AND b.uid_provinsi = " . $post['form']['src_prop'];
            }
            if ($post['form']['src_kabkota2']) {
                $w .= " AND b.uid_kabkota = " . $post['form']['src_kabkota2'];
            }
            if ($post['form']['src_reg']) {
                $w .= " AND c.kd_regional = " . $post['form']['src_reg'];
            }
            $urlVar .= 'search/' . urlencode(json_encode($post['form'])) . '/';
            $this -> view -> assign("search", $post['form']);
        } else {
            $w .= " AND a.tahun = ". ACTIVE_YEAR;
            $post['form']['tahun'] = ACTIVE_YEAR;

            $urlVar .= 'search/' . urlencode(json_encode($post['form'])) . '/';
            $this -> view -> assign("search", $post['form']);
        }
        $this->yearActive = $post['form']['tahun'];
        $search_json = urlencode(json_encode($post['form']));
        $this->view->assign("search_json", $search_json);
        //PAGING
        $limit = LIMIT_DOWNLOAD_EXCEL;
        // DATA
        $sql = 'SELECT MIN(a.indeks_uid) as min_index, GROUP_CONCAT(a.indeks_uid SEPARATOR ",") indeks_gabung, a.users_uid, a.tahun, MIN(a.tipe) as min_tipe, GROUP_CONCAT(a.tipe SEPARATOR ",") AS tipe_gabung,
                       b.name, b.uid_provinsi, b.uid_kabkota, c.kd_regional, c.nama_propinsi, d.nama_kabkot
                FROM ir_parent a
                LEFT JOIN users b ON a.users_uid = b.uid_users
                LEFT JOIN rf_provinsi c ON b.uid_provinsi = c.kd_propinsi
                LEFT JOIN rf_kabkota d ON b.uid_kabkota = d.kd_kota
                LEFT JOIN rf_regional e ON c.kd_regional = e.kd_regional
                WHERE '. $w .' GROUP BY a.users_uid ORDER BY a.tahun DESC LIMIT '. $offset .','. $limit;
        $data = $this->tables->query($sql);
        foreach ($data['data'] as $key => $value) {
          $data['data'][$key]['parent'] = explode(',',$value['indeks_gabung']);
          $data['data'][$key]['tipe'] = explode(',',$value['tipe_gabung']);
        }

        $sql2 = 'SELECT COUNT(a.indeks_uid)
                FROM ir_parent a
                LEFT JOIN users b ON a.users_uid = b.uid_users
                LEFT JOIN rf_provinsi c ON b.uid_provinsi = c.kd_propinsi
                LEFT JOIN rf_kabkota d ON b.uid_kabkota = d.kd_kota
                WHERE '. $w .' GROUP BY a.users_uid ORDER BY a.tahun DESC';

        $All = $this -> db -> query($sql2);
        $totalRow = ($All -> _numOfRows ? $All -> _numOfRows : 0);

          $isian_per_tipe = array(1, 2, 3, 4, 5);
          foreach($data['data'] as $key=>$val){
            $sql = 'SELECT DISTINCT(tipe) as tipe, SUM(nilai) jumlah_mengisi
                    FROM indeks_respon_evaluasi
                    WHERE deleted=0 AND hidden=0 AND parent_uid IN (' . $val['indeks_gabung'] . ')
                    GROUP BY tipe LIMIT 7';
            $child = $this->tables->query($sql);
            $isian = array();
            foreach($child['data'] as $ke=>$va){
              $total_isian = $isian_per_tipe[$va['tipe']];
              $isian[$va['tipe']] = array (
                                      'tipe'            => $va['tipe'],
                                      'jumlah_mengisi'  => ROUND($va['jumlah_mengisi'],2)
                                    );
            }
            // $data['data'][$key]['isian'] = $child['data'];
            $data['data'][$key]['isian'] = $isian;
          }


  			$this->view->assign("viewExcel", $data);

        header("Content-type: application/vnd-ms-excel");
        header('Content-Disposition: attachment; filename="EVALUASI_INDEKS_RESPON_'.time().'.xls"');
        $html = $this->view->fetch('parts/contents/evaluasiIndeksRespon/index/excel.html');
  			echo $html;
  	}

    public function respon()
    {
      $post = $this->post();
      $post1 = [];
      $post2 = [];
      $message = '';
      $view = '';
      $users_uid = 0;
      if($this->params('tahun')){ // EDIT DATA
        $post['submit-tahun'] = TRUE;
        $post['form']['tahun'] = $this->params('tahun');
        if($this->params('view')=='printout'){
          $view = $this->params('view');
        }
        if($this->params('user')){
          $users_uid_view = $this->params('user');
        }
      }
      if(isset($post['submit-data'])) {
        $this -> tables -> set("indeks_respon", "indeks_uid");
        $bobot = $post['form']['bobot'];
        // $this->debug->show($bobot);
        foreach ($post['form']['parent_uid'] as $key => $value) {
          unset($post1);
          $post1['form']['parent_uid'] = $value;
          $post1['form']['indeks_uid'] = $post['form']['indeks_uid'][$key];
          $post1['form']['isian_evaluasi'] = $post['form']['isian_evaluasi'][$key];
          $jumlah_isian_nilai += $post['form']['isian_evaluasi'][$key];
          $jumlah_isian += 1;
          $post1['submit'] = true;
          if ($this -> tables -> post($post1)) {
            $message = "Berhasil menyimpan data !";
          } else {
            $message = "Gagal menyimpan data !";
          }
          // echo '<pre>';print_r($file);print_r($_FILES);print_r($post);print_r($post1);
          // echo '<pre>';print_r($filename);print_r($post1);
          $post['submit-tahun'] = TRUE;
        }
        $this -> tables -> set("indeks_respon_evaluasi", "id");
        unset($post2);
        $r_isian = $jumlah_isian_nilai / $jumlah_isian;
        $nilai_bobot_akhir = $r_isian * ($bobot/100);
        $post2['form']['parent_uid'] = $post['form']['parent_uid'][0];
        $post2['form']['ref_uid'] = $post['form']['ref_uid'];
        $post2['form']['id'] = $post['form']['id'];
        $post2['form']['tipe'] = $post['form']['tipe'][0];
        $post2['form']['nilai'] = $nilai_bobot_akhir;
        $post2['form']['nilai_rataan_isian'] = $r_isian;
        // $this->debug->show($post2);
        $post2['submit'] = true;
        $this -> tables -> post($post2);
        // die('...');
      }
      // $this->debug->show($post);
      if(isset($post['submit-tahun']) && isset($post['form']['tahun']) && $post['form']['tahun'] >= 2015 && $post['form']['tahun'] <= ACTIVE_YEAR){
        /*
          TIPE :
          1. LANGIT BIRU (LB)
          2. PANTAI BERSIH (PB) --> HANYA PROPINSI
          3. PROGRAM KALI BERSIH (PKB)
          4. INDONESIA HIJAU (IH)
          5. GAMBUT LESTARI (GL)
        */
        $dataType = array();
        if($users_uid_view && is_numeric($users_uid_view)){
          $post['form']['users_uid'] = $users_uid_view;
        }else{
          $post['form']['users_uid'] = $this->me['uid_users'];
        }
        $post['submit'] = TRUE;
        // $this->debug->show($post);
        $this->tables->set('ir_parent', 'indeks_uid');
        for($i=1 ; $i<=5 ; $i++){
          $post['form']['tipe'] = $i;
          $checkData = $this->tables->fetch('deleted = 0 AND users_uid='.$post['form']['users_uid'].' AND tahun='.$post['form']['tahun'].' AND tipe='.$i);
          if($checkData['total']){
            $dataType[$i]['indeks_uid']   = $checkData['data'][0]['indeks_uid'];
            $dataType[$i]['users_uid']    = $checkData['data'][0]['users_uid'];
            $dataType[$i]['tahun']        = $checkData['data'][0]['tahun'];
            $dataType[$i]['tipe']         = $i;
            $dataType[$i]['tipe_nama']    = $this->IRType[$i];
          }else{
            if($this->me['role_user']==2 || $this->me['role_user']==3){
              if($this->tables->post($post)) {
                $dataType[$i]['indeks_uid'] = $this->tables->lastInsertID();
                $dataType[$i]['users_uid']  = $post['form']['users_uid'];
                $dataType[$i]['tahun']      = $$post['form']['tahun'];
                $dataType[$i]['tipe']       = $i;
                $dataType[$i]['tipe_nama']  = $this->IRType[$i];
              }
            }
          }
        }

        if($this->me['role_user']==3){ // KAB/KOTA
          unset($dataType[2]);
        }

        if(count($dataType)){
          foreach($dataType as $val){
            $dataEdit[$val['tipe']] = $this->_editIndeksRespon($val['indeks_uid'], $val['tipe']);
            $dataEditEv[$val['tipe']] = $this->_editIndeksResponEvaluasi($val['indeks_uid'], $val['tipe']);
            $userEntri = $val['users_uid'];
            $this->view-> assign("data_ir_".$val['tipe'], $dataEdit[$val['tipe']]);
            $this->view-> assign("data_ir_ev_".$val['tipe'], $dataEditEv[$val['tipe']]);
          }
          // $this->debug->show($dataEdit);
          $userEntri = $this->_getUserEntri($userEntri);
          // AMBIL ISIAN
          $this->rflangitbiru();
          $this->rfpkb();
          $this->rfindohijau();
          $this->rfgambut();
          if($userEntri['role_user']==2){ // PROVINSI
            $this->rfpantaibersih();
          }
          $data = ['Sudah', 'Belum'];
          $this->view->assign("pilihan", $data);


          $this->view-> assign("tahunSelected", $post['form']['tahun']);
          $this->view-> assign("dataType", $dataType);
          $this->view-> assign("userEntri", $userEntri);
        }else{
          $this->redirect('indeksRespon');
          exit();
        }
      }
      $this->view->assign("fromTab", $post['fromTab']);
      $this->view->assign("indeksResponActive", "active");
      $this->view->assign("message", $message);
      $this->view->assign("view", $view);
      $this->view->assign("title", 'EVALUASI INDEKS RESPON');
      $this->view->assign("icons", '<i class="ft-bar-chart"></i>');
      $this->view->display("index.html");
    }
    private function _getUserEntri($users_uid){
      $this->tables->set('v_users', 'uid_users');
      $data = $this->tables->fetch('uid_users ='.$users_uid);
      return $data['data'][0];
    }
    private function _checkFile($indeks_uid, $filename){
      $this->tables->set('indeks_respon', 'indeks_uid');
      $data = $this->tables->fetch('deleted = 0 AND indeks_uid ='.$indeks_uid);
      $data = $data['data'][0];
      $bukti = $data['bukti'];
      if($bukti){
        $bukti = explode('|', $bukti);
        // $this->debug->show($bukti);
        $file_baru = [];
        foreach($bukti as $k=>$v){
          if($filename[$k]){
            $file_baru[$k] = $filename[$k];
          }else{
            $file_baru[$k] = $v;
          }
        }
        return $file_baru;
      }else{
        return $filename;
      }
    }
    private function _editIndeksRespon($parent, $tipe)
    {
        $this->tables->set('indeks_respon', 'indeks_uid');
        $data = $this->tables->fetch('deleted = 0 AND parent_uid ='.$parent.' AND tipe ='.$tipe);
        foreach ($data['data'] as $key => $value) {
          $data2[$value['ref_uid']]['isian'] = explode('|',$value['isian']);
          $data2[$value['ref_uid']]['isian'][0] = str_replace(',', '.', $data2[$value['ref_uid']]['isian'][0]);
          $data2[$value['ref_uid']]['isian'][1] = str_replace(',', '.', $data2[$value['ref_uid']]['isian'][1]);
          $isian_1 = preg_replace('#[^0-9\.]#', '', $data2[$value['ref_uid']]['isian'][0]);
          $isian_2 = preg_replace('#[^0-9\.]#', '', $data2[$value['ref_uid']]['isian'][1]);
          // echo $isian_1 . ' - ' . $isian_2 . '<br/>';
          $persen_isian = $isian_1 / $isian_2 * 100;
          $data2[$value['ref_uid']]['isian']['persen'] = ROUND($persen_isian, 4);
          $data2[$value['ref_uid']]['isian_evaluasi'] = $value['isian_evaluasi'];
          $data2[$value['ref_uid']]['bukti'] = explode('|',$value['bukti']);
          $data2[$value['ref_uid']]['indeks_uid'] = $value['indeks_uid'];
        }
        // $this->debug->show($data2);
        return $data2;
    }

    private function _editIndeksResponEvaluasi($parent, $tipe)
    {
        $this->tables->set('indeks_respon_evaluasi', 'id');
        $data = $this->tables->fetch('deleted = 0 AND parent_uid ='.$parent.' AND tipe ='.$tipe);
        foreach ($data['data'] as $key => $value) {
          $data2[$value['ref_uid']]['nilai'] = ROUND($value['nilai'],2);
          $data2[$value['ref_uid']]['id'] = $value['id'];
        }
        // $this->debug->show($data2);
        return $data2;
    }

    public function deletedData(){
			$post = $this->post();
			if(isset($post['x'])){
				$this->tables->set("ir_parent","indeks_uid");
        $dataDelete = $this->tables->fetch('indeks_uid='.$post['x']);
        $dataDelete = $dataDelete['data'][0];
        $whereDelete = 'users_uid='.$dataDelete['users_uid'].' AND tahun='.$dataDelete['tahun'];
        if($this->tables->softDeleteBy($whereDelete)){
          echo json_encode(array('statusCode' => 200, 'message' => $this->message->delete('success')));
        }else{
          echo json_encode(array('statusCode' => 400, 'message' => $this->message->delete('failed')));
        }
			}else{
				echo json_encode(array('statusCode' => 403, 'message' => $this->message->access()));
			}
		}

    private function rflangitbiru()
    {
        if ($this->roleDataView == 3) {
          $w = ' AND prov=0';
        }
        $this->tables->set('irf_langitbiru', 'id');
        $data = $this->tables->fetch('deleted = 0 AND parent_1 = 0 AND parent_2 = 0 AND parent_3 = 0 ORDER BY id ASC');
        foreach ($data['data'] as $key => $value) {
            $data2 = $this->tables->fetch('deleted = 0 AND parent_1 = '.$value['id'].' AND parent_2 = 0 AND parent_3 = 0 ORDER BY id ASC');
            $data['data'][$key]['child1'] = $data2['data'];
            $data['data'][$key]['tchild1'] = $data2['total']+1;
            foreach ($data2['data'] as $ky => $val) {
                $data3 = $this->tables->fetch('deleted = 0 AND parent_1 = 0 AND parent_2 = '.$val['id'].' AND parent_3 = 0 '.$w.' ORDER BY id ASC');
                $data['data'][$key]['child1'][$ky]['child2'] = $data3['data'];
                $data['data'][$key]['child1'][$ky]['tchild2'] = $data3['total']+1;
                foreach ($data3['data'] as $k => $v) {
                    $data4 = $this->tables->fetch('deleted = 0 AND parent_1 = 0 AND parent_2 = 0 AND parent_3 = '.$v['id'].' '.$w.' ORDER BY id ASC');
                    foreach ($data4['data'] as $x => $y) {
                        $bukti = explode('|', $y['bukti']);
                        $data4['data'][$x]['bukti'] = $bukti;
                        $k_nilai = explode(';', $y['keterangan_nilai']);
                        $data4['data'][$x]['keterangan_nilai'] = $k_nilai;
                        $p_nilai = explode(';', $y['pilihan_nilai']);
                        $data4['data'][$x]['pilihan_nilai'] = $p_nilai;
                    }
                    $data['data'][$key]['child1'][$ky]['child2'][$k]['child3'] = $data4['data'];
                    $data['data'][$key]['child1'][$ky]['child2'][$k]['tchild3'] = $data4['total']+1;
                }
            }
        }
        // if ($this->params('debug')==1) {
        //   $this->debug->show($data);
        // }
        $this -> view -> assign("irf_langitbiru", $data['data']);
    }

    private function rfpantaibersih()
    {
        if ($this->roleDataView == 3) {
          $w = ' AND prov=0';
        }
        $this->tables->set('irf_pantaibersih', 'id');
        $data = $this->tables->fetch('deleted = 0 AND parent_1 = 0 AND parent_2 = 0 AND parent_3 = 0 ORDER BY id ASC');
        foreach ($data['data'] as $key => $value) {
            $data2 = $this->tables->fetch('deleted = 0 AND parent_1 = '.$value['id'].' AND parent_2 = 0 AND parent_3 = 0 ORDER BY id ASC');
            $data['data'][$key]['child1'] = $data2['data'];
            $data['data'][$key]['tchild1'] = $data2['total']+1;
            foreach ($data2['data'] as $ky => $val) {
                $data3 = $this->tables->fetch('deleted = 0 AND parent_1 = 0 AND parent_2 = '.$val['id'].' AND parent_3 = 0 '.$w.' ORDER BY id ASC');
                $data['data'][$key]['child1'][$ky]['child2'] = $data3['data'];
                $data['data'][$key]['child1'][$ky]['tchild2'] = $data3['total']+1;
                foreach ($data3['data'] as $k => $v) {
                    $data4 = $this->tables->fetch('deleted = 0 AND parent_1 = 0 AND parent_2 = 0 AND parent_3 = '.$v['id'].' '.$w.' ORDER BY id ASC');
                    foreach ($data4['data'] as $x => $y) {
                        $bukti = explode('|', $y['bukti']);
                        $data4['data'][$x]['bukti'] = $bukti;
                        $k_nilai = explode(';', $y['keterangan_nilai']);
                        $data4['data'][$x]['keterangan_nilai'] = $k_nilai;
                        $p_nilai = explode(';', $y['pilihan_nilai']);
                        $data4['data'][$x]['pilihan_nilai'] = $p_nilai;
                    }
                    $data['data'][$key]['child1'][$ky]['child2'][$k]['child3'] = $data4['data'];
                    $data['data'][$key]['child1'][$ky]['child2'][$k]['tchild3'] = $data4['total']+1;
                }
            }
        }
        $this -> view -> assign("irf_pantaibersih", $data['data']);
    }

    private function rfpkb()
    {
        if ($this->roleDataView == 3) {
          $w = ' AND prov=0';
        }
        $this->tables->set('irf_pkb', 'id');
        $data = $this->tables->fetch('deleted = 0 AND parent_1 = 0 AND parent_2 = 0 AND parent_3 = 0 ORDER BY id ASC');
        foreach ($data['data'] as $key => $value) {
            $data2 = $this->tables->fetch('deleted = 0 AND parent_1 = '.$value['id'].' AND parent_2 = 0 AND parent_3 = 0 ORDER BY id ASC');
            $data['data'][$key]['child1'] = $data2['data'];
            $data['data'][$key]['tchild1'] = $data2['total']+1;
            foreach ($data2['data'] as $ky => $val) {
                $data3 = $this->tables->fetch('deleted = 0 AND parent_1 = 0 AND parent_2 = '.$val['id'].' AND parent_3 = 0 '.$w.' ORDER BY id ASC');
                $data['data'][$key]['child1'][$ky]['child2'] = $data3['data'];
                $data['data'][$key]['child1'][$ky]['tchild2'] = $data3['total']+1;
                foreach ($data3['data'] as $k => $v) {
                    $data4 = $this->tables->fetch('deleted = 0 AND parent_1 = 0 AND parent_2 = 0 AND parent_3 = '.$v['id'].' '.$w.' ORDER BY id ASC');
                    foreach ($data4['data'] as $x => $y) {
                        $bukti = explode('|', $y['bukti']);
                        $data4['data'][$x]['bukti'] = $bukti;
                        $k_nilai = explode(';', $y['keterangan_nilai']);
                        $data4['data'][$x]['keterangan_nilai'] = $k_nilai;
                        $p_nilai = explode(';', $y['pilihan_nilai']);
                        $data4['data'][$x]['pilihan_nilai'] = $p_nilai;
                    }
                    $data['data'][$key]['child1'][$ky]['child2'][$k]['child3'] = $data4['data'];
                    $data['data'][$key]['child1'][$ky]['child2'][$k]['tchild3'] = $data4['total']+1;
                }
            }
        }
        $this -> view -> assign("irf_pkb", $data['data']);
    }

    private function rfindohijau()
    {
        if ($this->roleDataView == 3) {
          $w = ' AND prov=0';
        }
        $this->tables->set('irf_indohijau', 'id');
        $data = $this->tables->fetch('deleted = 0 AND parent_1 = 0 AND parent_2 = 0 AND parent_3 = 0 ORDER BY id ASC');
        foreach ($data['data'] as $key => $value) {
            $data2 = $this->tables->fetch('deleted = 0 AND parent_1 = '.$value['id'].' AND parent_2 = 0 AND parent_3 = 0 ORDER BY id ASC');
            $data['data'][$key]['child1'] = $data2['data'];
            $data['data'][$key]['tchild1'] = $data2['total']+1;
            foreach ($data2['data'] as $ky => $val) {
                $data3 = $this->tables->fetch('deleted = 0 AND parent_1 = 0 AND parent_2 = '.$val['id'].' AND parent_3 = 0 '.$w.' ORDER BY id ASC');
                $data['data'][$key]['child1'][$ky]['child2'] = $data3['data'];
                $data['data'][$key]['child1'][$ky]['tchild2'] = $data3['total']+1;
                foreach ($data3['data'] as $k => $v) {
                    $data4 = $this->tables->fetch('deleted = 0 AND parent_1 = 0 AND parent_2 = 0 AND parent_3 = '.$v['id'].' '.$w.' ORDER BY id ASC');
                    foreach ($data4['data'] as $x => $y) {
                        $bukti = explode('|', $y['bukti']);
                        $data4['data'][$x]['bukti'] = $bukti;
                        $k_nilai = explode(';', $y['keterangan_nilai']);
                        $data4['data'][$x]['keterangan_nilai'] = $k_nilai;
                        $p_nilai = explode(';', $y['pilihan_nilai']);
                        $data4['data'][$x]['pilihan_nilai'] = $p_nilai;
                    }
                    $data['data'][$key]['child1'][$ky]['child2'][$k]['child3'] = $data4['data'];
                    $data['data'][$key]['child1'][$ky]['child2'][$k]['tchild3'] = $data4['total']+1;
                }
            }
        }
        $this -> view -> assign("irf_indohijau", $data['data']);
    }

    private function rfgambut()
    {
        if ($this->roleDataView == 3) {
          $w = ' AND prov=0';
        }
        $this->tables->set('irf_gambut', 'id');
        $data = $this->tables->fetch('deleted = 0 AND parent_1 = 0 AND parent_2 = 0 AND parent_3 = 0 ORDER BY id ASC');
        foreach ($data['data'] as $key => $value) {
            $data2 = $this->tables->fetch('deleted = 0 AND parent_1 = '.$value['id'].' AND parent_2 = 0 AND parent_3 = 0 ORDER BY id ASC');
            $data['data'][$key]['child1'] = $data2['data'];
            $data['data'][$key]['tchild1'] = $data2['total']+1;
            foreach ($data2['data'] as $ky => $val) {
                $data3 = $this->tables->fetch('deleted = 0 AND parent_1 = 0 AND parent_2 = '.$val['id'].' AND parent_3 = 0 '.$w.' ORDER BY id ASC');
                $data['data'][$key]['child1'][$ky]['child2'] = $data3['data'];
                $data['data'][$key]['child1'][$ky]['tchild2'] = $data3['total']+1;
                foreach ($data3['data'] as $k => $v) {
                    $data4 = $this->tables->fetch('deleted = 0 AND parent_1 = 0 AND parent_2 = 0 AND parent_3 = '.$v['id'].' '.$w.' ORDER BY id ASC');
                    foreach ($data4['data'] as $x => $y) {
                        $bukti = explode('|', $y['bukti']);
                        $data4['data'][$x]['bukti'] = $bukti;
                        $k_nilai = explode(';', $y['keterangan_nilai']);
                        $data4['data'][$x]['keterangan_nilai'] = $k_nilai;
                        $p_nilai = explode(';', $y['pilihan_nilai']);
                        $data4['data'][$x]['pilihan_nilai'] = $p_nilai;
                    }
                    $data['data'][$key]['child1'][$ky]['child2'][$k]['child3'] = $data4['data'];
                    $data['data'][$key]['child1'][$ky]['child2'][$k]['tchild3'] = $data4['total']+1;
                }
            }
        }
        $this -> view -> assign("irf_gambut", $data['data']);
    }

    private function rfData()
    {
        if ($this -> me['role_user'] == 2) {
            $wProvinsi = "kd_propinsi=" . $this -> me['uid_provinsi'];
            $this -> tables -> set("rf_provinsi", "kd_propinsi");
            $rf = $propSelect = $this -> tables -> fetch($wProvinsi);
            $wRegional = "kd_regional=".$rf['data'][0]['kd_regional'];
            $wLokasi = " AND uid_provinsi=" . $this -> me['uid_provinsi'];
            $this -> tables -> set("rf_kabkota", "kd_kota");
            $rf = $this -> tables -> fetch("deleted=0 AND kd_provinsi=" . $this -> me['uid_provinsi']);
            $this -> view -> assign("kabkota", $rf['data']);
        } elseif ($this -> me['role_user'] == 3) {
            $wProvinsi = "kd_propinsi=" . $this -> me['uid_provinsi'];
            $this -> tables -> set("rf_provinsi", "kd_propinsi");
            $rf = $propSelect = $this -> tables -> fetch($wProvinsi);
            $wRegional = "kd_regional=".$rf['data'][0]['kd_regional'];
            $wLokasi = " AND uid_kabkota=" . $this -> me['uid_kabkota'];
        } elseif ($this -> me['role_user'] == 4 || $this -> me['role_user'] == 5) {
            $wProvinsi = "kd_regional =" . $this -> me['uid_regional'];
            $wLokasi = " AND kd_regional =" . $this -> me['uid_regional'];
        } else {
            $wProvinsi = "";
            $wLokasi = "";
        }

        $this -> tables -> set("rf_regional", "kd_regional");
        $rf = $regSelect = $this -> tables -> fetch($wRegional);
        $this -> view -> assign("regional", $rf['data']);

        $this -> tables -> set("rf_provinsi", "kd_propinsi");
        $rf = $propSelect = $this -> tables -> fetch($wProvinsi);
        $this -> view -> assign("provinsi", $rf['data']);

        $this -> tables -> set("v_lokasi_pemantauan", "uid_lokasi_pemantauan");
        $rf = $this -> tables -> fetch("deleted = 0 AND uid_rf_component = 2" . $wLokasi);
        $this -> view -> assign("lokasi", $rf['data']);

        $this -> tables -> set("rf_kabkota", "kd_kota");
        $rf = $this -> tables -> fetch('deleted = 0');
        $this -> view -> assign("kabkotaSelect2", $rf['data']);

        if ($this->me['role_user']==2) {
            $this -> tables -> set("rf_kabkota", "kd_kota");
            $rf = $this -> tables -> fetch('deleted=0 AND kd_provinsi='.$this -> me['uid_provinsi']);
            $this -> view -> assign("kabkotaSelect", $rf['data']);
            // $this->debug->show($rf);
        }
        if ($this->me['role_user']==4 || $this -> me['role_user'] == 5) {
            $this -> tables -> set("rf_provinsi", "kd_propinsi");
            $rf = $this -> tables -> fetch('kd_regional='.$this -> me['uid_regional']);
            $this -> view -> assign("propSelect", $rf['data']);
            // $this->debug->show($rf);
        }
        if ($this->me['role_user'] < 2) {
          $this -> view -> assign("regSelect", $regSelect['data']);
            $this -> view -> assign("propSelect", $propSelect['data']);
        }
    }

    private function _search($post, $properties)
    {
      $post['form']['keyword'] = str_replace("=","",strip_tags($post['form']['keyword']));
      if ($post['form']['keyword']) {
          if ($properties['total']) {
              $src['w'] .= " AND ";
              $src['w'] .= "(";
              for ($i=4;$i<$properties['total'];$i++) {
                  $src['w'] .= $properties['data'][$i] . " LIKE '%".$post['form']['keyword']."%' OR ";
              }
              $src['w'] .= $properties['data'][$properties['total']-1] . " LIKE '%".$post['form']['keyword']."%' ";
              $src['w'] .= ")";
          }
          $src['urlVar'] .= 'keyword/' . $post['form']['keyword'] . '/';
      }
      return $src;
    }

    public function test(){
      $test = ['', 'aaa'];
      print_r(implode('|', $test));
    }
}
