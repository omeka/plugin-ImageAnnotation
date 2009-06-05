<?php
class ImageAnnotation_AjaxController extends Omeka_Controller_Action
{
    public function getAnnotationAction() 
    {
        $fileId = (int)$this->_getParam('file_id');
                
        // get the annotations
        $annotations = get_db()->getTable('ImageAnnotation_Annotation')->findByFile($fileId);
                        
        // convert the annotations into arrays
        $annotationsAsArrays = array();
        foreach($annotations as $annotation) {
            $annotationAsArray = $annotation->toArray();
            // make the annotation editable
            $annotationAsArray['editable'] = true;
            $annotationsAsArrays[] = $annotationAsArray;
        }
        
        // send the annotation data to the view
        $this->view->annotations = $annotationsAsArrays;
    }
    
    public function deleteAnnotationAction()
    {
        $annotationId = $this->_getParam('id');
        $annotation = get_db()->getTable('ImageAnnotation_Annotation')->find($annotationId);      
        $annotation->delete();
    }
    
    public function saveAnnotationAction()
    {
        $a['text'] = $this->_getParam('text');
        $a['top'] = (int)$this->_getParam('top');
        $a['left'] = (int)$this->_getParam('left');
        $a['width'] = (int)$this->_getParam('width');
        $a['height'] = (int)$this->_getParam('height');
        $a['file_id'] = (int)$this->_getParam('file_id');
        $a['user_id'] = current_user()->id;
                
        $annotationId = $this->_getParam('id');
        if ($annotationId == 'new') {
            //new annotation
            $annotation = new ImageAnnotation_Annotation;
        } else {
            //existing annotation
            $annotation = get_db()->getTable('ImageAnnotation_Annotation')->find($annotationId);      
        }
        
        if ($annotation) {
            $annotation->setArray($a);
            $annotation->save();
            $responseData = array('annotation_id' => $annotation->id);
        } else {
            $responseData = array();
        }
        
        $this->view->responseData = $responseData;
    }
}