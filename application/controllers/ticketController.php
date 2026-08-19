<?php
/**
 * created at 	: 07/03/2024
 * created by 	: dasendria team
 * desc		  	: controller ticket
 *
 */
class ticketController extends Front
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
        // $this->debug->show($this->me);
        $reply = base64_decode($this->params("reply"));
        if(is_numeric($reply)){
          $this->postTicketReply();
          $this->view->assign("reply", 1);
          $ticketData = $this->db->fetch("SELECT * FROM v_ticket WHERE uid_ticket=".$reply." OR parent=".$reply." ORDER BY uid_ticket ASC");
          $this->view->assign("ticket_data",$ticketData['data']);
        }else{
          $this->postTicket();
          $this->_ref();
          $this->getDataTable();
        }
        $this -> view -> assign("ticketActive", "active");
        $this -> view -> assign("title", 'TICKET');
        $this -> view -> assign("icons", '<i class="ft-message-circle"></i>');
        $this -> view -> display("index.html");
    }

    private function postTicket(){
      $post = $this->post();
      if (isset($post['submit'])) {
          $files = $_FILES;
          if ($files) {
              $upld = $this->upload->uploadFile($files['lampiran'], 'ticket', 'document');
              if ($upld) {
                  $post['form']['lampiran'] = $upld;
              }
          }
          $post['form']['pesan'] = str_replace("=","",strip_tags($post['form']['pesan']));
          $post['form']['uid_topik'] = str_replace("=","",strip_tags($post['form']['uid_topik']));
          $post['form']['tentang'] = str_replace("=","",strip_tags($post['form']['tentang']));
          $post['form']['cruser'] = $this->me['uid_users'];
          $post['form']['parent'] = 0;
          $post['form']['status'] = 0;
          $this->tables->set("ticket", "uid_ticket");
          if ($this->tables->post($post)) {
              $message = "Berhasil dibuat !";
          } else {
              $message = "Gagal dibuat !";
          }
      }
    }

    private function postTicketReply(){
      $post = $this->post();
      if(isset($post['closesumbit'])){
        $post['form']['uid_ticket'] = $post['form']['uid_ticket'];
        $this->tables->set("ticket", "uid_ticket");
        $post['form']['status'] = 1;
        unset($post['form']['pesan']);
        $post['submit'] = true;
        if ($this->tables->post($post)) {
            $message = "Berhasil ditutup !";
        } else {
            $message = "Gagal ditutup !";
        }
        unset($post['submit']);
      }

      if (isset($post['submit']) && $post['form']['pesan'] != "") {
          $files = $_FILES;
          if ($files) {
              $upld = $this->upload->uploadFile($files['lampiran'], 'ticket', 'document');
              if ($upld) {
                  $post['form']['lampiran'] = $upld;
              }
          }
          $post['form']['pesan'] = str_replace("=","",strip_tags($post['form']['pesan']));

          $post['form']['cruser'] = $this->me['uid_users'];
          $post['form']['parent'] = $post['form']['uid_ticket'];
          $post['form']['status'] = 0;
          unset($post['form']['uid_ticket']);
          $this->tables->set("ticket", "uid_ticket");
          if ($this->tables->post($post)) {
              $message = "Berhasil dikirim !";
          } else {
              $message = "Gagal dikirim !";
          }
      }
    }

    private function getDataTable(){
      $viewsName = 'v_ticket';
      $key = $primaryKey = 'uid_ticket';
      $this->tables->set($viewsName, $key);
      $properties['data']	= ['tentang','nama_provinsi','nama_kabkota','nama_user'];
      $properties['total']= count($properties['data']);
      $urlVar  	= BASEURL . $this->url . '/';
      $w 			= $this->where. " and parent = 0";
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
                for ($i=0;$i<$properties['total'];$i++) {
                    $w .= $properties['data'][$i] . " LIKE '%".$post['form']['keyword']."%' OR ";
                }
                $w .= $properties['data'][$properties['total']-1] . " LIKE '%".$post['form']['keyword']."%' ";
                $w .= ")";
            }
        }
        if ($post['form']['uid_topik']) {
          $w .= " AND uid_topik=".$post['form']['uid_topik'];
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

      $listTickketId = implode(",", array_column($data['data'],'uid_ticket'));
      if($listTickketId){
        $getLastChat = $this->db->fetch("SELECT * FROM ticket WHERE parent IN({$listTickketId}) ORDER BY uid_ticket DESC LIMIT 1 ");

        foreach ($getLastChat['data'] as $key => $value) {
          $idxData = array_search($value['parent'], array_column($data['data'], 'uid_ticket'));
          if(is_numeric($idxData)){
            $data['data'][$idxData]['pesan'] = $value['pesan'];
            $data['data'][$idxData]['crdate'] = $value['crdate'];
            if($value['cruser'] != $this->me['uid_users']){
              $data['data'][$idxData]['pesan_balasan'] = $value['cruser'];
            }
          }
        }
      }
      $this->view->pagination($this->view, $totalRow, $offset+1, $limit, $urlVar);
      $this->view->assign("urlVar", $urlVar);
      $this->view->assign("totalRow", $totalRow);
      $this->view->assign("limit", $limit);
      $this->view->assign("page", $offset);
      $this->view->assign("view", $data['data']);
    }
    private function _ref(){
      $this->tables->set("rf_topik", "uid_topik");
      $rf = $this->tables->fetch('deleted=0');
      $this->view->assign("topik", $rf['data']);
    }
}
