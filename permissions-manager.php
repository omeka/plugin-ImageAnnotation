<?php

class ImageAnnotationPermissionsManager extends ImageAnnotationAbstractPermissionsManager
{
    static protected $pluginName = 'ImageAnnotation';
    
    static public function getInstance()
    {
        return ImageAnnotationAbstractPermissionsManager::getInstance(self::$pluginName);
    }
    
    /** 
     * Initializes the plugin permissions manager. 
     * 
     * @return void
     */
    protected function init() 
    {              
        // set the display texts for each of the resources
        $this->setResourceTexts(array(
            'ImageAnnotation_Annotations' => 'Image Annotations'
        ));
        
        // set the display texts for each permission of each resource
        $this->setPermissionsTexts(array(
            'ImageAnnotation_Annotations' => array(
                'add' => 'Add Their Own Annotations',
                'editSelf' => 'Edit Their Own Annotations',
                'editAll' => 'Edit The Annotations Of Other Users',
                'deleteSelf' => 'Delete Their Own Annotations',
                'deleteAll' => 'Delete The Annotations Of Other Users',    
                'showPublic' => 'View Public Annotations',
                'showNotPublic' =>  'View Private Annotations'
            )
        ));
        
        // set whether the default permissions for a role can be editted
         $this->setDefaultPermissionIsEditableForRole('anyone', 
                                                      'ImageAnnotation_Annotations', 
                                                      array('add', 
                                                            'editSelf', 
                                                            'editAll', 
                                                            'deleteSelf', 
                                                            'deleteAll'), 
                                                      false);        
    
        // set the default permissions for each role
        $this->setRoleHasPermissionByDefault(array('super', 'admin'), 
                                          'ImageAnnotation_Annotations',
                                          $this->getPermissionsForResource('ImageAnnotation_Annotations'),
                                          true);
        $this->setRoleHasPermissionByDefault(array('contributor', 'guest'), 
                                          'ImageAnnotation_Annotations',
                                          array('add', 'editSelf', 'deleteSelf', 'showPublic'),
                                          true);
        $this->setRoleHasPermissionByDefault(array('researcher'), 
                                          'ImageAnnotation_Annotations',
                                          array('add', 'editSelf', 'deleteSelf', 'showPublic', 'showNotPublic'),
                                          true);
        $this->setRoleHasPermissionByDefault(array('anyone'), 
                                          'ImageAnnotation_Annotations',
                                          array('showPublic'),
                                          true);
    }
}

class ImageAnnotationAbstractPermissionsManager 
{
    static private $pInstances;
    
    protected $resourceNamesToTexts;
    protected $resourceNamesToPermissionsToTexts;
    protected $canEditDefaultPermission;
    protected $hasPermissionByDefault;
    protected $pluginPrefix;
        
    /** 
     * Returns the singleton PluginPermissionsManager for the plugin name.
     * Assumes that the name of the subclass of PluginPermissionsManger 
     * is named: camelcased version of $pluginName plus 'PermissionsManager' 
     * 
     * @return PluginPermissionsManager The singleton PluginPermissionsManager 
     */
    public static function getInstance($pluginName)
    {
        $pluginName = trim($pluginName);
        
        if (empty($pluginName)) {
            return null;
        }
        
        if (!self::$pInstances[$pluginName]) {
            
            $inflector = new Inflector;
            $pluginManagerClassName = $inflector->camelize($pluginName) . 'PermissionsManager';
            $pManager = new $pluginManagerClassName;
            $pManager->initPluginPrefix($pluginName);
            $pManager->init();
            
            self::$pInstances[$pluginName] = $pManager;
        }
        return self::$pInstances[$pluginName];
    }

    public function initPluginPrefix($pluginName) 
    {
        $inflector = new Inflector;
        $this->pluginPrefix = $inflector->tableize($pluginName);
    }

    /** 
     * Returns the permissions for a resource.
     * 
     * @param string $resourceName The name of the resource
     * @return array An array of strings containing the permission names
     */
    public function getPermissionsForResource($resourceName)
    {        
        $permissionsToTexts = $this->resourceNamesToPermissionsToTexts[$resourceName];
        return array_keys($permissionsToTexts);
    }
    
    /** 
     * Sets the resource names and their corresponding display texts
     * 
     * @return void
     */
    public function setResourceTexts($resourceNamesToTexts)
    {
        $this->resourceNamesToTexts = $resourceNamesToTexts;
    }
    
    /** 
     * For each resource, sets the permission names and their corresponding display texts
     * 
     * @return void
     */
    public function setPermissionsTexts($resourceNamesToPermissionsToTexts) 
    {
        $this->resourceNamesToPermissionsToTexts = $resourceNamesToPermissionsToTexts;
    }
    
    /**
     * Loads the plugin resources and permissions for various roles based on plugin options
     *
     * @param Omeka_Acl $acl 
     * @return void
     */
    public function defineAcl($acl)
    {
        // load the plugin resources
        $resourceNames = $this->getResourceNames();
        $resources = array();
        foreach($resourceNames as $resourceName) {
            $resources[$resourceName] = $this->getPermissionsForResource($resourceName);
        }
        $acl->loadResourceList($resources);
            
        // load the permissions based on the plugin options
        $roleNames = $this->getRoleNames($acl);
        foreach($resources as $resourceName => $permissions) {
            $allowList = array();
            foreach ($roleNames as $roleName) {
                $permissionsForRole = array();
                foreach ($permissions as $permission) {
                    $optionName = $this->getPermissionOptionName($resourceName, $permission, $roleName);
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
     * Returns whether a plugin permission for a specific resource and roleName 
     * can be changed from its default.  If false, then no one, not even the super user,
     * should change the permission for the roleName.  If it is not specified whether the
     * the a default permission can be editted, then it returns true.
     * 
     * @param string $roleName The role name to be tested
     * @param string $resourceName The name of the resource to be tested
     * @param string $permission The permission to be tested
     * @return boolean Whether or not the whether a plugin permission for a specific resource and roleName 
     * can be changed from its default.
     */
    public function getDefaultPermissionIsEditableForRole($roleName, $resourceName, $permission)
    {
        return (!isset($this->canEditDefaultPermission[$roleName][$resourceName][$permission]) || 
                $this->canEditDefaultPermission[$roleName][$resourceName][$permission]);
    }
    
    /** 
     * Sets whether a plugin permission for a specific resource and roleName 
     * can be changed from its default.  If false, then no one, not even the super user,
     * should change the permission for the roleName.  If it is not specified whether the
     * the a default permission can be editted, then it returns true.
     * 
     * @param string|array $roleName The role name to be tested
     * @param string|array $resourceName The name of the resource to be tested
     * @param string|array $permission The permission to be tested
     * @param boolean $canEdit Whether or not the whether a plugin permission for a specific resource and roleName 
     * can be changed from its default.
     * @return void
     */    
    public function setDefaultPermissionIsEditableForRole($roleName, $resourceName, $permission, $canEdit) 
    {
        $roleNames = $this->varToArray($roleName);
        $resourceNames = $this->varToArray($resourceName);
        $permissions = $this->varToArray($permission);
        
        foreach($roleNames as $roleName) {
            foreach($resourceNames as $resourceName) {
                foreach($permissions as $permission) {
                    $this->canEditDefaultPermission[$roleName][$resourceName][$permission] = $canEdit;                    
                }
            }
        }
    }

    /**
     * Returns whether or not a role has permission to a resource by default, 
     * prior to user specified options. If the default permission is not specified, then
     * it returns false.
     *
     * @param string $roleName The name of the role 
     * @param string $resourceName The name of the resource
     * @param string $permission The name of the permission
     * @return boolean Whether or not a role has permission to a resource by default, 
     * prior to user specified options.
     */
    public function getRoleHasPermissionByDefault($roleName, $resourceName, $permission) 
    {
        return (isset($this->hasPermissionByDefault[$roleName][$resourceName][$permission]) && 
                $this->hasPermissionByDefault[$roleName][$resourceName][$permission]);
    }
    
    /**
     * Sets whether or not a role has permission to a resource by default, 
     * prior to user specified options. If the default permission is not specified, then
     * it returns false.
     *
     * @param string $roleName The name of the role 
     * @param string $resourceName The name of the resource
     * @param string $permission The name of the permission
     * @param boolean $hasByDefault Whether or not a role has permission to a resource by default, 
     * prior to user specified options.
     */
    public function setRoleHasPermissionByDefault($roleName, $resourceName, $permission, $hasByDefault)
    {
        $roleNames = $this->varToArray($roleName);
        $resourceNames = $this->varToArray($resourceName);
        $permissions = $this->varToArray($permission);
        
        foreach($roleNames as $roleName) {
            foreach($resourceNames as $resourceName) {
                foreach($permissions as $permission) {
                    $this->hasPermissionByDefault[$roleName][$resourceName][$permission] = $hasByDefault;                    
                }
            }
        }
    }

    /**
     * Returns the resource text to display on the configuration page for a resourceName
     * 
     * @param string $resourceName The name of the resource
     * @return string The resource text to display on the configuration page for a resourceName
     */
    public function getResourceText($resourceName)
    {
        if (array_key_exists($resourceName, $this->resourceNamesToTexts)) {
           return $this->resourceNamesToTexts[$resourceName];
        }
        return '';
    }

    /**
     * Returns the permission text to display on the configuration page for a permission
     * 
     * @param string $permission The name of the permission
     * @return string The permission text to display on the configuration page for a permission
     */
    public function getPermissionText($resourceName, $permission)
    {
        if (array_key_exists($resourceName, $this->resourceNamesToPermissionsToTexts) && 
            array_key_exists($permission, $this->resourceNamesToPermissionsToTexts[$resourceName]))
        {
            return $this->resourceNamesToPermissionsToTexts[$resourceName][$permission];        
        }
        return '';
    }

    /**
     * Returns an array of all of the resource names for the plugin.
     * 
     * @return array The resource names for the plugin
     */
    public function getResourceNames()
    {
        return array_keys($this->resourceNamesToTexts);
    }

    /**
     * Returns an array of all of the role names for the plugin. 
     * For the purposes of saving permissions as options, 
     * it adds an 'anyone' role for any user, although this role is not saved in the database.
     * 
     * @param Omeka_Acl $acl 
     * @return array The role names for the plugin
     */
    public function getRoleNames($acl=null) 
    {
        if (!$acl) {
            $acl = get_acl();
        }
        
        // HACK: if the GuestLogin plugin is active, and the
        // 'guest' role has not been added by the GuestLogin plugin, add it.
        // Remove this, once we load plugins in an order, where the dependencies are loaded first
        if (get_plugin_broker()->isActive('GuestLogin') && !$acl->hasRole('guest')) {
            $acl->addRole(new Zend_Acl_Role('guest'));
        }
            
        // return all role names except the super user and 'anyone'
        $roleNames = array_diff($acl->getRoleNames(), array('super', 'anyone'));
    
        // add the role name 'anyone' for anyone on the site
        $roleNames[] = 'anyone';
    
        // sort the role names alphabetically
        sort($roleNames);
    
        return $roleNames;
    }

    /**
     * Sets the default permissions as options in the database.
     * 
     * @return void
     */
    public function setDefaultPermissionOptions()
    {
        $roleNames = $this->getRoleNames();
        $resourceNames = $this->getResourceNames();
        foreach($resourceNames as $resourceName) {
            $permissions = $this->getPermissionsForResource($resourceName);
            foreach ($roleNames as $roleName) {
                foreach ($permissions as $permission) {
                    $hasPermission = $this->getRoleHasPermissionByDefault($roleName, $resourceName, $permission) ? '1' : '0';
                    $optionName = $this->getPermissionOptionName($resourceName, $permission, $roleName);
                    set_option($optionName, $hasPermission);
                }
            }
        }
    }

    /**
     * Deletes all the permission options from the database.
     * 
     * @return void
     */
    public function deletePermissionOptions()
    {
        $roleNames = $this->getRoleNames();
        $resourceNames = $this->getResourceNames();
        foreach($resourceNames as $resourceName) {
            $permissions = $this->getPermissionsForResource($resourceName);
            foreach ($roleNames as $roleName) {
                foreach ($permissions as $permission) {
                    $optionName = $this->getPermissionOptionName($resourceName, $permission, $roleName);
                    delete_option($optionName);
                }
            }
        }
    }
    
    /**
     * Returns the HTML code for the permissions configuration table to put on the plugin configuration page.
     * 
     * @return string The HTML code for the permissions configuration table.
     */
    public function getPermissionsConfigTable()
    {
        $html = '';
        
        // start the permissions table
        $html .= '<table>';

        // build the table header
        $html .=  '<tr>';
        $html .=  '<th>Resource</th>';
        $html .=  '<th>Permission</th>';
        $roleNames = $this->getRoleNames();
        foreach($roleNames as $roleName) {
            $html .=  '<th>' . html_escape($roleName) . '</th>';            
        }
        $html .=  '<tr>';

        $resourceNames = $this->getResourceNames();
        foreach($resourceNames as $resourceName) {
            $permissions = $this->getPermissionsForResource($resourceName);
            $isNewResource = true;
            foreach($permissions as $permission) {
                // build a row to specify whether each role has a permission for a resource
                $html .=  '<tr>';
                if ($isNewResource) {
                    $html .=  '<td>' . html_escape($this->getResourceText($resourceName)) . '</td>';
                    $isNewResource = false;                
                } else {
                    $html .=  '<td></td>';
                }
                $permissionText = $this->getPermissionText($resourceName, $permission);
                $html .=  '<td>' . html_escape($permissionText) . '</td>';
                foreach ($roleNames as $roleName) {
                    $cbName = $this->pluginPrefix . '_' . $permission . '[' . $roleName . ']';
                    $cbId = $this->getPermissionOptionName($resourceName, $permission, $roleName);
                    $isChecked = (get_option($cbId) == '1') ? true : false;
                    $isDisabled = !$this->getDefaultPermissionIsEditableForRole($roleName, $resourceName, $permission);
                    $cbParams = array('name'=>$cbName, 'id'=>$cbId);
                    if ($isDisabled) {
                        $cbParams['disabled'] = 'disabled';
                    }
                    $html .=  '<td>' . checkbox($cbParams, $isChecked, '1') . '</td>';
                }
            }
        }

        // close the permissions table
        $html .=  '</table>';
        return $html;
    }
    
    /**
     * Saves the permissions in configuration table on the plugin configuration page
     * as options in the database.
     * 
     * @return void
     */
    public function savePermissionsConfigTable()
    {
        $roleNames = $this->getRoleNames();
        $resourceNames = $this->getResourceNames();
        foreach($resourceNames as $resourceName) {
            $permissions = $this->getPermissionsForResource($resourceName);
            foreach ($roleNames as $roleName) {
                foreach ($permissions as $permission) {
                    $cbName = $this->pluginPrefix . '_' . $permission;
                    $optionName = $this->getPermissionOptionName($resourceName, $permission, $roleName);
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
     * Returns the name of the option that specifies whether a role has permission to a resource
     * 
     * @param string $resourceName The name of the resource
     * @param string $permission The name of the permission
     * @param string $roleName The name of the role
     * @return string The name of the option that specifies whether a role has a permission to a resource
     */
    public function getPermissionOptionName($resourceName, $permission, $roleName)
    {
        return $this->pluginPrefix . '_' . strtolower($resourceName) . '_' . $permission . '_' . $roleName;
    }
    
    
    /**
     * Returns whether the current user has permission to access a resource
     * 
     * @param string $resourceName The name of the resource
     * @param string $permission The name of the permission
     * @return bool Whether the current user has permission to access a resource
     */
    public function currentUserHasPermission($resourceName, $permission) 
    {
        return get_acl()->checkUserPermission($resourceName, $permission);    
    }
    
    /**
     * If the variable is not an array,
     * then it returns an an array that contains that variable, 
     * else it returns the variable. 
     *
     * @param mixed $v 
     * @return array
     */
    private function varToArray($v) 
    {
        if (!is_array($v)) {
            $a = array($v);
        } else {
            $a = $v;
        }
        return $a;
    }
}