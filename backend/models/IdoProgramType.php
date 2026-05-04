<?php
/**
 * Модель для работы с таблицей ido_program_types
 */

class IdoProgramType {
    private $db;
    
    public function __construct($pdo) {
        $this->db = $pdo;
    }
    
    /**
     * Получить все активные типы программ
     */
    public function getActiveTypes() {
        $stmt = $this->db->prepare("
            SELECT id, name, code, hours_min, active, created_at
            FROM ido_program_types
            WHERE active = 1
            ORDER BY name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Получить все типы программ (включая неактивные)
     */
    public function getAllTypes() {
        $stmt = $this->db->prepare("
            SELECT id, name, code, hours_min, active, created_at
            FROM ido_program_types
            ORDER BY id ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Получить тип программы по ID
     */
    public function getById($id) {
        $stmt = $this->db->prepare("
            SELECT id, name, code, hours_min, active, created_at
            FROM ido_program_types
            WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Создать новый тип программы
     */
    public function create($data) {
        $stmt = $this->db->prepare("
            INSERT INTO ido_program_types (name, code, hours_min, active)
            VALUES (:name, :code, :hours_min, :active)
        ");
        $stmt->execute([
            ':name' => $data['name'],
            ':code' => $data['code'] ?? null,
            ':hours_min' => $data['hours_min'] ?? null,
            ':active' => $data['active'] ?? 1,
        ]);
        return $this->db->lastInsertId();
    }
    
    /**
     * Обновить тип программы
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
        
        $sql = "UPDATE ido_program_types SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        $params = $data;
        $params[':id'] = $id;
        
        return $stmt->execute($params);
    }
    
    /**
     * Мягкое удаление типа программы (установка active=0)
     */
    public function softDelete($id) {
        return $this->update($id, ['active' => 0]);
    }
    
    /**
     * Проверка, можно ли удалить тип (нет связанных программ)
     */
    public function canDelete($id) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count FROM ido_programs WHERE type_id = :id AND active = 1
        ");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] == 0;
    }
}
