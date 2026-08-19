<?php

/**
 * created at : 15/07/2021
 * created by : dasendria team
 * desc : controller for lock system pelaporan, verifikasi & perhitungan
 */
class monitoringController extends Front
{
  public function init(){
    ($this->session->get('memberIKLH')?:$this->redirect("login"));

    //SET CUSTOM VIEWS FOLDER
    $this->view->setFolder('be');

    //LOAD MODELS
    $this->loadModel("tables");
    $this->loadModel("ref");
    $this->loadModel("users");

    //GLOBAL VAR
    $this->me 			= $this->session->get('memberIKLH');
    $this->ctrl 			= $this->uri->getController();
    $this->act 			= $this->uri->getAction();
    $this->url			= $this->ctrl . '/' . $this->act;

    //load function
    require_once "functions.php";
    $this->functions = new functions();
    $this->view->assign("functions",$this->functions);

    //ASSIGN VAR
    $this->view->assign("now",$this->now = date('Y-m-d'));
    $this->view->assign("me",$this->me);
    $this->view->assign("baseUrl",BASEURL);
    $this->view->assign("ctrl", $this->ctrl);
    $this->view->assign("act", $this->act);
    $this->view->assign("format",$this->format);
    $this->view->assign("time",time());
    $this->view->assign("thisYear", $this->year = date('Y'));
    $this->view->assign("assets",ASSETS);

    $this->where		= "deleted = 0";
  }
  public function index(){
    // $SQLIKA = 'SELECT kd_kota, nama_kabkot, kd_provinsi, (SELECT COUNT(uid_pelaporan_ika) FROM v_pelaporan_ika WHERE uid_kabkota=kd_kota AND YEAR(tanggal)='.$this->year.') AS total FROM rf_kabkota WHERE deleted=0';
    $SQLIKA = '
    SELECT kd_kota, nama_kabkot, kd_provinsi,
      (
          SELECT COUNT(a.uid_pelaporan_ika)
          FROM pelaporan_ika a
          JOIN lokasi_pemantauan b ON a.uid_lokasi_pemantauan = b.uid_lokasi_pemantauan
          WHERE b.uid_kabkota=kd_kota AND YEAR(a.tanggal)='.$this->year.' AND a.deleted = 0
      ) AS total
      FROM rf_kabkota WHERE deleted=0
    ';
      //SQL IKA TOTAL PELAPORAN BY KABKOTA
      /*

      SELECT am.kd_kota, am.nama_kabkot, am.kd_provinsi, bm.nama_propinsi, bm.kd_regional, cm.ur_regional,
          (
              SELECT COUNT(a.uid_pelaporan_ika)
              FROM pelaporan_ika a
              LEFT JOIN lokasi_pemantauan b ON a.uid_lokasi_pemantauan = b.uid_lokasi_pemantauan
              WHERE b.uid_kabkota=kd_kota AND YEAR(a.tanggal)=2021 AND a.deleted = 0
          ) AS total_pelaporan,
          (
              SELECT COUNT(a.uid_pelaporan_ika)
              FROM pelaporan_ika a
              LEFT JOIN lokasi_pemantauan b ON a.uid_lokasi_pemantauan = b.uid_lokasi_pemantauan
              WHERE b.uid_kabkota=kd_kota AND YEAR(a.tanggal)=2021 AND a.deleted = 0 AND shu IS NOT NULL
          ) AS total_pelaporan_upload_shu,
          (
              SELECT COUNT(a.uid_pelaporan_ika)
              FROM pelaporan_ika a
              LEFT JOIN lokasi_pemantauan b ON a.uid_lokasi_pemantauan = b.uid_lokasi_pemantauan
              WHERE b.uid_kabkota=kd_kota AND YEAR(a.tanggal)=2021 AND a.deleted = 0 AND (a.v_provinsi = 1 OR a.v_regional = 1 OR a.v_pusat = 1)
          ) AS total_pelaporan_verifikasi
      FROM rf_kabkota am
      LEFT JOIN rf_provinsi bm ON bm.kd_propinsi = am.kd_provinsi
      LEFT JOIN rf_regional cm ON cm.kd_regional = bm.kd_regional
      WHERE am.deleted=0

       */

     //SQL IKU TOTAL PELAPORAN BY KABKOTA
     /*

     SELECT am.kd_kota, am.nama_kabkot, am.kd_provinsi, bm.nama_propinsi, bm.kd_regional, cm.ur_regional,
         (
             SELECT COUNT(a.uid_pelaporan_iku)
             FROM pelaporan_iku a
             LEFT JOIN lokasi_pemantauan b ON a.uid_lokasi_pemantauan = b.uid_lokasi_pemantauan
             WHERE b.uid_kabkota=kd_kota AND YEAR(a.tanggal)=2021 AND a.deleted = 0
         ) AS total_pelaporan,
         (
             SELECT COUNT(a.uid_pelaporan_iku)
             FROM pelaporan_iku a
             LEFT JOIN lokasi_pemantauan b ON a.uid_lokasi_pemantauan = b.uid_lokasi_pemantauan
             WHERE b.uid_kabkota=kd_kota AND YEAR(a.tanggal)=2021 AND a.deleted = 0 AND shu IS NOT NULL
         ) AS total_pelaporan_upload_shu,
         (
             SELECT COUNT(a.uid_pelaporan_iku)
             FROM pelaporan_iku a
             LEFT JOIN lokasi_pemantauan b ON a.uid_lokasi_pemantauan = b.uid_lokasi_pemantauan
             WHERE b.uid_kabkota=kd_kota AND YEAR(a.tanggal)=2021 AND a.deleted = 0 AND (a.v_provinsi = 1 OR a.v_regional = 1 OR a.v_pusat = 1)
         ) AS total_pelaporan_verifikasi
     FROM rf_kabkota am
     LEFT JOIN rf_provinsi bm ON bm.kd_propinsi = am.kd_provinsi
     LEFT JOIN rf_regional cm ON cm.kd_regional = bm.kd_regional
     WHERE am.deleted=0

      */

    //SQL IKAL
    /*
      SELECT am.nama_propinsi, bm.ur_regional,
            (
                SELECT COUNT(a.uid_pelaporan_ikal)
                FROM pelaporan_ikal a
                LEFT JOIN lokasi_pemantauan b ON a.uid_lokasi_pemantauan = b.uid_lokasi_pemantauan
                WHERE b.uid_provinsi =am.kd_propinsi AND YEAR(a.tanggal)=2021 AND a.deleted = 0
            ) AS total_pelaporan,
            (
                SELECT COUNT(a.uid_pelaporan_ikal)
                FROM pelaporan_ikal a
                LEFT JOIN lokasi_pemantauan b ON a.uid_lokasi_pemantauan = b.uid_lokasi_pemantauan
                WHERE b.uid_provinsi =am.kd_propinsi AND YEAR(a.tanggal)=2021 AND a.deleted = 0 AND a.shu IS NOT NULL
            ) AS total_pelaporan_upload_shu,
            (
                SELECT COUNT(a.uid_pelaporan_ikal)
                FROM pelaporan_ikal a
                LEFT JOIN lokasi_pemantauan b ON a.uid_lokasi_pemantauan = b.uid_lokasi_pemantauan
                WHERE b.uid_provinsi =am.kd_propinsi AND YEAR(a.tanggal)=2021 AND a.deleted = 0 AND (a.v_provinsi = 1 OR a.v_regional = 1 OR a.v_pusat = 1)
            ) AS total_pelaporan_verifikasi

      FROM rf_provinsi am
      LEFT JOIN rf_regional bm ON bm.kd_regional = am.kd_regional

    */

    //SQL IKL

    /*

      SELECT am.kd_kota, am.nama_kabkot, am.kd_provinsi, bm.nama_propinsi, bm.kd_regional, cm.ur_regional,
            (
                SELECT COUNT(a.uid_pelaporan_iktl)
                FROM pelaporan_iktl a
                WHERE a.uid_kabkota=kd_kota AND YEAR(a.tanggal)=2021 AND a.deleted = 0
            ) AS total_pelaporan,
            (
                SELECT COUNT(a.uid_pelaporan_iktl)
                FROM pelaporan_iktl a
                WHERE a.uid_kabkota=kd_kota AND YEAR(a.tanggal)=2021 AND a.deleted = 0 AND lampiran IS NOT NULL
            ) AS total_pelaporan_upload_lampiran,
            (
                SELECT COUNT(a.uid_pelaporan_iktl)
                FROM pelaporan_iktl a
                WHERE a.uid_kabkota=kd_kota AND YEAR(a.tanggal)=2021 AND a.deleted = 0 AND (a.v_provinsi = 1 OR a.v_regional = 1 OR a.v_pusat = 1)
            ) AS total_pelaporan_verifikasi
        FROM rf_kabkota am
        LEFT JOIN rf_provinsi bm ON bm.kd_propinsi = am.kd_provinsi
        LEFT JOIN rf_regional cm ON cm.kd_regional = bm.kd_regional
        WHERE am.deleted=0

    */

    // $this->debug->show($SQLIKA);
    $IKA =  $this->tables->query($SQLIKA);
    $this->debug->show($IKA);


  }
  private function getData(){

  }
}



?>
