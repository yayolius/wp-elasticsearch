<?php
abstract class ModelBase {

    var $serverUrl = null; //It should be end with "/". https://github.com/ruflin/Elastica/issues/120#issuecomment-3423869
    var $elasticaClient = null;
    var $documentToIndex = null;
    var $documentType = null;
    var $documentPrefix = null;

    public static $_INDEX = 'wordpress';
    public static $_CHUNK_SIZE = 1000;
    public static $_SEARCHBOX_URL = "http://api.searchbox.io/";

    function initialize() {
        spl_autoload_register(array( $this, '__autoload_elastica'));
        $this->elasticaClient = new Elastica_Client(
            array(
                'url' => $this->serverUrl
            )
        );
    }
    function __autoload_elastica ($class) {
        $path = str_replace('_', DIRECTORY_SEPARATOR, $class);
        if (file_exists(dirname( __FILE__ ) . DIRECTORY_SEPARATOR . $path . '.php')) {
            require_once(dirname( __FILE__) . DIRECTORY_SEPARATOR . $path . '.php');
        }
    }

    /**
     * Index document by using its id. documentPrefix is used for making index unique
     * among all objects(post, comment, user, etc...)
     */
    public function index($bulk = false) {
        if (!empty( $this->documentToIndex )) {
            if ($this->elasticClient == null) {
                $this->initialize();
            }
            $elasticaIndex = $this->elasticaClient->getIndex(ModelBase::$_INDEX);
            $elasticaType = $elasticaIndex->getType($this->documentType);
            if ($bulk) {
                $i = 0;
                foreach ($this->documentToIndex as $doc) {
                    $documents[] = new Elastica_Document($this->documentPrefix . $doc['id'], $doc);
                    $i++;
                    //bulk index is better than unit index.
                    if ($i % ModelBase::$_CHUNK_SIZE == 0) {
                        $elasticaType->addDocuments($documents);
                        $documents = array();
                    }
                }
                if (!empty($documents)) {
                    $elasticaType->addDocuments($documents);
                }
            } else {
                $document = new Elastica_Document($this->documentPrefix . $this->documentToIndex['id'], $this->documentToIndex);
                $elasticaType->addDocument($document);
            }
            $elasticaType->getIndex()->refresh();
        }
    }

    /**
     * Delete specific index
     * @param $documentId
     */
    public function delete($documentId) {
        if ($this->elasticClient == null) {
            $this->initialize();
        }
        $elasticaIndex = $this->elasticaClient->getIndex(ModelBase::$_INDEX);
        $elasticaType = $elasticaIndex->getType($this->documentType);
        $elasticaType->deleteById($this->documentType . $documentId);
        $elasticaType->getIndex()->refresh();
    }

    /**
     * Delete entire type(all indexes)
     */
    public function deleteAll() {
        if ($this->elasticClient == null) {
            $this->initialize();
        }
        $elasticaIndex = $this->elasticaClient->getIndex(ModelBase::$_INDEX);
        $elasticaType = $elasticaIndex->getType($this->documentType);
        $elasticaType->delete();
        $elasticaType->getIndex()->refresh();
    }
}
