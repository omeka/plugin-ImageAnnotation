<?php head(array('title'=>'Browse Image Annotations','content_class' => 'horizontal-nav', 'bodyclass'=>'items primary browse-items')); ?>
<h1>Browse Image Annotations (<?php echo count($imageannotation_annotations);?> annotations total)</h1>
<div id="primary">
	<?php echo flash(); ?>
	
	<div id="browse-meta">
		<div id="simple-search-form">
			<?php echo simple_search("Search", array('id'=>'simple-search'), uri('image-annotation/moderate/browse')); ?>
		</div>

		<div class="pagination"><?php echo pagination_links(); ?></div>
	</div>
<?php
    if (count($imageannotation_annotations) > 0 ): ?>
    <table>
    <tr>
        <th><a href="<?php echo image_annotation_sort_uri('id');?>">Id</a></th>
        <th><a href="<?php echo image_annotation_sort_uri('recent');?>">Modified Date</a></th>
        <th><a href="<?php echo image_annotation_sort_uri('username');?>">Author</a></th>
        <th><a href="<?php echo image_annotation_sort_uri('itemid');?>">Item Id</a></th>
        <th><a href="<?php echo image_annotation_sort_uri('text');?>">Text</a></th>
        <th>Action</th>
    </tr>
    <?php foreach($imageannotation_annotations as $annotation):        
        $itemForAnnotation = $annotation->getItem();
    ?>
        <tr>
            <td><?php echo html_escape($annotation->id); ?></td>
            <td><?php echo html_escape($annotation->modified); ?></td>
            <td><?php echo html_escape($annotation->getUser()->username); ?></td>
            <td><?php echo link_to_item($itemForAnnotation->id, array(), 'show', $itemForAnnotation); ?></td>
            <td><?php echo html_escape($annotation->text); ?></td>
            <td><a href="<?php echo ADMIN_BASE_URL .  '/image-annotation/moderate/delete/' . $annotation->id; ?>" class="delete">Delete</a></td>
        </tr>
    <?php endforeach; ?>
    </table>    
    <?php else: ?>
        <div id="image-annotate-no-annotations">
            <p>There are no image annotations.
        </div>
    <?php endif; ?>
</div>
<?php foot(); ?>
