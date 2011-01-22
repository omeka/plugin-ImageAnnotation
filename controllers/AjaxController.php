<?php
class ImageAnnotation_AjaxController extends Omeka_Controller_Action
{        
    public function getAnnotationAction() 
    {
        // get the id of the file with annotations
        $fileId = (int)$this->_getParam('file_id');
  
        // make sure the current user can view public annotations
        $annotationsAsArrays = array();
        if (ImageAnnotationAnnotation::hasPermission('showPublic')) {
            // check to see if the current user can view non-public annotations
            $onlyPublicAnnotations = !ImageAnnotationAnnotation::hasPermission('showNotPublic');

            // get the annotations
            $annotations = get_db()->getTable('ImageAnnotationAnnotation')->findByFile($fileId, $onlyPublicAnnotations);
        
            // convert the annotations into arrays
            foreach($annotations as $annotation) {
                $annotationAsArray = $annotation->toArray();

                // specify whether the current user has permission to edit the annotation
                $annotationAsArray['editable'] = ImageAnnotationAnnotation::hasPermission('editAll') 
                    || (ImageAnnotationAnnotation::hasPermission('editSelf') && current_user()->id == $annotationAsArray['user_id']);
                
                // specify whether the current user has permission to delete the annotation
                $annotationAsArray['deletable'] = ImageAnnotationAnnotation::hasPermission('deleteAll') || 
                                                (ImageAnnotationAnnotation::hasPermission('deleteSelf') && current_user()->id == $annotationAsArray['user_id']);

                $annotationsAsArrays[] = $annotationAsArray;
            }
        }

        // send the annotation data
        $this->_helper->json($annotationsAsArrays);
    }
    
    public function deleteAnnotationAction()
    {
        // get the annotation to delete
        $annotationId = $this->_getParam('id');
        $annotation = get_db()->getTable('ImageAnnotationAnnotation')->find($annotationId);      
        
        // make sure the user has permission to delete the annotation
        if (ImageAnnotationAnnotation::hasPermission('deleteAll') ||
           (ImageAnnotationAnnotation::hasPermission('deleteSelf') && current_user()->id == $annotation->user_id)) {
               // delete the annotation
               $annotation->delete();            
        }
        
        $this->_helper->viewRenderer->setNoRender();
    }
    
    public function saveAnnotationAction()
    {        
        // get the id of the annotation to save                
        $annotationId = $this->_getParam('id');
        $annotation = null;
        if ($annotationId == 'new') {
            // make sure the current user can add a new annotation
            if (ImageAnnotationAnnotation::hasPermission('add')) {
                //create a new annotation
                $annotation = new ImageAnnotationAnnotation;
            }
        } else {
            //get the existing annotation
            $annotation = get_db()->getTable('ImageAnnotationAnnotation')->find($annotationId);      
        
            // make sure the current user can edit the annotation
            if (!(ImageAnnotationAnnotation::hasPermission('editAll') || 
               (ImageAnnotationAnnotation::hasPermission('editSelf') && current_user()->id == $annotation->user_id))) {
                   $annotation = null;
            }
        }
        
        if ($annotation) {
            // configure the annotation
            $a['text'] = $this->_getParam('text');
            $a['top'] = (int)$this->_getParam('top');
            $a['left'] = (int)$this->_getParam('left');
            $a['width'] = (int)$this->_getParam('width');
            $a['height'] = (int)$this->_getParam('height');
            $a['file_id'] = (int)$this->_getParam('file_id');
            $a['public'] = true;
            $a['user_id'] = current_user()->id;
            $annotation->setArray($a);
            
            // save the annotation
            $annotation->save();
            
            // set the response data with the annotation id
            $responseData = array('annotation_id' => $annotation->id);
        } else {
            $responseData = array();
        }
        
        $this->_helper->json(responseData);
    }
}
