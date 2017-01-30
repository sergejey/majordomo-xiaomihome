<?php
/**
* XiaomiHome 
* @package project
* @author Wizard <sergejey@gmail.com>
* @copyright http://majordomo.smartliving.ru/ (c)
* @version 0.1 (wizard, 17:01:07 [Jan 28, 2017])
*/
//
// https://github.com/louisZL/lumi-gateway-local-api

Define('XIAOMI_MULTICAST_PORT',9898);
Define('XIAOMI_MULTICAST_ADDRESS','224.0.0.50');


class xiaomihome extends module {
/**
* xiaomihome
*
* Module class constructor
*
* @access private
*/
function xiaomihome() {
  $this->name="xiaomihome";
  $this->title="XiaomiHome";
  $this->module_category="<#LANG_SECTION_DEVICES#>";
  $this->checkInstalled();
}
/**
* saveParams
*
* Saving module parameters
*
* @access public
*/
function saveParams($data=0) {
 $p=array();
 if (IsSet($this->id)) {
  $p["id"]=$this->id;
 }
 if (IsSet($this->view_mode)) {
  $p["view_mode"]=$this->view_mode;
 }
 if (IsSet($this->edit_mode)) {
  $p["edit_mode"]=$this->edit_mode;
 }
 if (IsSet($this->data_source)) {
  $p["data_source"]=$this->data_source;
 }
 if (IsSet($this->tab)) {
  $p["tab"]=$this->tab;
 }
 return parent::saveParams($p);
}
/**
* getParams
*
* Getting module parameters from query string
*
* @access public
*/
function getParams() {
  global $id;
  global $mode;
  global $view_mode;
  global $edit_mode;
  global $data_source;
  global $tab;
  if (isset($id)) {
   $this->id=$id;
  }
  if (isset($mode)) {
   $this->mode=$mode;
  }
  if (isset($view_mode)) {
   $this->view_mode=$view_mode;
  }
  if (isset($edit_mode)) {
   $this->edit_mode=$edit_mode;
  }
  if (isset($data_source)) {
   $this->data_source=$data_source;
  }
  if (isset($tab)) {
   $this->tab=$tab;
  }
}
/**
* Run
*
* Description
*
* @access public
*/
function run() {
 global $session;
  $out=array();
  if ($this->action=='admin') {
   $this->admin($out);
  } else {
   $this->usual($out);
  }
  if (IsSet($this->owner->action)) {
   $out['PARENT_ACTION']=$this->owner->action;
  }
  if (IsSet($this->owner->name)) {
   $out['PARENT_NAME']=$this->owner->name;
  }
  $out['VIEW_MODE']=$this->view_mode;
  $out['EDIT_MODE']=$this->edit_mode;
  $out['MODE']=$this->mode;
  $out['ACTION']=$this->action;
  $out['DATA_SOURCE']=$this->data_source;
  $out['TAB']=$this->tab;
  $this->data=$out;
  $p=new parser(DIR_TEMPLATES.$this->name."/".$this->name.".html", $this->data, $this);
  $this->result=$p->result;
}

    function processMessage($message, $ip) {
        echo date('Y-m-d H:i:s')."\n";
        echo "IP: ".$ip."\n";
        echo "MSG: ".$message."\n";

        $message_data=json_decode($message,true);
        if (isset($message_data['data'])) {
            $data_text=str_replace('\\"','"',$message_data['data']);
            $message_data['data']=json_decode($data_text,true);
        }

        if ($message_data['sid']) {
            $device=SQLSelectOne("SELECT * FROM xidevices WHERE SID='".DBSafe($message_data['sid'])."'");
            if (!$device['ID']) {
                $device=array();
                $device['SID']=$message_data['sid'];
                $device['TYPE']=$message_data['model'];
                $device['TITLE']=ucfirst($device['TYPE']).' '.date('Y-m-d');
                $device['ID']=SQLInsert('xidevices',$device_type);
            }
            $device['UPDATED']=date('Y-m-d H:i:s');
            SQLUpdate('xidevices',$device);

            if ($message_data['cmd']!='') {
                $command=$message_data['cmd'];
                if ($command=='heartbeat' && $message_data['model']=='gateway') {
                    $value=$message_data['data']['ip'];
                }
                if ($command=='report' && isset($message_data['data']['rgb']) && $message_data['model']=='gateway') {
                    $command='rgb';
                    $value=dechex($message_data['data']['rgb']);
                }
                if ($command=='report' && isset($message_data['data']['temperature']) && $message_data['model']=='sensor_ht') {
                    $command='temperature';
                    $value=round(((int)$message_data['data']['temperature'])/100,2);
                }
                if ($command=='heartbeat' && isset($message_data['data']['temperature']) && $message_data['model']=='sensor_ht') {
                    $command='temperature';
                    $value=round(((int)$message_data['data']['temperature'])/100,2);
                }
                if ($command=='report' && isset($message_data['data']['humidity']) && $message_data['model']=='sensor_ht') {
                    $command='humidity';
                    $value=round(((int)$message_data['data']['humidity'])/100,2);
                }
                if ($command=='report' && $message_data['model']=='switch') {
                    $value=1;
                    $command=$message_data['data']['status'];
                }
                if ($command=='report' && isset($message_data['data']['status']) && $message_data['model']=='cube') {
                    $value=1;
                    $command=$message_data['data']['status'];
                }
                if ($command=='report' && isset($message_data['data']['rotate']) && $message_data['model']=='cube') {
                    $value=$message_data['data']['rotate'];
                    $command='rotate';
                }
                if ($command=='report' && $message_data['model']=='plug') {
                    if ($message_data['data']['status']=='on') {
                        $value=1;
                    } else {
                        $value=0;
                    }
                    $command='status';
                }
                if ($command=='report' && $message_data['model']=='motion') {
                    $value=1;
                    $command='motion';
                }
                if ($command=='report' && $message_data['model']=='magnet') {
                    if ($message_data['data']['status']=='close') {
                        $value=1;
                    } else {
                        $value=0;
                    }
                    $command='status';
                }
                if (!isset($value)) {
                    $value=json_encode($message_data['data']);
                }
                $cmd_rec=SQLSelectOne("SELECT * FROM xicommands WHERE DEVICE_ID=".$device['ID']." AND TITLE LIKE '".DBSafe($command)."'");
                if (!$cmd_rec['ID']) {
                    $cmd_rec=array();
                    $cmd_rec['DEVICE_ID']=$device['ID'];
                    $cmd_rec['TITLE']=$command;
                    $cmd_rec['ID']=SQLInsert('xicommands',$cmd_rec);
                }
                $old_value=$cmd_rec['VALUE'];
                $cmd_rec['VALUE']=$value;
                $cmd_rec['UPDATED']=date('Y-m-d H:i:s');
                SQLUpdate('xicommands',$cmd_rec);
                if ($cmd_rec['LINKED_OBJECT'] && $cmd_rec['LINKED_PROPERTY']) {
                    setGlobal($cmd_rec['LINKED_OBJECT'].'.'.$cmd_rec['LINKED_PROPERTY'], $value, array($this->name=>'0'));
                }
                if ($cmd_rec['LINKED_OBJECT'] && $cmd_rec['LINKED_METHOD'] && ($cmd_rec['VALUE']!=$old_value || $command=='motion')) {
                    callMethod($cmd_rec['LINKED_OBJECT'].'.'.$cmd_rec['LINKED_METHOD'], $message_data['data'], array($this->name=>'0'));
                }
            }

        }
    }

    function sendMessage($message, $ip, $sock) {
        $payload=json_encode($data);
        socket_sendto($sock, $payload, strlen($payload), 0, $ip, XIAOMI_MULTICAST_PORT);
    }

function admin(&$out) {
 if (isset($this->data_source) && !$_GET['data_source'] && !$_POST['data_source']) {
  $out['SET_DATASOURCE']=1;
 }
 if ($this->data_source=='xidevices' || $this->data_source=='') {
  if ($this->view_mode=='' || $this->view_mode=='search_xidevices') {
   $this->search_xidevices($out);
  }
  if ($this->view_mode=='edit_xidevices') {
   $this->edit_xidevices($out, $this->id);
  }
  if ($this->view_mode=='delete_xidevices') {
   $this->delete_xidevices($this->id);
   $this->redirect("?data_source=xidevices");
  }
 }
 if (isset($this->data_source) && !$_GET['data_source'] && !$_POST['data_source']) {
  $out['SET_DATASOURCE']=1;
 }
 if ($this->data_source=='xicommands') {
  if ($this->view_mode=='' || $this->view_mode=='search_xicommands') {
   $this->search_xicommands($out);
  }
  if ($this->view_mode=='edit_xicommands') {
   $this->edit_xicommands($out, $this->id);
  }
 }
}
/**
* FrontEnd
*
* Module frontend
*
* @access public
*/
function usual(&$out) {
    if ($this->ajax) {
        global $op;
        if ($op=='process') {
            global $message;
            global $ip;
            $this->processMessage($message,$ip);
        }
    }
 $this->admin($out);
}
/**
* xidevices search
*
* @access public
*/
 function search_xidevices(&$out) {
  require(DIR_MODULES.$this->name.'/xidevices_search.inc.php');
 }
/**
* xidevices edit/add
*
* @access public
*/
 function edit_xidevices(&$out, $id) {
  require(DIR_MODULES.$this->name.'/xidevices_edit.inc.php');
 }
/**
* xidevices delete record
*
* @access public
*/
 function delete_xidevices($id) {
  $rec=SQLSelectOne("SELECT * FROM xidevices WHERE ID='$id'");
  // some action for related tables
  SQLExec("DELETE FROM xicommands WHERE DEVICE_ID='".$rec['ID']."'");
  SQLExec("DELETE FROM xidevices WHERE ID='".$rec['ID']."'");
 }
/**
* xicommands search
*
* @access public
*/
 function search_xicommands(&$out) {
  require(DIR_MODULES.$this->name.'/xicommands_search.inc.php');
 }
/**
* xicommands edit/add
*
* @access public
*/
 function edit_xicommands(&$out, $id) {
  require(DIR_MODULES.$this->name.'/xicommands_edit.inc.php');
 }
 function propertySetHandle($object, $property, $value) {
   $table='xicommands';
   $properties=SQLSelect("SELECT ID FROM $table WHERE LINKED_OBJECT LIKE '".DBSafe($object)."' AND LINKED_PROPERTY LIKE '".DBSafe($property)."'");
   $total=count($properties);
   if ($total) {
    for($i=0;$i<$total;$i++) {
     //to-do
    }
   }
 }
 function processCycle() {
  //to-do
 }
/**
* Install
*
* Module installation routine
*
* @access private
*/
 function install($data='') {
  setGlobal('cycle_xiaomihomeControl', 'restart');
  parent::install();
 }
/**
* Uninstall
*
* Module uninstall routine
*
* @access public
*/
 function uninstall() {
  SQLExec('DROP TABLE IF EXISTS xidevices');
  SQLExec('DROP TABLE IF EXISTS xicommands');
  parent::uninstall();
 }
/**
* dbInstall
*
* Database installation routine
*
* @access private
*/
 function dbInstall() {
/*
xidevices - 
xicommands - 
*/
  $data = <<<EOD
 xidevices: ID int(10) unsigned NOT NULL auto_increment
 xidevices: TITLE varchar(100) NOT NULL DEFAULT ''
 xidevices: TYPE varchar(100) NOT NULL DEFAULT '' 
 xidevices: SID varchar(255) NOT NULL DEFAULT ''
 xidevices: GATE_KEY varchar(255) NOT NULL DEFAULT '' 
 xidevices: PARENT_ID int(10) unsigned NOT NULL DEFAULT '0' 
 xidevices: UPDATED datetime
 
 xicommands: ID int(10) unsigned NOT NULL auto_increment
 xicommands: TITLE varchar(100) NOT NULL DEFAULT ''
 xicommands: VALUE varchar(255) NOT NULL DEFAULT ''
 xicommands: DEVICE_ID int(10) NOT NULL DEFAULT '0'
 xicommands: LINKED_OBJECT varchar(100) NOT NULL DEFAULT ''
 xicommands: LINKED_PROPERTY varchar(100) NOT NULL DEFAULT ''
 xicommands: LINKED_METHOD varchar(100) NOT NULL DEFAULT ''
 xicommands: UPDATED datetime
 
 xiqueue: ID int(10) unsigned NOT NULL auto_increment
 xiqueue: IP varchar(100) NOT NULL DEFAULT '' 
 xiqueue: DATA text 
 xiqueue: ADDED datetime 
 
EOD;
  parent::dbInstall($data);
 }
// --------------------------------------------------------------------
}
/*
*
* TW9kdWxlIGNyZWF0ZWQgSmFuIDI4LCAyMDE3IHVzaW5nIFNlcmdlIEouIHdpemFyZCAoQWN0aXZlVW5pdCBJbmMgd3d3LmFjdGl2ZXVuaXQuY29tKQ==
*
*/
