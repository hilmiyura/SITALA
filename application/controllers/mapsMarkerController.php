<?php
/**
 * created at 	: 07/03/2024
 * created by 	: dasendria team
 * desc		  	: controller ticket
 *
 */
class mapsMarkerController extends Front
{
    public function init()
    {
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
      // $this->debug->show((int)base64_decode($this->params("x")));
        // $dataPelaporan = $this->tables->query("SELECT * FROM v_pelaporan_iktl WHERE deleted = 0 AND uid_pelaporan_iktl=".(int)base64_decode($this->params("x")));
        $dataPelaporan = $this->tables->query("SELECT * FROM v_pelaporan_iktl WHERE deleted = 0 AND uid_pelaporan_iktl=1606");
        if($dataPelaporan['total'] > 0){
          $dataKabkota = $this->db->fetch("SELECT * FROM rf_kabkota WHERE kd_kota=".$dataPelaporan['data'][0]['uid_kabkota'])['data'][0];
          $kabkotaDetail['latitude'] = $dataKabkota['latitude'];
          $kabkotaDetail['longitude'] = $dataKabkota['longitude'];
          $kabkotaDetail['nama_kabkota'] = $dataPelaporan['data'][0]['nama_kabkota'];
          $kabkotaDetail['nama_provinsi'] = $dataPelaporan['data'][0]['nama_provinsi'];
          $this->view->assign("kabkota", $kabkotaDetail);
        }

        $this -> view -> assign("masterActive", "active");
        $this -> view -> assign("title", 'MAPS');
        $this -> view -> assign("icons", '<i class="ft-maps"></i>');
        $this -> view -> display("index.html");
    }
}
