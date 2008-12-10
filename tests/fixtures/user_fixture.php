<?php
class PostFixture extends CakeTestFixture {
    var $name = 'Post';

	var $fields = array(
		'id' => array('type' => 'integer', 'key' => 'primary'),
		'author_id' => array('type' => 'integer', 'null' => false),
		'title' => array('type' => 'string', 'null' => false),
		'body' => 'text',
		'published' => array('type' => 'string', 'length' => 1, 'default' => 'N'),
		'created' => 'datetime',
		'updated' => 'datetime'
	);

	var $records = array(
        array('author_id' => 1, 'title' => 'Containable Behavior', 
            'body' => 'A new addition to the CakePHP core is the ContainableBehavior. This model behavior allows you to filter and limit model find operations. Using Containable will help you cut down on needless wear and tear on your database, increasing the speed and overall performance of your application. The class will also help you search and filter your data for your users in a clean and consistent way.', 
            'published' => 'Y', 'created' => '2007-03-18 10:39:23', 'updated' => '2007-03-18 10:41:31'
        ),
        array('author_id' => 3, 'title' => 'Tree Behavior', 
            'body' => 'It\'s fairly common to want to store hierarchical data in a database table. Examples of such data might be categories with unlimited subcategories, data related to a multilevel menu system or a literal representation of hierarchy such as is used to store access control objects with ACL logic. For small trees of data, or where the data is only a few levels deep it is simple to add a parent_id field to your database table and use this to keep track of which item is the parent of what. Bundled with cake however, is a powerful behavior which allows you to use the benefits of MPTT logic without worrying about any of the intricacies of the technique - unless you want to ;).', 
            'published' => 'Y', 'created' => '2007-03-18 10:41:23', 'updated' => '2007-03-18 10:43:31'
        ),

        array('author_id' => 1, 'title' => 'ACL Behavior', 
            'body' => 'The Acl behavior provides a way to seamlessly integrate a model with your ACL system. It can create both AROs or ACOs transparently. To use the new behavior, you can add it to the $actsAs property of your model. When adding it to the actsAs array you choose to make the related Acl entry an ARO or an ACO. The default is to create AROs.', 
            'published' => 'Y', 'created' => '2007-03-18 10:43:23', 'updated' => '2007-03-18 10:45:31'
        )
    );
}
