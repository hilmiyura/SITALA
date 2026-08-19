<?php
/**
 * created at 	: 29/09/2020
 * created by 	: dasendria team
 * desc		  	: controller index IKLHK
 *
 */
class dashboardController extends Front
{
    public function init()
    {

      // ini_set("display_errors",true);
        ($this -> session -> get('memberIKLH') ? : $this -> redirect("login"));
        // $this->debug->show($this -> session -> get('memberIKLH'));
        $this->redirect('dashboardData');
        //SET CUSTOM VIEWS FOLDER
        $this -> view -> setFolder('be');

        //LOAD MODELS
        $this -> loadModel("tables");
        $this -> loadModel("ref");

        // LOAD FUNCTION
        require_once "functions.php";
        $this -> functions = new functions();
        $this -> view -> assign("functions", $this -> functions);
        require_once "excelReader.php";

        //GLOBAL VAR
        $this -> me = $this -> session -> get('memberIKLH');
        $this -> ctrl = $this -> uri -> getController();
        $this -> act = $this -> uri -> getAction();
        $this -> url = $this -> ctrl . '/' . $this -> act;

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
        $this->view->assign("dashboard",2);
        // $this->_summary();
        $this->getData();
        $this -> view -> assign("dashboardActive", "active");
        $this -> view -> assign("show", $show);
        $this -> view -> assign("title", 'Dashboard');
        $this -> view -> assign("icons", '<i class="ft-home"></i>');
        $this -> view -> display("index.html");
    }

    public function exportData()
    {
        $tahun = $this->params('y');
        $this->_summaryExport($tahun);
    }

    private function _summaryExport($tahun)
    {
        $w = 'deleted=0 AND hidden=0';
        $Y = $tahun;
        $w .= " AND YEAR(tanggal) ='" . $Y . "'";

        if ($this->me['role_user']==2) {
            $w .= ' AND uid_provinsi='.$this->me['uid_provinsi'];
        } elseif ($this->me['role_user']==3) {
            $w .= ' AND uid_kabkota='.$this->me['uid_kabkota'];
        } elseif ($this->me['role_user']==4 || $this -> me['role_user'] == 5) {
            $w .= ' AND kd_regional='.$this->me['uid_regional'];
        }

        if ($this->me['role_user'] != 3) {
            $this -> view -> assign("ikal", $this->_sumaryIKAL($w));
            if($Y < 2023 && $this->me['role_user']== 0){
              $wKd = ' AND kd_propinsi < 34';
            }
            $wRekap = '';
            if ($this->me['role_user']==2) {
                $wRekap = 'kd_provinsi='.$this -> me['uid_provinsi'];
                if($Y > 2022){
                  $wKd = ' AND deleted = 0';
                }
            } elseif ($this->me['role_user']==4 || $this -> me['role_user'] == 5) {
                $wRekap = 'kd_regional='.$this -> me['uid_regional'];
                if($Y < 2023){
                  $wKd = ' AND kd_propinsi < 34';
                }
            }

            if ($this->me['role_user'] != 2) {
                $this -> tables -> set("rf_provinsi", "kd_propinsi");
                $rf = $this -> tables -> fetch($wRekap.$wKd);
                foreach ($rf['data'] as $key=>$val) {
                    $wData = 'deleted=0 AND hidden=0 AND uid_provinsi='.$val['kd_propinsi'].' AND YEAR(tanggal)='.$Y;
                    $rf['data'][$key]['nama_text'] = $val['nama_propinsi'];
                    $rf['data'][$key]['iku'] = $this->_sumaryIKU($wData);
                    $rf['data'][$key]['ika'] = $this->_sumaryIKA($wData);
                    $rf['data'][$key]['ikl'] = $this->_sumaryIKL($wData);
                    $rf['data'][$key]['ikal'] = $this->_sumaryIKAL($wData);
                }
            } else {
                $this -> tables -> set("rf_kabkota", "kd_kota");
                $rf = $this -> tables -> fetch($wRekap.$wKd);
                foreach ($rf['data'] as $key=>$val) {
                    $wData = 'deleted=0 AND hidden=0 AND uid_kabkota='.$val['kd_kota'].' AND YEAR(tanggal)='.$Y;
                    $rf['data'][$key]['nama_text'] = $val['nama_kabkot'];
                    $rf['data'][$key]['iku'] = $this->_sumaryIKU($wData);
                    $rf['data'][$key]['ika'] = $this->_sumaryIKA($wData);
                    $rf['data'][$key]['ikl'] = $this->_sumaryIKL($wData);
                    $rf['data'][$key]['ikal'] = $this->_sumaryIKAL($wData);
                }
            }
            $this -> view -> assign("propRekap", $rf['data']);
            if ($this->params("ex") == "provinsi") {
              foreach ($rf['data'] as $key => $value) {
                $wRekap = 'deleted=0 AND kd_provinsi='.$value['kd_propinsi'];
                $this -> tables -> set("rf_kabkota", "kd_kota");
                $data2 = $this -> tables -> fetch($wRekap);
                foreach ($data2['data'] as $ke=>$val) {
                    $wData = 'deleted=0 AND hidden=0 AND uid_kabkota='.$val['kd_kota'].' AND YEAR(tanggal)='.$Y;
                    $data2['data'][$ke]['nama_text'] = $val['nama_kabkot'];
                    $data2['data'][$ke]['iku'] = $this->_sumaryIKU($wData);
                    $data2['data'][$ke]['ika'] = $this->_sumaryIKA($wData);
                    $data2['data'][$ke]['ikl'] = $this->_sumaryIKL($wData);
                }
                $rf['data']['detail'][$key] = $data2['data'];
              }
            }
            if ($this->params("ex") == "provinsi") {
                $this->expExcel(null, $rf['data']);
            }
        }
    }

    public function expExcel($kabkota = null, $provinsi = null)
    {
        // $this->debug->show($provinsi);
        //load function
        require_once "functions.php";
        $this -> functions = new functions();
        $this -> view -> assign("functions", $this -> functions);
        if ($kabkota) {
            $this -> view -> assign("viewk", $kabkota['data']);
            header("Content-type: application/vnd-ms-excel");
            header('Content-Disposition: attachment; filename="REKAPITULASI_'.time().'.xls"');
            $html = $this->view->fetch('parts/contents/dashboard/index/excel_dashboard.html');
            echo $html;
        } elseif ($provinsi) {
            $this -> view -> assign("propRekap", $provinsi);
            header("Content-type: application/vnd-ms-excel");
            header('Content-Disposition: attachment; filename="REKAPITULASI_'.time().'.xls"');
            $html = $this->view->fetch('parts/contents/dashboard/index/excel_dashboard.html');
            echo $html;
        }
    }

    public function getContent(){
      // $this->_summary();
      $html = $this->view->fetch('parts/contents/dashboard/index/index-2.html');
      $data = array("statusCode"=>200,"data"=>$form, "html"=>$html);
      echo json_encode($data);
    }

    private function getData()
    {
        $this->tables->set('pengumuman', 'uid_pengumuman');
        $data = $this->tables->fetch('deleted = 0 AND status = 1 ORDER BY tanggal DESC LIMIT 3');
        $this -> view -> assign("pengumuman", $data['data']);
    }
    public function rekapPerKab()
    {
        if ($this->params(prop) && is_numeric($this->params(prop)) && $this->params(tahun) && is_numeric($this->params(tahun))) {
            $wRekap = 'kd_provinsi='.$this->params(prop);
            $Y = $this->params(tahun);
            if($Y > 2022){
              $wRekap .= ' AND deleted = 0';
            }
            $this -> tables -> set("rf_kabkota", "kd_kota");
            $rf = $this -> tables -> fetch($wRekap);
            foreach ($rf['data'] as $key=>$val) {
                $wData = 'deleted=0 AND hidden=0 AND uid_kabkota='.$val['kd_kota'].' AND YEAR(tanggal)='.$Y;
                $rf['data'][$key]['nama_text'] = $val['nama_kabkot'];
                $rf['data'][$key]['iku'] = $this->_sumaryIKU($wData);
                $rf['data'][$key]['ika'] = $this->_sumaryIKA($wData);
                $rf['data'][$key]['ikl'] = $this->_sumaryIKL($wData);
            }
            $html = '';
            $i = 1;
            foreach ($rf['data'] as $key=>$val) {
                $html .= '<tr>';
                $html .= '<td class="text-center">'.$i.'</td>';
                $html .= '<td class="text-uppercase"><b>'.$val['nama_text'].'</b></td>';
                $html .= '<td class="text-center"><b>'.$val['iku']['verify'].' / '.$val['iku']['total'].'</b></td>';
                $html .= '<td class="text-center"><b>'.$val['ika']['verify'].' / '.$val['ika']['total'].'</b></td>';
                $html .= '<td class="text-center"><b>'.$val['ikl']['verify'].' / '.$val['ikl']['total'].'</b></td>';
                $html .= '</tr>';
                $i++;
            }
            echo $html;
        }
    }
    private function _summary()
    {
        $w = 'deleted=0 AND hidden=0';
        $Y = ACTIVE_YEAR;
        if ($this->params(tahun) && is_numeric($this->params(tahun))) {
            $w .= " AND YEAR(tanggal) ='" . $this->params(tahun) . "'";
            $Y	= $this->params(tahun);
        } else {
            $w .= " AND YEAR(tanggal) ='" . $Y . "'";
        }
        $this -> view -> assign("year", $Y);

        if ($this->me['role_user']==2) {
            $w .= ' AND uid_provinsi='.$this->me['uid_provinsi'];
        } elseif ($this->me['role_user']==3) {
            $w .= ' AND uid_kabkota='.$this->me['uid_kabkota'];
        } elseif ($this->me['role_user']==4 || $this -> me['role_user'] == 5) {
            $w .= ' AND kd_regional='.$this->me['uid_regional'];
        }

        $this->_years();
        $this -> view -> assign("iku", $this->_sumaryIKU($w));
        $this -> view -> assign("ika", $this->_sumaryIKA($w));
        $this -> view -> assign("ikl", $this->_sumaryIKL($w));
        if($Y < 2023 && $this->me['role_user']== 0){
          $wKd = ' AND kd_propinsi < 34';
        }
        if ($this->me['role_user'] != 3) {
            $this -> view -> assign("ikal", $this->_sumaryIKAL($w));
            $wRekap = 'kd_propinsi ';
            if ($this->me['role_user']==2) {
                $wRekap = 'kd_provinsi='.$this -> me['uid_provinsi'];
                if($Y > 2022){
                  $wKd = ' AND deleted = 0';
                }
            } elseif ($this->me['role_user']==4 || $this -> me['role_user'] == 5) {
                $wRekap = 'kd_regional='.$this -> me['uid_regional'];
                if($Y < 2023){
                  $wKd = ' AND kd_propinsi < 34';
                }
            }
            // $this->debug->show($wRekap.$wKd);

            if ($this->me['role_user'] != 2) {
                $this -> tables -> set("rf_provinsi", "kd_propinsi");
                $rf = $this -> tables -> fetch($wRekap.$wKd);
                foreach ($rf['data'] as $key=>$val) {
                    $wData = 'deleted=0 AND hidden=0 AND uid_provinsi='.$val['kd_propinsi'].' AND YEAR(tanggal)='.$Y;
                    $rf['data'][$key]['nama_text'] = $val['nama_propinsi'];
                    $rf['data'][$key]['iku'] = $this->_sumaryIKU($wData);
                    $rf['data'][$key]['ika'] = $this->_sumaryIKA($wData);
                    $rf['data'][$key]['ikl'] = $this->_sumaryIKL($wData);
                    $rf['data'][$key]['ikal'] = $this->_sumaryIKAL($wData);
                }
            } else {
                $this -> tables -> set("rf_kabkota", "kd_kota");
                $rf = $this -> tables -> fetch($wRekap.$wKd);
                foreach ($rf['data'] as $key=>$val) {
                    $wData = 'deleted=0 AND hidden=0 AND uid_kabkota='.$val['kd_kota'].' AND YEAR(tanggal)='.$Y;
                    $rf['data'][$key]['nama_text'] = $val['nama_kabkot'];
                    $rf['data'][$key]['iku'] = $this->_sumaryIKU($wData);
                    $rf['data'][$key]['ika'] = $this->_sumaryIKA($wData);
                    $rf['data'][$key]['ikl'] = $this->_sumaryIKL($wData);
                    $rf['data'][$key]['ikal'] = $this->_sumaryIKAL($wData);
                }
            }
            $this -> view -> assign("propRekap", $rf['data']);
        }
    }
    private function _sumaryIKU($w)
    {
        // $iku 			= array('total'=>0, 'verify'=>0, 'update'=>'', 'persen'=>0);
        // //1. TOTAL DATA
        // $sql 			= 'SELECT COUNT(uid_pelaporan_iku) AS x FROM v_pelaporan_iku WHERE '.$w;
        // $data			= $this->db->query($sql);
        // $iku['total'] 	= $data->fields['x'];
        // //2. VERIFIED
        // $sql 			= 'SELECT COUNT(uid_pelaporan_iku) AS x FROM v_pelaporan_iku WHERE '.$w.' AND v_pusat = 1 AND IF(role_user = 2, v_regional = 1, v_pusat = 1) AND IF(role_user = 3, v_provinsi = 1, v_pusat = 1)';
        // $data			= $this->db->query($sql);
        // $iku['verify']	= $data->fields['x'];
        // //3. LAST UPDATE
        // $sql 			= 'SELECT MAX(crdate) x, MAX(chdate) AS y FROM v_pelaporan_iku WHERE '.$w;
        // $data			= $this->db->query($sql);
        // $iku['update'] 	= ($data->fields['y'] ? $data->fields['y'] : $data->fields['x']);
        // $iku['update']	= ($data->fields['x'] ? date('d-m-Y : H:i', $iku['update']) : '-');
        // //4. PERSEN
        // if ($iku['total']) {
        //     $iku['persen']	= ($iku['verify'] / $iku['total']) * 100;
        // }
        // return $iku;
        // $this->debug->show($iku);
        // $this -> view -> assign("iku", $iku);
    }
    private function _sumaryIKA($w)
    {
        // $ika 			= array('total'=>0, 'verify'=>0, 'update'=>'', 'persen'=>0);
        // //1. TOTAL DATA
        // $sql 			= 'SELECT COUNT(uid_pelaporan_ika) AS x FROM v_pelaporan_ika WHERE '.$w;
        // $data			= $this->db->query($sql);
        // $ika['total'] 	= $data->fields['x'];
        // //2. VERIFIED
        // $sql 			= 'SELECT COUNT(uid_pelaporan_ika) AS x FROM v_pelaporan_ika WHERE '.$w.' AND v_pusat = 1 AND IF(role_user = 2, v_regional = 1, v_pusat = 1) AND IF(role_user = 3, v_provinsi = 1, v_pusat = 1)';
        // $data			= $this->db->query($sql);
        // $ika['verify']	= $data->fields['x'];
        // //3. LAST UPDATE
        // $sql 			= 'SELECT MAX(crdate) x, MAX(chdate) AS y FROM v_pelaporan_ika WHERE '.$w;
        // $data			= $this->db->query($sql);
        // $ika['update'] 	= ($data->fields['y'] ? $data->fields['y'] : $data->fields['x']);
        // $ika['update']	= ($data->fields['x'] ? date('d-m-Y : H:i', $ika['update']) : '-');
        // //4. PERSEN
        // if ($ika['total']) {
        //     $ika['persen']	= ($ika['verify'] / $ika['total']) * 100;
        // }
        // return $ika;
        // $this->debug->show($ika);
        // $this -> view -> assign("ika", $ika);
    }
    private function _sumaryIKL($w)
    {
        // $ikl 			= array('total'=>0, 'verify'=>0, 'update'=>'', 'persen'=>0);
        // //1. TOTAL DATA
        // $sql 			= 'SELECT COUNT(uid_pelaporan_iktl) AS x FROM v_pelaporan_iktl WHERE '.$w;
        // $data			= $this->db->query($sql);
        // $ikl['total'] 	= $data->fields['x'];
        // //2. VERIFIED
        // $sql 			= 'SELECT COUNT(uid_pelaporan_iktl) AS x FROM v_pelaporan_iktl WHERE '.$w.' AND v_pusat = 1 AND IF(role_user = 2, v_regional = 1, v_pusat = 1) AND IF(role_user = 3, v_provinsi = 1, v_pusat = 1)';
        // $data			= $this->db->query($sql);
        // $ikl['verify']	= $data->fields['x'];
        // //3. LAST UPDATE
        // $sql 			= 'SELECT MAX(crdate) x, MAX(chdate) AS y FROM v_pelaporan_iktl WHERE '.$w;
        // $data			= $this->db->query($sql);
        // $ikl['update'] 	= ($data->fields['y'] ? $data->fields['y'] : $data->fields['x']);
        // $ikl['update']	= ($data->fields['x'] ? date('d-m-Y : H:i', $ikl['update']) : '-');
        // //4. PERSEN
        // if ($ikl['total']) {
        //     $ikl['persen']	= ($ikl['verify'] / $ikl['total']) * 100;
        // }
        // return $ikl;
        // $this->debug->show($iku);
        // $this -> view -> assign("ikl", $ikl);
    }
    private function _sumaryIKAL($w)
    {
        // $ikal 			= array('total'=>0, 'verify'=>0, 'update'=>'', 'persen'=>0);
        // //1. TOTAL DATA
        // $sql 			= 'SELECT COUNT(uid_pelaporan_ikal) AS x FROM v_pelaporan_ikal WHERE '.$w;
        // $data			= $this->db->query($sql);
        // $ikal['total'] 	= $data->fields['x'];
        // //2. VERIFIED
        // $sql 			= 'SELECT COUNT(uid_pelaporan_ikal) AS x FROM v_pelaporan_ikal WHERE '.$w.' AND v_pusat = 1 AND IF(role_user = 2, v_regional = 1, v_pusat = 1) AND IF(role_user = 3, v_provinsi = 1, v_pusat = 1)';
        // $data			= $this->db->query($sql);
        // $ikal['verify']	= $data->fields['x'];
        // //3. LAST UPDATE
        // $sql 			= 'SELECT MAX(crdate) x, MAX(chdate) AS y FROM v_pelaporan_ikal WHERE '.$w;
        // $data			= $this->db->query($sql);
        // $ikal['update'] = ($data->fields['y'] ? $data->fields['y'] : $data->fields['x']);
        // $ikal['update']	= ($data->fields['x'] ? date('d-m-Y : H:i', $ikal['update']) : '-');
        // //4. PERSEN
        // if ($ikal['total']) {
        //     $ikal['persen']	= ($ikal['verify'] / $ikal['total']) * 100;
        // }
        // return $ikal;
        // $this->debug->show($iku);
        // $this -> view -> assign("ikal", $ikal);
    }
    private function _years()
    {
        for ($i=ACTIVE_YEAR;$i>=(ACTIVE_YEAR-2);$i--) {
            $years[] = $i;
        }
        // $this->debug->show($years);
        $this -> view -> assign("years", $years);
    }
}
