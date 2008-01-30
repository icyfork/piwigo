<?php
// +-----------------------------------------------------------------------+
// | PhpWebGallery - a PHP based picture gallery                           |
// | Copyright (C) 2002-2003 Pierrick LE GALL - pierrick@phpwebgallery.net |
// | Copyright (C) 2003-2007 PhpWebGallery Team - http://phpwebgallery.net |
// +-----------------------------------------------------------------------+
// | file          : $Id$
// | last update   : $Date$
// | last modifier : $Author$
// | revision      : $Revision$
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

if (!defined('PHPWG_ROOT_PATH'))
{
  die('Hacking attempt!');
}

include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');
include_once(PHPWG_ROOT_PATH.'include/functions_mail.inc.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
check_status(ACCESS_ADMINISTRATOR);

//---------------------------------------------------------------- verification
if ( !isset( $_GET['cat_id'] ) || !is_numeric( $_GET['cat_id'] ) )
{
  $_GET['cat_id'] = '-1';
}

$template->set_filenames( array('categories'=>'admin/cat_modify.tpl') );

//--------------------------------------------------------- form criteria check
if (isset($_POST['submit']))
{
  $data =
    array(
      'id' => $_GET['cat_id'],
      'name' => @$_POST['name'],
      'commentable' => $_POST['commentable'],
      'uploadable' =>
        isset($_POST['uploadable']) ? $_POST['uploadable'] : 'false',
      'comment' =>
        $conf['allow_html_descriptions'] ?
          @$_POST['comment'] : strip_tags(@$_POST['comment'])
      );

  mass_updates(
    CATEGORIES_TABLE,
    array(
      'primary' => array('id'),
      'update' => array_diff(array_keys($data), array('id'))
      ),
    array($data)
    );

  set_cat_visible(array($_GET['cat_id']), $_POST['visible']);
  set_cat_status(array($_GET['cat_id']), $_POST['status']);

  if (isset($_POST['parent']))
  {
    move_categories(
      array($_GET['cat_id']),
      $_POST['parent']
      );
  }

  $image_order = '';
  if ( !isset($_POST['image_order_default']) )
  {
    for ($i=1; $i<=3; $i++)
    {
      if ( !empty($_POST['order_field_'.$i]) )
      {
        if (! empty($image_order) )
        {
          $image_order .= ',';
        }
        $image_order .= $_POST['order_field_'.$i];
        if ($_POST['order_direction_'.$i]=='DESC')
        {
          $image_order .= ' DESC';
        }
      }
    }
  }
  $image_order = empty($image_order) ? 'null' : "'$image_order'";
  $query = '
UPDATE '.CATEGORIES_TABLE.' SET image_order='.$image_order.'
WHERE ';
  if (isset($_POST['image_order_subcats']))
  {
    $query .= 'uppercats REGEXP \'(^|,)'.$_GET['cat_id'].'(,|$)\'';
  }
  else
  {
    $query .= 'id='.$_GET['cat_id'].';';
  }
  pwg_query($query);

  array_push($page['infos'], l10n('editcat_confirm'));
}
else if (isset($_POST['set_random_representant']))
{
  set_random_representant(array($_GET['cat_id']));
}
else if (isset($_POST['delete_representant']))
{
  $query = '
UPDATE '.CATEGORIES_TABLE.'
  SET representative_picture_id = NULL
  WHERE id = '.$_GET['cat_id'].'
;';
  pwg_query($query);
}
else if (isset($_POST['submitAdd']))
{
  $output_create = create_virtual_category(
    $_POST['virtual_name'],
    (0 == $_POST['parent'] ? null : $_POST['parent'])
    );

  if (isset($output_create['error']))
  {
    array_push($page['errors'], $output_create['error']);
  }
  else
  {
    // Virtual category creation succeeded
    //
    // Add the information in the information list
    array_push($page['infos'], $output_create['info']);

    // Link the new category to the current category
    associate_categories_to_categories(
      array($_GET['cat_id']),
      array($output_create['id'])
      );

    // information
    array_push(
      $page['infos'],
      sprintf(
        l10n('Category elements associated to the following categories: %s'),
        '<ul><li>'
        .get_cat_display_name_from_id($output_create['id'])
        .'</li></ul>'
        )
      );
  }
}
else if (isset($_POST['submitDestinations'])
         and isset($_POST['destinations'])
         and count($_POST['destinations']) > 0)
{
  associate_categories_to_categories(
    array($_GET['cat_id']),
    $_POST['destinations']
    );

  $category_names = array();
  foreach ($_POST['destinations'] as $category_id)
  {
    array_push(
      $category_names,
      get_cat_display_name_from_id($category_id)
      );
  }

  array_push(
    $page['infos'],
    sprintf(
      l10n('Category elements associated to the following categories: %s'),
      '<ul><li>'.implode('</li><li>', $category_names).'</li></ul>'
      )
    );
}

$query = '
SELECT *
  FROM '.CATEGORIES_TABLE.'
  WHERE id = '.$_GET['cat_id'].'
;';
$category = mysql_fetch_array( pwg_query( $query ) );
// nullable fields
foreach (array('comment','dir','site_id', 'id_uppercat') as $nullable)
{
  if (!isset($category[$nullable]))
  {
    $category[$nullable] = '';
  }
}

$category['is_virtual'] = empty($category['dir']) ? true : false;

// Navigation path
$url = PHPWG_ROOT_PATH.'admin.php?page=cat_modify&amp;cat_id=';

$navigation = get_cat_display_name_cache(
  $category['uppercats'],
  PHPWG_ROOT_PATH.'admin.php?page=cat_modify&amp;cat_id='
  );

$form_action = PHPWG_ROOT_PATH.'admin.php?page=cat_modify&amp;cat_id='.$_GET['cat_id'];
$status = ($category['status']=='public')?'STATUS_PUBLIC':'STATUS_PRIVATE';
$lock = ($category['visible']=='true')?'UNLOCKED':'LOCKED';

if ($category['commentable'] == 'true')
{
  $commentable = 'COMMENTABLE_TRUE';
}
else
{
  $commentable = 'COMMENTABLE_FALSE';
}
if ($category['uploadable'] == 'true')
{
  $uploadable = 'UPLOADABLE_TRUE';
}
else
{
  $uploadable = 'UPLOADABLE_FALSE';
}

//----------------------------------------------------- template initialization

$base_url = PHPWG_ROOT_PATH.'admin.php?page=';
$cat_list_url = $base_url.'cat_list';

$self_url = $cat_list_url;
if (!empty($category['id_uppercat']))
{
  $self_url.= '&amp;parent_id='.$category['id_uppercat'];
}

$template->assign_vars(
  array(
    'CATEGORIES_NAV'     => $navigation,
    'CAT_NAME'           => $category['name'],
    'CAT_COMMENT'        => $category['comment'],

    $status              => 'checked="checked"',
    $lock                => 'checked="checked"',
    $commentable         => 'checked="checked"',
    $uploadable          => 'checked="checked"',

    'IMG_ORDER_DEFAULT'  => empty($category['image_order']) ?
                              'checked="checked"' : '',

    'L_EDIT_NAME'        => l10n('name'),
    'L_STORAGE'          => l10n('storage'),
    'L_REMOTE_SITE'      => l10n('remote_site'),
    'L_EDIT_COMMENT'     => l10n('description'),
    'L_EDIT_STATUS'      => l10n('conf_access'),
    'L_STATUS_PUBLIC'    => l10n('public'),
    'L_STATUS_PRIVATE'   => l10n('private'),
    'L_EDIT_LOCK'        => l10n('lock'),
    'L_EDIT_UPLOADABLE'  => l10n('editcat_uploadable'),
    'L_EDIT_COMMENTABLE' => l10n('comments'),
    'L_YES'              => l10n('yes'),
    'L_NO'               => l10n('no'),
    'L_SUBMIT'           => l10n('submit'),
    'L_SET_RANDOM_REPRESENTANT'=>l10n('cat_representant'),

    'U_JUMPTO' => make_index_url(
      array(
        'category' => $category
        )
      ),

    'MAIL_CONTENT' => empty($_POST['mail_content'])
        ? '' : stripslashes($_POST['mail_content']),
    'U_CHILDREN' => $cat_list_url.'&amp;parent_id='.$category['id'],
    'U_HELP' => PHPWG_ROOT_PATH.'popuphelp.php?page=cat_modify',

    'F_ACTION' => $form_action,
    )
  );


if ('private' == $category['status'])
{
  $template->assign_block_vars(
    'permissions',
    array(
      'URL'=>$base_url.'cat_perm&amp;cat='.$category['id']
        )
    );
}

// manage category elements link
if ($category['nb_images'] > 0)
{
  $template->assign_block_vars(
    'elements',
    array(
      'URL'=>$base_url.'element_set&amp;cat='.$category['id']
      )
    );
}

// image order management
$matches = array();
if ( !empty( $category['image_order'] ) )
{
  preg_match_all('/([a-z_]+) *(?:(asc|desc)(?:ending)?)? *(?:, *|$)/i',
    $category['image_order'], $matches);
}

$sort_fields = array(
  '' => '',
  'date_creation' => l10n('Creation date'),
  'date_available' => l10n('Post date'),
  'average_rate' => l10n('Average rate'),
  'hit' => l10n('most_visited_cat'),
  'file' => l10n('File name'),
  'id' => 'Id',
  );

for ($i=0; $i<3; $i++) // 3 fields
{
  $template->assign_block_vars('image_order', array('NUMBER'=>$i+1) );
  foreach ($sort_fields as $sort_field => $name)
  {
    $selected='';
    if ( isset($matches[1][$i]) and $matches[1][$i]==$sort_field )
    {
      $selected='selected="selected"';
    }
    elseif ( empty($sort_field) )
    {
      $selected='selected="selected"';
    }

    $template->assign_block_vars('image_order.field',
      array(
        'SELECTED' => $selected,
        'VALUE' => $sort_field,
        'OPTION' => $name
        )
      );
  }

  $template->assign_block_vars('image_order.order',
    array(
      'SELECTED' =>
        ( empty($matches[2][$i]) or strcasecmp($matches[2][$i],'ASC')==0 )
          ? 'selected="selected"' : '',
      'VALUE' => 'ASC',
      'OPTION' => 'Ascending'
      )
    );

  $template->assign_block_vars('image_order.order',
    array(
      'SELECTED' =>
        ( isset($matches[2][$i]) and strcasecmp($matches[2][$i],'DESC')==0 )
          ? 'selected="selected"' : '',
      'VALUE' => 'DESC',
      'OPTION' => 'Descending'
      )
    );
}


// representant management
if ($category['nb_images'] > 0
    or !empty($category['representative_picture_id']))
{
  $template->assign_block_vars('representant', array());

  // picture to display : the identified representant or the generic random
  // representant ?
  if (!empty($category['representative_picture_id']))
  {
    $query = '
SELECT id,tn_ext,path
  FROM '.IMAGES_TABLE.'
  WHERE id = '.$category['representative_picture_id'].'
;';
    $row = mysql_fetch_array(pwg_query($query));
    $src = get_thumbnail_url($row);
    $url = PHPWG_ROOT_PATH.'admin.php?page=picture_modify';
    $url.= '&amp;image_id='.$category['representative_picture_id'];

    $template->assign_block_vars(
      'representant.picture',
      array(
        'SRC' => $src,
        'URL' => $url
        )
      );
  }
  else // $category['nb_images'] > 0
  {
    $template->assign_block_vars('representant.random', array());
  }

  // can the admin choose to set a new random representant ?
  if ($category['nb_images'] > 0)
  {
    $template->assign_block_vars('representant.set_random', array());
  }

  // can the admin delete the current representant ?
  if (
    ($category['nb_images'] > 0
     and $conf['allow_random_representative'])
    or
    ($category['nb_images'] == 0
     and !empty($category['representative_picture_id'])))
  {
    $template->assign_block_vars('representant.delete_representant', array());
  }
}

if (!$category['is_virtual'])
{
  $template->assign_block_vars(
    'storage',
    array('CATEGORY_DIR'=>preg_replace('/\/$/',
                                       '',
                                       get_complete_dir($category['id']))));
}
else
{
  $template->assign_block_vars(
    'delete',
    array(
      'URL'=>$self_url.'&amp;delete='.$category['id']
      )
    );

  $template->assign_block_vars('move', array());

  // the category can be moved in any category but in itself, in any
  // sub-category
  $unmovables = get_subcat_ids(array($category['id']));

  $blockname = 'move.parent_option';

  $template->assign_block_vars(
    $blockname,
    array(
      'SELECTED'
        => empty($category['id_uppercat']) ? 'selected="selected"' : '',
      'VALUE'=> 0,
      'OPTION' => '------------'
      )
    );

  $query = '
SELECT id,name,uppercats,global_rank
  FROM '.CATEGORIES_TABLE.'
  WHERE id NOT IN ('.implode(',', $unmovables).')
;';

  display_select_cat_wrapper(
    $query,
    empty($category['id_uppercat']) ? array() : array($category['id_uppercat']),
    $blockname
    );
}

$category['cat_dir'] = get_complete_dir($_GET['cat_id']);
if (is_numeric($category['site_id']) and url_is_remote($category['cat_dir']) )
{
  $query = '
SELECT galleries_url
  FROM '.SITES_TABLE.'
  WHERE id = '.$category['site_id'].'
;';
  list($galleries_url) = mysql_fetch_array(pwg_query($query));
  $template->assign_block_vars('server', array('SITE_URL' => $galleries_url));
}

if (!$category['is_virtual'] and !url_is_remote($category['cat_dir']) )
{
  $template->assign_block_vars('upload' ,array());
}

$blockname = 'category_option_parent';

$template->assign_block_vars(
  $blockname,
  array(
    'VALUE'=> 0,
    'OPTION' => '------------'
    )
  );

$query = '
SELECT id,name,uppercats,global_rank
  FROM '.CATEGORIES_TABLE.'
;';

display_select_cat_wrapper(
  $query,
  array(),
  $blockname
  );

// destination categories
$query = '
SELECT id,name,uppercats,global_rank
  FROM '.CATEGORIES_TABLE.'
  WHERE id != '.$category['id'].'
;';

display_select_cat_wrapper(
  $query,
  array(),
  'category_option_destination'
  );

// info by email to an access granted group of category informations
if (isset($_POST['submitEmail']) and !empty($_POST['group']))
{
  set_make_full_url();

  /* TODO: if $category['representative_picture_id'] 
    is empty find child representative_picture_id */
  if (!empty($category['representative_picture_id']))
  {
    $query = '
SELECT id, file, path, tn_ext
  FROM '.IMAGES_TABLE.'
  WHERE id = '.$category['representative_picture_id'].'
;';

    $result = pwg_query($query);
    if (mysql_num_rows($result) > 0)
    {
      $element = mysql_fetch_assoc($result);

      $img_url  = '<a href="'.
                      make_picture_url(array(
                          'image_id' => $element['id'],
                          'image_file' => $element['file'],
                          'category' => $category
                        ))
                      .'"><img src="'.get_thumbnail_url($element).'"/></a>';
    }
  }
  
  if (!isset($img_url))
  {
    $img_url = '';
  }

  // TODO Mettre un array pour traduction subjet
  pwg_mail_group(
    $_POST['group'],
    get_str_email_format(true), /* TODO add a checkbox in order to choose format*/
    get_l10n_args('[%s] Come to visit the category %s',
      array($conf['gallery_title'], $category['name'])),
    'admin',
    'cat_group_info',
    array
    (
      'IMG_URL' => $img_url,
      'CAT_NAME' => $category['name'],
      'LINK' => make_index_url(
          array(
            'category' => array(
              'id' => $category['id'],
              'name' => $category['name'],
              'permalink' => $category['permalink']
              ))),
      'CPL_CONTENT' => empty($_POST['mail_content'])
                          ? '' : stripslashes($_POST['mail_content'])
    ),
    '' /* TODO Add listbox in order to choose Language selected */);

  unset_make_full_url();

  $query = '
SELECT
    name
  FROM '.GROUPS_TABLE.'
  WHERE id = '.$_POST['group'].'
;';
  list($group_name) = mysql_fetch_row(pwg_query($query));
  
  array_push(
    $page['infos'],
    sprintf(
      l10n('An information email was sent to group "%s"'),
      $group_name
      )
    );
}

if ('private' == $category['status'])
{
  $query = '
SELECT
    group_id
  FROM '.GROUP_ACCESS_TABLE.'
  WHERE cat_id = '.$category['id'].'
;';
}
else
{
  $query = '
SELECT
    id AS group_id
  FROM '.GROUPS_TABLE.'
;';
}
$group_ids = array_from_query($query, 'group_id');

if (count($group_ids) > 0)
{
  $query = '
SELECT
    id,
    name
  FROM '.GROUPS_TABLE.'
  WHERE id IN ('.implode(',', $group_ids).')
  ORDER BY name ASC
;';
  $result = pwg_query($query);

  while ($row = mysql_fetch_array($result))
  {
    $template->assign_block_vars(
      'group_mail.group_option',
      array(
        'VALUE' => $row['id'],
        'OPTION' => $row['name'],
        )
      );
  }
}

//----------------------------------------------------------- sending html code
$template->assign_var_from_handle('ADMIN_CONTENT', 'categories');
?>
