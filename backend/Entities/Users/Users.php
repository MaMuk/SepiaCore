<?php
namespace SepiaCore\Entities\Users;

use SepiaCore\Entities\BaseEntity;

class Users extends BaseEntity
{
    public function __construct($table = 'users')
    {
        parent::__construct($table); // Users table
    }
/*    public function read($id = null, $page = 1, $limit = 10, $sortBy = 'date_created', $sortOrder = 'DESC', $filters = null) {
        //todo: we need to filter sensitive fields from user records before returning it via api.
        if ($id) {
            $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            //return $stmt->fetch(\PDO::FETCH_ASSOC);
            $record = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $record ? $this->transformRecord($record) : null;
        }
        else {
            $query = "SELECT * FROM {$this->table}";

            if ($filters && is_array($filters)) {
                $filterClauses = [];
                $filterParams = [];
                foreach ($filters as $field => $value) {
                    $filterClauses[] = "{$field} LIKE :{$field}";
                    $filterParams[":{$field}"] = '%' . $value . '%';
                }
                if (count($filterClauses) > 0) {
                    $query .= " WHERE " . implode(' AND ', $filterClauses);
                }
            }

            $stmt = $this->pdo->prepare($query);

            if (isset($filterParams)) {
                foreach ($filterParams as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
            }

            $stmt->execute();
            $records = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return array_map([$this, 'transformRecord'], $records);
        }
    }*/
}