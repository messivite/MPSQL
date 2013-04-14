<?php

/*
 * PDO SINGLETON CLASS IN MEMCACHE INTEGRATION
 * @author  Mustafa Aksoy
 * @version v0.1
 * @licence Use how you like it, just please don't remove or alter this PHPDoc
 */

class MPSQL {

    CONST FETCH_ARRAY = "FETCHING_ARRAY";
    CONST FETCH_OBJECT = "FETCHING_OBJECT";

    protected static $db, $memcacheObj;
    private static /* $_instance = array(), */$_dsn, $_dbname,
            $_dbusername, $_pass, $_memcache, $_tableName, $_query, $_params, $_expireTime;
    public static $sqlQuery;
    protected static $_fetchTypes = array(
        self::FETCH_ARRAY => PDO::FETCH_ASSOC,
        self::FETCH_OBJECT => PDO::FETCH_OBJ
    );
    protected static $_fetchType;

    private function __construct() {
        try {
            self::$db = new PDO('mysql:host=' . self::$_dsn . ';dbname=' . self::$_dbname . '', self::$_dbusername, self::$_pass);
            self::$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$_memcache = false;
        } catch (PDOException $e) {
            throw new Exception($e->getMessage());
//TODO Logger Exception
        }
    }

    /*
     * @TODO: v1.2 Abstract class run instance calling
     */

//    public static function getInstance($class) {
//        if (empty(self::$_instance[$class])) {
//            self::$_instance[$class] = new $class;
//        }
//        return self::$_instance[$class];
//    }

    public static function setup($dsn = "", $dbname = "", $dbusername = "", $pass = "") {
        self::$_dsn = $dsn;
        self::$_dbname = $dbname;
        self::$_dbusername = $dbusername;
        self::$_pass = $pass;
        self::$_memcache = false;
        if (!self::$db) {
            new MPSQL();
        }
        return self::$db;
    }

    /*
     * @return object
     * @desc Set Use Memcache Setting,
     * @params: $status:bool,$expireTime : int
     */

    public static function setUseCache($status, $expireTime = 3600) {
        if (is_bool($status)) {
            self::$_expireTime = $expireTime;
            self::$_memcache = $status;
            self::_prepareMemcache();
        } else {
            self::$_memcache = false;
        }
    }

    public static function execQuery($query, $fields = array()) {

        if (isset($query)) {
            self::$_query = $query;
            self::$_params = $fields;
        } else {
            throw new Exception("Missing SQL Query");
        }
        try {
            $sth = self::$db->prepare(self::$_query);
            $sth->execute(self::$_params);
            self::$sqlQuery = $sth->queryString;
//                if(self::_isMemcacheActive()){
//                    if(self::deleteMemcache()){
//                        return true;
//                    }else{
//                        return false;
//                    }
//                }
            return true;
        } catch (PDOException $e) {
            throw new Exception($e->getMessage());
        }
    }

    /*
     * @param: query caching table
     * @return bool
     * @desc: UPDATE or INSERT query after Memcache Delete key
     */

    public static function deleteMemcache($tableName) {

        if (!isset($tableName)) {
            throw new Exception("Missing Table Name");
        }

        $memcacheKey = md5("SELECT * FROM" . $tableName . serialize(array()));

        if (self::$memcacheObj->delete($memcacheKey)) {
            return true;
        } else {
            return false;
        }
    }

    /*
     * @desc: Query result getting ALL
     */

    public static function getAll($query, $fields = array()) {
        if (isset($query) && isset($fields)) {
            self::$_query = $query;
            self::$_params = $fields;
        } else {
            throw new Exception("Not Setting SQL Query or Missing Parameter");
        }
        $results = array();
        try {

            if (self::_isMemcacheActive()) {
                $memcacheKey = md5(self::$_query . serialize(self::$_params));
                $memcacheResult = self::$memcacheObj->get($memcacheKey);
                if ($memcacheResult) {
                    return $memcacheResult;
                } else {
                    $newResult = self::_getAll();
                    if ($newResult) {
                        self::$memcacheObj->set($memcacheKey, $newResult, 0, self::$_expireTime);
                        return $newResult;
                    } else {
                        return NULL;
                    }
                }
            } else {
                return self::_getAll();
            }
        } catch (PDOException $e) {
            throw new Exception($e->getMessage());
        }
    }

    /*
     * @desc: Query Result Getting ALL
     * @return array or stdObject
     */

    protected static function _getAll() {
        $results = array();
        $sth = self::$db->prepare(self::$_query);
        $sth->execute(self::$_params);
        self::$sqlQuery = $sth->queryString;

        while ($row = $sth->fetch(self::$_fetchType)) {
            $results[] = $row;
        }
        return $results;
    }

    /*
     * @desc: Get Row one ROW
     * @params: $query: string
     * @params: $fields: array
     * @return stdObject or Array
     */

    public static function getRow($query, $fields) {
        if (isset($query) && isset($fields)) {
            self::$_query = $query;
            self::$_params = $fields;
        } else {
            throw new Exception("Not Setting SQL Query");
        }
        try {
            if (self::_isMemcacheActive()) {
                $memcacheKey = md5(self::$_query . serialize(self::$_params));
                $memcacheResult = self::$memcacheObj->get($memcacheKey);

                if ($memcacheResult) {
                    return $memcacheResult;
                } else {
                    $newResult = self::_getRow();
                    if ($newResult) {
                        self::$memcacheObj->set($memcacheKey, $newResult, 0, self::$_expireTime);
                        return $newResult;
                    } else {
                        return NULL;
                    }
                }
            } else {
                return self::_getRow();
            }
        } catch (PDOException $e) {
            throw new Exception($e->getMessage());
        }
    }

    /*
     * @desc: get Row Helper Function
     * @return: array or stdObject
     */

    protected static function _getRow() {
        $sth = self::$db->prepare(self::$_query);
        $sth->execute(self::$_params);
        return $sth->fetch(self::$_fetchType);
    }

    /*
     * @return bool
     * @desc is Memcache Active ?
     */

    protected static function _isMemcacheActive() {
        if (self::$_memcache) {
            return true;
        } else {
            return false;
        }
    }

    /*
     * @desc: Preparing Memcache Object (Connection)
     */

    private static function _prepareMemcache() {

        if (self::$_memcache) {
            self::$memcacheObj = new Memcache();
            self::$memcacheObj->connect(MEMCACHE_HOST, MEMCACHE_PORT);
        }
    }

    /*
     * @todo: Adding Custom Filter and JOIN SQL PARAM Structure
     * @desc: only WHERE filter AND Query.
     */

    public static function findOne($tableName, $params) {

        self::$_tableName = (string) $tableName;
        $query = "SELECT * FROM " . self::$_tableName;
        if (is_array($params)) {
            $query.=" WHERE ";
            $count = 0;

            foreach ($params as $key => $value) {

                $fields2[':' . $key] = $value;
                if ($count > 0) {
                    $query.=$key . "=:" . $key;
                } else {
                    $query.=$key . "=:" . $key . " AND ";
                }
                $count++;
            }
            if ($count > 2) {
                $query = substr($query, 0, -1);
            }
        }
//echo $query;
//        }elseif(is_string($params)){
//            echo 2;
//            exit;
//        }

        try {
//MPSQL::_createSqlQuery()
            $sth = self::$db->prepare($query);
            $sth->execute($fields2);
            $result = $sth->fetch(self::$_fetchType);
            return $result;
        } catch (PDOException $e) {
            throw new Exception($e->getMessage());
//TODO exception logger
        }
        return self::$_tableName;
    }

    protected static function _createSqlQuery() {
        return "SELECT * FROM " . self::$_tableName . " WHERE id=:id ORDER BY id DESC LIMIT 1 ";
    }

    /*
     * @desc: Set Query PDO Result Fetching Type
     */

    public static function setFetchType($fetchType) {
        switch ($fetchType):
            case self::FETCH_ARRAY:
                self::$_fetchType = self::$_fetchTypes[$fetchType];
                break;
            case self::FETCH_OBJECT:
                self::$_fetchType = self::$_fetchTypes[$fetchType];
                break;
            default:
                self::$_fetchType = self::$_fetchTypes[self::FETCH_ARRAY];
                break;
        endswitch;
    }

    /*
     * @desc: Get MPSQL query result fetch type
     */

    static public function _getFetchType() {
        return self::$_fetchType;
    }

    /*
     * @desc: Get MPSQL Memcache Option Status
     */

    static public function _getStatusCache() {
        return self::$memcache;
    }

    public function __clone() {
        self::$db = clone self::$db;
    }

    public static function lastInsertId() {
        return self::$db->lastInsertId();
    }

}

?>
