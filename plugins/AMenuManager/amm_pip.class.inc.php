<?php
/* -----------------------------------------------------------------------------
  Plugin     : Advanced Menu Manager
  Author     : Grum
    email    : grum@piwigo.org
    website  : http://photos.grum.fr
    PWG user : http://forum.phpwebgallery.net/profile.php?id=3706

    << May the Little SpaceFrog be with you ! >>
  ------------------------------------------------------------------------------
  See main.inc.php for release information

  PIP classe => manage integration in public interface

  --------------------------------------------------------------------------- */
if (!defined('PHPWG_ROOT_PATH')) { die('Hacking attempt!'); }

include_once(PHPWG_PLUGINS_PATH.'AMenuManager/amm_root.class.inc.php');
include_once(PHPWG_PLUGINS_PATH.'GrumPluginClasses/classes/GPCAjax.class.inc.php');

class AMM_PIP extends AMM_root
{
  protected $displayRandomImageBlock=true;
  protected $registeredBlocks;
  protected $randomPictProp=null;
  protected $users;
  protected $groups;
  protected $currentBuiltMenu=-1;

  function AMM_PIP($prefixeTable, $filelocation)
  {
    parent::__construct($prefixeTable, $filelocation);
    $this->css = new GPCCss(dirname($this->getFileLocation()).'/'.$this->getPluginNameFiles()."2.css");

    $this->users=new GPCUsers();
    $this->groups=new GPCGroups();

    $this->loadConfig();
    $this->initEvents();
  }


  /**
   * initialize events call for the plugin
   */
  public function initEvents()
  {
    parent::initEvents();

    add_event_handler('blockmanager_prepare_display', array(&$this, 'blockmanagerSortBlocks') );
    add_event_handler('blockmanager_apply', array(&$this, 'blockmanagerApply') );
    add_event_handler('loc_end_page_header', array(&$this->css, 'applyCSS'));
    add_event_handler('loc_end_page_header', array(&$this, 'applyJS'));
    add_event_handler('get_categories_menu_sql_where', array(&$this, 'buildMenuFromCat'), 75);
  }


  public function blockmanagerApply($menu_ref_arr)
  {
    $menu=&$menu_ref_arr[0];

    $this->addBlockRandomPicture($menu);
    $this->addBlockLinks($menu);
    $this->addBlockPersonnal($menu);
    $this->addBlockAlbum($menu);
    $this->manageBlocksContent($menu);
    $this->manageBlocks($menu);
  }


  /**
   * Add a new random picture block
   */
  private function addBlockRandomPicture(&$menu)
  {
    global $user;

    if((
        ($block=$menu->get_block('mbAMM_randompict'))!=null) and
        ($user['nb_total_images']>0) and
        isset($this->config['amm_randompicture_title'][$user['language']]) and
        $this->displayRandomImageBlock
      )
    {
      GPCCore::addHeaderJS('jquery', 'themes/default/js/jquery.min.js');
      GPCCore::addHeaderJS('amm.randomPictPublic', 'plugins/AMenuManager/js/amm_randomPictPublic'.GPCCore::getMinified().'.js', array('jquery'));

      $block->set_title(base64_decode($this->config['amm_randompicture_title'][$user['language']]));
      $block->template=dirname(__FILE__).'/menu_templates/menubar_randompic.tpl';

      $this->randomPictProp = array(
        'delay' => $this->config['amm_randompicture_periodicchange'],
        'blockHeight' => $this->config['amm_randompicture_height'],
        'showname' => $this->config['amm_randompicture_showname'],
        'showcomment' => $this->config['amm_randompicture_showcomment'],
        'pictures' => $this->getRandomPictures($this->config['amm_randompicture_preload'])
      );

      if(count($this->randomPictProp['pictures'])==0) $this->displayRandomImageBlock=false;
    }
    else
    {
      $this->displayRandomImageBlock=false;
    }
  }


  /**
   * Add a new block (links)
   */
  private function addBlockLinks(&$menu)
  {
    global $user;

    $nbLink=0;

    if(($block=$menu->get_block('mbAMM_links'))!=null &&
       isset($this->config['amm_links_title'][$user['language']])
      )
    {
      $urls=$this->getLinks(true);

      if(count($urls)>0)
      {
        $userGroups=$this->getUserGroups($user['id']);;

        foreach($urls as $key => $val)
        {
          $this->users->setAlloweds(explode(",", $val['accessUsers']), false);
          $this->groups->setAlloweds(explode(",", $val['accessGroups']), false);

          if(!$this->users->isAllowed($user['status']))
          {
            unset($urls[$key]);
          }
          else
          {
            $ok=true;
            foreach($userGroups as $group)
            {
              if(!$this->groups->isAllowed($group)) $ok=false;
            }
            if(!$ok) unset($urls[$key]);
          }
        }

        if($this->config['amm_links_show_icons']=='y')
        {
          foreach($urls as $key => $url)
          {
            $urls[$key]['icon']=get_root_url().'plugins/'.AMM_DIR."/links_pictures/".$url['icon'];
          }
        }

        $block->set_title(base64_decode($this->config['amm_links_title'][$user['language']]));
        $block->template=dirname(__FILE__).'/menu_templates/menubar_links.tpl';

        $block->data = array(
          'LINKS' => $urls,
          'icons' => $this->config['amm_links_show_icons']
        );
      }
    }
  }


  /**
   * Add personnal blocks
   */
  private function addBlockPersonnal(&$menu)
  {
    $sections=$this->getPersonalisedBlocks(true);

    if(count($sections))
    {
      $idDone=array();
      foreach($sections as $key => $val)
      {
        if(!isset($idDone[$val['id']]))
        {
          if(($block=$menu->get_block('mbAMM_personalised'.$val['id']))!= null)
          {
            $block->set_title($val['title']);
            $block->template = dirname(__FILE__).'/menu_templates/menubar_personalised.tpl';
            $block->data = stripslashes($val['content']);
          }
          $idDone[$val['id']]="";
        }
      }
    }
  }




  /**
   * Add album to menu
   */
  private function addBlockAlbum(&$menu)
  {
    if(count($this->config['amm_albums_to_menu'])>0)
    {
      $sql="SELECT id, name, permalink, global_rank
            FROM ".CATEGORIES_TABLE."
            WHERE id IN(".implode(',', $this->config['amm_albums_to_menu']).");";

      $result=pwg_query($sql);
      if($result)
      {
        while($row=pwg_db_fetch_assoc($result))
        {
          $this->currentBuiltMenu=$row['id'];

          $row['name']=trigger_event('render_category_name', $row['name'], 'amm_album_to_menu');

          if(($block=$menu->get_block('mbAMM_album'.$row['id']))!= null)
          {
            $block->set_title($row['name']);
            $block->template = dirname(__FILE__).'/menu_templates/menubar_album.tpl';
            $block->data = array(
              'album' => get_categories_menu(),
              'name' => $row['name'],
              'link' => make_index_url(array('category' => $row)),
              'nbPictures' => ''
            );
          }
        }
      }
      $this->currentBuiltMenu=-1;
    }
  }

  /**
   * manage items from special & menu blocks
   *  - reordering items
   *  - grouping items
   *  - managing rights to access
   */
  private function manageBlocksContent(&$menu)
  {
    global $user;

    $blocks=Array();

    if($menu->is_hidden('mbMenu'))
    {
      // if block is hidden, make a fake to manage AMM submenu features
      // the fake block isn't displayed
      $blocks['menu']=new DisplayBlock('amm_mbMenu');
      $blocks['menu']->data=Array();
    }
    else
    {
      $blocks['menu']=$menu->get_block('mbMenu');
    }

    if($menu->is_hidden('mbSpecials'))
    {
      // if block is hidden, make a fake to manage AMM submenu features
      // the fake block isn't displayed
      $blocks['special']=new DisplayBlock('amm_mbSpecial');
      $blocks['special']->data=Array();
    }
    else
    {
      $blocks['special']=$menu->get_block('mbSpecials');
    }

    $menuItems=array_merge($blocks['menu']->data, $blocks['special']->data);
    $this->sortCoreBlocksItems();

    $blocks['menu']->data=Array();
    $blocks['special']->data=Array();
    $userGroups=$this->getUserGroups($user['id']);

    foreach($this->config['amm_blocks_items'] as $key => $val)
    {
      if(isset($menuItems[$key]))
      {
        $access=explode("/",$val['visibility']);
        $this->users->setAlloweds(str_replace(",", "/", $access[0]), false);
        $this->groups->setAlloweds(str_replace(",", "/", $access[1]), false);

        /*
         * test if user status is allowed to access the menu item
         * if access is managed by group, the user have to be associated with an allowed group to access the menu item
         */
        if($this->users->isAllowed($user['status']))
        {
          $ok=true;
          foreach($userGroups as $group)
          {
            if(!$this->groups->isAllowed($group)) $ok=false;
          }
          if($ok) $blocks[$val['container']]->data[$key]=$menuItems[$key];
        }
      }
    }
    if(count($blocks['menu']->data)==0) $menu->hide_block('mbMenu');
    if(count($blocks['special']->data)==0) $menu->hide_block('mbSpecials');
  }


  /**
   * return groups for a user
   *
   * @param String $userId
   * @return Array
   */
  private function getUserGroups($userId)
  {
    global $user;

    $returned=array();

    $sql="SELECT group_id FROM ".USER_GROUP_TABLE." WHERE user_id='".$user['id']."';";
    $result=pwg_query($sql);
    if($result)
    {
      while($row=pwg_db_fetch_assoc($result))
      {
        $returned[]=$row['group_id'];
      }
    }
    return($returned);
  }


  /**
   * reordering blocks and manage access right
   *
   */
  private function manageBlocks($menu)
  {
    $this->registeredBlocks=$this->getRegisteredBlocks(true);

    foreach($menu->get_registered_blocks() as $key => $block)
    {
      if(!isset($this->registeredBlocks[$block->get_id()]))
      {
        $menu->hide_block($block->get_id());
      }
    }

  }


  /**
   * sort menu blocks according to AMM rules (overriding piwigo's sort rules)
   */
  public function blockmanagerSortBlocks($blocks)
  {
    $this->registeredBlocks=$this->getRegisteredBlocks(true);

    if(!isset($this->registeredBlocks['mbAMM_randompict'])) $this->displayRandomImageBlock=false;

    foreach($blocks[0]->get_registered_blocks() as $key => $block)
    {
      if(isset($this->registeredBlocks[$block->get_id()]))
      {
        $blocks[0]->set_block_position($block->get_id(), $this->registeredBlocks[$block->get_id()]['order']);
      }
    }
  }







  /**
   * return a list of thumbnails
   * each array items is an array
   *  'imageId'   => (integer)
   *  'imageFile' => (String)
   *  'comment'   => (String)
   *  'path'      => (String)
   *  'tn_ext'    => (String)
   *  'catId'     => (String)
   *  'name'      => (String)
   *  'permalink' => (String)
   *  'imageName' => (String)
   *
   * @param Integer $number : number of returned images
   * @return Array
   */
  private function getRandomPictures($num=25)
  {
    global $user;

    $returned=array();

    $sql=array();

    $sql['select']="SELECT i.id as image_id, i.file as image_file, i.comment, i.path, i.tn_ext, c.id as catid, c.name, c.permalink, RAND() as rndvalue, i.name as imgname ";
    $sql['from']="FROM ".CATEGORIES_TABLE." c, ".IMAGES_TABLE." i, ".IMAGE_CATEGORY_TABLE." ic ";
    $sql['where']="WHERE c.id = ic.category_id
            AND ic.image_id = i.id
            AND i.level <= ".$user['level']." ";

    if($user['forbidden_categories']!="")
    {
      $sql['where'].=" AND c.id NOT IN (".$user['forbidden_categories'].") ";
    }

    switch($this->config['amm_randompicture_selectMode'])
    {
      case 'f':
        $sql['from'].=", ".USER_INFOS_TABLE." ui
          LEFT JOIN ".FAVORITES_TABLE." f ON ui.user_id=f.user_id ";
        $sql['where'].=" AND ui.status='webmaster'
                         AND f.image_id = i.id ";
        break;
      case 'c':
        $sql['where'].="AND (";
        foreach($this->config['amm_randompicture_selectCat'] as $key => $val)
        {
          $sql['where'].=($key==0?'':' OR ')." FIND_IN_SET($val, c.uppercats) ";
        }
        $sql['where'].=") ";
        break;
    }

    $sql=$sql['select'].$sql['from'].$sql['where']." ORDER BY rndvalue LIMIT 0,$num";


    $result = pwg_query($sql);
    if($result)
    {
      while($row=pwg_db_fetch_assoc($result))
      {
        $row['section']='category';
        $row['category']=array(
          'id' => $row['catid'],
          'name' => $row['name'],
          'permalink' => $row['permalink']
        );

        $row['link']=make_picture_url($row);
        $row['thumb']=get_thumbnail_url($row);

        $returned[]=$row;
      }
    }

    return($returned);
  }




  public function applyJS()
  {
    global $user, $template, $page;

    if(!array_key_exists('body_id', $page))
    {
      /*
       * it seems the error message reported on mantis:1476 is displayed because
       * the 'body_id' doesn't exist in the $page
       *
       * not abble to reproduce the error, but initializing the key to an empty
       * value if it doesn't exist may be a sufficient solution
       */
      $page['body_id']="";
    }


    if($this->displayRandomImageBlock && $page['body_id'] == 'theCategoryPage')
    {
      $local_tpl = new Template(AMM_PATH."admin/", "");
      $local_tpl->set_filename('body_page', dirname($this->getFileLocation()).'/menu_templates/menubar_randompic.js.tpl');

      $local_tpl->assign('data', $this->randomPictProp);

      $template->append('head_elements', $local_tpl->parse('body_page', true));
    }
  }



  public function buildMenuFromCat($where)
  {
    global $user;

    if($this->currentBuiltMenu>-1)
    {
      if($user['expand'])
      {
        $where=preg_replace('/id_uppercat\s+is\s+NULL/i', 'id_uppercat is NOT NULL', $where);
      }
      else
      {
        $where=preg_replace('/id_uppercat\s+is\s+NULL/i', 'id_uppercat is NULL OR id_uppercat IN ('.$this->currentBuiltMenu.')', $where);
      }

      $where.=" AND FIND_IN_SET(".$this->currentBuiltMenu.", uppercats) AND cat_id!=".$this->currentBuiltMenu." ";
    }

    return($where);
  }

} // AMM_PIP class


?>
