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
add_plugin_hook('public_theme_header', 'image_annotation_javascripts');
add_plugin_hook('admin_theme_header', 'image_annotation_javascripts');
add_plugin_hook('admin_theme_footer', 'image_annotation_admin_theme_footer');
add_plugin_hook('define_acl','image_annotation_define_acl');
add_plugin_hook('config_form', 'image_annotation_config_form');
add_plugin_hook('config', 'image_annotation_config');
add_plugin_hook('before_delete_item', 'image_annotation_before_delete_item');
add_filter('admin_navigation_main', 'image_annotation_admin_navigation');


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
    
    // set default permissions
    $roleNames = image_annotation_get_acl_role_names();
    $resourceNames = image_annotation_get_acl_resource_names();
    foreach($resourceNames as $resourceName) {
        $permissions = image_annotation_get_acl_permissions($resourceName);
        foreach ($roleNames as $roleName) {
            foreach ($permissions as $permission) {
                $hasPermission = image_annotation_has_permission_by_default($resourceName, $permission, $roleName) ? '1' : '0';
                $optionName = image_annotation_get_acl_option_name($resourceName, $permission, $roleName);
                set_option($optionName, $hasPermission);
            }
        }
    }
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

    // delete options
    // set default permissions
    $roleNames = image_annotation_get_acl_role_names();
    $resourceNames = image_annotation_get_acl_resource_names();
    foreach($resourceNames as $resourceName) {
        $permissions = image_annotation_get_acl_permissions($resourceName);
        foreach ($roleNames as $roleName) {
            foreach ($permissions as $permission) {
                $optionName = image_annotation_get_acl_option_name($resourceName, $permission, $roleName);
                delete_option($optionName);
            }
        }
    }
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
 * Adds javascripts to the header of the page.
 * 
 * @param Zend_Controller_Request_Http $request
 * @return void
 */
function image_annotation_javascripts($request)
{
    if ($request->getControllerName() == 'items' && $request->getActionName() == 'show') {
        echo js('jquery');
        echo '<script type="text/javascript">jQuery.noConflict();</script>';
        echo js('jquery-ui-1.7.1');
        echo js('jquery.annotate');
        echo js('livepipe');
        echo js('tabs');
        echo '<link rel="stylesheet" media="screen" href="', css('annotation'), '" />';
    }
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
        echo image_annotation_display_annotated_image_gallery_for_item($item=null);
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
 * Returns the markup code for an image annotation gallery to the admin theme footer on the view item page.
 *
 * @param File $imageFile The file to be annotated.
 * @param bool $isEditable Specify whether or not the image can be annotated at all.  If false, this overrides plugin permissions.
 * @param string $imageSize The size of the image. ('thumbnail', 'square_thumbnail', or 'fullsize') 
 * @return string
 */
function image_annotation_display_annotated_image($imageFile, $isEditable=false, $imageSize='fullsize')
{
    // check to make sure the user has permission to add items
    $isAddable = get_acl()->checkUserPermission('ImageAnnotation_Annotations', 'add') && $isEditable;
    
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
    // start the permissions table
    echo '<table>';

    // build the table header
    echo '<tr>';
    echo '<th>Resource</th>';
    echo '<th>Permission</th>';
    $roleNames = image_annotation_get_acl_role_names();
    foreach($roleNames as $roleName) {
        echo '<th>' . html_escape($roleName) . '</th>';            
    }
    echo '<tr>';
    
    $resourceNames = image_annotation_get_acl_resource_names();
    foreach($resourceNames as $resourceName) {
        $permissions = image_annotation_get_acl_permissions($resourceName);
        $isNewResource = true;
        foreach($permissions as $permission) {
            // build a row to specify whether each role has a permission for a resource
            echo '<tr>';
            if ($isNewResource) {
                echo '<td>' . html_escape(image_annotation_get_acl_resource_text($resourceName)) . '</td>';
                $isNewResource = false;                
            } else {
                echo '<td></td>';
            }
            $permissionText = image_annotation_get_acl_permission_text($permission);
            echo '<td>' . html_escape($permissionText) . '</td>';
            foreach ($roleNames as $roleName) {
                $cbName = 'image_annotation_' . $permission . '[' . $roleName . ']';
                $cbId = image_annotation_get_acl_option_name($resourceName, $permission, $roleName);
                $isChecked = (get_option($cbId) == '1') ? true : false;
                $isDisabled = !image_annotation_get_acl_can_edit_default_permission($resourceName, $permission, $roleName);
                $cbParams = array('name'=>$cbName, 'id'=>$cbId);
                if ($isDisabled) {
                    $cbParams['disabled'] = 'disabled';
                }
                echo '<td>' . checkbox($cbParams, $isChecked, '1') . '</td>';
            }
        }
    }

    // close the permissions table
    echo '</table>';
}

/** 
 * Returns whether a plugin permission for a specific resource and roleName 
 * can be changed from its default.  If false, then no one, not even the super user,
 * should change the permission for the roleName.
 * 
 * @param string $resourceName The name of the resource to be tested
 * @param string $permission The permission to be tested
 * @param string $roleName The role name to be tested
 * @return boolean Whether or not the whether a plugin permission for a specific resource and roleName 
 * can be changed from its default.
 */
function image_annotation_get_acl_can_edit_default_permission($resourceName, $permission, $roleName)
{
    switch($resourceName) {
        case 'ImageAnnotation_Annotations':
            switch($roleName) {
                case 'anyone':
                   switch($permission) {
                       case 'add':
                       case 'editSelf':
                       case 'editAll':
                       case 'deleteSelf':
                       case 'deleteAll':
                           return false;
                       break;
                   }
               break;
           }
        break;
    }
   
    return true;
}

/** 
 * Prepares and renders the plugin's configuration form.
 * 
 * @return void
 */
function image_annotation_config()
{
    $roleNames = image_annotation_get_acl_role_names();
    $resourceNames = image_annotation_get_acl_resource_names();
    foreach($resourceNames as $resourceName) {
        $permissions = image_annotation_get_acl_permissions($resourceName);
        foreach ($roleNames as $roleName) {
            foreach ($permissions as $permission) {
                $cbName = 'image_annotation_' . $permission;
                $optionName = image_annotation_get_acl_option_name($resourceName, $permission, $roleName);
                if ($_POST[$cbName][$roleName] == '1') {
                    set_option($optionName, '1');    
                } else {
                    set_option($optionName, '0');    
                }
            }
        }
    }
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
    // load the plugin resources
    $resourceNames = image_annotation_get_acl_resource_names();
    $resources = array();
    foreach($resourceNames as $resourceName) {
        $resources[$resourceName] = image_annotation_get_acl_permissions($resourceName);
    }
    $acl->loadResourceList($resources);
    
    // load the permissions based on the plugin options
    $roleNames = image_annotation_get_acl_role_names($acl);
    foreach($resources as $resourceName => $permissions) {
        $allowList = array();
        foreach ($roleNames as $roleName) {
            $permissionsForRole = array();
            foreach ($permissions as $permission) {
                $optionName = image_annotation_get_acl_option_name($resourceName, $permission, $roleName);
                if (get_option($optionName) == '1') {
                    $permissionsForRole[] = $permission;
                }  
            }
            // convert 'anyone' role name to null, so that the resource applies to anyone
            if ($roleName == 'anyone') {
                $roleName = null;
            }
            if (count($permissionsForRole) > 0) {
                $allowList[] = array($roleName, $resourceName, $permissionsForRole);                
            }
        }
        $acl->loadAllowList($allowList);
    }
}


/**
 * Returns the name of the option that specifies whether a role has permission to a resource
 * 
 * @param string $resourceName The name of the resource
 * @param string $permission The name of the permission
 * @param string $roleName The name of the role
 * @return string The name of the option that specifies whether a role has a permission to a resource
 */
function image_annotation_get_acl_option_name($resourceName, $permission, $roleName)
{
    $pluginPrefix = 'image_annotation';
    return $pluginPrefix . '_' . strtolower($resourceName) . '_' . $permission . '_' . $roleName;
}

/**
 * Returns whether or not a role has permission to a resource by default, prior to user specified options.
 * 
 * @param string $resourceName The name of the resource
 * @param string $permission The name of the permission
 * @param string $roleName The name of the role
 * @return boolean Whether or not a role has permission to a resource by default, prior to user specified options
 */
function image_annotation_has_permission_by_default($resourceName, $permission, $roleName) 
{
    switch($resourceName) {
        case 'ImageAnnotation_Annotations':
            switch($roleName) {
                case 'super':
                case 'admin':
                    return true;
                break;
                
                case 'contributor':
                case 'guest':
                    switch($permission) {
                        case 'add':
                        case 'editSelf':
                        case 'deleteSelf':
                        case 'showPublic':
                            return true;
                        break;
                    }
                break;
                
                case 'researcher':
                    switch($permission) {
                        case 'add':
                        case 'editSelf':
                        case 'deleteSelf':
                        case 'showPublic':
                        case 'showNotPublic':
                            return true;
                        break;
                    }
                break;
                
                default:
                    // permissions for every other role
                    switch($permission) {
                        case 'showPublic':
                            return true;
                        break;
                    }
                break;
            }
        break;
    }
    return false;
}

/**
 * Returns the resource text to display on the configuration page for a resourceName
 * 
 * @param string $resourceName The name of the resource
 * @return string The resource text to display on the configuration page for a resourceName
 */
function image_annotation_get_acl_resource_text($resourceName)
{
    $resourceText = '';
    switch($resourceName) {
        case 'ImageAnnotation_Annotations';
            $resourceText = 'Image Annotations';
        break;
    }
    return $resourceText;
}

/**
 * Returns the permission text to display on the configuration page for a permission
 * 
 * @param string $permission The name of the permission
 * @return string The permission text to display on the configuration page for a permission
 */
function image_annotation_get_acl_permission_text($permission)
{
    $permissionText = '';
    switch($permission) {
        case 'add':
            $permissionText = 'Add Their Own Annotations';
        break;
    
        case 'editSelf':
            $permissionText = 'Edit Their Own Annotations';
        break;
    
        case 'editAll':
            $permissionText = "Edit The Annotations Of Other Users";
        break;
    
        case 'deleteSelf':
            $permissionText = "Delete Their Own Annotations";
        break;
    
        case 'deleteAll':
            $permissionText = "Delete The Annotations Of Other Users";
        break;
    
        case 'showPublic':
            $permissionText = "View Public Annotations";
        break;
    
        case 'showNotPublic':
            $permissionText = "View Private Annotations";
        break;
    }
    return $permissionText;
}

/**
 * Returns an array of all of the resource names for the plugin.
 * 
 * @return array The resource names for the plugin
 */
function image_annotation_get_acl_resource_names()
{
    return array('ImageAnnotation_Annotations');
}

/**
 * Returns an array of all of the role names for the plugin. 
 * For the purposes of saving permissions as options, 
 * it adds an 'anyone' role for any user, although this role is not saved in the database.
 * 
 * @return array The role names for the plugin
 */
function image_annotation_get_acl_role_names($acl=null) 
{
    if (!$acl) {
        $acl = get_acl();
    }
    
    // return all role names except the super user
    $roleNames = array_diff($acl->getRoleNames(), array('super'));
    
    // add the role name 'anyone' for anyone on the site
    $roleNames[] = 'anyone';
    
    // sort the role names alphabetically
    sort($roleNames);
    
    return $roleNames;
}

/**
 * Returns an array of permissions for a plugin resource.
 * 
 * @param string $resourceName
 * @return array The permission for the plugin resource
 */
function image_annotation_get_acl_permissions($resourceName)
{
    $permissions = array();
    switch($resourceName) {
        case 'ImageAnnotation_Annotations':
        default:
            $permissions = array('add', 'editSelf', 'editAll', 'deleteSelf', 'deleteAll', 'showPublic', 'showNotPublic');
        break;
    }

    return $permissions;
}

function image_annotation_sort_uri($sortByName)
{
    return current_uri(array('sort' => $sortByName));
}
