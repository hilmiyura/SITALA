<?php
/**
 * created at 	: 29/09/2020
 * created by 	: dasendria team
 * desc		  	: controller index IKLHK
 *
 */
class faqController extends Front
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

        // $this -> view -> assign("primaryKey", "uid_faq");
        // $this -> tableName = "faq";
        // $this -> primaryKey = "uid_faq";
        // $this -> where = "deleted = 0";
    }

    //INDEX FUNCTION IS A DEFAULT ACTION
    public function index()
    {
        $tablename  = 'faq';
        $viewname   = 'v_faq';
        $primaryKey = 'uid_faq';
        $this->getData();
        $this->_list($tablename, $viewname, $primaryKey, 0);
        $this -> view -> assign("faqActive", "active");
        $this -> view -> assign("title", 'FAQ');
        $this -> view -> assign("icons", '<i class="ft-message-circle"></i>');
        $this -> view -> display("index.html");
    }

    private function getData()
    {
        $this->tables->set('rf_topik', 'uid_topik');
        $data = $this->tables->fetch('deleted = 0 ORDER BY uid_topik DESC');
        $this -> view -> assign("faq", $data['data']);
        // $data = $this->tables->query('SELECT a.*, b.nama_topik FROM faq a LEFT JOIN rf_topik b ON(a.topik = b.uid_topik)');
        // $this -> view -> assign("faq", $data['data']);
        // $this->debug->show($data['data']);
    }

    private function _list($tablename, $viewname, $primaryKey)
    {
        $this->tables->set($tablename, $primaryKey);
        $properties	= $this->_getProperties($tablename);
        $urlVar  = BASEURL . $this->url . '/show/data/';
        $w = $this->where;
        $w .= " AND status = 1 ";
        if ($this->params('uid')) {
            $this->view->assign("params_index", $this->params('uid'));
            $uid = $this->params('uid');
            $w .= " AND status = 1 AND topik = ".$uid;
            $this->tables->set('rf_topik', 'uid_topik');
            $ref = $this->tables->fetch('uid_topik ='.$uid);
            $this->view->assign("topik", $ref['data'][0]['nama_topik']);
        }
        // ORDER BY
        $o = $primaryKey." DESC";
        // SEARCH -------------------------------------------------------------------
        $post 		= $this->post();
        if ($this->params('search')) {
            if (!isset($post['search'])) {
                if (!isset($post['search'])) {
                  $post['form']['keyword'] = str_replace("=","",strip_tags($post['form']['keyword']));
                  $post['form']['keyword'] = urldecode($this->params('keyword'));
                  $post['search'] = 1;
                }
            }
        }
        if (isset($post['search'])) {
            $src = $this->_search($post, $properties);
            $w 		.= $src['w'];
            $urlVar .= $src['urlVar'];
            $urlVar .= 'search/' . time() . '/';
            $this->view->assign("search", $post['form']);
        }
        // PAGING --------------------------------------------------------------------
        $offset = 0;
        $limit	= 10;
        if (isset($_REQUEST['page']) && $_REQUEST['page'] > 1) {
            $offset	= $_REQUEST['page'] - 1;
        }
        $this->tables->set($tablename, $primaryKey);
        $paging		= array("offset"=>$offset,"limit"=>$limit);
        $data		= $this->tables->fetch($w, $o);
        // $this->debug->show($data);
        $All		= $this->db->query('SELECT count('.$primaryKey.') as x FROM '.$tablename.' WHERE '. $w);
        $totalRow	= 0;
        foreach ($data['data'] as $key => $val) {
        }
        // ASSIGN --------------------------------------------------------------------
        if (isset($All->fields['x'])) {
            $totalRow 	= $All->fields['x'];
        }
        $this->view->pagination($this->view, $totalRow, $offset+1, $limit, $urlVar);
        $this->view->assign("urlVar", $urlVar);
        $this->view->assign("totalRow", $totalRow);
        $this->view->assign("limit", $limit);
        $this->view->assign("page", $offset);
        $this->view->assign("view", $data['data']);
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
