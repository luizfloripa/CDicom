<?php
ini_set('max_execution_time', 1600);
require_once('class_dicom.php');

$autoloader = join(DIRECTORY_SEPARATOR,[__DIR__,'vendor','autoload.php']);
require $autoloader;

use PHPOnCouch\CouchClient;
use PHPOnCouch\CouchDocument;

//Busca os DCM no diretório files
$Directory = new RecursiveDirectoryIterator('files/');
$Iterator = new RecursiveIteratorIterator($Directory);
$Regex = new RegexIterator($Iterator, '/^.+(.dcm)$/i', RecursiveRegexIterator::GET_MATCH);


try {
    $conn = new PDO('pgsql:dbname=dicom;host=192.168.100.81', 'postgres', 'postgres');
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException  $e) {
    print $e->getMessage();
}

foreach($Regex as $name => $Regex){
    dicom_load($name, $conn);
//    die();
}

function dicom_load($file, $db)
{
    $d = new dicom_tag($file);
    $d->load_tags();
    $tags = $d->tags;
    $arrayDoc['id'] = $d->get_tag('0020', '000e');
    if($arrayDoc['id'] == "")
        return;
    try {
        $conn = $db;
        $serie = $arrayDoc['id'];
        $res = $conn->query("SELECT * FROM serie WHERE tag_0020_000e = '$serie'", PDO::FETCH_ASSOC)->fetch();

        //verifica se a serie já existe
        if($res['id']){
            $myId = $res['id'];
        }
        else{
            $ISp_Res = $conn->prepare("INSERT INTO serie(tag_0020_000e) VALUES (?)");
            $ISp_Res->bindParam(1, $serie, PDO::PARAM_STR);
            $ISp_Res->execute();
            $myId = $conn->lastInsertId('serie_id_seq');
        }
        $tag_0020_000d = $tags['0020,000d'];
        $tag_0010_0020 = $tags['0010,0020'];
        $tag_0008_0018 = $tags['0008,0018'];

        foreach ($tags as $key => $value) {
            //converte , para _ e adiciona o prefixo tag_
            $key = "tag_" . str_replace(',', '_', $key);
            $ISp_Res = $conn->prepare("INSERT INTO tag(idSerie, tag_0020_000d, tag_0010_0020, tag_0008_0018, tag, valor) VALUES (?, ?, ?, ?, ?, ?)");
            $Bus_Id = $arrayDoc['id'];
            $ISp_Res->bindParam(1, $myId, PDO::PARAM_INT);
            $ISp_Res->bindParam(2, $tag_0020_000d, PDO::PARAM_STR);
            $ISp_Res->bindParam(3, $tag_0010_0020, PDO::PARAM_STR);
            $ISp_Res->bindParam(4, $tag_0008_0018, PDO::PARAM_STR);
            $ISp_Res->bindParam(5, $key, PDO::PARAM_STR);
            $ISp_Res->bindParam(6, $value, PDO::PARAM_STR);
            $ISp_Res->execute();
            $arrayDoc[$key] = $value;
        }

        $tag_0008_0020 = $arrayDoc["tag_0008_0020"];
        $tag_0010_0030 = $arrayDoc["tag_0010_0030"];
        $tag_0010_0010 = $arrayDoc["tag_0010_0010"];
        $tag_0008_1030 = $arrayDoc["tag_0008_1030"];
        $tag_0008_103E = $arrayDoc["tag_0008_103E"];
        $tag_0008_0060 = $arrayDoc["tag_0008_0060"];

        $ISp_Res = $conn->prepare("INSERT INTO tagsindex(origem, tag_0008_0020, tag_0010_0030, tag_0010_0010, tag_0008_1030, tag_0008_103E, tag_0008_0060) VALUES (?,?,?,?,?,?,?)");
        $ISp_Res->bindParam(1, $myId, PDO::PARAM_INT);
        $ISp_Res->bindParam(2, $tag_0008_0020, PDO::PARAM_STR);
        $ISp_Res->bindParam(3, $tag_0010_0030, PDO::PARAM_STR);
        $ISp_Res->bindParam(4, $tag_0010_0010, PDO::PARAM_STR);
        $ISp_Res->bindParam(5, $tag_0008_1030, PDO::PARAM_STR);
        $ISp_Res->bindParam(6, $tag_0008_103E, PDO::PARAM_STR);
        $ISp_Res->bindParam(7, $tag_0008_0060, PDO::PARAM_STR);
        $ISp_Res->execute();

    } catch (PDOException  $e) {
        echo "<pre>";
        print $e->getMessage();
//        die();
    }
    // save on cassandra
    try{
        $data = file_get_contents($file);
        $data = base64_encode($data);
        $ISp_Res = $conn->prepare("INSERT INTO dicomimg.dicomimg_files(tag_0010_0020, tag_0020_000d, tag_0020_000e, tag_0008_0018, dcm) VALUES (?, ?, ?, ?, ?)");
        $ISp_Res->bindParam(1, $arrayDoc["tag_0010_0020"], PDO::PARAM_STR);
        $ISp_Res->bindParam(2, $arrayDoc["tag_0020_000d"], PDO::PARAM_STR);
        $ISp_Res->bindParam(3, $arrayDoc["tag_0020_000e"], PDO::PARAM_STR);
        $ISp_Res->bindParam(4, $arrayDoc["tag_0008_0018"], PDO::PARAM_STR);
        $ISp_Res->bindParam(5, $data, PDO::PARAM_LOB);
        $ISp_Res->execute();
    } catch (PDOException  $e) {
        print $e->getMessage();
    }
}