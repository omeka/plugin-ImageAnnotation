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
}