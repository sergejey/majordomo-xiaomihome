<?php

global $session;

if ($this->owner->name == 'panel') {
   $out['CONTROLPANEL'] = 1;
}

$qry = '1';

$go_linked_object=gr('go_linked_object');
$go_linked_property=gr('go_linked_property');
if ($go_linked_object && $go_linked_property) {
   $tmp = SQLSelectOne("SELECT ID, DEVICE_ID FROM xicommands WHERE LINKED_OBJECT = '".DBSafe($go_linked_object)."' AND LINKED_PROPERTY='".DBSafe($go_linked_property)."'");
   if ($tmp['ID']) {
      $this->redirect("?id=".$tmp['ID']."&view_mode=edit_xidevices&id=".$tmp['DEVICE_ID']."&tab=data");
   }
}

global $save_qry;

if ($save_qry) {
   $qry = $session->data['xidevices_qry'];
} else {
   $session->data['xidevices_qry'] = $qry;
}

if (!$qry) $qry = '1';

$sortby=gr('sortby');
if (!$sortby && $session->data['xiamihome_sort']) {
   $sortby = $session->data['xiamihome_sort'];
} else {
   $session->data['xiamihome_sort']=$sortby;
   $session->save();
}

if ($sortby=='updated') {
   $sortby_xidevices = 'xidevices.UPDATED DESC';
} elseif ($sortby=='type') {
   $sortby_xidevices = 'xidevices.TYPE';
} else {
   $sortby_xidevices = 'xidevices.TITLE';
}

$out['SORTBY'] = $sortby_xidevices;

$data_updated=gr('data_updated','int');
if ($data_updated>0) {
   $qry.=" AND xidevices.UPDATED>'".date('Y-m-d H:i:s',$data_updated)."'";
}

if (!gr('ajax')) {
   if (gr('type')) {
      $type = gr('type');
      if ($type == 'all') {
         $type = '';
      }
   }
   if ($type) {
      $qry.=" AND xidevices.TYPE='".DBSafe($type)."'";
      $out['TYPE']=$type;
   }
}

$res = SQLSelect("SELECT * FROM xidevices WHERE $qry ORDER BY $sortby_xidevices");

if ($res[0]['ID']) {

   $total = count($res);
   for ($i = 0; $i < $total; $i++) {
      $tmp = explode(' ', $res[$i]['UPDATED']);
      $res[$i]['UPDATED'] = $tmp[0] . " " . $tmp[1];

      $commands = SQLSelect("SELECT * FROM xicommands WHERE DEVICE_ID=" . $res[$i]['ID'] . " AND TITLE!='report' AND TITLE!='heartbeat' AND TITLE!='write_ack' AND TITLE!='read_ack' AND TITLE!='write_rsp' AND TITLE!='read_rsp' AND TITLE!='server_ack' AND TITLE!='server_rsp' AND TITLE!='discovery_rsp' AND TITLE!='iam' AND TITLE!='ip' AND TITLE!='command' ORDER BY TITLE");

      if ($commands[0]['ID']) {
         $totalc = count($commands);
         for ($ic = 0; $ic < $totalc; $ic++) {
            $res[$i]['COMMANDS'] .= '<nobr>' . $commands[$ic]['TITLE'] . ': <i>' . $commands[$ic]['VALUE'] . '</i>';
            if ($commands[$ic]['LINKED_OBJECT'] != '') {
               $device=SQLSelectOne("SELECT ID, TITLE FROM devices WHERE LINKED_OBJECT='".DBSafe($commands[$ic]['LINKED_OBJECT'])."'");
               if ($device['TITLE']) {
                  $res[$i]['COMMANDS'] .= ' - <a href="'.ROOTHTML.'panel/devices/'.$device['ID'].'.html" target=_blank>' . $device['TITLE'].'</a>';
               } else {
                  $res[$i]['COMMANDS'] .= ' (' . $commands[$ic]['LINKED_OBJECT'];
                  if ($commands[$ic]['LINKED_PROPERTY'] != '') {
                     $res[$i]['COMMANDS'] .= '.' . $commands[$ic]['LINKED_PROPERTY'];
                  } elseif ($commands[$ic]['LINKED_METHOD'] != '') {
                     $res[$i]['COMMANDS'] .= '.' . $commands[$ic]['LINKED_METHOD'];
                  }
                  $res[$i]['COMMANDS'] .= ')';
               }
            }
            if ($commands[$ic]['TITLE'] == 'battery_level') {
               $res[$i]['POWER'] = $commands[$ic]['VALUE'];
               $res[$i]['POWER_WARNING'] = 'success';
               if ($res[$i]['POWER']<= 40)
                  $res[$i]['POWER_WARNING'] = 'warning';
               if ($res[$i]['POWER']<= 20)
                  $res[$i]['POWER_WARNING'] = 'danger';
            }
            $res[$i]['COMMANDS'] .= ";</nobr> ";

            if (time()-strtotime($res[$i]['UPDATED'])>3600) {
                  $res[$i]['LOST']='1';
            }
         }
      }
   }
   $out['TYPES']=SQLSelect("SELECT `TYPE`, COUNT(*) as TOTAL FROM xidevices GROUP BY `TYPE` ORDER BY `TYPE` ");
   $out['RESULT'] = $res;
}

if (gr('ajax')) { //
   $data=array();
   $data['ITEMS']=array();
   if (is_array($res) && $data_updated>0) {
      foreach($res as $item) {
         $data['ITEMS'][]=array('ID'=>$item['ID'],'COMMANDS'=>$item['COMMANDS'],'UPDATED'=>$item['UPDATED']);
      }
   }
   $data['TM']=time();
   echo json_encode($data);
   exit;
}

