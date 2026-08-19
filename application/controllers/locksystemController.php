<?php

/**
 * created at : 15/07/2021
 * created by : dasendria team
 * desc : controller for lock system pelaporan, verifikasi & perhitungan
 */
class locksystemController extends Front
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
    $this->view->assign("thisYear", date('Y'));
    $this->view->assign("assets",ASSETS);


    $this->view->assign("primaryKey", "uid_lock_system");
    $this->viewName 	= "rf_lock_system";
    $this->primaryKey	= "uid_lock_system";
    $this->where		= "deleted = 0";
  }
  public function index(){
    $post = $this->post();
    if(isset($post['submit'])){
      if(strtotime($post['form']['tanggal_mulai']) <= strtotime($post['form']['tanggal_selesai'])){
        $post['form']['menu'] = implode(",",$post['form']['menu']);
        $post['form']['submenu'] = implode(",",$post['form']['submenu']);
        $post['form']['kabkota'] = implode(",",$post['form']['kabkota']);
        $post['form']['provinsi'] = implode(",",$post['form']['provinsi']);
        $post['form']['p3e'] = implode(",",$post['form']['p3e']);
        $post['form']['direktorat'] = implode(",",$post['form']['direktorat']);
        $post['form']['kabkota_irlh'] = implode(",",$post['form']['kabkota_irlh']);
        $post['form']['provinsi_irlh'] = implode(",",$post['form']['provinsi_irlh']);
        $post['form']['p3e_irlh'] = implode(",",$post['form']['p3e_irlh']);
        $post['form']['direktorat_irlh'] = implode(",",$post['form']['direktorat_irlh']);
        $post['form']['menu_tahunan'] = implode(",",$post['form']['menu_tahunan']);
        $post['form']['tahun'] = implode(",",$post['form']['tahun']);
        $post['form']['aktif'] = ($post['form']['aktif'] == 'on' ? 1 :0);
        $post['form']['aktif_tahunan'] = ($post['form']['aktif_tahunan'] == 'on' ? 1 :0);
        $this->tables->set($this->viewName, $this->primaryKey);
        // $this->debug->show($post);
        if($this->tables->post($post)){
          $message = "Berhasil menyimpan data !";
        }else{
          $message = "Gagal menimpan data, tanggal tutup harus lebih kecil dari tanggal buka !";
        }
      }else{
        $message = "Gagal menimpan data !";
      }
    }
    $this->getData();
    $this->rfData();
    $this->view->assign("masterActive","active");
    $this->view->assign("show",$show);
    $this->view->assign("message",$message);
    $this->view->assign("icons",'<i class="la la-lock"></i>');
    $this->view->assign("title",'Lock System');
    $this->view->display("index.html");
  }
  private function getData(){
    $this->tables->set($this->viewName, $this->primaryKey);
    $data = $this->tables->fetch("deleted = 0");
    if($data['total']){
      $data['data'][0]['menu'] = explode(",",$data['data'][0]['menu']);
      $data['data'][0]['submenu'] = explode(",",$data['data'][0]['submenu']);
      $data['data'][0]['kabkota'] = explode(",",$data['data'][0]['kabkota']);
      $data['data'][0]['provinsi'] = explode(",",$data['data'][0]['provinsi']);
      $data['data'][0]['p3e'] = explode(",",$data['data'][0]['p3e']);
      $data['data'][0]['direktorat'] = explode(",",$data['data'][0]['direktorat']);
      $data['data'][0]['kabkota_irlh'] = explode(",",$data['data'][0]['kabkota_irlh']);
      $data['data'][0]['provinsi_irlh'] = explode(",",$data['data'][0]['provinsi_irlh']);
      $data['data'][0]['p3e_irlh'] = explode(",",$data['data'][0]['p3e_irlh']);
      $data['data'][0]['direktorat_irlh'] = explode(",",$data['data'][0]['direktorat_irlh']);
      $data['data'][0]['menu_tahunan'] = explode(",",$data['data'][0]['menu_tahunan']);
      $data['data'][0]['tahun'] = explode(",",$data['data'][0]['tahun']);
    }
    // $this->debug->show($data['total']);
    $this->view->assign("form", $data['data'][0]);
  }

  private function rfData()
  {//function referensi data index
      $this -> tables -> set("users", "uid_users");
      $rf = $this -> tables -> fetch('deleted = 0 AND role_user = 3');
      $this -> view -> assign("kabkota", $rf['data']);

      $this -> tables -> set("users", "uid_users");
      $rf = $this -> tables -> fetch('deleted = 0 AND role_user = 2');
      $this -> view -> assign("provinsi", $rf['data']);

      $this -> tables -> set("users", "uid_users");
      $rf = $this -> tables -> fetch('deleted = 0 AND role_user = 4');
      $this -> view -> assign("p3e", $rf['data']);

      $this -> tables -> set("users", "uid_users");
      $rf = $this -> tables -> fetch('deleted = 0 AND role_user = 1 AND komponen IS NOT NULL');
      $this -> view -> assign("direktorat", $rf['data']);
  }
}



?>
