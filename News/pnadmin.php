<?php
/**
 * Zikula Application Framework
 *
 * @copyright  (c) Zikula Development Team
 * @link       http://www.zikula.org
 * @version    $Id: pnadmin.php 82 2009-02-25 23:09:21Z mateo $
 * @license    GNU/GPL - http://www.gnu.org/copyleft/gpl.html
 * @author     Mark West <mark@zikula.org>
 * @category   Zikula_3rdParty_Modules
 * @package    Content_Management
 * @subpackage News
 */

/**
 * the main administration function
 * This function is the default function, and is called whenever the
 * module is initiated without defining arguments.  As such it can
 * be used for a number of things, but most commonly it either just
 * shows the module menu and returns or calls whatever the module
 * designer feels should be the default function (often this is the
 * view() function)
 * 
 * @author Mark West
 * @return string HTML output
 */
function News_admin_main()
{
    // Security check
    if (!SecurityUtil::checkPermission('Stories::Story', '::', ACCESS_EDIT)) {
        return LogUtil::registerPermissionError();
    }

    // Create output object
    $renderer = pnRender::getInstance('News', false);

    // Return the output that has been generated by this function
    return $renderer->fetch('news_admin_main.htm');
}

/**
 * create a new news article
 * this function is purely a wrapper for the output from news_user_new
 * @author Mark West
 * @return string HTML string
 */
function News_admin_new()
{
    // Return the output that has been generated by this function
    return pnModFunc('News', 'user', 'new');
}

/**
 * modify an item
 * This is a standard function that is called whenever an administrator
 * wishes to modify a current module item
 * @param int 'sid' the id of the item to be modified
 * @param int 'objectid' generic object id maps to sid if present
 * @author Mark West
 * @return string HTML string
 */
function News_admin_modify($args)
{
    $sid = FormUtil::getPassedValue('sid', isset($args['sid']) ? $args['sid'] : null, 'GETPOST');
    $objectid = FormUtil::getPassedValue('objectid', isset($args['objectid']) ? $args['objectid'] : null, 'GET');
    // At this stage we check to see if we have been passed $objectid
    if (!empty($objectid)) {
        $sid = $objectid;
    }

    // Check if we're redirected to preview
    $inpreview = false;
    $item = SessionUtil::getVar('newsitem');
    if (!empty($item) && isset($item['sid'])) {
        $inpreview = true;
        $sid = $item['sid'];
    }

    // Validate the essential parameters
    if (empty($sid)) {
        return LogUtil::registerError(_MODARGSERROR);
    }

    // Get the news article in the db
    $dbitem = pnModAPIFunc('News', 'user', 'get', array('sid' => $sid));

    if ($dbitem === false) {
        return LogUtil::registerError(pnML('_NOSUCHITEM', array('i' => _NEWS_STORY)), 404);
    }

    // Security check
    if (!SecurityUtil::checkPermission('Stories::Story', "$dbitem[aid]::$sid", ACCESS_EDIT)) {
        return LogUtil::registerPermissionError();
    }

    // merge the data of the db and the preview if exist
    $item = $inpreview ? array_merge($dbitem, $item) : $dbitem;
    unset($dbitem);

    // Get the format types. 'home' string is bits 0-1, 'body' is bits 2-3.
    $item['hometextcontenttype'] = isset($item['hometextcontenttype']) ? $item['hometextcontenttype'] : ($item['format_type']%4);
    $item['bodytextcontenttype'] = isset($item['bodytextcontenttype']) ? $item['bodytextcontenttype'] : (($item['format_type']/4)%4);

    // Set the publishing date options.
    if (!$inpreview) {
        if (DateUtil::getDatetimeDiff_AsField($item['from'], $item['time'], 6) >= 0 && is_null($item['to'])) {
            $item['unlimited'] = 1;
            $item['tonolimit'] = 0;
        } elseif (DateUtil::getDatetimeDiff_AsField($item['from'], $item['time'], 6) < 0 && is_null($item['to'])) {
            $item['unlimited'] = 0;
            $item['tonolimit'] = 1;
        } else  {
            $item['unlimited'] = 0;
            $item['tonolimit'] = 0;
        }
    } else {
        $item['unlimited'] = isset($item['unlimited']) ? 1 : 0;
        $item['tonolimit'] = isset($item['tonolimit']) ? 1 : 0;
    }

    // Check if we need a preview
    $preview = '';
    if (isset($item['preview']) && $item['preview'] == 0) {
        $preview = pnModFunc('News', 'user', 'preview',
                             array('title' => $item['title'],
                                   'hometext' => $item['hometext'],
                                   'hometextcontenttype' => $item['hometextcontenttype'],
                                   'bodytext' => $item['bodytext'],
                                   'bodytextcontenttype' => $item['bodytextcontenttype'],
                                   'notes' => $item['notes']));
    }

    // Get the module configuration vars
    $modvars = pnModGetVar('News');

    if ($modvars['enablecategorization']) {
        // load the category registry util
        if (!($class = Loader::loadClass('CategoryRegistryUtil'))) {
            pn_exit (pnML('_UNABLETOLOADCLASS', array('s' => 'CategoryRegistryUtil')));
        }
        $catregistry = CategoryRegistryUtil::getRegisteredModuleCategories('News', 'stories');

        // check if the __CATEGORIES__ info needs a fix (when preview)
        if (isset($item['__CATEGORIES__'])) {
            foreach ($item['__CATEGORIES__'] as $prop => $catid) {
                if (is_numeric($catid)) {
                    $item['__CATEGORIES__'][$prop] = array('id' => $catid);
                }
            }
        }
    }

    // Create output object
    $renderer = pnRender::getInstance('News', false);

    if ($modvars['enablecategorization']) {
        $renderer->assign('catregistry', $catregistry);
    }

    // Pass the module configuration to the template
    $renderer->assign($modvars);

    // Assign the item to the template
    $renderer->assign($item);

    // Get the preview of the item
    $renderer->assign('preview', $preview);

    // Assign the content format
    $formattedcontent = pnModAPIFunc('News', 'user', 'isformatted', array('func' => 'new'));
    $renderer->assign('formattedcontent', $formattedcontent);

    // Return the output that has been generated by this function
    return $renderer->fetch('news_admin_modify.htm');
}

/**
 * This is a standard function that is called with the results of the
 * form supplied by News_admin_modify() to update a current item
 * @param int 'sid' the id of the item to be updated
 * @param int 'objectid' generic object id maps to sid if present
 * @param string 'title' the title of the news item
 * @param string 'urltitle' the title of the news item formatted for the url
 * @param string 'language' the language of the news item
 * @param string 'bodytext' the summary text of the news item
 * @param int 'bodytextcontenttype' the content type of the summary text
 * @param string 'extendedtext' the body text of the news item
 * @param int 'extendedtextcontenttype' the content type of the body text
 * @param string 'notes' any administrator notes
 * @param int 'published_status' the published status of the item
 * @param int 'ihome' publish the article in the homepage
 * @author Mark West
 * @return bool true
 */
function News_admin_update($args)
{
    $story = FormUtil::getPassedValue('story', isset($args['story']) ? $args['story'] : null, 'POST');
    if (!empty($story['objectid'])) {
        $story['sid'] = $story['objectid'];
    }

    // Validate the essential parameters
    if (empty($story['sid'])) {
        return LogUtil::registerError(_MODARGSERROR);
    }

    // Confirm authorisation code
    if (!SecurityUtil::confirmAuthKey()) {
        return LogUtil::registerAuthidError(pnModURL('News', 'admin', 'view'));
    }

    // Get the unedited news article for the permissions check
    $item = pnModAPIFunc('News', 'user', 'get', array('sid' => $story['sid']));
    if ($item === false) {
        return LogUtil::registerError(pnML('_NOSUCHITEM', array('i' => _NEWS_STORY)), 404);
    }

    // Security check
    if (!SecurityUtil::checkPermission('Stories::Story', "$item[aid]::$item[sid]", ACCESS_EDIT)) {
        return LogUtil::registerPermissionError();
    }

    // Validate the input
    $validationerror = false;
    if ($story['preview'] != 0 && empty($story['title'])) {
        $validationerror = _TITLE;
    }
    // both text fields can't be empty
    if ($story['preview'] != 0 && empty($story['hometext']) && empty($story['bodytext'])) {
        $validationerror = _NEWS_ARTICLECONTENT;
    }

    // Reformat the attributes array
    // from {0 => {name => '...', value => '...'}} to {name => value}
    if (isset($story['attributes'])) {
        $attributes = array();
        foreach ($story['attributes'] as $attr) {
            if (!empty($attr['name']) && !empty($attr['value'])) {
                $attributes[$attr['name']] = $attr['value'];
            }
        }
        unset($story['attributes']);
        $story['__ATTRIBUTES__'] = $attributes;
    }

    // if the user has selected to preview the article we then route them back
    // to the new function with the arguments passed here
    if ($story['preview'] == 0 || $validationerror !== false) {
        // log the error found if any
        if ($validationerror !== false) {
            LogUtil::registerError(pnML('_NOFOUND', array('i' => $validationerror)));
        }
        // back to the referer form
        SessionUtil::setVar('newsitem', $story);
        return pnRedirect(pnModURL('News', 'admin', 'modify'));

    } else {
        // As we're not previewing the item let's remove it from the session
        SessionUtil::delVar('newsitem');
    }

    // Update the story
    if (pnModAPIFunc('News', 'admin', 'update',
                    array('sid' => $story['sid'],
                          'title' => $story['title'],
                          'urltitle' => $story['urltitle'],
                          '__CATEGORIES__' => isset($story['__CATEGORIES__']) ? $story['__CATEGORIES__'] : null,
                          '__ATTRIBUTES__' => isset($story['__ATTRIBUTES__']) ? $story['__ATTRIBUTES__'] : null,
                          'language' => isset($story['language']) ? $story['language'] : '',
                          'hometext' => isset($story['hometext']) ? $story['hometext'] : '',
                          'hometextcontenttype' => $story['hometextcontenttype'],
                          'bodytext' => isset($story['bodytext']) ? $story['bodytext'] : '',
                          'bodytextcontenttype' => $story['bodytextcontenttype'],
                          'notes' => $story['notes'],
                          'ihome' => isset($story['ihome']) ? $story['ihome'] : 0,
                          'unlimited' => isset($story['unlimited']) ? $story['unlimited'] : null,
                          'from' => mktime($story['fromHour'], $story['fromMinute'], 0, $story['fromMonth'], $story['fromDay'], $story['fromYear']),
                          'tonolimit' => isset($story['tonolimit']) ? $story['tonolimit'] : null,
                          'to' => mktime($story['toHour'], $story['toMinute'], 0, $story['toMonth'], $story['toDay'], $story['toYear']),
                          'published_status' => $story['published_status']))) {
        // Success
        LogUtil::registerStatus(pnML('_UPDATEITEMSUCCEDED', array('i' => _NEWS_STORY)));
    }

    return pnRedirect(pnModURL('News', 'admin', 'view'));
}

/**
 * delete item
 * This is a standard function that is called whenever an administrator
 * wishes to delete a current module item.  Note that this function is
 * the equivalent of both of the modify() and update() functions above as
 * it both creates a form and processes its output.  This is fine for
 * simpler functions, but for more complex operations such as creation and
 * modification it is generally easier to separate them into separate
 * functions.  There is no requirement in the Zikula MDG to do one or the
 * other, so either or both can be used as seen appropriate by the module
 * developer
 * @param int 'sid' the id of the news item to be deleted
 * @param int 'objectid' generic object id maps to sid if present
 * @param int 'confirmation' confirmation that this news item can be deleted
 * @author Mark West
 * @return mixed HTML string if no confirmation, true if delete successful, false otherwise
 */
function News_admin_delete($args)
{
    $sid = FormUtil::getPassedValue('sid', isset($args['sid']) ? $args['sid'] : null, 'REQUEST');
    $objectid = FormUtil::getPassedValue('objectid', isset($args['objectid']) ? $args['objectid'] : null, 'REQUEST');
    $confirmation = FormUtil::getPassedValue('confirmation', null, 'POST');
    if (!empty($objectid)) {
        $sid = $objectid;
    }

    // Validate the essential parameters
    if (empty($sid)) {
        return LogUtil::registerError(_MODARGSERROR);
    }

    // Get the news story
    $item = pnModAPIFunc('News', 'user', 'get', array('sid' => $sid));

    if ($item == false) {
        return LogUtil::registerError(pnML('_NOSUCHITEM', array('i' => _NEWS_STORY)), 404);
    }

    // Security check
    if (!SecurityUtil::checkPermission('Stories::Story', "$item[aid]::$item[sid]", ACCESS_DELETE)) {
        return LogUtil::registerPermissionError();
    }

    // Check for confirmation.
    if (empty($confirmation)) {
        // No confirmation yet
        // Create output object
        $renderer = pnRender::getInstance('News', false);

        // Add News story ID
        $renderer->assign('sid', $sid);

        // Return the output that has been generated by this function
        return $renderer->fetch('news_admin_delete.htm');
    }

    // If we get here it means that the user has confirmed the action

    // Confirm authorisation code
    if (!SecurityUtil::confirmAuthKey()) {
        return LogUtil::registerAuthidError(pnModURL('News', 'admin', 'view'));
    }

    // Delete
    if (pnModAPIFunc('News', 'admin', 'delete', array('sid' => $sid))) {
        // Success
        LogUtil::registerStatus(pnML('_DELETEITEMSUCCEDED', array('i' => _NEWS_STORY)));
    }

    return pnRedirect(pnModURL('News', 'admin', 'view'));
}

/**
 * view items
 * @param int 'startnum' starting number for paged output
 * @author Mark West
 * @return string HTML string
 */
function News_admin_view($args)
{
    // Security check
    if (!SecurityUtil::checkPermission('Stories::Story', '::', ACCESS_EDIT)) {
        return LogUtil::registerPermissionError();
    }

    $startnum    = FormUtil::getPassedValue('startnum', isset($args['startnum']) ? $args['startnum'] : null, 'GET');
    $news_status = FormUtil::getPassedValue('news_status', isset($args['news_status']) ? $args['news_status'] : null, 'GETPOST');
    $language    = FormUtil::getPassedValue('language', isset($args['language']) ? $args['language'] : null, 'POST');
    $property    = FormUtil::getPassedValue('news_property', isset($args['news_property']) ? $args['news_property'] : null, 'GETPOST');
    $category    = FormUtil::getPassedValue("news_{$property}_category", isset($args["news_{$property}_category"]) ? $args["news_{$property}_category"] : null, 'GETPOST');
    $clear       = FormUtil::getPassedValue('clear', false, 'POST');
    $purge       = FormUtil::getPassedValue('purge', false, 'GET');
    $order       = FormUtil::getPassedValue('order', isset($args['order']) ? $args['order'] : 'from', 'GETPOST');
//    $monthyear   = FormUtil::getPassedValue('monthyear', isset($args['monthyear']) ? $args['monthyear'] : null, 'POST');

    if ($purge) {
        if (pnModAPIFunc('News', 'admin', 'purgepermalinks')) {
            LogUtil::registerStatus(_PURGEPERMALINKSSUCCESFUL);
        } else {
            LogUtil::registerError(_PURGEPERMALINKSFAILED);
        }
        return pnRedirect(strpos(pnServerGetVar('HTTP_REFERER'), 'purge') ? pnModURL('News', 'admin', 'view') : pnServerGetVar('HTTP_REFERER'));
    }
    if ($clear) {
        // reset the filter
        $property = null;
        $category = null;
        $news_status = null;
        $order = 'from';
    }

    // clean the session preview data
    SessionUtil::delVar('newsitem');

    // get module vars for later use
    $modvars = pnModGetVar('News');

    if ($modvars['enablecategorization']) {
        // load the category registry util
        if (!($class = Loader::loadClass('CategoryRegistryUtil'))) {
            pn_exit (pnML('_UNABLETOLOADCLASS', array('s' => 'CategoryRegistryUtil')));
        }
        $catregistry  = CategoryRegistryUtil::getRegisteredModuleCategories('News', 'stories');
        $properties = array_keys($catregistry);

        // Validate and build the category filter - mateo
        if (!empty($property) && in_array($property, $properties) && !empty($category)) {
            $catFilter = array($property => $category);
        }

        // Assign a default property - mateo
        if (empty($property) || !in_array($property, $properties)) {
            $property = $properties[0];
        }

        // plan ahead for ML features
        $propArray = array();
        foreach ($properties as $prop) {
            $propArray[$prop] = $prop;
        }
    }

    $multilingual = pnConfigGetVar ('multilingual', false);

    $now = DateUtil::getDatetime();
    $status = null;
    if (isset($news_status) && $news_status != '') {
        if ($news_status == 0) {
            $status = 0;
            $to = $now;
        } elseif ($news_status == 5) {
            $status = 0; // scheduled is actually published in the future
            $from = $now; //getDatetime_NextDay
        } else {
            $status = $news_status;
        }
    }

    // Get all news story
    $items = pnModAPIFunc('News', 'user', 'getall',
                          array('startnum' => $startnum,
                                'status'   => $status,
                                'numitems' => $modvars['itemsperpage'],
                                'ignoreml' => ($multilingual ? false : true),
                                'language' => $language,
                                'order'    => isset($order) ? $order : 'from',
                                'from'     => isset($from) ? $from : null,
                                'to'       => isset($to) ? $to : null,
                                'category' => isset($catFilter) ? $catFilter : null,
                                'catregistry' => isset($catregistry) ? $catregistry : null));

    // Set the possible status for later use
    $itemstatus = array (
        '' => _ALL, 
        0  => _NEWS_PUBLISHED,
        1  => _NEWS_REJECTED,
        2  => _NEWS_PENDING,
        3  => _NEWS_ARCHIVED,
        4  => _NEWS_DRAFT,
        5  => _NEWS_SCHEDULED
    );

/*    // Load localized month names
    $months = explode(' ', _MONTH_LONG);
    $newsmonths = array();
    // get all matching news stories
    $monthsyears = pnModAPIFunc('News', 'user', 'getMonthsWithNews');
    foreach ($monthsyears as $monthyear) {
        $month = DateUtil::getDatetime_Field($monthyear, 2);
        $year  = DateUtil::getDatetime_Field($monthyear, 1);
        $linktext = $months[$month-1]." $year";
        $newsmonths[$monthyear] = $linktext;
    }
*/

    $newsitems = array();
    foreach ($items as $item) {
        $options = array();
        $options[] = array('url'   => pnModURL('News', 'user', 'display', array('sid' => $item['sid'])),
                           'image' => 'demo.gif',
                           'title' => _VIEW);

        if (SecurityUtil::checkPermission('Stories::Story', "$item[aid]::$item[sid]", ACCESS_EDIT)) {
            $options[] = array('url'   => pnModURL('News', 'admin', 'modify', array('sid' => $item['sid'])),
                               'image' => 'xedit.gif',
                               'title' => _EDIT);

            if (SecurityUtil::checkPermission('Stories::Story', "$item[aid]::$item[sid]", ACCESS_DELETE)) {
                $options[] = array('url'   => pnModURL('News', 'admin', 'delete', array('sid' => $item['sid'])),
                                   'image' => '14_layer_deletelayer.gif',
                                   'title' => _DELETE);
            }
        }
        $item['options'] = $options;

        if (in_array($item['published_status'], array_keys($itemstatus))) {
            $item['status'] = $itemstatus[$item['published_status']];
        } else {
            $item['status'] = _NEWS_UNKNOWN;
        }

        if ($item['ihome'] == 0) {
            $item['ihome'] = _YES;
        } else {
            $item['ihome'] = _NO;
        }

        $item['infuture'] = DateUtil::getDatetimeDiff_AsField($item['from'], DateUtil::getDatetime(), 6) < 0;
        $newsitems[] = $item;
    }

    // Create output object
    $renderer = pnRender::getInstance('News', false);

    // Assign the items and modvars to the template
    $renderer->assign('newsitems', $newsitems);
    $renderer->assign($modvars);

    // Assign the default and selected language
    $renderer->assign('lang', pnUserGetLang());
    $renderer->assign('language', $language);

    // Assign the current status filter and the possible ones
    $renderer->assign('news_status', $news_status);
    $renderer->assign('itemstatus', $itemstatus);
    $renderer->assign('order', $order);
    $renderer->assign('orderoptions', array(
                                        'from' => _NEWS_STORIESORDER1, 
                                        'sid' => _NEWS_STORIESORDER0));
//    $renderer->assign('monthyear', $monthyear);
//    $renderer->assign('newsmonths', $newsmonths);

    // Assign the categories information if enabled
    if ($modvars['enablecategorization']) {
        $renderer->assign('catregistry', $catregistry);
        $renderer->assign('numproperties', count($propArray));
        $renderer->assign('properties', $propArray);
        $renderer->assign('property', $property);
        $renderer->assign('category', $category);
    }
    
    // Count the items for the selected status and category
    $statuslinks = array();
    $statuslinks[] = array('count' => pnModAPIFunc('News', 'user', 'countitems',
                                                   array('category' => isset($catFilter) ? $catFilter : null,
                                                         'status' => 0,
                                                         'to' => $now)),
                           'url' => pnModURL('News', 'admin', 'view',
                                             array('news_status' => 0,
                                                   'news_property' => $property,
                                                   'news_'.$property.'_category' => isset($category) ? $category : null)),
                           'title' => _NEWS_PUBLISHED);
    $statuslinks[] = array('count' => pnModAPIFunc('News', 'user', 'countitems',
                                                    array('category' => isset($catFilter) ? $catFilter : null,
                                                          'status' => 0,
                                                          'from' => $now)),
                            'url' => pnModURL('News', 'admin', 'view',
                                              array('news_status' => 5,
                                                    'news_property' => $property,
                                                    'news_'.$property.'_category' => isset($category) ? $category : null)),
                            'title' => _NEWS_SCHEDULED);
    $statuslinks[] = array('count' => pnModAPIFunc('News', 'user', 'countitems',
                                                    array('category' => isset($catFilter) ? $catFilter : null,
                                                          'status' => 2)),
                            'url' => pnModURL('News', 'admin', 'view',
                                              array('news_status' => 2,
                                                    'news_property' => $property,
                                                    'news_'.$property.'_category' => isset($category) ? $category : null)),
                            'title' => _NEWS_PENDING);
    $statuslinks[] = array('count' => pnModAPIFunc('News', 'user', 'countitems',
                                                    array('category' => isset($catFilter) ? $catFilter : null,
                                                          'status' => 4)),
                            'url' => pnModURL('News', 'admin', 'view',
                                              array('news_status' => 4,
                                                    'news_property' => $property,
                                                    'news_'.$property.'_category' => isset($category) ? $category : null)),
                            'title' => _NEWS_DRAFT);
    $statuslinks[] = array('count' => pnModAPIFunc('News', 'user', 'countitems',
                                                    array('category' => isset($catFilter) ? $catFilter : null,
                                                          'status' => 3)),
                            'url' => pnModURL('News', 'admin', 'view',
                                              array('news_status' => 3,
                                                    'news_property' => $property,
                                                    'news_'.$property.'_category' => isset($category) ? $category : null)),
                            'title' => _NEWS_ARCHIVED);
    $statuslinks[] = array('count' => pnModAPIFunc('News', 'user', 'countitems',
                                                    array('category' => isset($catFilter) ? $catFilter : null,
                                                          'status' => 1)),
                            'url' => pnModURL('News', 'admin', 'view',
                                              array('news_status' => 1,
                                                    'news_property' => $property,
                                                    'news_'.$property.'_category' => isset($category) ? $category : null)),
                            'title' => _NEWS_REJECTED);
    $alllink = array('count' => $statuslinks[0]['count'] + $statuslinks[1]['count'] + $statuslinks[2]['count'] + $statuslinks[3]['count'] + $statuslinks[4]['count'] + $statuslinks[5]['count'],
                     'url' => pnModURL('News', 'admin', 'view',
                                       array('news_property' => $property,
                                             'news_'.$property.'_category' => isset($category) ? $category : null)),
                     'title' => _ALL);
    $renderer->assign('statuslinks', $statuslinks);
    $renderer->assign('alllink', $alllink);
  
    // Assign the values for the smarty plugin to produce a pager
    $renderer->assign('pager', array('numitems' => pnModAPIFunc('News', 'user', 'countitems', array('category' => isset($catFilter) ? $catFilter : null)),
                                     'itemsperpage' => $modvars['itemsperpage']));

    // Return the output that has been generated by this function
    return $renderer->fetch('news_admin_view.htm');
}

/**
 * This is a standard function to modify the configuration parameters of the
 * module
 * @author Mark West
 * @return string HTML string
 */
function News_admin_modifyconfig()
{
    // Security check
    if (!SecurityUtil::checkPermission('Stories::Story', '::', ACCESS_ADMIN)) {
        return LogUtil::registerPermissionError();
    }

    if (!($class = Loader::loadClass('CategoryRegistryUtil'))) {
        pn_exit (pnML('_UNABLETOLOADCLASS', array('s' => 'CategoryRegistryUtil')));
    }
    $catregistry   = CategoryRegistryUtil::getRegisteredModuleCategories('News', 'stories');
    $properties    = array_keys($catregistry);
    $propertyName  = pnModGetVar('News', 'topicproperty');
    $propertyIndex = empty($propertyName) ? 0 : array_search($propertyName, $properties);

    // Create output object
    $renderer = pnRender::getInstance('News', false);

    // Number of items to display per page
    $renderer->assign(pnModGetVar('News'));

    $renderer->assign('properties', $properties);
    $renderer->assign('property', $propertyIndex);

    // Return the output that has been generated by this function
    return $renderer->fetch('news_admin_modifyconfig.htm');
}

/**
 * This is a standard function to update the configuration parameters of the
 * module given the information passed back by the modification form
 * @author Mark West
 * @param int 'itemsperpage' number of articles per page
 * @return bool true
 */
function News_admin_updateconfig()
{
    // Security check
    if (!SecurityUtil::checkPermission('Stories::Story', '::', ACCESS_ADMIN)) {
        return LogUtil::registerPermissionError();
    }

    // Confirm authorisation code
    if (!SecurityUtil::confirmAuthKey()) {
        return LogUtil::registerAuthidError(pnModURL('News', 'admin', 'view'));
    }

    // Update module variables
    $modvars = array();

    $refereronprint = (int)FormUtil::getPassedValue('refereronprint', 0, 'POST');
    if ($refereronprint != 0 && $refereronprint != 1) {
        $refereronprint = 0;
    }
    $modvars['refereronprint'] = $refereronprint;
    $modvars['itemsperpage'] = (int)FormUtil::getPassedValue('itemsperpage', 25, 'POST');
    $modvars['storyhome'] = (int)FormUtil::getPassedValue('storyhome', 10, 'POST');
    $modvars['storyorder'] = (int)FormUtil::getPassedValue('storyorder', 1, 'POST');
    $modvars['enablecategorization'] = (bool)FormUtil::getPassedValue('enablecategorization', false, 'POST');
    $modvars['enableattribution'] = (bool)FormUtil::getPassedValue('enableattribution', false, 'POST');
    $catimagepath = FormUtil::getPassedValue('catimagepath', '/images/categories/', 'POST');
    if (substr($catimagepath, -1) != '/') {
        $catimagepath .= '/'; // add slash if needed
    }
    $modvars['catimagepath'] = $catimagepath;

    if (!($class = Loader::loadClass('CategoryRegistryUtil'))) {
        pn_exit (pnML('_UNABLETOLOADCLASS', array('s' => 'CategoryRegistryUtil')));
    }
    $catregistry   = CategoryRegistryUtil::getRegisteredModuleCategories('News', 'stories');
    $properties    = array_keys($catregistry);
    $topicproperty = FormUtil::getPassedValue('topicproperty', null, 'POST');
    $modvars['topicproperty'] = $properties[$topicproperty];

    $permalinkformat = FormUtil::getPassedValue('permalinkformat', null, 'POST');
    if ($permalinkformat == 'custom') {
        $permalinkformat = FormUtil::getPassedValue('permalinkstructure', null, 'POST');
    }
    $modvars['permalinkformat'] = $permalinkformat;

    pnModSetVars('News', $modvars);

    // Let any other modules know that the modules configuration has been updated
    pnModCallHooks('module','updateconfig','News', array('module' => 'News'));

    // the module configuration has been updated successfuly
    LogUtil::registerStatus(_CONFIGUPDATED);

    return pnRedirect(pnModURL('News', 'admin', 'main'));
}