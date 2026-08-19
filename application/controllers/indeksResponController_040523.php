<?php
/**
 * created at 	: 29/09/2020
 * created by 	: dasendria team
 * desc		  	: controller index IKLHK
 *
 */
class indeksResponController extends Front
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
        if($_SERVER['REMOTE_ADDR']!='103.144.175.184') die('SEDANG DEVELOPMENT');
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
    }

    //INDEX FUNCTION IS A DEFAULT ACTION
    public function index()
    {
        $this->rfData();
        $this->dataTable();
        $this -> cekLockSystem(4, $this -> me['uid_users']);
        $this -> view -> assign("indeksResponActive", "active");
        $this -> view -> assign("show", $show);
        $this -> view -> assign("message", $message);
        $this -> view -> assign("title", 'ISIAN INDEKS RESPON');
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
      } elseif ($this -> me['role_user'] == 4) {
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
      $offset = (isset($_REQUEST['page']) && $_REQUEST['page'] > 1 ? $_REQUEST['page'] - 1 : 0);
      $limit = LIMIT;
      // if($this->params('debug')=='1'){
      //   $limit = 1000;
      // }
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

      // COUNT HARUS DICARI LAGI
      $sql2 = 'SELECT COUNT(a.indeks_uid)
              FROM ir_parent a
              LEFT JOIN users b ON a.users_uid = b.uid_users
              LEFT JOIN rf_provinsi c ON b.uid_provinsi = c.kd_propinsi
              LEFT JOIN rf_kabkota d ON b.uid_kabkota = d.kd_kota
              WHERE '. $w .' GROUP BY a.users_uid ORDER BY a.tahun DESC';

      // $All = $this -> db -> query('SELECT COUNT(DISTINCT(a.users_uid)) as x FROM ir_parent a WHERE '. $w);
      // $totalRow = (isset($All -> fields['x']) ? $All -> fields['x'] : 0);
      $All = $this -> db -> query($sql2);
      $totalRow = ($All -> _numOfRows ? $All -> _numOfRows : 0);
      // $this->debug->show($totalRow);
      if($totalRow > 0){
        // TOTAL ISIAN LANGIT BIRU (LB)
        $LB = $this -> db -> query('SELECT COUNT(id) as x FROM irf_langitbiru WHERE deleted=0 AND hidden=0 AND parent_3 > 0');
        $LB = $LB->fields['x'];
        // TOTAL ISIAN PANTAI BERSIH
        $PB = $this -> db -> query('SELECT COUNT(id) as x FROM irf_pantaibersih WHERE deleted=0 AND hidden=0 AND parent_3 > 0');
        $PB = $PB->fields['x'];
        // TOTAL ISIAN PROGRAM KALI BESIH
        $PKB = $this -> db -> query('SELECT COUNT(id) as x FROM irf_pkb WHERE deleted=0 AND hidden=0 AND parent_3 > 0');
        $PKB = $PKB->fields['x'];
        // TOTAL ISIAN INDONESIA HIJAU
        $IH = $this -> db -> query('SELECT COUNT(id) as x FROM irf_indohijau WHERE deleted=0 AND hidden=0 AND parent_3 > 0');
        $IH = $IH->fields['x'];
        // TOTAL ISIAN GAMBUT LESTARI
        $GL = $this -> db -> query('SELECT COUNT(id) as x FROM irf_gambut WHERE deleted=0 AND hidden=0 AND parent_3 > 0');
        $GL = $GL->fields['x'];

        $isian_per_tipe = array(1 => $LB, 2 => $PB, 3 => $PKB, 4 => $IH, 5 => $GL);
        // $this->debug->show($data);
        foreach($data['data'] as $key=>$val){
          $sql = 'SELECT DISTINCT(tipe) as tipe, COUNT(tipe) jumlah_mengisi
                  FROM indeks_respon
                  WHERE deleted=0 AND hidden=0 AND parent_uid IN (' . $val['indeks_gabung'] . ') AND (isian <> "" AND isian IS NOT NULL AND isian <> "|")
                  GROUP BY tipe';
          $child = $this->tables->query($sql);
          $isian = array();
          foreach($child['data'] as $ke=>$va){
            $total_isian = $isian_per_tipe[$va['tipe']];
            // $child['data'][$ke]['total_isian'] = $total_isian;
            // $child['data'][$ke]['persen_mengisi'] = ROUND($va['jumlah_mengisi'] / $total_isian * 100, 0);
            $isian[$va['tipe']] = array (
                                    'tipe'            => $va['tipe'],
                                    'jumlah_mengisi'  => $va['jumlah_mengisi'],
                                    'total_pengisian' => $total_isian,
                                    'persen_mengisi'  => ROUND($va['jumlah_mengisi'] / $total_isian * 100, 0)
                                  );
          }
          // $data['data'][$key]['isian'] = $child['data'];
          $data['data'][$key]['isian'] = $isian;
        }
      }
      if($this->params('debug')=='1'){
        $this->debug->show($data);
      }

      $this -> view -> pagination($this -> view, $totalRow, $offset + 1, $limit, $urlVar);
      $this -> view -> assign("urlVar", $urlVar);
      $this -> view -> assign("totalRow", $totalRow);
      $this -> view -> assign("limit", $limit);
      $this -> view -> assign("page", $offset);
      $this -> view -> assign("view", $data['data']);
    }

    public function respon()
    {
      $post = $this->post();
      $post1 = [];
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
        // $this->debug->show($post);
        foreach ($post['form']['parent_uid'] as $key => $value) {
          unset($file);unset($post1);
          $ref_uid = $post['form']['ref_uid'][$key];
          $file[$ref_uid] = $_FILES['bukti']['name'][$ref_uid];
          foreach ($file[$ref_uid] as $ky => $val) {
            $tmpFilePath = $_FILES['bukti']['tmp_name'][$ref_uid][$ky];
            if (isset($tmpFilePath) && $tmpFilePath){
              $file_upload = time().'_'.$val;
              $newFilePath = UPLOADFOLDER ."/respon/" . $file_upload;
              if(move_uploaded_file($tmpFilePath, $newFilePath)) {
              // if(TRUE) {
                $filename[$ref_uid][$ky] = $file_upload;
              }
            }else{
              $filename[$ref_uid][$ky] = '';
            }
          }
          if($post['form']['indeks_uid'][$key]){
            $filename[$ref_uid] = $this->_checkFile($post['form']['indeks_uid'][$key], $filename[$ref_uid]);
          }
          $post1['form']['bukti'] = implode('|', $filename[$ref_uid]);

          $post1['form']['parent_uid'] = $value;
          if ($post['form']['isian1'] || $post['form']['isian2'] || $post['form']['isian3'] || $post['form']['isian4']) {
            $post1['form']['isian'] = $post['form']['isian1'][$key].'|'.$post['form']['isian2'][$key].'|'.$post['form']['isian3'][0].'|'.$post['form']['isian4'][0].'|'.$post['form']['isian5'][0].'|'.$post['form']['isian6'][0].'|'.$post['form']['isian7'][0].'|'.$post['form']['isian8'][0];
          } else {
            $post1['form']['isian'] = $post['form']['isian'][$key];
          }
          $post1['form']['isian'] = strip_tags($post1['form']['isian']);
          $post1['form']['ref_uid'] = $post['form']['ref_uid'][$key];
          $post1['form']['tipe'] = $post['form']['tipe'][$key];
          $post1['form']['indeks_uid'] = $post['form']['indeks_uid'][$key];
          // $this->debug->show($post);
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
            $dataEdit = $this->_editIndeksRespon($val['indeks_uid'], $val['tipe']);
            $userEntri = $val['users_uid'];
            $this->view-> assign("data_ir_".$val['tipe'], $dataEdit);
          }
          // if ($this->params('debug')==1) {
          //   $this->debug->show($dataType);
          // }
          $userEntri = $this->_getUserEntri($userEntri);

          if($userEntri['role_user'] == 2){
            $userEntri['show_gambut'] = $userEntri['gambut_provinsi'];
          }

          if($userEntri['role_user'] == 3){
            $userEntri['show_gambut'] = $userEntri['gambut_kabkota'];
          }

          // $this->debug->show($userEntri);
          // AMBIL ISIAN
          $this->rflangitbiru();
          $this->rfpkb();
          $this->rfindohijau();
          if($userEntri['show_gambut']){
            $this->rfgambut();
          }else{
            unset($dataType[5]);
          }
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
      $this->view->assign("title", 'ISIAN INDEKS RESPON');
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
          $data2[$value['ref_uid']]['bukti'] = explode('|',$value['bukti']);
          $data2[$value['ref_uid']]['indeks_uid'] = $value['indeks_uid'];
        }
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
        $this->tables->set('irf_langitbiru', 'id');
        $data = $this->tables->fetch('deleted = 0 AND parent_1 = 0 AND parent_2 = 0 AND parent_3 = 0 ORDER BY id ASC');
        foreach ($data['data'] as $key => $value) {
            $data2 = $this->tables->fetch('deleted = 0 AND parent_1 = '.$value['id'].' AND parent_2 = 0 AND parent_3 = 0 ORDER BY id ASC');
            $data['data'][$key]['child1'] = $data2['data'];
            $data['data'][$key]['tchild1'] = $data2['total']+1;
            foreach ($data2['data'] as $ky => $val) {
                $data3 = $this->tables->fetch('deleted = 0 AND parent_1 = 0 AND parent_2 = '.$val['id'].' AND parent_3 = 0 ORDER BY id ASC');
                $data['data'][$key]['child1'][$ky]['child2'] = $data3['data'];
                $data['data'][$key]['child1'][$ky]['tchild2'] = $data3['total']+1;
                foreach ($data3['data'] as $k => $v) {
                    $data4 = $this->tables->fetch('deleted = 0 AND parent_1 = 0 AND parent_2 = 0 AND parent_3 = '.$v['id'].' ORDER BY id ASC');
                    foreach ($data4['data'] as $x => $y) {
                        $bukti = explode('|', $y['bukti']);
                        $data4['data'][$x]['bukti'] = $bukti;
                    }
                    $data['data'][$key]['child1'][$ky]['child2'][$k]['child3'] = $data4['data'];
                    $data['data'][$key]['child1'][$ky]['child2'][$k]['tchild3'] = $data4['total']+1;
                }
            }
        }
        $this -> view -> assign("irf_langitbiru", $data['data']);
    }

    private function rfpantaibersih()
    {
        $this->tables->set('irf_pantaibersih', 'id');
        $data = $this->tables->fetch('deleted = 0 AND parent_1 = 0 AND parent_2 = 0 AND parent_3 = 0 ORDER BY id ASC');
        foreach ($data['data'] as $key => $value) {
            $data2 = $this->tables->fetch('deleted = 0 AND parent_1 = '.$value['id'].' AND parent_2 = 0 AND parent_3 = 0 ORDER BY id ASC');
            $data['data'][$key]['child1'] = $data2['data'];
            $data['data'][$key]['tchild1'] = $data2['total']+1;
            foreach ($data2['data'] as $ky => $val) {
                $data3 = $this->tables->fetch('deleted = 0 AND parent_1 = 0 AND parent_2 = '.$val['id'].' AND parent_3 = 0 ORDER BY id ASC');
                $data['data'][$key]['child1'][$ky]['child2'] = $data3['data'];
                $data['data'][$key]['child1'][$ky]['tchild2'] = $data3['total']+1;
                foreach ($data3['data'] as $k => $v) {
                    $data4 = $this->tables->fetch('deleted = 0 AND parent_1 = 0 AND parent_2 = 0 AND parent_3 = '.$v['id'].' ORDER BY id ASC');
                    foreach ($data4['data'] as $x => $y) {
                        $bukti = explode('|', $y['bukti']);
                        $data4['data'][$x]['bukti'] = $bukti;
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
        $this->tables->set('irf_pkb', 'id');
        $data = $this->tables->fetch('deleted = 0 AND parent_1 = 0 AND parent_2 = 0 AND parent_3 = 0 ORDER BY id ASC');
        foreach ($data['data'] as $key => $value) {
            $data2 = $this->tables->fetch('deleted = 0 AND parent_1 = '.$value['id'].' AND parent_2 = 0 AND parent_3 = 0 ORDER BY id ASC');
            $data['data'][$key]['child1'] = $data2['data'];
            $data['data'][$key]['tchild1'] = $data2['total']+1;
            foreach ($data2['data'] as $ky => $val) {
                $data3 = $this->tables->fetch('deleted = 0 AND parent_1 = 0 AND parent_2 = '.$val['id'].' AND parent_3 = 0 ORDER BY id ASC');
                $data['data'][$key]['child1'][$ky]['child2'] = $data3['data'];
                $data['data'][$key]['child1'][$ky]['tchild2'] = $data3['total']+1;
                foreach ($data3['data'] as $k => $v) {
                    $data4 = $this->tables->fetch('deleted = 0 AND parent_1 = 0 AND parent_2 = 0 AND parent_3 = '.$v['id'].' ORDER BY id ASC');
                    foreach ($data4['data'] as $x => $y) {
                        $bukti = explode('|', $y['bukti']);
                        $data4['data'][$x]['bukti'] = $bukti;
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
        $this->tables->set('irf_indohijau', 'id');
        $data = $this->tables->fetch('deleted = 0 AND parent_1 = 0 AND parent_2 = 0 AND parent_3 = 0 ORDER BY id ASC');
        foreach ($data['data'] as $key => $value) {
            $data2 = $this->tables->fetch('deleted = 0 AND parent_1 = '.$value['id'].' AND parent_2 = 0 AND parent_3 = 0 ORDER BY id ASC');
            $data['data'][$key]['child1'] = $data2['data'];
            $data['data'][$key]['tchild1'] = $data2['total']+1;
            foreach ($data2['data'] as $ky => $val) {
                $data3 = $this->tables->fetch('deleted = 0 AND parent_1 = 0 AND parent_2 = '.$val['id'].' AND parent_3 = 0 ORDER BY id ASC');
                $data['data'][$key]['child1'][$ky]['child2'] = $data3['data'];
                $data['data'][$key]['child1'][$ky]['tchild2'] = $data3['total']+1;
                foreach ($data3['data'] as $k => $v) {
                    $data4 = $this->tables->fetch('deleted = 0 AND parent_1 = 0 AND parent_2 = 0 AND parent_3 = '.$v['id'].' ORDER BY id ASC');
                    foreach ($data4['data'] as $x => $y) {
                        $bukti = explode('|', $y['bukti']);
                        $data4['data'][$x]['bukti'] = $bukti;
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
        $this->tables->set('irf_gambut', 'id');
        $data = $this->tables->fetch('deleted = 0 AND parent_1 = 0 AND parent_2 = 0 AND parent_3 = 0 ORDER BY id ASC');
        foreach ($data['data'] as $key => $value) {
            $data2 = $this->tables->fetch('deleted = 0 AND parent_1 = '.$value['id'].' AND parent_2 = 0 AND parent_3 = 0 ORDER BY id ASC');
            $data['data'][$key]['child1'] = $data2['data'];
            $data['data'][$key]['tchild1'] = $data2['total']+1;
            foreach ($data2['data'] as $ky => $val) {
                $data3 = $this->tables->fetch('deleted = 0 AND parent_1 = 0 AND parent_2 = '.$val['id'].' AND parent_3 = 0 ORDER BY id ASC');
                $data['data'][$key]['child1'][$ky]['child2'] = $data3['data'];
                $data['data'][$key]['child1'][$ky]['tchild2'] = $data3['total']+1;
                foreach ($data3['data'] as $k => $v) {
                    $data4 = $this->tables->fetch('deleted = 0 AND parent_1 = 0 AND parent_2 = 0 AND parent_3 = '.$v['id'].' ORDER BY id ASC');
                    foreach ($data4['data'] as $x => $y) {
                        $bukti = explode('|', $y['bukti']);
                        $data4['data'][$x]['bukti'] = $bukti;
                    }
                    $data['data'][$key]['child1'][$ky]['child2'][$k]['child3'] = $data4['data'];
                    $data['data'][$key]['child1'][$ky]['child2'][$k]['tchild3'] = $data4['total']+1;
                }
            }
        }
        $this -> view -> assign("irf_gambut", $data['data']);
    }

    private function cekLockSystem($menu, $users)
    {
        $messageLock = null;
        $lockAction = 0;
        $data = $this -> tables -> query("SELECT * FROM rf_lock_system WHERE deleted = 0 AND aktif = 1");
        if ($data['total']) {
            $data['data'][0]['menu'] = explode(",", $data['data'][0]['menu']);
            $data['data'][0]['kabkota'] = explode(",", $data['data'][0]['kabkota']);
            $data['data'][0]['provinsi'] = explode(",", $data['data'][0]['provinsi']);
            $data['data'][0]['p3e'] = explode(",", $data['data'][0]['p3e']);
            if (is_numeric(array_search($menu, $data['data'][0]['menu']))) {
                // $messageLock .= " abaikan pesan, halaman sedang dalam pengembangan";
                if (strtotime($data['data'][0]['tanggal_mulai']) <= strtotime(date('Y-m-d')) && strtotime($data['data'][0]['tanggal_selesai']) >= strtotime(date('Y-m-d'))) {
                  if (is_numeric(array_search($users, $data['data'][0]['kabkota'])) || is_numeric(array_search($users, $data['data'][0]['provinsi'])) || is_numeric(array_search($users, $data['data'][0]['p3e']))) {
                    $lockAction = 0;
                  }else {
                    $messageLock .= "<br>";
                    $messageLock .= "Halaman ini dikunci untuk sementara waktu dari tanggal " . $this -> format -> dateFormat($data['data'][0]['tanggal_mulai']) . " <b>s/d</b> " . $this -> format -> dateFormat($data['data'][0]['tanggal_selesai']);
                    $messageLock .= ($data['data'][0]['deskripsi'] ? '<br>' . $data['data'][0]['deskripsi'] : '');
                    $lockAction = 1;
                  }
                } elseif (strtotime("-3 week", strtotime($data['data'][0]['tanggal_mulai'])) <= strtotime(date('Y-m-d')) && strtotime($data['data'][0]['tanggal_selesai']) >= strtotime(date('Y-m-d'))) {
                    $messageLock .= "<br>";
                    $messageLock .= "Halaman ini akan dikunci untuk sementara waktu dari tanggal " . $this -> format -> dateFormat($data['data'][0]['tanggal_mulai']) . " <b>s/d</b> " . $this -> format -> dateFormat($data['data'][0]['tanggal_selesai']);
                    $messageLock .= ($data['data'][0]['deskripsi'] ? '<br>' . $data['data'][0]['deskripsi'] : '');
                    $lockAction = 0;
                }
            }

            $data['data'][0]['menu_tahunan'] = explode(",", $data['data'][0]['menu_tahunan']);
            $data['data'][0]['tahun'] = explode(",", $data['data'][0]['tahun']);
            if (is_numeric(array_search($menu, $data['data'][0]['menu_tahunan'])) && is_numeric(array_search($this->yearActive, $data['data'][0]['tahun']))) {
                $lockActionYear = $this->yearActive;
            } else {
                $lockActionYear = 0;
            }
        }
        $this -> view -> assign("messageLock", $messageLock);
        $this -> view -> assign("lockAction", $lockAction);
        $this -> view -> assign("lockActionYear", $lockActionYear);
        if ($this->params('debug')==1) {
          $this->debug->show($lockAction);
        }
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
            $rf = $this -> tables -> fetch("kd_provinsi=" . $this -> me['uid_provinsi']);
            $this -> view -> assign("kabkota", $rf['data']);
        } elseif ($this -> me['role_user'] == 3) {
            $wProvinsi = "kd_propinsi=" . $this -> me['uid_provinsi'];
            $this -> tables -> set("rf_provinsi", "kd_propinsi");
            $rf = $propSelect = $this -> tables -> fetch($wProvinsi);
            $wRegional = "kd_regional=".$rf['data'][0]['kd_regional'];
            $wLokasi = " AND uid_kabkota=" . $this -> me['uid_kabkota'];
        } elseif ($this -> me['role_user'] == 4) {
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
            $rf = $this -> tables -> fetch('kd_provinsi='.$this -> me['uid_provinsi']);
            $this -> view -> assign("kabkotaSelect", $rf['data']);
            // $this->debug->show($rf);
        }
        if ($this->me['role_user']==4) {
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
