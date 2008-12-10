<?php
require_once('sphinx_test_models.php');
class SphinxSearchableTest extends CakeTestCase {
    var $fixtures = array(
        'plugin.sphinx_searchable.post'
    );


    function testQuery() {
        $this->Post =& new Post(false, null, "test");
    }
}
?>
