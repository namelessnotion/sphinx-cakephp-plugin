<?php
class SphinxConfig {
    var $default = array(
        "searchd" => array(
            "address" => "127.0.0.1",
            "port" => 3313, //default is 3312 
            "log" => "test_sphinx/log/searchd.log",
            "query_log" => "text_sphinx/log/query.log",
            "read_timeout" => 5,
            "max_children" => 30,
            "pid_file" => "test_sphinx/searchd.pid",
            "max_matches" => 1000,
            "seamless_rotate" => 1,
            "preopen_indexes" => 0,
            "unlink_old" => 1
        ),
        "indexer" => array(
            "mem_limit" => "256M"
        ),
        "sources" => array(
            "Post" => array(
                "sql_query" => array(
                    "conditions" => array("Post.published" => "Y"),
                    "contain" => array(),
                    "fields" => array("Post.title", "Post.body", "Post.created")
                ),
                "index" => array(
                    "path" =>
                    "docinfo" => "extern",
                    "mlock" => 0,
                    "morphology" => "none",
                    "min_world_len" => 1,
                    "charset_type" => "utf-8",
                    "html_strip" => 1
                )
            )
        )
    );
}
?>
