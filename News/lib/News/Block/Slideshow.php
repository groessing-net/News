<?php
/**
 * Zikula Application Framework
 *
 * @copyright  (c) Zikula Development Team
 * @link       http://www.zikula.org
 * @version    $Id: slideshow.php 77 2009-02-25 17:33:19Z espaan $
 * @license    GNU/GPL - http://www.gnu.org/copyleft/gpl.html
 * @author     msshams <ms.shams@gmail.com>
 * @category   Zikula_3rdParty_Modules
 * @package    Content_Management
 * @subpackage News
 */

class News_Block_Slideshow extends Zikula_Block
{
/**
 * initialise block
 *
 * @author       The Zikula Development Team
 */
public function init()
{
    SecurityUtil::registerPermissionSchema('Slideshowblock::', 'Block ID::');
}

/**
 * get information on block
 *
 * @author       The Zikula Development Team
 * @return       array       The block information
 */
public function info()
{
    return array('module'          => 'News',
                 'text_type'       => ('slideshow'),
                 'text_type_long'  => ('Display news slideshow'),
                 'allow_multiple'  => false,
                 'form_content'    => false,
                 'form_refresh'    => false,
                 'show_preview'    => true,
                 'admin_tableless' => true);
}

/**
 * display block
 *
 * @author       The Zikula Development Team
 * @param        array       $blockinfo     a blockinfo structure
 * @return       output      the rendered bock
 */
public function display($blockinfo)
{
    // security check
    if (!SecurityUtil::checkPermission('Slideshowblock::', $blockinfo['bid'].'::', ACCESS_OVERVIEW)) {
        return;
    }

    // Break out options from our content field
    $vars = BlockUtil::varsFromContent($blockinfo['content']);

    // Defaults
    if (!isset($vars['limit'])) {
        $vars['limit'] = 4;
    }

    // work out the paraemters for the api all
    $apiargs = array();
    $apiargs['numitems'] = $vars['limit'];
    $apiargs['status'] = 0;
    $apiargs['ignorecats'] = true;

    if (isset($vars['category']) && !empty($vars['category'])) {
        if (!Loader::loadClass('CategoryUtil') || !Loader::loadClass('CategoryRegistryUtil')) {
            return LogUtil::registerError(__f('Error! Could not load [%s] class.', 'CategoryUtil | CategoryRegistryUtil'));
        }
        $cat = CategoryUtil::getCategoryByID($vars['category']);
        $categories = CategoryUtil::getCategoriesByPath($cat['path'], '', 'path');
        $catstofilter = array();
        foreach ($categories as $category) {
            $catstofilter[] = $category['id'];
        }
        $apiargs['category'] = array('Main' => $catstofilter);
    }
    $apiargs['filterbydate'] = true;

    // call the api
    $items = ModUtil::apiFunc('News', 'user', 'getall', $apiargs);

    // check for an empty return
    if (empty($items)) {
        return;
    }

    // create the output object
    $this->view->setCaching(false);

    // loop through the items
    $picupload_uploaddir = ModUtil::getVar('News', 'picupload_uploaddir');
    $picupload_maxpictures = ModUtil::getVar('News', 'picupload_maxpictures');
    $slideshowoutput = array();
	$count = 0;
    foreach ($items as $item) {
		$count++;
        if ($item['pictures'] > 0) {
            $this->view->assign('readperm', SecurityUtil::checkPermission('News::', "$item[cr_uid]::$item[sid]", ACCESS_READ));
            $this->view->assign('count', $count);
            $this->view->assign('picupload_uploaddir', $picupload_uploaddir);
            $this->view->assign($item);
            $slideshowoutput[] = $this->view->fetch('news_block_slideshow_row.htm', $item['sid'], null, false, false);
        }
    }

    // assign the results
    $this->view->assign('slideshow', $slideshowoutput);
    $this->view->assign('dom');

    $blockinfo['content'] = $this->view->fetch('news_block_slideshow.htm');

    return BlockUtil::themeBlock($blockinfo);
}

/**
 * modify block settings
 *
 * @author       The Zikula Development Team
 * @param        array       $blockinfo     a blockinfo structure
 * @return       output      the bock form
 */
public function modify($blockinfo)
{
    // Break out options from our content field
    $vars = BlockUtil::varsFromContent($blockinfo['content']);

    // Defaults
    if (empty($vars['limit'])) {
        $vars['limit'] = 4;
    }

    // Create output object
    $this->view->setCaching(false);

    $mainCat = CategoryRegistryUtil::getRegisteredModuleCategory('News', 'news', 'Main', 30); // 30 == /__SYSTEM__/Modules/Global
    $this->view->assign('mainCategory', $mainCat);
    $this->view->assign(ModUtil::getVar('News'));

    // assign the block vars
    $this->view->assign($vars);
    $this->view->assign('dom');

    // Return the output that has been generated by this function
    return $this->view->fetch('news_block_slideshow_modify.htm');
}

/**
 * update block settings
 *
 * @author       The Zikula Development Team
 * @param        array       $blockinfo     a blockinfo structure
 * @return       $blockinfo  the modified blockinfo structure
 */
public function update($blockinfo)
{
    // Get current content
    $vars = BlockUtil::varsFromContent($blockinfo['content']);

    // alter the corresponding variable
    $vars['category']    = FormUtil::getPassedValue('category', null, 'POST');
    $vars['limit']       = (int)FormUtil::getPassedValue('limit', null, 'POST');

    // write back the new contents
    $blockinfo['content'] = BlockUtil::varsToContent($vars);

    // clear the block cache
    $this->view->clear_cache('news_block_slideshow.htm');

    return $blockinfo;
}
}