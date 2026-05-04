<?php
/**
 * Модель для работы с таблицей ido_programs
 */

class IdoProgram {
    private $db;
    
    public function __construct($pdo) {
        $this->db = $pdo;
    }
    
    /**
     * Получить все активные программы с информацией о типе
     */
    public function getActivePrograms($typeId = null) {
        $sql = "
            SELECT p.id, p.type_id, p.code, p.name, p.hours, p.doc_template, p.active, 
                   p.created_at, p.updated_at, t.name as type_name, t.code as type_code
            FROM ido_programs p
            LEFT JOIN ido_program_types t ON p.type_id = t.id
            WHERE p.active = 1
        ";
        
        if ($typeId !== null) {
            $sql .= " AND p.type_id = :type_id";
        }
        
        $sql .= " ORDER BY t.name ASC, p.name ASC";
        
        $stmt = $this->db->prepare($sql);
        if ($typeId !== null) {
            $stmt->execute([':type_id' => $typeId]);
        } else {
            $stmt->execute();
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Получить все программы (включая неактивные)
     */
    public function getAllPrograms() {
        $stmt = $this->db->prepare("
            SELECT p.id, p.type_id, p.code, p.name, p.hours, p.doc_template, p.active, 
                   p.created_at, p.updated_at, t.name as type_name
            FROM ido_programs p
            LEFT JOIN ido_program_types t ON p.type_id = t.id
            ORDER BY p.id ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Получить программу по ID
     */
    public function getById($id) {
        $stmt = $this->db->prepare("
            SELECT p.id, p.type_id, p.code, p.name, p.hours, p.doc_template, p.active, 
                   p.created_at, p.updated_at, t.name as type_name
            FROM ido_programs p
            LEFT JOIN ido_program_types t ON p.type_id = t.id
            WHERE p.id = :id
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Создать новую программу
     */
    public function create($data) {
        $stmt = $this->db->prepare("
            INSERT INTO ido_programs (type_id, code, name, hours, doc_template, active)
            VALUES (:type_id, :code, :name, :hours, :doc_template, :active)
        ");
        $stmt->execute([
            ':type_id' => $data['type_id'],
            ':code' => $data['code'] ?? null,
            ':name' => $data['name'],
            ':hours' => $data['hours'] ?? 0,
            ':doc_template' => $data['doc_template'] ?? null,
            ':active' => $data['active'] ?? 1,
        ]);
        return $this->db->lastInsertId();
    }
    
    /**
     * Обновить программу
     */
    public function update($id, $data) {
        $fields = [];
        foreach ($data as $key => $value) {
            if ($key !== 'id') {
                $fields[] = "$key = :$key";
            }
        }
        if (empty($fields)) {
            return false;
        }
        
        $sql = "UPDATE ido_programs SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        $params = $data;
        $params[':id'] = $id;
        
        return $stmt->execute($params);
    }
    
    /**
     * Мягкое удаление программы (установка active=0)
     */
    public function softDelete($id) {
        return $this->update($id, ['active' => 0]);
    }
    
    /**
     * Получить программы по типу
     */
    public function getByType($typeId, $activeOnly = true) {
        $sql = "
            SELECT id, type_id, code, name, hours, doc_template, active, created_at, updated_at
            FROM ido_programs
            WHERE type_id = :type_id
        ";
        
        if ($activeOnly) {
            $sql .= " AND active = 1";
        }
        
        $sql .= " ORDER BY name ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':type_id' => $typeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
