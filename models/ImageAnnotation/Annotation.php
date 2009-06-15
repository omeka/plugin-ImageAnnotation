<?php
/**
 * ImageAnnotation_Annotation - represents an image annotation
 *
 * @version $Id$
 * @package ImageAnnotation
 * @author CHNM
 * @copyright Center for History and New Media, 2008
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 **/

class ImageAnnotation_Annotation extends Omeka_Record
{
    public $user_id;
    public $file_id;
    
    public $top;
    public $left;
    public $width;
    public $height;
    
    public $text;
    
    public $added;
    public $modified;
    
    public $public;
    
    public function save()
    {
        $this->modified = date("Y-m-d H:i:s");
        return parent::save();
    }
    
    // Returns whether or not an annotation overlaps another annotation
    public function overlaps($annotation)
    {
        return overlapsX($annotation) && overlapsY($annotation);
    }
    
    // Returns whether or not the X coordinates of this annotation overlaps annother annotation
    public function overlapsX($annotation) 
    {
        return $this->between($this->left, $annotation->left, $annotation->left + $annotation->width) ||
               $this->between($annotation->left, $this->left, $this->left + $this->width);
    }
    
    // Returns whether or not the Y coordinates of this annotation overlaps annother annotation
    public function overlapsY($annotation) 
    {      
        return $this->between($this->top, $annotation->top, $annotation->top + $annotation->height) ||
               $this->between($annotation->top, $this->top, $this->top + $this->height);
    }
    
    // Returns whether a number is between two other numbers inclusive
    // $a <= $b <= $c
    private function between($b, $a, $c) 
    {
        return $b >= $a && $b <= $c;
    }
    
    public function getUser()
    {
        $user = $this->getDb()->getTable('User')->find($this->user_id);
        return $user;
    }
    
    public function getFile()
    {
        $file =  $this->getDb()->getTable('File')->find($this->file_id);
        return $file;
    }
    
    public function getItem()
    {
        $table = $this->getDb()->getTable('Item');
        $select = $table->getSelect();                
        $select->joinInner(array('fi' => $this->getDb()->getTableName('File')), "fi.item_id = i.id", array()); 
        $select->joinInner(array('an' => $this->getDb()->getTableName('ImageAnnotation_Annotation')), "fi.id = an.file_id", array());
        $select->where("an.id = ?", array($this->id));
        return $table->fetchObject($select);
    }
    
    /**
     * Returns whether the current user has permission to do something 
     * on an ImageAnnotation_Annotations resource
     *
     * @param string $permission 
     * @return bool Whether the current user has permission
     */
    static public function hasPermission($permission)
    {
        return get_acl()->checkUserPermission('ImageAnnotation_Annotations', $permission);
    }
}