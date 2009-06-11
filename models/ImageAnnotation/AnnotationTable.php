<?php
/**
 * ImageAnnotation_AnnotationTable - represents a image annotation table
 *
 * @version $Id$
 * @package ImageAnnotation
 * @author CHNM
 * @copyright Center for History and New Media, 2008
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 **/

class ImageAnnotation_AnnotationTable extends Omeka_Db_Table
{
    
    public function findByItem($item, $onlyPublicAnnotations=true)
    {
        // get all of the annotations for every thumbnail file associated with the item
        $annotations = array();
        if (!$item->hasThumbnail()) {
            $files = $item->getFiles();
            foreach($files as $file) {            
                if ($file->hasThumbnail()) {
                    $annotations[] = findByFile($file);
                }
            }
        }
        return $annotations;
    }
    
    public function findByFile($file, $onlyPublicAnnotations=true)
    {
        if ($file instanceof File) {
            $fileId = $file->id;
        } else if ( is_int($file) ) {
            $fileId = $file;
        } else {
            throw new Exception($file . ' is an invalid parameter. You must specify a file object or file object id to find an annotation by file.');
        }
                
        $db = $this->getDb();
		$select = new Omeka_Db_Select;
		$select->from(array('a'=>$db->ImageAnnotation_Annotation), array('a.*'));
		if ($onlyPublicAnnotations) {
		    $select->where("a.file_id = ? AND a.public = TRUE");
		} else {
    		$select->where("a.file_id = ?");		    
		}
		$select->order('(a.width * a.height) ASC');
				
		return $this->fetchObjects($select, array($fileId));        
    }
}