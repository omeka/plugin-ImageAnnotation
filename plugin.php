<?php
/**
 * @version $Id$
 * @copyright Center for History and New Media, 2007-2008
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Omeka
 * @subpackage ImageAnnotation
 **/

define('IMAGE_ANNOTATION_PLUGIN_DIR', dirname(__FILE__));
 
add_plugin_hook('install', 'image_annotation_install');
add_plugin_hook('uninstall', 'image_annotation_uninstall');
add_plugin_hook('define_routes', 'image_annotation_routes');
add_plugin_hook('public_theme_header', 'image_annotation_public_theme_header');
add_plugin_hook('admin_theme_header', 'image_annotation_admin_theme_header');
add_plugin_hook('admin_theme_footer', 'image_annotation_admin_theme_footer');
add_plugin_hook('define_acl','image_annotation_define_acl');
add_plugin_hook('config_form', 'image_annotation_config_form');
add_plugin_hook('config', 'image_annotation_config');
add_plugin_hook('before_delete_item', 'image_annotation_before_delete_item');
add_filter('admin_navigation_main', 'image_annotation_admin_navigation');

require_once(IMAGE_ANNOTATION_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'permissions-manager.php');


/**
 * Creates the plugin model tables and sets initial options.
 * 
 * @return void
 */
function image_annotation_install()
{
    $db = get_db();
    $db->exec("CREATE TABLE `{$db->prefix}image_annotation_annotations` (
      `id` int(11) unsigned NOT NULL auto_increment,
      `user_id` int(11) unsigned NOT NULL,
      `file_id` int(11) unsigned NOT NULL,
      `top` mediumint(8) unsigned NOT NULL,
      `left` mediumint(8) unsigned NOT NULL,
      `width` mediumint(8) unsigned NOT NULL,
      `height` mediumint(8) unsigned NOT NULL,
      `text` text character set utf8 collate utf8_unicode_ci NOT NULL,
      `added` timestamp NOT NULL default CURRENT_TIMESTAMP,
      `modified` timestamp NOT NULL default '0000-00-00 00:00:00',
      `public` tinyint(4) NOT NULL default '1',
      PRIMARY KEY  (`id`),
      KEY `file_id` (`file_id`),
      KEY `user_id` (`user_id`),
      KEY `added` (`added`),
      KEY `modified` (`modified`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");
    
    // set default permission options
    ImageAnnotationPermissionsManager::getInstance()->setDefaultPermissionOptions();
}

/**
 * Removes the plugin model tables and deletes the plugin options.
 * 
 * @return void
 */
function image_annotation_uninstall()
{
    // drop model tables
    $db = get_db();
    $db->exec("DROP TABLE `{$db->prefix}image_annotation_annotations`");

    // delete default permission options
    ImageAnnotationPermissionsManager::getInstance()->deletePermissionOptions();
}

/**
 * Add the routes from the routes.ini file in the plugin directory
 * 
 * @return void
 **/
function image_annotation_routes($router) 
{
    $configIni = new Zend_Config_Ini(IMAGE_ANNOTATION_PLUGIN_DIR .
    DIRECTORY_SEPARATOR . 'routes.ini', 'routes');
    $router->addConfig($configIni);
}

/**
 * Add the admin navigation for the plugin.
 *
 * @return array
 */
function image_annotation_admin_navigation($tabs)
{
   if (get_acl()->checkUserPermission('ImageAnnotation_Annotations', 'deleteSelf') ||
       get_acl()->checkUserPermission('ImageAnnotation_Annotations', 'deleteAll')) {
       $tabs['Image Annotations'] = uri('image-annotation/moderate');    
   }
   return $tabs;
}

/**
 * Adds css and javascripts to the header of the public pages.
 * 
 * @param Zend_Controller_Request_Http $request
 * @return void
 */
function image_annotation_public_theme_header($request) 
{
    echo image_annotation_javascripts();
    echo image_annotation_css('image-annotation', 'public');
}

/**
 * Adds css and javascripts to the header of the admin pages.
 * 
 * @param Zend_Controller_Request_Http $request
 * @return void
 */
function image_annotation_admin_theme_header($request) 
{
    echo image_annotation_javascripts();
    echo image_annotation_css('image-annotation', 'admin');
}

/**
 * Returns the HTML code to embed the javascripts of plugin
 * 
 * @return string The HTML code to embed the javascripts of the plugin
 */
function image_annotation_javascripts()
{
    $html = '';
    $html .= js('jquery');
    $html .= '<script type="text/javascript">jQuery.noConflict();</script>';
    $html .= image_annotation_js('jquery-ui-1.7.1');
    $html .= image_annotation_js('jquery.annotate');
    $html .= image_annotation_js('livepipe');
    $html .= image_annotation_js('tabs');
    return $html;
}    


/**
 * Returns HTML code to embed a shared css file of the plugin
 *  Note: If the controller's module is that of another plugin, 
 *  then the js() and css() functions will not find this plugin's javascripts or css files.
 *  This is a bug. Until this bug is fixed we must use image_annotation_js and image_annotation_css
 * 
 * @param string $file The name of the css file without the extension.
 * @param string $themeType The type of theme ('public', 'admin', or 'shared')
 * @return string The HTML code to embed a shared css file of the plugin
 */
function image_annotation_css($file, $themeType='public')
{
    $cssURL = WEB_PLUGIN . '/ImageAnnotation/views/' . $themeType . '/css/' . $file . '.css';
    echo '<link rel="stylesheet" media="screen" href="' . $cssURL . '" />';
}

/**
 * Returns HTML code to embed a shared javascript file of the plugin
 *  Note: If the controller's module is that of another plugin, 
 *  then the js() and css() functions will not find this plugin's javascripts or css files.
 *  This is a bug. Until this bug is fixed we must use image_annotation_js and image_annotation_css
 * 
 * @param string $file The name of the javascript file without the extension. 
 * @return string The HTML code to embed a shared javascript file of the plugin
 */
function image_annotation_js($file) 
{
    $jsURL = WEB_PLUGIN . '/ImageAnnotation/views/shared/javascripts/' . $file . '.js';
    return '<script type="text/javascript" src="'. $jsURL  .'" charset="utf-8"></script>'."\n";
}

/**
 * Displays an image annotation gallery to the admin theme footer on the view item page.
 *
 * @param Zend_Controller_Request_Http $request 
 * @return void
 */
function image_annotation_admin_theme_footer($request)
{
    if ($request->getControllerName() == 'items' && $request->getActionName() == 'show') {
        echo image_annotation_display_annotated_image_gallery_for_item();
    }
}

/**
 * Returns the markup code for an image annotation gallery.
 *
 * @param Item $item The item, whose images are to be annotated
 * @param bool $isEditable Specify whether or not the file's images can be annotated at all.  If false, this overrides plugin permissions.
 * @return string
 */
function image_annotation_display_annotated_image_gallery_for_item($item=null, $isEditable=true)
{
    if ($item == null) {
        $item = get_current_item();
    }
    
    if (!$item->hasThumbnail()) {
        return '';
    }   
    
    $html = '';
	$html .= '<div class="annotated-images" id="annotated-images-' . $item->id . '">';
	$html .= '<ul class="annotated-images-thumbs" id="annotated-images-thumbs-' . $item->id . '">';
	while(loop_files_for_item($item)) {
        $file = get_current_file();
        if ($file->hasThumbnail()) {
			$html .= '<li><a href="#annotated-images-file-'.$file->id.'">';
			$html .= display_file($file, array('imageSize' => 'square_thumbnail', 'linkToFile' => false));
			$html .= '</a></li>';
        }
    }
	$html .= '</ul>';
	$html .= '<div class="annotated-images-fullsize" id="annotated-images-fullsize-' . $item->id . '">';
	while(loop_files_for_item($item)) {
        $file = get_current_file();
        if ($file->hasThumbnail()) {
			$html .= '<div class="annotated-images-file" id="annotated-images-file-' . $file->id .'">';
            $html .= image_annotation_display_annotated_image($file, $isEditable);
			$html .= '</div>';
        }
    }
	$html .= '</div>';
	$html .= '</div>';
	ob_start();
?>
<script type="text/javascript" charset="utf-8">
    Event.observe(window,'load',function() {
        $$('#annotated-images-thumbs-<?php echo $item->id; ?>').each(function(tabGroup){  
            new Control.Tabs(tabGroup, {
                beforeChange: function(oldTab) {
                jQuery(".image-annotate-edit-close").click();
            }
         });
       });
    });
</script>
<?php
    $html .= ob_get_contents();
    ob_end_clean();
    return $html;
}

/**
 * Returns the markup code for an image annotation
 *
 * @param File $imageFile The file to be annotated.
 * @param bool $isEditable Specify whether or not the image can be annotated at all.  If false, this overrides plugin permissions.
 * @param string $imageSize The size of the image. ('thumbnail', 'square_thumbnail', or 'fullsize') 
 * @return string
 */
function image_annotation_display_annotated_image($imageFile, $isEditable=false, $imageSize='fullsize')
{
    // check to make sure the user has permission to add annotation
    $isAddable = get_acl()->checkUserPermission('ImageAnnotation_Annotations', 'add'); //&& $isEditable;
        
    $html = '';        
    $html .= '<div class="annotated-image">';
    $html .= display_file($imageFile, array('imageSize' => $imageSize, 'linkToFile'=>false));
    $html .= '</div>';
    // specify the file annotations
    $useAjax = false;
    $imageId = $imageFile->id;
    $ajaxPath = CURRENT_BASE_URL . '/image-annotation/ajax/';
    $fileAnnotations = array( 
        'editable' => ($isEditable ? 'true': 'false'),
        'addButtonText' => 'Add Annotation',
        'addable' => ($isAddable ? 'true': 'false'),
        'imageId' => $imageId,
        'getUrl' => $ajaxPath . "get-annotation/file_id/" . $imageId . '/',  
        'saveUrl' => $ajaxPath . "save-annotation/file_id/" . $imageId . '/',  
        'deleteUrl' => $ajaxPath . "delete-annotation/file_id/" . $imageId . '/',  
        'useAjax' => ($useAjax ? 'true': 'false')   
    );
    ob_start();
?>
<script language="javascript">
      jQuery(window).load(function() {
            jQuery("img[src$='files/display/<?php echo $imageId; ?>/<?php echo $imageSize; ?>']").annotateImage(<?php echo json_encode($fileAnnotations); ?>);        
      });
</script>
<?php
    $html .= ob_get_contents();
    ob_end_clean();
    return $html;
}

/**
 * Prepares and renders the plugin's configuration form.
 * 
 * @return void
 */
function image_annotation_config_form()
{
    echo ImageAnnotationPermissionsManager::getInstance()->getPermissionsConfigTable();
}

/** 
 * Prepares and renders the plugin's configuration form.
 * 
 * @return void
 */
function image_annotation_config()
{
    ImageAnnotationPermissionsManager::getInstance()->savePermissionsConfigTable();
}

/**
 * Deletes annotations when an item is deleted
 * 
 * @param Item $item
 * @return void
 */
function image_annotation_before_delete_item($item)
{
    //delete all annotations for the deleted item
    $annotations = get_db()->getTable('ImageAnnotation_Annotations')->findByItem($item);
    foreach($annotations as $annotation) {
        $annotation->delete();
    }
}

/**
 * Loads the plugin resources and permissions for various roles based on plugin options
 *
 * @param Omeka_Acl $acl 
 * @return void
 */
function image_annotation_define_acl($acl)
{
    ImageAnnotationPermissionsManager::getInstance()->defineAcl($acl);
}

/**
 * Returns the current url with the sort url appended if it is not already there
 * 
 * @param string $sortByName
 * @return string The current url with the sort url appended if it is not already there
 */
function image_annotation_sort_uri($sortByName)
{
    return current_uri(array('sort' => $sortByName));
}
