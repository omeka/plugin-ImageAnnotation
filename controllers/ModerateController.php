<?php
class ImageAnnotation_ModerateController extends Omeka_Controller_Action
{
    public function init()
    {
        $this->_modelClass = 'ImageAnnotation_Annotation';
        $this->_browseRecordsPerPage = 10;
    }
    
    public function browseAction()
    {
        // only show the annotations that the user has permission to edit
        if (!ImageAnnotation_Annotation::hasPermission('editAll')) {
            if (ImageAnnotation_Annotation::hasPermission('editSelf')) {
                $this->_setParam('by_user', $this->getCurrentUser()->id);                            
            } else {
                // the user does not have permission to edit any annotations,
                // so restrict the annotations to those owned by those from user_id = 0,
                // and no user has user_id = 0, so no annotations will be returned 
                $this->_setParam('by_user', 0);
            }
        }
        parent::browseAction();
    }
    
    public function deleteAction()
    {        
        // get the annotation to delete
        $annotationId = $this->_getParam('id');
        $annotation = get_db()->getTable('ImageAnnotation_Annotation')->find($annotationId);      
        
        // make sure the user has permission to delete the annotation
        if (ImageAnnotation_Annotation::hasPermission('deleteAll') ||
           (ImageAnnotation_Annotation::hasPermission('deleteSelf') && current_user()->id == $annotation->user_id)) {
               // delete the annotation
               $annotation->delete();            
        }
        $this->redirect->goto('browse');
    }
    
    // /**
    //  * @param array Set of options (currently empty)
    //  * @return array Keyed array containing the following: 'items' = all the Item
    //  * objects returned by the search, 'page' = the page # of the results, 
    //  * 'per_page' = the number of items displayed for the given results,
    //  * 'total_results' = the total # of results returned by the search query,
    //  * 'total_records' = the total # of records in the database (equivalent to # of
    //  * records that would be returned by a blank search query).
    //  **/
    // public function search($className, $options = array())
    // {   
    //     //need to use Inflector
    //     $pluralClassName = Inflector::pluralize($className);
    //     
    //     $request = $this->getRequest();
    //     $controller = $this->getActionController();
    //     $table = Omeka_Context::getInstance()->getDb()->getTable($className);
    //     
    //     // Page should be passed as the 'page' parameter or it defaults to 1
    //     $resultPage = $request->get('page') or $resultPage = 1;
    //     
    //     $perms  = array();
    //     $filter = array();
    //     $order  = array();
    //     
    //     //Show only public items
    //     if ($request->get('public')) {
    //         $perms['public'] = true;
    //     }
    //     
    //     //Here we add some filtering for the request    
    //     try {
    //         
    //         // User-specific record browsing
    //         if ($userToView = $request->get('user')) {
    //                     
    //             // Must be logged in to view items specific to certain users
    //             if (!$controller->isAllowed('browse', $pluralClassName)) {
    //                 throw new Exception( 'May not browse by specific users.' );
    //             }
    //             
    //             if (is_numeric($userToView)) {
    //                 $filter['user'] = $userToView;
    //             }
    //         }
    //         
    //         if ($search = $request->get('search')) {
    //             $filter['search'] = $search;
    //         }
    //         
    //         if ($range = $request->get('range')) {
    //             $filter['range'] = $range;
    //         }
    //         
    //     } catch (Exception $e) {
    //         $controller->flash($e->getMessage());
    //     }
    //     
    //     $params = array_merge($perms, $filter, $order);
    //     
    //     //Get the item count after other filtering has been applied, which is the total number of items found
    //     $totalRecords = $table->count($params);
    //     Zend_Registry::set('total_results', $totalResults);                
    //     
    //     //Permissions are checked automatically at the SQL level
    //     $totalRecords = $table->count();
    //     Zend_Registry::set('total_records', $totalRecords);
    //     
    //     // Now that we are retrieving the actual result set, limit and offset are applied.        
    //     $recordsPerPage = $this->getRecordsPerPage();
    //     
    //     //Retrieve the records themselves
    //     $records = $itemTable->findBy($params, $recordsPerPage, $resultPage);
    //     
    //     return array(
    //         'records'=>$records, 
    //         'total_results'=>$totalResults, 
    //         'total_records' => $totalRecords, 
    //         'page' => $resultPage, 
    //         'per_page' => $recordsPerPage);
    // }
    // 
    // /**
    //  * Retrieve the number of records to display on any given browse page.
    //  * This can be modified as a query parameter provided that a user is actually logged in.
    //  *
    //  * @return integer
    //  **/
    // public function getRecordsPerPage($pluralClassName)
    // {
    //     //Retrieve the number from the options table
    //     $options = Omeka_Context::getInstance()->getOptions();
    //     
    //     if (is_admin_theme()) {
    //         $perPage = (int) $options['per_page_admin'];
    //     } else {
    //         $perPage = (int) $options['per_page_public'];
    //     }
    //     
    //     // If users are allowed to modify the # of records displayed per page, 
    //     // then they can pass the 'per_page' query parameter to change that.        
    //     if ($this->_actionController->isAllowed('modifyPerPage', $pluralClassName) && ($queryPerPage = $this->getRequest()->get('per_page'))) {
    //         $perPage = $queryPerPage;
    //     }     
    //     
    //     // We can never show less than one item per page (bombs out).
    //     if ($perPage < 1) {
    //         $perPage = 1;
    //     }
    //     
    //     return $perPage;
    // }
}