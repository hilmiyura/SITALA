<?php

    /**
     * created at 	: 28/11/2022
     * created by 	: dasendria team
     * desc		  	: controller daerah kabkota dan provinsi
     *
     */
  class daerahController extends Front
  {
      public function init()
      {
          ($this->session->get('memberIKLH') ?: $this->redirect("login"));

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
          $this->view->assign("functions", $this->functions);

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
          $this->where		= "deleted = 0";
          // $this->debug->show($this->me);
      }
      //INDEX FUNCTION IS A DEFAULT ACTION
      public function index()
      {

        if ($this->params("x") == "kabkota") {
          $data = $this->tables->query("SELECT nama_kabkot AS nama, logo FROM rf_kabkota WHERE deleted = 0 ORDER BY kd_kota,kd_provinsi ASC");
          $this->view->assign("folder","logo-kabkota");
        }elseif ($this->params("x") == "provinsi") {
          $data = $this->tables->query("SELECT nama_propinsi AS nama, logo FROM rf_provinsi ORDER BY kd_propinsi ASC");
          $this->view->assign("folder","logo-provinsi");
        }

        $this->view->assign("view",$data['data']);
        $this->view->assign("masterActive", "active");
        $this->view->assign("show", $show);
        $this->view->assign("message", $message);
        $this->view->assign("icons", '<i class="la la-user"></i>');
        $this->view->assign("title", 'Daftar Pengguna');
        $this->view->display("index.html");
      }
    }
?>
