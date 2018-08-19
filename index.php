<?php
ini_set('max_execution_time', 600);
require_once('class_dicom.php');

$autoloader = join(DIRECTORY_SEPARATOR,[__DIR__,'vendor','autoload.php']);
require $autoloader;

use PHPOnCouch\CouchClient;
use PHPOnCouch\CouchDocument;

//Busca os DCM no diretório files
$Directory = new RecursiveDirectoryIterator('files/');
$Iterator = new RecursiveIteratorIterator($Directory);
$Regex = new RegexIterator($Iterator, '/^.+(.dcm)$/i', RecursiveRegexIterator::GET_MATCH);

//importa os DCM
foreach($Regex as $name => $Regex){
    dicom_load($name);
}

function dicom_load($file)
{
    $d = new dicom_tag($file);
    $d->load_tags();
    $tags = $d->tags;

    $arrayDoc['id'] = $d->get_tag('0008', '0018');
    foreach ($tags as $key => $value) {
        //converte , para _ e adiciona o prefixo tag_
        $key = "tag_" . str_replace(',', '_', $key);
        $arrayDoc[$key] = $value;
    }

    $client = new CouchClient('http://:@192.168.100.65:5984', 'dicom');

    $doc = new CouchDocument($client);
    //cria o documento e salva do couchdb
        $key =  $arrayDoc["tag_0010_0020"]."|".$arrayDoc["tag_0020_000d"]."|".$arrayDoc["tag_0020_000e"]."|".$arrayDoc["tag_0008_0018"];
        try {
            $doc->set($arrayDoc);
            //salva o binário no hbase
            shell_exec("/home/unisul/hbase-1.2.1/bin/hbase shell ".$key.' "'.$file.'"');
        } catch (Exception $e) {
            echo "Document storage failed : " . $e->getMessage() . "<BR>\n";
        }
}