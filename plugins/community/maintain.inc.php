<?php

if (!defined("COMMUNITY_PATH"))
{
  define('COMMUNITY_PATH', PHPWG_PLUGINS_PATH.basename(dirname(__FILE__)));
}

function plugin_install()
{
  global $conf, $prefixeTable;

  if ('mysql' == $conf['dblayer'])
  {
    $query = '
CREATE TABLE '.$prefixeTable.'community_permissions (
  id int(11) NOT NULL AUTO_INCREMENT,
  type varchar(255) NOT NULL,
  group_id smallint(5) unsigned DEFAULT NULL,
  user_id smallint(5) DEFAULT NULL,
  category_id smallint(5) unsigned DEFAULT NULL,
  recursive enum(\'true\',\'false\') NOT NULL DEFAULT \'true\',
  create_subcategories enum(\'true\',\'false\') NOT NULL DEFAULT \'false\',
  moderated enum(\'true\',\'false\') NOT NULL DEFAULT \'true\',
  PRIMARY KEY (id)
) ENGINE=MyISAM DEFAULT CHARACTER SET utf8
;';
    pwg_query($query);

    $query = '
CREATE TABLE '.$prefixeTable.'community_pendings (
  image_id mediumint(8) unsigned NOT NULL,
  state varchar(255) NOT NULL,
  added_on datetime NOT NULL,
  validated_by smallint(5) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARACTER SET utf8
;';
    pwg_query($query);
  }
  elseif ('pgsql' == $conf['dblayer'])
  {
    $query = '
CREATE TABLE "'.$prefixeTable.'community_permissions" (
  "id" serial NOT NULL,
  "type" VARCHAR(255) NOT NULL,
  "group_id" INTEGER,
  "user_id" INTEGER,
  "category_id" INTEGER,
  "recursive" BOOLEAN default true,
  "create_subcategories" BOOLEAN default false,
  "moderated" BOOLEAN default true,
  PRIMARY KEY ("id")
)
;';
    pwg_query($query);

    $query = '
CREATE TABLE "'.$prefixeTable.'community_pendings" (
  image_id INTEGER NOT NULL,
  state VARCHAR(255) NOT NULL,
  added_on TIMESTAMP NOT NULL,
  validated_by INTEGER
)
;';
    pwg_query($query);
  }
  else
  {
    $query = '
CREATE TABLE "'.$prefixeTable.'community_permissions" (
  "id" INTEGER NOT NULL,
  "type" VARCHAR(255) NOT NULL,
  "group_id" INTEGER,
  "user_id" INTEGER,
  "category_id" INTEGER,
  "recursive" BOOLEAN default true,
  "create_subcategories" BOOLEAN default false,
  "moderated" BOOLEAN default true,
  PRIMARY KEY ("id")
)
;';
    pwg_query($query);

    $query = '
CREATE TABLE "'.$prefixeTable.'community_pendings" (
  image_id INTEGER NOT NULL,
  state VARCHAR(255) NOT NULL,
  added_on TIMESTAMP NOT NULL,
  validated_by INTEGER
)
;';
    pwg_query($query);
  }
}

function plugin_uninstall()
{
  global $prefixeTable;
  
  $query = 'DROP TABLE '.$prefixeTable.'community_permissions;';
  pwg_query($query);

  $query = 'DROP TABLE '.$prefixeTable.'community_pendings;';
  pwg_query($query);
}

function plugin_activate()
{
  global $prefixeTable;

  $are_new_tables_installed = false;
  
  $query = 'SHOW TABLES;';
  $result = pwg_query($query);
  while ($row = pwg_db_fetch_row($result))
  {
    if ($prefixeTable.'community_permissions' == $row[0])
    {
      $are_new_tables_installed = true;
    }
  }

  if (!$are_new_tables_installed)
  {
    plugin_install();
  }

  community_get_data_from_core21();
  community_get_data_from_community21();

  $query = '
SELECT
    COUNT(*)
  FROM '.$prefixeTable.'community_permissions
;';
  list($counter) = pwg_db_fetch_row(pwg_query($query));
  if (0 == $counter)
  {
    community_create_default_permission();
  }

  include_once(dirname(__FILE__).'/include/functions_community.inc.php');
  community_update_cache_key();
}

function community_get_data_from_core21()
{
  global $conf, $prefixeTable;
  
  $from_piwigo21_file = $conf['local_data_dir'].'/plugins/core_user_upload_to_community.php';
  if (is_file($from_piwigo21_file))
  {
    include($from_piwigo21_file);
    $user_upload_conf = unserialize($user_upload_conf);
    
    include_once(PHPWG_ROOT_PATH.'admin/include/functions_upload.inc.php');
    prepare_upload_configuration();

    $user_upload_conf['upload_user_access'] = 1;

    if (isset($user_upload_conf['uploadable_categories']) and is_array($user_upload_conf['uploadable_categories']))
    {
      $type = 'any_registered_user';
      if (isset($user_upload_conf['upload_user_access']) and 1 == $user_upload_conf['upload_user_access'])
      {
        $type = 'any_visitor';
      }

      $inserts = array();
    
      foreach ($user_upload_conf['uploadable_categories'] as $category_id)
      {
        array_push(
          $inserts,
          array(
            'type' => $type,
            'category_id' => $category_id,
            'recursive' => 'false',
            'create_subcategories' => 'false',
            'moderated' => 'true',
            )
          );
      }
      
      if (count($inserts) > 0)
      {
        mass_inserts(
          $prefixeTable.'community_permissions',
          array_keys($inserts[0]),
          $inserts
          );
      }
    }
    
    if (isset($user_upload_conf['waiting_rows']) and is_array($user_upload_conf['waiting_rows']))
    {
      $id_of_user = array();
      
      $query = '
SELECT
    '.$conf['user_fields']['id'].' AS id,
    '.$conf['user_fields']['username'].' AS username
  FROM '.USERS_TABLE.'
;';
      $result = pwg_query($query);
      while ($row = pwg_db_fetch_assoc($result))
      {
        $id_of_user[ $row['username'] ] = $row['id'];
      }
      
      $inserts = array();
      
      foreach ($user_upload_conf['waiting_rows'] as $pending)
      {
        $source_path = get_complete_dir($pending['storage_category_id']).$pending['file'];
        
        if (is_file($source_path))
        {
          $image_id = add_uploaded_file($source_path, $pending['file'], array($pending['storage_category_id']), 16);
          
          array_push(
            $inserts,
            array(
              'image_id' => $image_id,
              'added_on' => date ('Y-m-d H:i:s', $pending['date']),
              'state' => 'moderation_pending',
              )
            );

          $data = array();
          
          if (isset($pending['username']) and isset($id_of_user[ $pending['username'] ]))
          {
            $data['added_by'] = $id_of_user[ $pending['username'] ];
          }
          
          foreach (array('date_creation', 'author', 'name', 'comment') as $field)
          {
            $value = getAttribute($pending['infos'], $field);
            if (!empty($value))
            {
            $data[$field] = pwg_db_real_escape_string($value);
            }
          }
          
          if (count($data) > 0)
          {
            $data['id'] = $image_id;
            
            mass_updates(
              IMAGES_TABLE,
              array(
                'primary' => array('id'),
                'update'  => array_keys($data)
                ),
              array($data)
              );
          }
          
          // deletion
          unlink($source_path);
          if (!isset($pending['tn_ext']))
          {
            $pending['tn_ext'] = 'jpg';
          }
          @unlink(get_thumbnail_path(array('path'=>$source_path, 'tn_ext'=>$pending['tn_ext'])));
        }
      }
      
      if (count($inserts) > 0)
      {
        mass_inserts(
          $prefixeTable.'community_pendings',
          array_keys($inserts[0]),
          $inserts
          );
      }
    }
    unlink($from_piwigo21_file);
  }
}

function community_get_data_from_community21()
{
  global $prefixeTable;
  
  $old_community_table = $prefixeTable.'community';
  $query = 'SHOW TABLES;';
  $result = pwg_query($query);
  while ($row = pwg_db_fetch_row($result))
  {
    if ($old_community_table == $row[0])
    {
      $inserts = array();
      
      $query = '
SELECT
    *
  FROM '.$old_community_table.'
;';
      $result = pwg_query($query);
      while ($row = pwg_db_fetch_assoc($result))
      {
        array_push(
          $inserts,
          array(
            'type' => 'user',
            'user_id' => $row['user_id'],
            'category_id' => null,
            'recursive' => 'true',
            'create_subcategories' => $row['permission_level'] == 2 ? 'true' : 'false',
            'moderated' => 'false',
            )
          );
      }
      
      if (count($inserts) > 0)
      {
        mass_inserts(
          $prefixeTable.'community_permissions',
          array_keys($inserts[0]),
          $inserts
          );
      }
      
      $query = 'DROP TABLE '.$old_community_table.';';
      pwg_query($query);
      
      break;
    }
  }
}

function community_create_default_permission()
{
  global $prefixeTable;
  
  // create an album "Community"
  $category_info = create_virtual_category('Community');

  $insert = array(
    'type' => 'any_registered_user',
    'category_id' => $category_info['id'],
    'recursive' => 'true',
    'create_subcategories' => 'true',
    'moderated' => 'true',
    );

  mass_inserts($prefixeTable.'community_permissions', array_keys($insert), array($insert));
}
?>
