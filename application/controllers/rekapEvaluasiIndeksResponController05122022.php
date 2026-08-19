<?php
/**
 * created at 	: 29/09/2020
 * created by 	: dasendria team
 * desc		  	: controller index IKLHK
 *
 */
class rekapEvaluasiIndeksResponController extends Front
{
    public $IRType = array(
      1 => 'Langit Biru',
      2 => 'Pantai Bersih',
      3 => 'Program Kali Bersih',
      4 => 'Indonesia Hijau',
      5 => 'Gambut Lestari'
    );

    public function init()
    {
        // if($_SERVER['REMOTE_ADDR']=='114.124.205.99') die('sedang development');
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
    }

    //INDEX FUNCTION IS A DEFAULT ACTION
    public function index()
    {
      $this->rflangitbiru();
      $this->rfpantaibersih();
      $this->rfpkb();
      $this->rfindohijau();
      $this->rfgambut();
      $this->dataTable();
      $this->view->assign("indeksResponActive", "active");
      $this->view->assign("message", $message);
      $this->view->assign("view", $view);
      $this->view->assign("title", 'REKAP EVALUASI INDEKS RESPON');
      $this->view->assign("icons", '<i class="ft-bar-chart"></i>');
      $this->view->display("index.html");
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
          if ($post['form']['tahun']) {
              $w .= " AND a.tahun =" . $post['form']['tahun'];
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
      // DATA
      $sql = 'SELECT MIN(a.indeks_uid) as min_index, GROUP_CONCAT(a.indeks_uid SEPARATOR ",") indeks_gabung, a.users_uid, a.tahun, MIN(a.tipe) as min_tipe, GROUP_CONCAT(a.tipe SEPARATOR ",") AS tipe_gabung,
                     b.name, b.uid_provinsi, b.uid_kabkota, c.kd_regional, c.nama_propinsi, d.nama_kabkot
              FROM ir_parent a
              LEFT JOIN users b ON a.users_uid = b.uid_users
              LEFT JOIN rf_provinsi c ON b.uid_provinsi = c.kd_propinsi
              LEFT JOIN rf_kabkota d ON b.uid_kabkota = d.kd_kota
              LEFT JOIN rf_regional e ON c.kd_regional = e.kd_regional
              WHERE '. $w .' GROUP BY a.users_uid ORDER BY a.tahun DESC';
      $data1 = $this->tables->query($sql);
      $data2 = $this->tables->query($sql);
      $data3 = $this->tables->query($sql);
      $data4 = $this->tables->query($sql);
      $data5 = $this->tables->query($sql);

      // LANGIT BITU
      foreach($data1['data'] as $key=>$val){
        $sql = 'SELECT *
                FROM indeks_respon
                WHERE deleted=0 AND hidden=0 AND parent_uid IN (' . $val['indeks_gabung'] . ') AND tipe = 1';
        $child = $this->tables->query($sql);
        $isian = array();
        foreach($child['data'] as $ke=>$va){
            $child[$va['ref_uid']]['isian_evaluasi'] = $va['isian_evaluasi'];
            $child[$va['ref_uid']]['isian'] = explode('|',$va['isian']);
            $child[$va['ref_uid']]['bukti'] = explode('|',$va['bukti']);
        }

        $sql2 = 'SELECT *
                FROM indeks_respon_evaluasi
                WHERE deleted=0 AND hidden=0 AND parent_uid IN (' . $val['indeks_gabung'] . ') AND tipe = 1';
        $child2 = $this->tables->query($sql2);
        $isian2 = array();
        foreach($child2['data'] as $k=>$v){
            $child2[$v['ref_uid']]['nilai'] = ROUND($v['nilai'],2);
            $child2[$v['ref_uid']]['id'] = $v['id'];
        }
        $data1['data'][$key]['isian_evaluasi'] = $child2;
        $data1['data'][$key]['isian'] = $child;
    }
    // END LANGIT BITU

    if ($this->params('debug')==1) {
      $this->debug->show($data1);
    }

      // PANTAI BERSIH
      foreach($data2['data'] as $key=>$val){
        $sql = 'SELECT *
                FROM indeks_respon
                WHERE deleted=0 AND hidden=0 AND parent_uid IN (' . $val['indeks_gabung'] . ') AND tipe = 2';
        $child = $this->tables->query($sql);
        $isian = array();
        foreach($child['data'] as $ke=>$va){
            $child[$va['ref_uid']]['isian_evaluasi'] = $va['isian_evaluasi'];
            $child[$va['ref_uid']]['isian'] = explode('|',$va['isian']);
            $child[$va['ref_uid']]['bukti'] = explode('|',$va['bukti']);
        }

        $sql2 = 'SELECT *
                FROM indeks_respon_evaluasi
                WHERE deleted=0 AND hidden=0 AND parent_uid IN (' . $val['indeks_gabung'] . ') AND tipe = 2';
        $child2 = $this->tables->query($sql2);
        $isian2 = array();
        foreach($child2['data'] as $k=>$v){
            $child2[$v['ref_uid']]['nilai'] = ROUND($v['nilai'],2);
            $child2[$v['ref_uid']]['id'] = $v['id'];
        }

        $data2['data'][$key]['isian_evaluasi'] = $child2;
        $data2['data'][$key]['isian'] = $child;
      }
      // END PANTAI BERSIH

      // PROGRAM KALI BERSIH
      foreach($data3['data'] as $key=>$val){
        $sql = 'SELECT *
                FROM indeks_respon
                WHERE deleted=0 AND hidden=0 AND parent_uid IN (' . $val['indeks_gabung'] . ') AND tipe = 3';
        $child = $this->tables->query($sql);
        $isian = array();
        foreach($child['data'] as $ke=>$va){
            $child[$va['ref_uid']]['isian_evaluasi'] = $va['isian_evaluasi'];
            $child[$va['ref_uid']]['isian'] = explode('|',$va['isian']);
            $child[$va['ref_uid']]['bukti'] = explode('|',$va['bukti']);
        }

        $sql2 = 'SELECT *
                FROM indeks_respon_evaluasi
                WHERE deleted=0 AND hidden=0 AND parent_uid IN (' . $val['indeks_gabung'] . ') AND tipe = 3';
        $child2 = $this->tables->query($sql2);
        $isian2 = array();
        foreach($child2['data'] as $k=>$v){
            $child2[$v['ref_uid']]['nilai'] = ROUND($v['nilai'],2);
            $child2[$v['ref_uid']]['id'] = $v['id'];
        }

        $data3['data'][$key]['isian_evaluasi'] = $child2;
        $data3['data'][$key]['isian'] = $child;
      }
      // END PROGRAM KALI BERSIH

      // INDONESIA HIJAU
      foreach($data4['data'] as $key=>$val){
        $sql = 'SELECT *
                FROM indeks_respon
                WHERE deleted=0 AND hidden=0 AND parent_uid IN (' . $val['indeks_gabung'] . ') AND tipe = 4';
        $child = $this->tables->query($sql);
        $isian = array();
        foreach($child['data'] as $ke=>$va){
            $child[$va['ref_uid']]['isian_evaluasi'] = $va['isian_evaluasi'];
            $child[$va['ref_uid']]['isian'] = explode('|',$va['isian']);
            $child[$va['ref_uid']]['bukti'] = explode('|',$va['bukti']);
        }

        $sql2 = 'SELECT *
                FROM indeks_respon_evaluasi
                WHERE deleted=0 AND hidden=0 AND parent_uid IN (' . $val['indeks_gabung'] . ') AND tipe = 4';
        $child2 = $this->tables->query($sql2);
        $isian2 = array();
        foreach($child2['data'] as $k=>$v){
            $child2[$v['ref_uid']]['nilai'] = ROUND($v['nilai'],2);
            $child2[$v['ref_uid']]['id'] = $v['id'];
        }

        $data4['data'][$key]['isian_evaluasi'] = $child2;
        $data4['data'][$key]['isian'] = $child;
      }
      // END INDONESIA HIJAU

      // GAMBUT LESTARI
      foreach($data5['data'] as $key=>$val){
        $sql = 'SELECT *
                FROM indeks_respon
                WHERE deleted=0 AND hidden=0 AND parent_uid IN (' . $val['indeks_gabung'] . ') AND tipe = 5';
        $child = $this->tables->query($sql);
        $isian = array();
        foreach($child['data'] as $ke=>$va){
            $child[$va['ref_uid']]['isian_evaluasi'] = $va['isian_evaluasi'];
            $child[$va['ref_uid']]['isian'] = explode('|',$va['isian']);
            $child[$va['ref_uid']]['bukti'] = explode('|',$va['bukti']);
        }

        $sql2 = 'SELECT *
                FROM indeks_respon_evaluasi
                WHERE deleted=0 AND hidden=0 AND parent_uid IN (' . $val['indeks_gabung'] . ') AND tipe = 5';
        $child2 = $this->tables->query($sql2);
        $isian2 = array();
        foreach($child2['data'] as $k=>$v){
            $child2[$v['ref_uid']]['nilai'] = ROUND($v['nilai'],2);
            $child2[$v['ref_uid']]['id'] = $v['id'];
        }

        $data5['data'][$key]['isian_evaluasi'] = $child2;
        $data5['data'][$key]['isian'] = $child;
      }
      // END GAMBUT LESTARI

      $this -> view -> pagination($this -> view, $totalRow, $offset + 1, $limit, $urlVar);
      $this -> view -> assign("urlVar", $urlVar);
      $this -> view -> assign("totalRow", $totalRow);
      $this -> view -> assign("limit", $limit);
      $this -> view -> assign("page", $offset);
      $this -> view -> assign("view1", $data1['data']);
      $this -> view -> assign("view2", $data2['data']);
      $this -> view -> assign("view3", $data3['data']);
      $this -> view -> assign("view4", $data4['data']);
      $this -> view -> assign("view5", $data5['data']);
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
        // $this->debug->show($data['data']);
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
}
