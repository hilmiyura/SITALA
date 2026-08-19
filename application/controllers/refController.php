<?php

    /**
     * created at 	: 29/09/2020
     * created by 	: dasendria team
     * desc		  	: controller Users IKLHK
     *
     */
  class refController extends Front
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
          if ($this->me['role_user'] != 0 && $this->me['role_user'] != 1) {
              $this->redirect('users/profile');
          }

          $post = $this->post();
          if (isset($post['submit'])) {
              if ($post['form']['password']) {
                  $post['form']['password'] = base64_encode($this->users->EncDec('encode', $post['form']['password']));
              } else {
                  unset($post['form']['password']);
              }
              if ($post['form']['role_user'] == 2) {
                  $post['form']['uid_kabkota'] = 0;
              }
              $this->tables->set("users", "uid_users");
              if ($this->tables->post($post)) {
                  $message = "Berhasil menyimpan data !";
              } else {
                  $message = "Gagal menimpan data !";
              }
          }

          $this->getData();
          $this->rfData();
          $this->view->assign("masterActive", "active");
          $this->view->assign("show", $show);
          $this->view->assign("message", $message);
          $this->view->assign("icons", '<i class="la la-user"></i>');
          $this->view->assign("title", 'Daftar Pengguna');
          $this->view->display("index.html");
      }

      public function pengumuman()
      {
          if ($this->me['role_user'] != 0 && $this->me['role_user'] != 1) {
              $this->redirect('dashboard');
          }
          $tablesName = 'pengumuman';
          $viewsName  = 'pengumuman';
          $primaryKey = 'uid_pengumuman';

          $post = $this->post();
          if (isset($post['submit'])) {
              if (!$post['form'][$primaryKey]) {
                  $post['form']['tanggal'] = $this->now;
              }
              $files = $_FILES;
              if ($files) {
                  $upld = $this->upload->uploadFile($files['lampiran'], 'pengumuman', 'document');
                  if ($upld) {
                      $post['form']['lampiran'] = $upld;
                  }
              }
              $this->tables->set("pengumuman", "uid_pengumuman");
              if ($this->tables->post($post)) {
                  $message = "Berhasil menyimpan data !";
              } else {
                  $message = "Gagal menyimpan data !";
              }
          }

          $this->getData($tablesName, $viewsName, $primaryKey);
          $this->rfData();
          $this->view->assign("primaryKey", $primaryKey);
          $this->view->assign("masterActive", "active");
          $this->view->assign("show", $show);
          $this->view->assign("message", $message);
          $this->view->assign("icons", '<i class="la la-bell"></i>');
          $this->view->assign("title", 'Daftar Pengumuman');
          $this->view->display("index.html");
      }

      public function faq()
      {
          if ($this->me['role_user'] != 0 && $this->me['role_user'] != 1) {
              $this->redirect('dashboard');
          }
          $tablesName = 'faq';
          $viewsName  = 'v_faq';
          $primaryKey = 'uid_faq';

          $post = $this->post();
          if (isset($post['submit'])) {
              if (!$post['form'][$primaryKey]) {
                $post['form']['tanggal'] = $this->now;
                $post['form']['isi'] = str_replace("=","",strip_tags($post['form']['isi']));
                $post['form']['jawaban'] = str_replace("=","",strip_tags($post['form']['jawaban']));
              }
              $files = $_FILES;
              if ($files) {
                  $upld = $this->upload->uploadFile($files['lampiran'], 'faq', 'document');
                  if ($upld) {
                      $post['form']['lampiran'] = $upld;
                  }
              }
              $this->tables->set("faq", "uid_faq");
              if ($this->tables->post($post)) {
                  $message = "Berhasil menyimpan data !";
              } else {
                  $message = "Gagal menyimpan data !";
              }
          }

          if (isset($post['topik'])) {
            $post['submit'] = true;
              $this->tables->set("rf_topik", "uid_topik");
              if ($this->tables->post($post)) {
                  $message = "Berhasil menyimpan data !";
              } else {
                  $message = "Gagal menyimpan data !";
              }
          }

          $this->getData($tablesName, $viewsName, $primaryKey);
          $this->rfData();
          $this->view->assign("primaryKey", $primaryKey);
          $this->view->assign("masterActive", "active");
          $this->view->assign("show", $show);
          $this->view->assign("message", $message);
          $this->view->assign("icons", '<i class="la la-comments"></i>');
          $this->view->assign("title", 'Daftar FAQ');
          $this->view->display("index.html");
      }

      private function getData($tablesName, $viewsName, $primaryKey)
      {
          $this->tables->set($viewsName, $key);
          $properties	= $this->_getProperties($viewsName);
          $urlVar  	= BASEURL . $this->url . '/';
          $w 			= $this->where;
          $o 			= $primaryKey . " ASC";
          $post 		= $this->post();
          if ($this->params('search')) {
              $post['search'] = true;
              $post['form'] 	= json_decode(urldecode($this->params('search')), 1);
          }
          if (isset($post['search'])) {
            $post['form']['keyword'] = str_replace("=","",strip_tags($post['form']['keyword']));
              if ($post['form']['keyword']) {
                  if ($properties['total']) {
                      $w .= " AND ";
                      $w .= "(";
                      for ($i=5;$i<$properties['total'];$i++) {
                          $w .= $properties['data'][$i] . " LIKE '%".$post['form']['keyword']."%' OR ";
                      }
                      $w .= $properties['data'][$properties['total']-1] . " LIKE '%".$post['form']['keyword']."%' ";
                      $w .= ")";
                  }
              }
              $urlVar .= 'search/' . urlencode(json_encode($post['form'])) . '/';
              $this->view->assign("search", $post['form']);
          }
          //PAGING
          $offset   	= (isset($_REQUEST['page']) && $_REQUEST['page'] > 1 ? $_REQUEST['page'] - 1 : 0);
          $limit	  	= LIMIT;
          $data	    	= $this->tables->query('SELECT * FROM ' . $viewsName . ' WHERE '. $w . ' ORDER BY ' . $o . ' LIMIT ' . $offset . ',' . $limit);
          $All	  		= $this->db->query('SELECT count('.$primaryKey.') as x FROM '.$viewsName.' WHERE '. $w);
          $totalRow 	= (isset($All->fields['x']) ? $All->fields['x'] : 0);
          // $this->debug->show($data);
          $this->view->pagination($this->view, $totalRow, $offset+1, $limit, $urlVar);
          $this->view->assign("urlVar", $urlVar);
          $this->view->assign("totalRow", $totalRow);
          $this->view->assign("limit", $limit);
          $this->view->assign("page", $offset);
          $this->view->assign("view", $data['data']);
      }

      public function editData()
      {
          header("Content-Type: application/json; charset=UTF-8");
          if ($this->params("x")) {
              $this->tables->set($this->params("tbl"), $this->params("pk"));
              $dataEdit = $this->tables->fetch("deleted = 0 AND ".$this->params("pk")."=".$this->params("x"));
              if ($dataEdit['data'][0]['lampiran']) {
                  $dataEdit['data'][0]['lampiran'] = BASEURL.'uploads/'.$this->params("tbl").'/'.$dataEdit['data'][0]['lampiran'];
              }
              echo json_encode($dataEdit['data'][0]);
          }
      }

      public function deletedData()
      {
          $post = $this->post();
          if (isset($post['x'])) {
              $this->tables->set($this->params("tbl"), $this->params("pk"));
              if ($this->tables->softDelete($post['x'])) {
                  echo json_encode(array('statusCode' => 200, 'message' => $this->message->delete('success')));
              } else {
                  echo json_encode(array('statusCode' => 400, 'message' => $this->message->delete('failed')));
              }
          } else {
              echo json_encode(array('statusCode' => 403, 'message' => $this->message->access()));
          }
      }

      private function rfData()
      {
          $this->tables->set("rf_topik", "uid_topik");
          $rf = $this->tables->fetch('deleted=0');
          $this->view->assign("topik", $rf['data']);

          $this->tables->set("rf_regional", "kd_regional");
          $rf = $this->tables->fetch();
          $this->view->assign("regional", $rf['data']);
          // $this->debug->show($rf);
      }

      public function profile()
      {
          $post = $this->post();
          if (isset($post['submit'])) {
              if ($post['form']['password']) {
                  $post['form']['uid_users'] = $this->me['uid_users'];
                  $post['form']['password'] = base64_encode($this->users->EncDec('encode', $post['form']['password']));
                  $this->tables->set("users", "uid_users");
                  if ($this->tables->post($post)) {
                      $message = "Berhasil menyimpan data !";
                  } else {
                      $message = "Gagal menimpan data !";
                  }
              } else {
                  $message = "Gagal menimpan data !";
              }
          }
          $dataUser = $this->tables->query("SELECT * FROM v_users WHERE uid_users=".$this->me['uid_users']);
          if ($dataUser['data'][0]['password']) {
              $dataUser['data'][0]['password'] = $this->users->EncDec('decode', base64_decode($dataUser['data'][0]['password'], true));
          }
          $this->view->assign("form", $dataUser['data'][0]);

          // $this->debug->show($this->me);


          $this->view->assign("location", $this->me['city'].",".$this->me['region'].",".$this->me['country']);
          $this->view->assign("message", $message);
          $this->view->assign("masterActive", "active");
          $this->view->assign("icons", '<i class="la la-user"></i>');
          $this->view->assign("title", 'Profile');
          $this->view->display("index.html");
      }

      private function _getProperties($model)
      {
          $sql = "SHOW COLUMNS FROM ".$model;
          $result = $this->db->fetch($sql);
          //$this->debug->show($result);
          if ($result['total']) {
              $data = array();
              foreach ($result['data'] as $key=>$val) {
                  $data[$key] = $val['Field'];
              }
              $result['data'] = $data;
              return $result;
          } else {
              die('Coloums of table '. $model .' not found');
          }
      }
  }
