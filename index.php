<?php

require_once dirname(__FILE__)."/settings/config.php";
require_once dirname(__FILE__)."/class/MPSQL.class.php";

try {
     MPSQL::setup(MYSQL_HOST, MYSQL_DB, MYSQL_USER, MYSQL_PASS); 
     MPSQL::setFetchType(MPSQL::FETCH_OBJECT);
     MPSQL::setUseCache(true);
     
     
     
//     $query = "UPDATE articles SET url=:url WHERE id=:id";
//     $fields = array(":id"=>1,":url"=>"www.yazarbaz.com");
//     var_dump(MPSQL::execQuery($query,$fields));
//     if(MPSQL::execQuery($query,$fields)){
//         MPSQL::deleteMemcache();
//     }
     
     $query = "SELECT * FROM articles";
     $result = MPSQL::getAll($query);
     echo "<pre>";
     print_r($result);
     echo "</pre>";
     //$query = "SELECT * FROM articles WHERE id=:id";   
     //$result = MPSQL::getRow($query,array(':id' => '1'));
     //print_r($result);
     //$result = MPSQL::findOne("articles",$fields);
     //var_dump($result);
     exit;
} catch (Exception $e) {
    echo 'Caught exception: ',  $e->getMessage(), "\n";
}
?>