<?php
/**
 * created at 	: 29/09/2020
 * created by 	: dasendria team
 * desc		  	: controller index IKLHK
 *
 */
class indeksResponController extends Front
{
    public function init()
    {
        if($_SERVER['REMOTE_ADDR']!='103.144.175.182') die('sedang development');

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
        $this -> view -> assign("indeksResponActive", "active");
        $this -> view -> assign("show", $show);
        $this -> view -> assign("message", $message);
        $this -> view -> assign("title", 'INDEKS RESPON');
        $this -> view -> assign("icons", '<i class="ft-bar-chart"></i>');
        $this -> view -> display("index.html");
    }

    private function dataTable()
    {
      $urlVar = BASEURL . $this -> url . '/';
      $w = ' a.deleted=0 AND a.hidden=0 ';
      if ($this -> me['role_user'] == 3 || $this->me['role_user'] == 2) {
          $w .= " AND users_uid =" . $this -> me['uid_users'];
      } elseif ($this->me['role_user'] == 4) {
        $w .=" AND c.kd_regional =".$this->me['uid_regional'];
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
          $urlVar .= 'search/' . urlencode(json_encode($post['form'])) . '/';
          $this -> view -> assign("search", $post['form']);
      } else {
          $w .= " AND a.tahun = 2017";
          $post['form']['tahun'] = 2017;

          $urlVar .= 'search/' . urlencode(json_encode($post['form'])) . '/';
          $this -> view -> assign("search", $post['form']);
      }
      $this->yearActive = $post['form']['tahun'];
      $search_json = urlencode(json_encode($post['form']));
      $this->view->assign("search_json", $search_json);
      //PAGING
      $offset = (isset($_REQUEST['page']) && $_REQUEST['page'] > 1 ? $_REQUEST['page'] - 1 : 0);
      $limit = LIMIT;
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

      $this -> view -> pagination($this -> view, $totalRow, $offset + 1, $limit, $urlVar);
      $this -> view -> assign("urlVar", $urlVar);
      $this -> view -> assign("totalRow", $totalRow);
      $this -> view -> assign("limit", $limit);
      $this -> view -> assign("page", $offset);
      $this -> view -> assign("view", $data['data']);
      if($this->params('debug')=='1'){
        $this->debug->show($data);
      }
    }

    public function deletedData(){
			$post = $this->post();
			if(isset($post['x'])){
				$this->tables->set("ir_parent","indeks_uid");
          if($this->tables->softDelete($post['x'])){
            echo json_encode(array('statusCode' => 200, 'message' => $this->message->delete('success')));
          }else{
            echo json_encode(array('statusCode' => 400, 'message' => $this->message->delete('failed')));
          }
			}else{
				echo json_encode(array('statusCode' => 403, 'message' => $this->message->access()));
			}
		}

    public function respon()
    {
      $post = $this -> post();
      $parent = $this->params('parent');
      $parent2 = $this->params('parent2');
      $parent3 = $this->params('parent3');
      $parent4 = $this->params('parent4');
      $parent5 = $this->params('parent5');
      $tipe = $this->params('tipe');
      $tipe2 = $this->params('tipe2');
      $tipe3 = $this->params('tipe3');
      $tipe4 = $this->params('tipe4');
        $tipe5 = $this->params('tipe5');
        $tahun = $this->params('tahun');
        $nama_propinsi = $this->params('nama_propinsi');
        $nama_kabkot = $this->params('nama_kabkot');
      if (isset($post['submit'])) {
          $post['form']['users_uid'] = $this -> me['uid_users'];
          $this->tables->set('ir_parent', 'indeks_uid');
          $data = $this->tables->fetch('deleted = 0 AND users_uid='.$post['form']['users_uid'].' AND tahun='.$post['form']['tahun'].' AND tipe='.$post['form']['tipe']);
          if ($data['total']) {
            $uid = $data['data'][0]['indeks_uid'];
            $tipe = $data['data'][0]['tipe'];
            $post['form']['indeks_uid'] = $uid;
          }
          $this -> tables -> set("ir_parent", "indeks_uid");
          if ($this -> tables -> post($post)) {
            $last = $this -> tables -> lastInsertID();
            if ($last) {
              $uid = $last;
            }
            $this->redirect($this->url.'/parent/'.$uid.'/tipe/'.$post['form']['tipe'].'/tahun/'.$post['form']['tahun']);
          } else {
              $message = "Gagal menyimpan data !";
          }
      }
      if (isset($post['submit-data'])) {
        $this -> tables -> set("indeks_respon", "indeks_uid");
        foreach ($post['form']['parent_uid'] as $key => $value) {
          $file = array_filter($_FILES['bukti']['name'][$post['form']['ref_uid'][$key]]);
          foreach ($file as $ky => $val) {
            $tmpFilePath = $_FILES['bukti']['tmp_name'][$post['form']['ref_uid'][$key]][$ky];
            if ($tmpFilePath != ""){
              $filename[$post['form']['ref_uid'][$key]][$ky] = time().$ky.$val;
              $newFilePath = UPLOADFOLDER ."/respon/" . $filename[$post['form']['ref_uid'][$key]][$ky];
              if(move_uploaded_file($tmpFilePath, $newFilePath)) {
                $filenam[$key] = implode('|',$filename[$post['form']['ref_uid'][$key]]);
                $post1['form']['bukti'] = $filenam[$key];
              }
            }
          }
          $post1['form']['parent_uid'] = $value;
          if ($post['form']['isian1'] || $post['form']['isian2']) {
            $post1['form']['isian'] = $post['form']['isian1'][$key].'|'.$post['form']['isian2'][$key];
          } else {
            $post1['form']['isian'] = $post['form']['isian'][$key];
          }
          $post1['form']['ref_uid'] = $post['form']['ref_uid'][$key];
          $post1['form']['tipe'] = $post['form']['tipe'][$key];
          $post1['form']['indeks_uid'] = $post['form']['indeks_uid'][$key];
          // $this->debug->show($post1);
          $post1['submit'] = true;
          if ($this -> tables -> post($post1)) {
            $this->redirect($this->url.'/parent/'.$parent.'/tipe/'.$tipe.'/tahun/'.$tahun);
          } else {
              $message = "Gagal menyimpan data !";
          }
        }
      }
        $this->getData();
        $this->indeksRespon($parent, $tipe);
        $this->indeksRespon2($parent2, $tipe2);
        $this->indeksRespon3($parent3, $tipe3);
        $this->indeksRespon4($parent4, $tipe4);
        $this->indeksRespon5($parent5, $tipe5);
        $this->view->assign("parent", $parent);
        $this->view->assign("parent2", $parent2);
        $this->view->assign("parent3", $parent3);
        $this->view->assign("parent4", $parent4);
        $this->view->assign("parent5", $parent5);
        $this->view->assign("tipe", $tipe);
        $this->view->assign("tipe2", $tipe2);
        $this->view->assign("tipe3", $tipe3);
        $this->view->assign("tipe4", $tipe4);
        $this->view->assign("tipe5", $tipe5);
        $this->view->assign("tahun", $tahun);
        $this->view->assign("nama_propinsi", $nama_propinsi);
        $this->view->assign("nama_kabkot", $nama_kabkot);
        $this -> view -> assign("indeksResponActive", "active");
        $this -> view -> assign("show", $show);
        $this -> view -> assign("message", $message);
        $this -> view -> assign("title", 'INDEKS RESPON');
        $this -> view -> assign("icons", '<i class="ft-bar-chart"></i>');
        $this -> view -> display("index.html");
    }

    public function respon2()
    {
      $post = $this -> post();
      $parent = $this->params('parent');
      $parent2 = $this->params('parent2');
      $parent3 = $this->params('parent3');
      $parent4 = $this->params('parent4');
      $parent5 = $this->params('parent5');
      $tipe = $this->params('tipe');
      $tipe2 = $this->params('tipe2');
      $tipe3 = $this->params('tipe3');
      $tipe4 = $this->params('tipe4');
        $tipe5 = $this->params('tipe5');
        $tahun = $this->params('tahun');
        $nama_propinsi = $this->params('nama_propinsi');
        $nama_kabkot = $this->params('nama_kabkot');
      if (isset($post['submit'])) {
          $post['form']['users_uid'] = $this -> me['uid_users'];
          $this->tables->set('ir_parent', 'indeks_uid');
          $data = $this->tables->fetch('deleted = 0 AND users_uid='.$post['form']['users_uid'].' AND tahun='.$post['form']['tahun'].' AND tipe='.$post['form']['tipe']);
          if ($data['total']) {
            $uid = $data['data'][0]['indeks_uid'];
            $tipe = $data['data'][0]['tipe'];
            $post['form']['indeks_uid'] = $uid;
          }
          $this -> tables -> set("ir_parent", "indeks_uid");
          if ($this -> tables -> post($post)) {
            $last = $this -> tables -> lastInsertID();
            if ($last) {
              $uid = $last;
            }
            $this->redirect($this->url.'/parent/'.$uid.'/tipe/'.$post['form']['tipe'].'/tahun/'.$post['form']['tahun']);
          } else {
              $message = "Gagal menyimpan data !";
          }
      }
      if (isset($post['submit-data'])) {
        $this -> tables -> set("indeks_respon", "indeks_uid");
        foreach ($post['form']['parent_uid'] as $key => $value) {
          $file = array_filter($_FILES['bukti']['name'][$post['form']['ref_uid'][$key]]);
          foreach ($file as $ky => $val) {
            $tmpFilePath = $_FILES['bukti']['tmp_name'][$post['form']['ref_uid'][$key]][$ky];
            if ($tmpFilePath != ""){
              $filename[$post['form']['ref_uid'][$key]][$ky] = time().$ky.$val;
              $newFilePath = UPLOADFOLDER ."/respon/" . $filename[$post['form']['ref_uid'][$key]][$ky];
              if(move_uploaded_file($tmpFilePath, $newFilePath)) {
                $filenam[$key] = implode('|',$filename[$post['form']['ref_uid'][$key]]);
                $post1['form']['bukti'] = $filenam[$key];
              }
            }
          }
          $post1['form']['parent_uid'] = $value;
          if ($post['form']['isian1'] || $post['form']['isian2']) {
            $post1['form']['isian'] = $post['form']['isian1'][$key].'|'.$post['form']['isian2'][$key];
          } else {
            $post1['form']['isian'] = $post['form']['isian'][$key];
          }
          $post1['form']['ref_uid'] = $post['form']['ref_uid'][$key];
          $post1['form']['tipe'] = $post['form']['tipe'][$key];
          $post1['form']['indeks_uid'] = $post['form']['indeks_uid'][$key];
          $post1['submit'] = true;
          if ($this -> tables -> post($post1)) {
            $this->redirect($this->url.'/parent/'.$parent.'/tipe/'.$tipe.'/tahun/'.$tahun);
          } else {
              $message = "Gagal menyimpan data !";
          }
        }
      }
        $this->getData();
        $this->indeksRespon($parent, $tipe);
        $this->indeksRespon2($parent2, $tipe2);
        $this->indeksRespon3($parent3, $tipe3);
        $this->indeksRespon4($parent4, $tipe4);
        $this->indeksRespon5($parent5, $tipe5);
        $this->view->assign("parent", $parent);
        $this->view->assign("parent2", $parent2);
        $this->view->assign("parent3", $parent3);
        $this->view->assign("parent4", $parent4);
        $this->view->assign("parent5", $parent5);
        $this->view->assign("tipe", $tipe);
        $this->view->assign("tipe2", $tipe2);
        $this->view->assign("tipe3", $tipe3);
        $this->view->assign("tipe4", $tipe4);
        $this->view->assign("tipe5", $tipe5);
        $this->view->assign("tahun", $tahun);
        $this->view->assign("nama_propinsi", $nama_propinsi);
        $this->view->assign("nama_kabkot", $nama_kabkot);
        $this -> view -> assign("indeksResponActive", "active");
        $this -> view -> assign("show", $show);
        $this -> view -> assign("message", $message);
        $this -> view -> assign("title", 'INDEKS RESPON');
        $this -> view -> assign("icons", '<i class="ft-bar-chart"></i>');
        $this -> view -> display("index.html");
    }

    private function indeksRespon($parent, $tipe)
    {
        $this->tables->set('indeks_respon', 'indeks_uid');
        $data = $this->tables->fetch('deleted = 0 AND parent_uid ='.$parent.' AND tipe ='.$tipe);
        foreach ($data['data'] as $key => $value) {
          $data2[$value['ref_uid']]['isian'] = explode('|',$value['isian']);
          $data2[$value['ref_uid']]['bukti'] = explode('|',$value['bukti']);
          $data2[$value['ref_uid']]['indeks_uid'] = $value['indeks_uid'];
        }
        // $this->debug->show($data2);
        $this -> view -> assign("data_ir", $data2);
    }

    private function indeksRespon2($parent2, $tipe2)
    {
        $this->tables->set('indeks_respon', 'indeks_uid');
        $data = $this->tables->fetch('deleted = 0 AND parent_uid ='.$parent2.' AND tipe ='.$tipe2);
        foreach ($data['data'] as $key => $value) {
          $data2[$value['ref_uid']]['isian'] = explode('|',$value['isian']);
          $data2[$value['ref_uid']]['bukti'] = explode('|',$value['bukti']);
          $data2[$value['ref_uid']]['indeks_uid'] = $value['indeks_uid'];
        }
        // $this->debug->show($data2);
        $this -> view -> assign("data_ir2", $data2);
    }

    private function indeksRespon3($parent3, $tipe3)
    {
        $this->tables->set('indeks_respon', 'indeks_uid');
        $data = $this->tables->fetch('deleted = 0 AND parent_uid ='.$parent3.' AND tipe ='.$tipe3);
        foreach ($data['data'] as $key => $value) {
          $data2[$value['ref_uid']]['isian'] = explode('|',$value['isian']);
          $data2[$value['ref_uid']]['bukti'] = explode('|',$value['bukti']);
          $data2[$value['ref_uid']]['indeks_uid'] = $value['indeks_uid'];
        }
        // $this->debug->show($data2);
        $this -> view -> assign("data_ir3", $data2);
    }

    private function indeksRespon4($parent4, $tipe4)
    {
        $this->tables->set('indeks_respon', 'indeks_uid');
        $data = $this->tables->fetch('deleted = 0 AND parent_uid ='.$parent4.' AND tipe ='.$tipe4);
        foreach ($data['data'] as $key => $value) {
          $data2[$value['ref_uid']]['isian'] = explode('|',$value['isian']);
          $data2[$value['ref_uid']]['bukti'] = explode('|',$value['bukti']);
          $data2[$value['ref_uid']]['indeks_uid'] = $value['indeks_uid'];
        }
        // $this->debug->show($data2);
        $this -> view -> assign("data_ir4", $data2);
    }

    private function indeksRespon5($parent5, $tipe5)
    {
        $this->tables->set('indeks_respon', 'indeks_uid');
        $data = $this->tables->fetch('deleted = 0 AND parent_uid ='.$parent5.' AND tipe ='.$tipe5);
        foreach ($data['data'] as $key => $value) {
          $data2[$value['ref_uid']]['isian'] = explode('|',$value['isian']);
          $data2[$value['ref_uid']]['bukti'] = explode('|',$value['bukti']);
          $data2[$value['ref_uid']]['indeks_uid'] = $value['indeks_uid'];
        }
        // $this->debug->show($data2);
        $this -> view -> assign("data_ir5", $data2);
    }

    private function getData()
    {
        $this->rflangitbiru();
        $this->rfpantaibersih();
        $this->rfpkb();
        $this->rfindohijau();
        $this->rfgambut();
        $data = ['Sudah', 'Belum'];
        $this -> view -> assign("pilihan", $data);
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

    private function rfData()
    {
        if ($this -> me['role_user'] == 2) {
            $wProvinsi = "kd_propinsi=" . $this -> me['uid_provinsi'];
            $wLokasi = " AND uid_provinsi=" . $this -> me['uid_provinsi'];
            $this -> tables -> set("rf_kabkota", "kd_kota");
            $rf = $this -> tables -> fetch("kd_provinsi=" . $this -> me['uid_provinsi']);
            $this -> view -> assign("kabkota", $rf['data']);
        } elseif ($this -> me['role_user'] == 3) {
            $wProvinsi = "kd_propinsi=" . $this -> me['uid_provinsi'];
            $wLokasi = " AND uid_kabkota=" . $this -> me['uid_kabkota'];
        } elseif ($this -> me['role_user'] == 4) {
            $wProvinsi = "kd_regional =" . $this -> me['uid_regional'];
            $wLokasi = " AND kd_regional =" . $this -> me['uid_regional'];
        } else {
            $wProvinsi = "";
            $wLokasi = "";
        }
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

    private function _getProperties($model)
    {// function get Coloums in table
        $sql = "SHOW COLUMNS FROM " . $model;
        $result = $this -> db -> fetch($sql);
        //$this->debug->show($result);
        if ($result['total']) {
            $data = array();
            foreach ($result['data'] as $key => $val) {
                $data[$key] = $val['Field'];
            }
            $result['data'] = $data;
            return $result;
        } else {
            die('Coloums of table ' . $model . ' not found');
        }
    }
}
