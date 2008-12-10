<?php
class SphinxShell extends Shell {
    var $tasks = array("Index", "GenerateConfig", "Query", "HaltSearchd", "RestartSearchd", "StartSearchd");

    function main() {
        $this->out('Interactive SphinxSearchable Shell');
        $this->hr();
        $this->out('[I]ndex');
        $this->out('[G]enerateConfig');
        $this->out('[Q]uery');
        $this->out('[H]altSearchD');
        $this->out('[R]estartSearchd');
        $this->out('[S]tartSearchd');
        $this->out('[Q]uit');

        $taskToDo = strtoupper($this->in(__('What would you like to do?', true), array('I', 'G', 'Q', 'S', 'R', 'S')));
        switch($taskToDo) {
            case 'I':
                $this->Index->execute();
                break;
            case 'G':
                $this->GenerateConfig->execute();
                break;
            case 'Q':
                $this->Query->execute();
                break;
            case 'H':
                $this->StopSearchd->execute();
                break;
            case 'R':
                $this->RestartSearchd->execute();
                break;
            case 'S':
                $this->StartSearchd->execute();
                break;
            default:
                $this->out(__('You have made an invalid select.'));
        }

        $this->main();
    }

    function help() {
        $this->out('Sphinx Searchable Shell Help');
    }

        
}
?>
