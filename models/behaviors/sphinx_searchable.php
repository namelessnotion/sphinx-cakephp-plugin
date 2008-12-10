<?php
class SphinxSearchableBehavior extends ModelBehavior {

    var $sphinx = false;
    var $schema = array();
    var $runtime = array();

    var $defaultSettings = array(
        "autoMapField" => true,
        "mapFields" => array()
    );
    /*
     * $settings = array(
     *      "autoMapField" => true,
     *      "mapFields" => array( 
     *          "Contestant.title" => "title",
     *          "Profile.full_name" => "name"
     *      )
     *  )
     *
     *  mapFields are used for the sphinx query syntax to map  @title "slow night" to @contestant__title "slow night"
     *  note the 2 underscores ( __ ) that seperate the lower case model alias and field name, sphinx doesn't like .'s and 
     *  changes everything to lowercase
     *  mapFields will overwrite/merge with the autoMapFields
     *
     */

    function setup(&$Model, $settings = array()) {
        debug("loaded");
        if(!isset($this->settings[$Model->alias])) {
            $this->settings[$Model->alias] = array();
        }
        if(!is_array($settings)) {
            $settings = array();
        }
        $this->settings[$Model->alias] = Set::merge($this->defaultSettings, $this->settings[$Model->alias]);
        $this->settings[$Model->alias] = array_merge($this->settings[$Model->alias], $settings);

        App::import('Datasource', 'SphinxSearchable.SphinxSource');
        $this->sphinx = ConnectionManager::getDataSource('sphinx');
        $this->schema = $this->sphinx->getSchema(Inflector::tableize($Model->alias));
        $this->autoMapFields($Model);
    }

    function beforeFind(&$Model, $query) {
        if(!isset($query['sphinx'])) {
            return $query;
        }

        if(isset($query['limit'])) {
            $query['sphinx']['limit'] = $query['limit'];
        }

        if(isset($query['page'])) {
            $query['sphinx']['page'] = $query['page'];
        }

        if(isset($query['conditions'])) {
            if(!isset($query['sphinx']['filters'])) {
                $query['sphinx']['filters'] = array();
            }
           $query['sphinx']['filters'] = array_merge($query['sphinx']['filters'], $this->convertConditionsToFilters($query['conditions'])); 
        }
        if(!isset($query['sphinx']['index'])) {
            $query['sphinx']['index'] = Inflector::tableize($Model->alias);
        }
        $query['sphinx']['query'] = $this->replaceMappedFields($Model, $query['sphinx']['query']);
        $results = $this->sphinx->read($query['sphinx']);
        $this->runtime[$Model->alias]['results'] = $results;
        $query['conditions'] = Set::merge($query['conditions'], $this->getMatchedConditions($Model));
        unset($results);
        unset($query['limit']);
        unset($query['offset']);
        unset($query['page']);
        return $query;
    }

    function afterFind(&$Model, $results, $primary) {
        if(!empty($this->runtime[$Model->alias])) {
            $newResults = array();
            $orderedIds = $this->getMatchedIds($Model);
            foreach($results as $result) {
                $id = $result['Contestant']['id'];
                $sphinxInfo =  array("Sphinx" => array(
                    "count" => $this->runtime[$Model->alias]['results']['total'],
                    "weight" => $this->runtime[$Model->alias]['results']['matches'][$id]['weight']
                ));
                $result = Set::merge($sphinxInfo, $result);
                $newResults[(int) array_search($result['Contestant']['id'], $orderedIds)] = $result;
                unset($result);
            
            }
            unset($results);
            ksort($newResults);
            return $newResults;
        }
        return $results;
    }

    function autoMapFields(&$Model) {
        $map = array();
        if($this->settings[$Model->alias]['autoMapField']) {
            $modelFields = array_keys($Model->schema()); 

            //find all sphinx fields and attr that are from Contestant
            foreach($modelFields as $modelField) {
                $sphinxFieldGuess = strtolower($Model->alias)."__".$modelField;
                if(array_search($sphinxFieldGuess, $this->schema['fields'])) {
                    $map["@".$modelField] = "@".$sphinxFieldGuess; 
                }
            }
            unset($modelFields);
            $this->settings[$Model->alias]['mapFields'] = Set::merge($map, $this->settings[$Model->alias]['mapFields']);

            return $map;
        }
    }

    function replaceMappedFields(&$Model, $query) {
        foreach($this->settings[$Model->alias]['mapFields'] as $key => $field) {
            $query = preg_replace('/'.$key.'/', $field, $query);
        }
        return $query;
    }

    function getMatchedIds (&$Model) {
        return array_keys($this->runtime[$Model->alias]['results']['matches']);
    } 

    function getMatchedConditions(&$Model) {
        return array( $Model->alias.".".$Model->primaryKey => $this->getMatchedIds($Model));
    }

    function processQuery(&$query) {

    }

    /**
     * converts =, <>, IN and NOT IN conditions to filters
     * all other conditions types are ignored
     */
    function convertConditionsToFilters($conditions, $exclude = false) {
        if($exclude && preg_match("/^not$/i", $exclude)) {
            $exclude = true;
        } else {
            $exclude = false;
        }

        $filters = array();
        foreach($conditions as $field => $value) {
            if( preg_match("/^(or|not)$/i", $field)) {
                $filters = array_merge($filters, $this->convertConditionsToFilters($value, $field));
            } else {
                if(isset($this->schema['attrs'][$field])) {
                    $filters[] = array("attribute" => $this->schema['attrs'][$field]['attr_name'], "values" => $value, "exclude" => $exclude);
                }
            }
        }
        return $filters;
    }

}
?>
