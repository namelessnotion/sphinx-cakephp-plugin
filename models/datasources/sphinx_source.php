<?php
App::import('Vendor', 'SphinxSearchable.sphinxapi', array('file' => 'sphinxapi'.DS.'sphinxapi.php'));
class SphinxSource extends Datasource {
    var $sphinx = false;

    var $_queryDefaults = array(
        "index" => "*",
        "limit" => 20,
        "offset" => 0,
        "max_matches" => 0,
        "cutoff" => 0
    );

    function __construct($config) {
        parent::__construct($config);
        $this->sphinx = new SphinxClient();
        $this->sphinx->SetServer($this->config['host'], $this->config['port']);
    }

    function read($query) {
        $query = Set::merge($this->_queryDefaults, $query);

        $this->setLimits($query);

        if(isset($query['max_query_time'])) {
            $this->setMaxQueryTime($query['max_query_time']);
        }

        if(isset($query['match_mode'])) {
            $this->setMatchMode($query['match_mode']);
        }

        if(isset($query['rank_mode'])) {
            $this->setRankMode($query['rank_mode']);
        }

        if(isset($query['sort_mode'])) {
            $this->setSortMode($query['sort_mode']);
        }

        if(isset($query['filters'])) {
            foreach($query['filters'] as $filter) {
                $this->setFilter($filter);
            }
        }

        

        // if(count(explode(",",$query['index'])) > 1) {
        //     $this->setArrayResult(true);
        // }

        $this->sphinx->SetFieldWeights(array(
            "title" => 2,
            "contestant_responses_response" => 2,
            "profiles_country" => 1
        )); 

        $result =  $this->sphinx->Query($query['query'], $query['index']);
        if($result === false) {
            trigger_error("sphinx query failed: ".$this->sphinx->GetLastError());
        }
        $this->reset();
        return $result; 
    }

    function reset() {
        $this->sphinx->ResetFilters();
        $this->sphinx->ResetGroupBy();
    }

    /**
     * @todo cache schema
     */

    function getSchema($index) {
        $schema = array();
        $result = $this->read(array("index" => $index, "query" => "", "limit" => 1));
        foreach($result['fields'] as $field) {
            $orig_field = $field;
            $schema['fields'][$this->cakealize($field)] = $orig_field;
        }
        foreach($result['attrs'] as $key => $field) {
            $schema['attrs'][$this->cakealize($key)] = array("type" => $field, "attr_name" => $key);
        }
        return $schema;
    }

    /**
    *   Example: contestant_response__response -> ContestantResponse.response
    */
    function cakealize($sphinx_field_or_attr_name) {
        $field =  str_replace("__", ".", $sphinx_field_or_attr_name);
        if($c=preg_match_all("/((?:[a-z][a-z0-9_]*))/is", $field, $matches)) {
            if(isset($matches[0][1])) {
                $field = Inflector::camelize($matches[0][0]).".".$matches[0][1];
            }
        }
        return $field;
    }

    function sphinxize($cakephp_field_name) {
        if($c=preg_match_all("/((?:[a-z][A-Z][a-z0-9_]*))/is", $cakephp_field_name, $matches)) {
            return Inflector::underscore($matches[0][0])."__".$matches[0][1];
        }
    }

    function setFieldWeights($weights) {
        $this->sphinx->SetFieldWeights($weights);
    }

    function setLimits($query) {
        //Sphinx defaults
        $limit = 20;
        $offset = 0;
        $max_matches = 0;
        $cutoff = 0;

        extract($query);
        if(isset($page)) {
            $offset = ($page-1) * $limit;
        }

        $this->sphinx->SetLimits($offset, $limit, $max_matches, $cutoff);
    }

 
    function setMaxQueryTime($max_query_time) {
        if(is_numeric($max_query_time) && $max_query_time >= 0) {
            $this->sphinx->SetMaxQueryTime($max_query_time);
        } else {
            trigger_error("max_query_time must be an unsigned integer");
        }
    }

    function setMatchMode($mode) {
        $matchModes = array(
            "all" => SPH_MATCH_ALL,             //matches all query words (default mode) 
            "any" => SPH_MATCH_ANY,             //matches any of the query words;
            "phrase" => SPH_MATCH_PHRASE,       //matches query as a phrase, requiring perfect match
            "boolean" => SPH_MATCH_BOOLEAN,     //matches query as a boolean expression
            "extended" => SPH_MATCH_EXTENDED    //matches query as an expression in Sphinx internal query language
        );

        if(isset($matchModes[$mode])) {
            $this->sphinx->SetMatchMode($matchModes[$mode]);
        } else {
            trigger_error("invalid sphinx match mode");
        }
    }

 
    function setRankingMode($mode) {
        $rankModes = array(
            "proximity_bm25" => SPH_RANK_PROXIMITY_BM25,    //default ranking mode which uses and combines both phrase proximity and BM25 ranking.

            "bm25" => SPH_RANK_BM25,                        //statistical ranking mode which uses BM25 ranking only 
                                                            //(similar to most other full-text engines). This mode is 
                                                            //faster but may result in worse quality on queries which contain more than 1 keyword.
            
            "none" => SPH_RANK_NONE                         //disabled ranking mode. This mode is the fastest. It is 
                                                            //essentially equivalent to boolean searching. A weight of 1 is assigned to all matches.
        );

        if(isset($rankModes[$mode])) {
            $this->sphinx->SetRankingMode($rankModes[$mode]);
        } else {
            trigger_error("invalid sphinx ranking mode");
        }
    }

    function setSortMode($mode) {
        $sortModes = array(
            "relevance" => SPH_SORT_RELEVANCE,
            "attr_desc" => SPH_SORT_ATTR_DESC,
            "attr_asc" => SPH_SORT_ATTR_ASC,
            "time_segments" => SPH_SORT_TIME_SEGMENTS,
            "extended" => SPH_SORT_EXTENDED,
            "expr" => SPH_SORT_EXPR
        );

        if(isset($sortModes[$mode])) {
            $this->sphinx->SetSortMode($sortModes[$mode]);
        } else {
            trigger_error("invalid sphinx sort mode");
        }
    }

    function setFilter($attribute, $values = null, $exclude = false) {
        if(is_array($attribute)) {
            extract($attribute);
        }

        if(!is_array($values)) {
            $values = array($values);
        }
        $this->sphinx->SetFilter($attribute, $values, $exclude);
    }

    function setFilterFloatRange($attribute, $min, $max, $exclude = false) {
        $this->sphinx->SetFilterFloatRange($attribute, $min, $max, $exclude);
    }

    function escapeString($string) {
        return $this->sphinx->EscapeString($string);
    }

    function setArrayResult($arrayresult) {
        $this->sphinx->SetArrayResult($arrayresult);
    }

}
?>
