<?php
// +-----------------------------------------------------------------------+
// | Piwigo - a PHP based picture gallery                                  |
// +-----------------------------------------------------------------------+
// | Copyright(C) 2010      Pierrick LE GALL             http://piwigo.org |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License as published by  |
// | the Free Software Foundation                                          |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, |
// | USA.                                                                  |
// +-----------------------------------------------------------------------+

if (!defined('PHOTOS_ADD_BASE_URL'))
{
  die ("Hacking attempt!");
}

// by default, the form values are the current configuration
// we may overwrite them with the current form values
$form_values = array();

foreach ($upload_form_config as $param_shortname => $param)
{
  $param_name = 'upload_form_'.$param_shortname;
  $form_values[$param_shortname] = $conf[$param_name];
}

// +-----------------------------------------------------------------------+
// |                             process form                              |
// +-----------------------------------------------------------------------+

if (isset($_POST['submit']))
{
  $updates = array();

  // let's care about the specific checkbox that disable/enable other
  // settings
  $field = 'websize_resize';
  
  if (empty($_POST[$field]))
  {
    $value = false;
  }
  else
  {
    $fields[] = 'websize_maxwidth';
    $fields[] = 'websize_maxheight';
    $fields[] = 'websize_quality';

    $value = true;
  }
  
  $updates[] = array(
    'param' => 'upload_form_'.$field,
    'value' => boolean_to_string($value),
    );
  $form_values[$field] = $value;;

  // and now other fields, processed in a generic way
  $fields[] = 'thumb_maxwidth';
  $fields[] = 'thumb_maxheight';
  $fields[] = 'thumb_quality';
  
  foreach ($fields as $field)
  {
    $value = null;
    if (!empty($_POST[$field]))
    {
      $value = $_POST[$field];
    }
    $form_values[$field] = $value;
    
    if ($upload_form_config[$field]['can_be_null'] and empty($value))
    {
      $updates[] = array(
        'param' => 'upload_form_'.$field,
        'value' => 'false'
        );
    }
    else
    {
      $min = $upload_form_config[$field]['min'];
      $max = $upload_form_config[$field]['max'];
      $pattern = $upload_form_config[$field]['pattern'];
      
      if (preg_match($pattern, $value) and $value >= $min and $value <= $max)
      {
         $updates[] = array(
          'param' => 'upload_form_'.$field,
          'value' => $value
          );
      }
      else
      {
        array_push(
          $page['errors'],
          sprintf(
            l10n($upload_form_config[$field]['error_message']),
            $min,
            $max
            )
          );
      }
    }
  }

  if (count($page['errors']) == 0)
  {
    mass_updates(
      CONFIG_TABLE,
      array(
        'primary' => array('param'),
        'update' => array('value')
        ),
      $updates
      );
    
    array_push(
      $page['infos'],
      l10n('Your configuration settings are saved')
      );
  }
}

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

// specific case, "websize_resize" is a checkbox
$field = 'websize_resize';
$form_values[$field] = $form_values[$field] ? 'checked="checked"' : '';

$template->assign(
    array(
      'F_ADD_ACTION'=> PHOTOS_ADD_BASE_URL,
      'values' => $form_values
    )
  );


// +-----------------------------------------------------------------------+
// |                           sending html code                           |
// +-----------------------------------------------------------------------+

$template->assign_var_from_handle('ADMIN_CONTENT', 'plugin_admin_content');
?>