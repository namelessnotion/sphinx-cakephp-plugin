<?php
App::import('File');
class GenerateConfigTask extends Shell {
    var $dryRun = null;
    var $_defaultPathes = array(
        "sphinxConfig" => "sphinx",
        "saveTo" => "config/sphinx/sphinx.conf"
    );
    var $sphinxConfig = null;
    var $generatedConfig = null;
    var $sphinxDatasource = false;

    function _welcome() {
        $this->out("Generating Sphinx Config file for indexer and searchd");
        $this->hr();
    }
    
    function execute() {
        if(!isset($this->args[0])) {
            $this->sphinxConfig = config("sphinx");
        } else {
            $this->sphinxConfig = config($this->_defaultPathes['sphinxConfig']);
        }
        if($this->sphinxConfig) {
            $this->sphinxConfig = new SphinxConfig();
        } else {
            $this->err('Failed to load sphinx config, config/sphinx.php');
            exit();
        }
        if(!$this->_loadDbConfig()) {
            $this->err('Failed to load database config, config/database.php');
            exit();
        }
        $this->process();
    }

    function process() {
        $this->_postBuildSources();
        $this->out($this->_buildSources());
    }

    /**
     * merge defaults
     * insert database configuration
     * insert sql_query
     */
    function _postBuildSources() {
        foreach($this->sphinxConfig->default['sources'] as $modelName => &$source) {
            //insert database configuration
            if(isset($source['database'])) {
                $database = $source['database'];
            } else {
                $database = 'default';
            }
            $dbConfig = $this->cakeDbConfigToSphinx($this->DbConfig->{$source['database']});
            $source = Set::merge($dbConfig, $source);
            unset($source['database']);

            //move index
            if(isset($source['index'])) {
                $this->sphinxConfig->default['indexes'][$modelName] = $source['index'];
                unset($source['index']);
            }

            if(is_array($source['sql_query'])) 
            {
                $source['sql_query']['log'] = true;
                $queryAndAttrs = $this->_getSQLQuery($modelName, $source['sql_query']);
                $source['sql_query'] = $queryAndAttrs['sql_query'];
            }
        }
    }
    /**
     * Turns a cakephp $options array for find into a sql query we can use in sphinx config
     * Notes: All field names are lowercase in sphinx, also .'s are not handled so we replace .'s with __ (underscore x2)
     * Joins are done thru contain, belongsTo and hasOne will be left joined
     * HABTM and hasMany will be group_cat
     * Fields of type INT will be sql_attr_uint
     * Fields of group cat ints will be sql_attr_multi
     * Fields named created will be sql_attr_timestamp
     *
     * array(
     *  conditions => array(
     *      "Post.published" => "Y"
     *  ),
     *  contain => array(
     *      "Tag", "Author"
     *  ),
     *  fields => array(
     *      "Post.id", "Post.body", "Post.title", "Post.created", "Post.author_id", "Author.name", "Tag.tag"
     *  )
     * )
     *
     * sql_query = select 
     *      posts.id as post__id, 
     *      posts.body as post__body, 
     *      posts.title as post__title,
     *      posts.author_id as post__author_id,
     *
     *      GROUP_CONCAT(tags.tag SEPERATOR ', ') as tag__tag
     *      FROM posts
     *      LEFT JOIN authors ON posts.author_id = authors.id
     *      LEFT JOIN tags_posts ON posts.id = tags_posts.post_id
     *      LEFT JOIN tags ON tags.id = tags_posts.tag_id
     *      WHERE posts.published = "Y"
     *      GROUP BY posts.id
     *
     *  sql_attr_uint = posts.author_id
     *  sql_attr_timestamp = posts.created
     *
     *
     */
    function _getSQLQuery($modelName, $options) {
        $sqlQuery = "";
        $leftJoins = array();
        $selectFields = array();
        $attrs = array();
        $isMulti = array();
        extract($options);
        $this->_runtimeLoadModel($modelName);
        $this->_loadSphinxDatasource();

        if(isset($contain)) {

            $modelPrimaryKey = Inflector::singularize($this->{$modelName}->table).'.'. $this->{$modelName}->primaryKey;
            $modelTable = $this->{$modelName}->table;
            foreach($contain as $assocKey => $assocModel) {
                $assocArray = false;
                $assocForeignKey = false; 
                if(is_array($assocModel)) {
                    $assocArray = $assocModel;
                    $assocModel = $assocKey;
                }
                $assocTable = $this->{$modelName}->{$assocModel}->table;
                $assocPrimaryKey = $assocTable .'.'. $this->{$modelName}->{$assocModel}->primaryKey;

                if(isset($this->{$modelName}->belongsTo[$assocModel])) {
                    $foreignKey = $modelTable.'.'.$this->{$modelName}->belongsTo[$assocModel]['foreignKey'];
                    $leftJoins[] = 'LEFT JOIN '.$assocTable.' on '.$assocPrimaryKey.' = '.$foreignKey;
                } else if(isset($this->{$modelName}->hasOne[$assocModel])) {
                    $foreignKey = $assocTable.'.'.$this->{$modelName}->hasOne[$assocModel]['foreignKey'];
                    $leftJoins[] = 'LEFT JOIN '.$assocTable.' on '.$modelPrimaryKey.' = '.$foreignKey;
                } else if(isset($this->{$modelName}->hasMany[$assocModel])) {
                    $foreignKey = $assocTable.'.'.$this->{$modelName}->hasMany[$assocModel]['foreignKey'];
                    $leftJoins[] = 'LEFT JOIN '.$assocTable.' on '.$modelPrimaryKey.' = '.$foreignKey;
                    $isMulti[] = $assocModel;
                } else if(isset($this->{$modelName}->hasAndBelongsToMany[$assocModel]) ) {
                    $joinTable = $this->{$modelName}->hasAndBelongsToMany[$assocModel]['joinTable'];
                    $foreignKey = $this->{$modelName}->hasAndBelongsToMany[$assocModel]['foreignKey'];
                    $associationForeignKey = $this->{$modelName}->hasAndBelongsToMany[$assocModel]['associationForeignKey'];
                    $leftJoins[] = 'LEFT JOIN '.$joinTable.' on '.$modelPrimaryKey.' = '. $joinTable.'.'.$foreignKey;
                    $leftJoins[] = 'LEFT JOIN '.$assocTable.' on '.$assocPrimaryKey.' = '. $joinTable.'.'.$associationForeignKey;
                    $isMulti[] = $assocModel;
                }
            }
            print_r($leftJoins);
            print_r($isMulti);
        }

        if(isset($fields)) {
            foreach($fields as $field) {
                $model = null;
                $fieldParts = explode(".", $field);
                $fieldModelName = $fieldParts[0];
                $field = $fieldParts[1];
                unset($fieldParts);
                if($fieldModelName == $modelName) {
                    $model =& $this->{$modelName};
                } else {
                    $model =& $this->{$modelName}->{$fieldModelName};
                }
                $fieldType = $model->getColumnType($field);
                if(!in_array($model->alias, $isMulti)) {
                    $selectFields[] = $model->table.'.'.$field.' as '.Inflector::underscore($model->alias).'__'.$field;
                    if($fieldType == "integer") {
                        $attrs[] = "sql_attr_uint ".Inflector::underscore($model->alias).'__'.$field;
                    }
                    if($fieldType == "datetime") {
                        $attrs[] = "sql_attr_timestamp ".Inflector::underscore($model->alias).'__'.$field;
                    }
                } else {
                    $selectFields[] = 'GROUP_CONCAT('.$model->table.'.'.$field." SEPERATOR ',')".' as '.Inflector::underscore($model->alias).'__'.$field; 
                    $attrs[] = 'sql_attr_multi uint '.Inflector::underscore($model->alias).'__'.$field.' from field';
                }

                unset($model);
            }
            print_r($attrs);
            print_r($selectFields);
            $sqlQuery = "select ".implode(",\n\r\t\t", $selectFields)."\n\r\t\tFROM ".$modelTable." ".implode("\n\r\t\t", $leftJoins);
            $where = "";
            if(isset($conditions)) {
                $where = str_replace('`', '', $this->{$modelName}->getDataSource()->conditions($conditions, false));
                $where = str_replace($modelName.'.', $modelTable.'.', $where);
                if(isset($contain)) {
                    foreach($contain as $containName) {
                        $where = str_replace($containName.'.', $this->{$modelName}->{$containName}->table.'.', $where);
                    }
                }
            }
            $sqlQuery .= "\n\r\t\t".trim($where)."\n\r\t\tGROUP BY ".$modelTable.".id";
            
        }
        $sqlQuery = str_replace("\n\r", " \\\n\r", $sqlQuery);
        return array("sql_query" => $sqlQuery, "sql_attr" => $attrs);
    }

    function _loadSphinxDatasource() {
        if(!$this->sphinxDatasource) {
            App::import('Datasource', 'SphinxSearchable.SphinxSource');
            $this->sphinxDatasource = ConnectionManager::getDataSource("sphinx");
        }
    }

    function _runtimeLoadModel($modelName) {
        $this->uses = Set::merge($this->uses, array($modelName));
        $this->_loadModels();
    }

    function _buildSources() {
        $content = "";
        foreach($this->sphinxConfig->default['sources'] as $key => $source) {
            $content .= sprintf("source %s {\n\r", strtolower($key));
            foreach($source as $fieldName => $fieldValue) {
                $content .= sprintf("\t%s = %s\n\r", $fieldName, $fieldValue);
            }
            $content .= "\n\r}";
        }
        return $content;
    }

    function cakeDbConfigToSphinx($config) {
        $map = array(
            "host" => "sql_host",
            "port" => "sql_port",
            "login" => "sql_user",
            "password" => "sql_pass",
            "database" => "sql_db"
        );
        $sphinxDbConfig = array();
        foreach($config as $fieldName => $fieldValue) {
            if(isset($map[$fieldName])) {
                $sphinxDbConfig[ $map[$fieldName] ] = $fieldValue;
            }
        }
        return $sphinxDbConfig;
    }
}

?>
