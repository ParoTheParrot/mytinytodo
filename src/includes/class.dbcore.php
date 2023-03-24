<?php

/*
    This file is a part of myTinyTodo.
    (C) Copyright 2022 Max Pozdeev <maxpozdeev@gmail.com>
    Licensed under the GNU GPL version 2 or any later. See file COPYRIGHT for details.
*/


// FIXME: experimental, subject to change

class DBCore
{
    /** @var Database_Abstract $db */
    protected $db;

    /** @var DBCore $defaultdb */
    protected static $defaultInstance;

    /**
     *
     * @param Database_Abstract $db Value of DBConnection::instance() or similar
     * @return void
     */
    public function __construct(Database_Abstract $db) {
        $this->db = $db;
    }

    /**
     *
     * @return Database_Abstract
     * @throws Exception
     */
    public function connection()
    {
        if (!isset($this->db)) {
            throw new Exception("DBConnection is not set");
        }
        return $this->db;
    }

    /**
     *
     * @return DBCore
     * @throws Exception
     */
    public static function defaultInstance() : DBCore
    {
        if (!isset(self::$defaultInstance)) {
            throw new Exception("DBCore defaultInstance is not initialized");
        }
        return self::$defaultInstance;
    }

    /**
     *
     * @param DBCore $instance
     * @return void
     */
    public static function setDefaultInstance(DBCore $instance)
    {
        self::$defaultInstance = $instance;
    }

    /**
     *
     * @param int $id
     * @return int
     */
    public function getListIdByTaskId(int $id): int
    {
        $db = $this->db;
        $listId = (int)$db->sq("SELECT list_id FROM {$db->prefix}todolist WHERE id=". (int)$id);
        return $listId;
    }


    public function taskExists(int $id): bool
    {
        $db = $this->db;
        $count = (int) $db->sq("SELECT COUNT(*) FROM {$db->prefix}todolist WHERE id = $id");
        return ($count > 0) ? true : false;
    }


    public function getTaskById(int $id): ?array
    {
        $db = $this->db;
        $r = $db->sqa("
            SELECT todo.*, GROUP_CONCAT(tags.id) AS tags_ids, GROUP_CONCAT(tags.name) AS tags
            FROM {$db->prefix}todolist AS todo
            LEFT JOIN {$db->prefix}tag2task AS t2t ON todo.id = t2t.task_id
            LEFT JOIN {$db->prefix}tags AS tags ON t2t.tag_id = tags.id
            WHERE todo.id = $id
        ");
        return $r;
    }

    /**
     *
     * @param int $listId
     * @param string $sqlWhere
     * @param int|string $sort
     * @param null|int $limit
     * @return array
     */
    public function getTasksByListId(int $listId, string $sqlWhere, /* int|string */ $sort, ?int $limit = null): array
    {
        $db = $this->db;

        if ($sqlWhere != '') {
            $sqlWhere = "AND $sqlWhere";
        }

        $sqlSort = '';
        if (is_int($sort)) {
            $sqlSort = "ORDER BY compl ASC, ";
            if ($sort == 1) $sqlSort .= "prio DESC, ddn ASC, duedate ASC, ow ASC";          // byPrio
            elseif ($sort == 101) $sqlSort .= "prio ASC, ddn DESC, duedate DESC, ow DESC";  // byPrio (reverse)
            elseif ($sort == 2) $sqlSort .= "ddn ASC, duedate ASC, prio DESC, ow ASC";      // byDueDate
            elseif ($sort == 102) $sqlSort .= "ddn DESC, duedate DESC, prio ASC, ow DESC";  // byDueDate (reverse)
            elseif ($sort == 3) $sqlSort .= "d_created ASC, prio DESC, ow ASC";             // byDateCreated
            elseif ($sort == 103) $sqlSort .= "d_created DESC, prio ASC, ow DESC";          // byDateCreated (reverse)
            elseif ($sort == 4) $sqlSort .= "d_edited ASC, prio DESC, ow ASC";              // byDateModified
            elseif ($sort == 104) $sqlSort .= "d_edited DESC, prio ASC, ow DESC";           // byDateModified (reverse)
            elseif ($sort == 5) $sqlSort .= "title ASC, prio DESC, ow ASC";                 // byTitle
            elseif ($sort == 105) $sqlSort .= "title DESC, prio ASC, ow DESC";              // byTitle (reverse)
            else $sqlSort .= "ow ASC";
        }
        else if ($sort != '') {
            $sqlSort = "ORDER BY $sort";
        }

        $sqlLimit = '';
        if (!is_null($limit)) {
            $sqlLimit = "LIMIT $limit";
        }

        $q = $db->dq("
            SELECT todo.*, todo.duedate IS NULL AS ddn, GROUP_CONCAT(tags.id) AS tags_ids, GROUP_CONCAT(tags.name) AS tags
            FROM {$db->prefix}todolist AS todo
            LEFT JOIN {$db->prefix}tag2task AS t2t ON todo.id = t2t.task_id
            LEFT JOIN {$db->prefix}tags AS tags ON t2t.tag_id = tags.id
            WHERE todo.list_id = $listId  $sqlWhere
            GROUP BY todo.id
            $sqlSort
            $sqlLimit
        ");

        $data = array();
        while ($r = $q->fetchAssoc()) {
            $data[] = $r;
        }
        return $data;
    }
}

