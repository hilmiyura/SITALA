<?php
/**
 * created at 	: 29/09/2020
 * created by 	: dasendria team
 * desc		  	: controller index IKLHK
 *
 */
class rekapBobotController extends Front
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
      // $this->rflangitbiru();
      // $this->rfpantaibersih();
      // $this->rfpkb();
      // $this->rfindohijau();
      // $this->rfgambut();
      $this->dataTable();
      $this->view->assign("indeksResponActive", "active");
      $this->view->assign("message", $message);
      $this->view->assign("view", $view);
      $this->view->assign("title", 'REKAP BOBOT INDEKS RESPON');
      $this->view->assign("icons", '<i class="ft-bar-chart"></i>');
      $this->view->display("index.html");
    }

    private function dataTable()
    {
      $urlVar = BASEURL . $this -> url . '/';
      $w = ' a.deleted=0 AND a.hidden=0 ';
      if ($this -> me['role_user'] == 3 || $this->me['role_user'] == 2) {
          $w .= " AND users_uid =" . $this -> me['uid_users'];
      } elseif ($this->me['role_user'] == 4 || $this -> me['role_user'] == 5) {
        $w .=" AND c.kd_regional =".$this->me['uid_regional'];
      }
      // $o = $this -> primaryKey . " DESC";
      $post = $this -> post();
      if ($this -> params('search')) {
          $post['search'] = true;
          $post['form'] = json_decode(urldecode($this -> params('search')), 1);
      }
      if ($post['form']['tahun']) {
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
                     b.name, b.role_user, b.uid_provinsi, b.uid_kabkota, c.gambut, d.gambut as gambut_kabkota, c.kd_regional, c.nama_propinsi, d.nama_kabkot
              FROM ir_parent a
              LEFT JOIN users b ON a.users_uid = b.uid_users
              LEFT JOIN rf_provinsi c ON b.uid_provinsi = c.kd_propinsi
              LEFT JOIN rf_kabkota d ON b.uid_kabkota = d.kd_kota
              LEFT JOIN rf_regional e ON c.kd_regional = e.kd_regional
              WHERE '. $w .' GROUP BY a.users_uid ORDER BY a.tahun DESC';
      $data1 = $data2 = $data3 = $data4 = $data5 = null;
      $dataParent = $this->tables->query($sql);
      $this->tables->set('bobot_ir', 'bobot_ir_uid');
      $data = $this->tables->fetch('deleted = 0');
      foreach ($data['data'] as $key => $value) {
        $bobot[$value['kategori']] =$value;
      }
      // $this->debug->show($dataParent);
      foreach ($dataParent['data'] as $key => $val) {
        if($val['uid_provinsi'] == 0 && $val['uid_kabkota'] == 0){
          // unset($dataParent['data'][$key]);
        }else{
          if($val['nama_kabkot'] && $val['gambut'] != 1){
            $nb[$key] = $bobot[4];
          }elseif($val['nama_kabkot'] && $val['gambut'] == 1){
            $nb[$key] = $bobot[3];
          }elseif(!$val['nama_kabkot'] && $val['gambut'] != 1){
            $nb[$key] = $bobot[2];
          }elseif(!$val['nama_kabkot'] && $val['gambut'] == 1){
            $nb[$key] = $bobot[1];
          }
          // lANGIT BIRU
          $data1['data'][$key] = $val;
          $data1['data']['total'] = $dataParent['total'];
          $data1['data'][$key]['bobot'] = $nb[$key];


          $sql = 'SELECT nilai
          FROM indeks_respon_evaluasi
          WHERE deleted=0 AND hidden=0 AND parent_uid IN (' . $val['indeks_gabung'] . ') AND tipe = 1 GROUP BY ref_uid';
          $child = $this->tables->query($sql);
          $nilai[$key] = array_column($child['data'], 'nilai');
          $data1['data'][$key]['lb_all'] = array_sum($nilai[$key]);
          $data1['data'][$key]['lb_final'] = $nilai_final[$key][0] = round($data1['data'][$key]['lb_all'] * $nb[$key]['lb'] / 100, 2);
          // $data1['data'][$key]['isian_evaluasi'] = $nilai[$key];
          // $data1['data'][$key]['lb_count'] = count($nilai[$key]) * 100;
          // $data1['data'][$key]['lb_nilai'] = round($data1['data'][$key]['lb_all'] / $data1['data'][$key]['lb_count'] * 100, 2);
          //END

          //PANTAI BERSIH
          $sql = 'SELECT nilai
          FROM indeks_respon_evaluasi
          WHERE deleted=0 AND hidden=0 AND parent_uid IN (' . $val['indeks_gabung'] . ') AND tipe = 2 GROUP BY ref_uid';
          $child = $this->tables->query($sql);
          $nilai[$key] = array_column($child['data'], 'nilai');
          $data1['data'][$key]['pb_all'] = array_sum($nilai[$key]);
          $data1['data'][$key]['pb_final'] = $nilai_final[$key][1] = round($data1['data'][$key]['pb_all'] * $nb[$key]['pl'] / 100, 2);
          //END

          // // PROGRAM KALI BERSIH
          $sql = 'SELECT nilai
          FROM indeks_respon_evaluasi
          WHERE deleted=0 AND hidden=0 AND parent_uid IN (' . $val['indeks_gabung'] . ') AND tipe = 3 GROUP BY ref_uid';
          $child = $this->tables->query($sql);
          $nilai[$key] = array_column($child['data'], 'nilai');
          $data1['data'][$key]['sql'] =$sql;
          $data1['data'][$key]['kb_all'] = array_sum($nilai[$key]);
          $data1['data'][$key]['kb_final'] = $nilai_final[$key][2] = round($data1['data'][$key]['kb_all'] * $nb[$key]['kb'] / 100, 2);
          //END

          // // // INDONESIA HIJAU
          $sql = 'SELECT nilai
          FROM indeks_respon_evaluasi
          WHERE deleted=0 AND hidden=0 AND parent_uid IN (' . $val['indeks_gabung'] . ') AND tipe = 4 GROUP BY ref_uid';
          $child = $this->tables->query($sql);
          $nilai[$key] = array_column($child['data'], 'nilai');
          $data1['data'][$key]['ih_all'] = array_sum($nilai[$key]);
          $data1['data'][$key]['ih_final'] = $nilai_final[$key][3] = round($data1['data'][$key]['ih_all'] * $nb[$key]['ih'] / 100, 2);
          //END


          // GAMBUT LESTARI
          $sql = 'SELECT nilai
          FROM indeks_respon_evaluasi
          WHERE deleted=0 AND hidden=0 AND parent_uid IN (' . $val['indeks_gabung'] . ') AND tipe = 5 GROUP BY ref_uid';
          $child = $this->tables->query($sql);
          $nilai[$key] = array_column($child['data'], 'nilai');
          $data1['data'][$key]['gl_all'] = array_sum($nilai[$key]);
          $data1['data'][$key]['gl_final'] = $nilai_final[$key][4] = round($data1['data'][$key]['gl_all'] * $nb[$key]['gl'] / 100, 2);
          //END

          $data1['data'][$key]['nilai_final'] = array_sum($nilai_final[$key]);
        }

        $data1['data'] = array_values($data1['data']);
      }
      // $this->debug->show($data1);

      usort($data1['data'], function($a, $b) {
          if ($a['nilai_final'] < $b['nilai_final']) {
              return 1;
          } elseif ($a['nilai_final'] < $b['nilai_final']) {
              return -1;
          }
          return 0;
      });


      $this -> view -> pagination($this -> view, $totalRow, $offset + 1, $limit, $urlVar);
      $this -> view -> assign("urlVar", $urlVar);
      $this -> view -> assign("totalRow", $totalRow);
      $this -> view -> assign("limit", $limit);
      $this -> view -> assign("page", $offset);
      $this -> view -> assign("view1", $data1['data']);
    }
    private function rfData()
    {
        if ($this -> me['role_user'] == 2) {
            $wProvinsi = "kd_propinsi=" . $this -> me['uid_provinsi'];
            $wLokasi = " AND uid_provinsi=" . $this -> me['uid_provinsi'];
            $this -> tables -> set("rf_kabkota", "kd_kota");
            $rf = $this -> tables -> fetch("deleted=0 AND kd_provinsi=" . $this -> me['uid_provinsi']);
            $this -> view -> assign("kabkota", $rf['data']);
        } elseif ($this -> me['role_user'] == 3) {
            $wProvinsi = "kd_propinsi=" . $this -> me['uid_provinsi'];
            $wLokasi = " AND uid_kabkota=" . $this -> me['uid_kabkota'];
        } elseif ($this -> me['role_user'] == 4 || $this -> me['role_user'] == 5) {
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
}
