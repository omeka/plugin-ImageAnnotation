<?php
/**
 * @version $Id$
 * @copyright Center for History and New Media, 2007-2008
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Omeka
 * @subpackage ImageAnnotation
 **/
 
add_plugin_hook('install', 'image_annotation_install');
add_plugin_hook('uninstall', 'image_annotation_uninstall');
add_plugin_hook('admin_theme_header', 'image_annotation_javascripts');
add_plugin_hook('admin_theme_footer', 'image_annotation_admin_theme_footer');
add_plugin_hook('define_acl','image_annotation_define_acl');

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
}

function image_annotation_uninstall()
{
    $db = get_db();
    $db->exec("DROP TABLE `{$db->prefix}image_annotation_annotations`");
}

function image_annotation_define_acl($acl)
{
    $resourceName = 'ImageAnnotation_Annotations';
    
    $resources = array(
        $resourceName => array('add', 'editSelf', 'editAll', 'deleteSelf', 'deleteAll', 'showPublic', 'showNotPublic')
    );
    $acl->loadResourceList($resources);

    $allowList = array(
        // anyone can view public annotations
        array(null, $resourceName, 'showPublic'),
        
        // researchers can view annotations that are not yet public
        array(array('researcher', 'contributor', 'admin'), $resourceName, array('showNotPublic')),
        
        // contributors and admins can add. edit, and delete their annotations
        array(array('contributor', 'admin'), $resourceName, array('add', 'editSelf', 'deleteSelf')),
        
        // admins can edit and delete other user's annotations
        array('admin', $resourceName, array('editAll', 'deleteAll')),
    );
    if ($acl->hasRole('guest')) {
        // if Guest Login is installed, guests can add. edit, and delete their annotations
        $allowList[] = array('guest', $resourceName, array('add', 'editSelf', 'deleteSelf'));
    }
    $acl->loadAllowList($allowList);
}

function image_annotation_javascripts($request)
{
    if ($request->getControllerName() == 'items' && $request->getActionName() == 'show') {
        echo js('jquery');
        echo '<script type="text/javascript">jQuery.noConflict();</script>';
        echo js('jquery-ui-1.7.1');
        echo js('jquery.annotate');
        echo '<link rel="stylesheet" media="screen" href="', css('annotation'), '" />';
    }
}    

function image_annotation_admin_theme_footer($request)
{
    if ($request->getControllerName() == 'items' && $request->getActionName() == 'show') {
        echo image_annotation_display_annotated_image_gallery_for_item($item=null);
    }
}

function image_annotation_display_annotated_image_gallery_for_item($item=null)
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
            $html .= image_annotation_display_annotated_image($file, true);
			$html .= '</div>';
        }
    }
	$html .= '</div>';
	$html .= '</div>';
	ob_start();
?>
<script type="text/javascript" charset="utf-8">
    Event.observe(window,'load',function(){
    $$('#annotated-images-thumbs-<?php echo $item->id; ?>').each(function(tab_group){  
         new Control.Tabs(tab_group);  
     });
    });
</script>
<?php
    $html .= ob_get_contents();
    ob_end_clean();
    return $html;
}

function image_annotation_display_annotated_image($imageFile, $isEditable=false, $imageSize='fullsize')
{
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