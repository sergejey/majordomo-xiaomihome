<?php

   if ($this->owner->name == 'panel') {
      $out['CONTROLPANEL'] = 1;
   }

   $table_name = 'xidevices';

   $rec = SQLSelectOne("SELECT * FROM $table_name WHERE ID='$id'");

   if ($rec['ID']) {
      $battery = SQLSelectOne("SELECT * FROM xicommands WHERE DEVICE_ID='" . $rec['ID'] . "' AND TITLE='battery_level'");
      if ($battery['ID']) {
         $out['POWER'] = $battery['VALUE'];
         $out['POWER_WARNING'] = 'success';
         if ($out['POWER'] <= 40)
            $out['POWER_WARNING'] = 'warning';
         if ($out['POWER'] <= 20)
            $out['POWER_WARNING'] = 'danger';

         if ($rec['TYPE']=='switch' ||
             $rec['TYPE']=='sensor_switch' ||
             $rec['TYPE']=='sensor_switch.aq2' ||
             $rec['TYPE']=='sensor_switch.aq3' ||
             $rec['TYPE']=='86sw1' ||
             $rec['TYPE']=='86sw2' ||
             $rec['TYPE']=='remote.b186acn01' ||
             $rec['TYPE']=='remote.b286acn01' ||
             $rec['TYPE']=='remote.b1acn01' ||  
             $rec['TYPE']=='sensor_86sw1' ||
             $rec['TYPE']=='sensor_86sw2' ||
             $rec['TYPE']=='sensor_ht' ||
             $rec['TYPE']=='weather' ||
             $rec['TYPE']=='weather.v1' ||
             $rec['TYPE']=='vibration' ||
             $rec['TYPE']=='vibration.aq1' ||
             $rec['TYPE']=='sensor_wleak.aq1')
         {
            $out['BATTERY_TYPE'] = 'CR2032';
         } elseif ($rec['TYPE']=='motion' ||
                   $rec['TYPE']=='sensor_motion' ||
                   $rec['TYPE']=='sensor_motion.aq2' ||
                   $rec['TYPE']=='cube' ||
                   $rec['TYPE']=='sensor_cube.aqgl01')
         {
            $out['BATTERY_TYPE'] = 'CR2450';
         } elseif ($rec['TYPE']=='magnet' || $rec['TYPE']=='sensor_magnet' || $rec['TYPE']=='sensor_magnet.aq2') {
            $out['BATTERY_TYPE'] = 'CR1632';
         } elseif ($rec['TYPE']=='smoke' || $rec['TYPE']=='sensor_smoke') {
            $out['BATTERY_TYPE'] = 'CR123A';
         } elseif ($rec['TYPE']=='lock.aq1') {
            $out['BATTERY_TYPE'] = '8 AA';
         }
      }
   }

   if ($this->mode == 'update') {

      $ok = 1;

      // step: default
      if ($this->tab == '') {
         global $title;
         $rec['TITLE'] = $title;
         if ($rec['TITLE'] == '') {
            $out['ERR_TITLE'] = 1;
            $ok = 0;
         }
         if ($rec['TYPE']=='gateway' || $rec['TYPE']=='acpartner.v3') {
            global $gate_key;
            $rec['GATE_KEY']=$gate_key;
         }
      }

      //UPDATING RECORD
      if ($ok) {
         if ($rec['ID']) {
            SQLUpdate($table_name, $rec);
         } else {
            $new_rec = 1;
            $rec['ID'] = SQLInsert($table_name, $rec);
         }

         $commands = array();

         $commands[] = 'command';

         if ($rec['TYPE'] == 'gateway' || $rec['TYPE'] == 'acpartner.v3') {
            $commands[] = 'ringtone';
         }

         if ($rec['TYPE'] == 'curtain') {
            $commands[] = 'curtain_status';
         }

         if (count($commands)>0) {
            foreach($commands as $command) {
               $cmd_rec = SQLSelectOne("SELECT * FROM xicommands WHERE DEVICE_ID=" . $rec['ID'] . " AND TITLE LIKE '" . DBSafe($command) . "'");
               if (!$cmd_rec['ID']) {
                  $cmd_rec = array();
                  $cmd_rec['DEVICE_ID'] = $rec['ID'];
                  $cmd_rec['TITLE'] = $command;
                  $cmd_rec['ID'] = SQLInsert('xicommands',$cmd_rec);
               }
            }
         }
         $out['OK'] = 1;
      } else {
         $out['ERR'] = 1;
      }
   }

   // step: default
   if ($this->tab == '') {
      if ($rec['UPDATED'] != '') {
         $tmp = explode(' ', $rec['UPDATED']);
         $out['UPDATED_DATE'] = fromDBDate($tmp[0]);
         $tmp2 = explode(':', $tmp[1]);
         $updated_hours = $tmp2[0];
         $updated_minutes = $tmp2[1];
      }

      for($i = 0; $i < 60; $i++) {
         $title = $i;
         if ($i < 10) $title = "0$i";
         if ($title == $updated_minutes) {
            $out['UPDATED_MINUTES'][] = array('TITLE'=>$title, 'SELECTED'=>1);
         } else {
            $out['UPDATED_MINUTES'][] = array('TITLE'=>$title);
         }
      }

      for($i = 0; $i < 24; $i++) {
         $title = $i;
         if ($i < 10) $title = "0$i";
         if ($title == $updated_hours) {
            $out['UPDATED_HOURS'][] = array('TITLE'=>$title, 'SELECTED'=>1);
         } else {
            $out['UPDATED_HOURS'][] = array('TITLE'=>$title);
         }
      }
   }

   // step: data
   if ($this->tab == 'data') {
      $new_id = 0;

      global $delete_id;

      if ($delete_id) {
         SQLExec("DELETE FROM xicommands WHERE ID='".(int)$delete_id."'");
      }

      $properties = SQLSelect("SELECT * FROM xicommands WHERE DEVICE_ID='".$rec['ID']."' ORDER BY ID");

      $total = count($properties);

      for($i = 0; $i < $total; $i++) {
         if ($properties[$i]['ID'] == $new_id) continue;
         if ($this->mode == 'update') {
            /*
            global ${'title'.$properties[$i]['ID']};
            $properties[$i]['TITLE']=trim(${'title'.$properties[$i]['ID']});
            global ${'value'.$properties[$i]['ID']};
            $properties[$i]['VALUE']=trim(${'value'.$properties[$i]['ID']});
            */
            $old_linked_object=$properties[$i]['LINKED_OBJECT'];
            $old_linked_property=$properties[$i]['LINKED_PROPERTY'];
            global ${'linked_object'.$properties[$i]['ID']};
            $properties[$i]['LINKED_OBJECT']=trim(${'linked_object'.$properties[$i]['ID']});
            global ${'linked_property'.$properties[$i]['ID']};
            $properties[$i]['LINKED_PROPERTY']=trim(${'linked_property'.$properties[$i]['ID']});
            global ${'linked_method'.$properties[$i]['ID']};
            $properties[$i]['LINKED_METHOD']=trim(${'linked_method'.$properties[$i]['ID']});
            SQLUpdate('xicommands', $properties[$i]);
            if ($old_linked_object && $old_linked_object!=$properties[$i]['LINKED_OBJECT'] && $old_linked_property && $old_linked_property!=$properties[$i]['LINKED_PROPERTY']) {
             removeLinkedProperty($old_linked_object, $old_linked_property, $this->name);
            }
         }

         if ($properties[$i]['LINKED_OBJECT'] && $properties[$i]['LINKED_PROPERTY']) {
            addLinkedProperty($properties[$i]['LINKED_OBJECT'], $properties[$i]['LINKED_PROPERTY'], $this->name);
         }

         if (file_exists(DIR_MODULES.'devices/devices.class.php')) {
            if ($properties[$i]['TITLE']=='motion') {
               $properties[$i]['SDEVICE_TYPE'] = 'motion';
            } elseif ($properties[$i]['TITLE']=='click' || 
               $properties[$i]['TITLE']=='left_click' || 
               $properties[$i]['TITLE']=='rigt_click' || 
               $properties[$i]['TITLE']=='both_click' || 
               $properties[$i]['TITLE']=='double_click' || 
               $properties[$i]['TITLE']=='left_double_click' || 
               $properties[$i]['TITLE']=='right_double_click' || 
               $properties[$i]['TITLE']=='long_click'  || 
               $properties[$i]['TITLE']=='left_long_click'  || 
               $properties[$i]['TITLE']=='right_long_click'  || 
               $properties[$i]['TITLE']=='long_click_press'  || 
               $properties[$i]['TITLE']=='long_click_release' ||
               $properties[$i]['TITLE']=='long_both_click' || 
               $properties[$i]['TITLE']=='flip90' ||
               $properties[$i]['TITLE']=='flip180' ||
               $properties[$i]['TITLE']=='move' ||
               $properties[$i]['TITLE']=='tap_twice' ||
               $properties[$i]['TITLE']=='shake_air' ||
               $properties[$i]['TITLE']=='swing' ||
               $properties[$i]['TITLE']=='alert' ||
               $properties[$i]['TITLE']=='free_fall') {
               $properties[$i]['SDEVICE_TYPE'] = 'button';
            } elseif ($properties[$i]['TITLE']=='alarm' && $rec['TYPE']=='smoke') {
               $properties[$i]['SDEVICE_TYPE']='smoke';
            } elseif ($properties[$i]['TITLE']=='status') {
               $properties[$i]['SDEVICE_TYPE']='openclose';
            } elseif ($properties[$i]['TITLE']=='channel' || $properties[$i]['TITLE']=='channel_0' || $properties[$i]['TITLE']=='channel_1') {
               $properties[$i]['SDEVICE_TYPE']='relay';
            } elseif ($properties[$i]['TITLE']=='temperature') {
               $properties[$i]['SDEVICE_TYPE']='sensor_temp';
            } elseif ($properties[$i]['TITLE']=='humidity') {
               $properties[$i]['SDEVICE_TYPE']='sensor_humidity';
            } elseif ($properties[$i]['TITLE']=='voltage') {
               $properties[$i]['SDEVICE_TYPE']='sensor_voltage';
            } elseif ($properties[$i]['TITLE']=='rgb') {
               $properties[$i]['SDEVICE_TYPE']='rgb';
            } elseif ($properties[$i]['TITLE']=='load_power' || $properties[$i]['TITLE']=='power_consumed' || $properties[$i]['TITLE']=='energy_consumed') {
               $properties[$i]['SDEVICE_TYPE']='sensor_power';
            } elseif ($properties[$i]['TITLE']=='ringtone' ||
               $properties[$i]['TITLE']=='no_motion' ||
               $properties[$i]['TITLE']=='no_close' ||
               $properties[$i]['TITLE']=='density' ||
               $properties[$i]['TITLE']=='iam') {
               $properties[$i]['SDEVICE_TYPE']='sensor_state';
            } elseif ($properties[$i]['TITLE']=='brightness' || $properties[$i]['TITLE']=='curtain_level') {
               $properties[$i]['SDEVICE_TYPE']='dimmer';
            } elseif ($properties[$i]['TITLE']=='illumination' || $properties[$i]['TITLE']=='lux') {
               $properties[$i]['SDEVICE_TYPE']='sensor_light';
            } elseif ($properties[$i]['TITLE']=='pressure_kpa' || $properties[$i]['TITLE']=='pressure_mm') {
               $properties[$i]['SDEVICE_TYPE']='sensor_pressure';
            } elseif ($properties[$i]['TITLE']=='leak') {
               $properties[$i]['SDEVICE_TYPE']='leak';
            } elseif ($properties[$i]['TITLE']=='battery_level') {
               $properties[$i]['SDEVICE_TYPE']='sensor_percentage';
            } else {
               $properties[$i]['SDEVICE_TYPE']='sensor_general';
            }
         }
      }
      $out['PROPERTIES'] = $properties;
   }

   if (is_array($rec)) {
      foreach($rec as $k => $v) {
         if (!is_array($v)) {
            $rec[$k] = htmlspecialchars($v);
         }
      }
   }

   outHash($rec, $out);
