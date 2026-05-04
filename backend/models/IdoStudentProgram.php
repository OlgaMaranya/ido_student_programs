<?php
/**
 * Модель для работы с таблицей ido_student_programs
 */

class IdoStudentProgram {
    private $db;
    
    public function __construct($pdo) {
        $this->db = $pdo;
    }
    
    /**
     * Получить записи студента по person_id (сквозная идентификация)
     */
    public function getByPersonId($personId) {
        $stmt = $this->db->prepare("
            SELECT isp.id, isp.stud_id, isp.person_id, isp.program_id, 
                   isp.start_date, isp.end_date, isp.status, isp.doc_number, 
                   isp.doc_date, isp.comment, isp.created_by, isp.created_at, isp.updated_at,
                   p.name as program_name, t.name as type_name, t.code as type_code
            FROM ido_student_programs isp
            LEFT JOIN ido_programs p ON isp.program_id = p.id
            LEFT JOIN ido_program_types t ON p.type_id = t.id
            WHERE isp.person_id = :person_id
            ORDER BY isp.created_at DESC
        ");
        $stmt->execute([':person_id' => $personId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Получить запись по ID
     */
    public function getById($id) {
        $stmt = $this->db->prepare("
            SELECT isp.id, isp.stud_id, isp.person_id, isp.program_id, 
                   isp.start_date, isp.end_date, isp.status, isp.doc_number, 
                   isp.doc_date, isp.comment, isp.created_by, isp.created_at, isp.updated_at,
                   p.name as program_name, t.name as type_name
            FROM ido_student_programs isp
            LEFT JOIN ido_programs p ON isp.program_id = p.id
            LEFT JOIN ido_program_types t ON p.type_id = t.id
            WHERE isp.id = :id
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Создать новую запись о программе студента
     */
    public function create($data) {
        $stmt = $this->db->prepare("
            INSERT INTO ido_student_programs 
            (stud_id, person_id, program_id, start_date, end_date, status, doc_number, doc_date, comment, created_by)
            VALUES (:stud_id, :person_id, :program_id, :start_date, :end_date, :status, :doc_number, :doc_date, :comment, :created_by)
        ");
        $stmt->execute([
            ':stud_id' => $data['stud_id'],
            ':person_id' => $data['person_id'],
            ':program_id' => $data['program_id'],
            ':start_date' => $data['start_date'] ?? null,
            ':end_date' => $data['end_date'] ?? null,
            ':status' => $data['status'] ?? 1,
            ':doc_number' => $data['doc_number'] ?? null,
            ':doc_date' => $data['doc_date'] ?? null,
            ':comment' => $data['comment'] ?? null,
            ':created_by' => $data['created_by'],
        ]);
        return $this->db->lastInsertId();
    }
    
    /**
     * Обновить запись
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
        
        $sql = "UPDATE ido_student_programs SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        $params = $data;
        $params[':id'] = $id;
        
        return $stmt->execute($params);
    }
    
    /**
     * Удалить запись
     */
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM ido_student_programs WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
    
    /**
     * Поиск записей для отчёта с фильтрами
     */
    public function search($filters = []) {
        $sql = "
            SELECT 
                CONCAT(p.surname, ' ', p.name, ' ', p.patronymic) as fio,
                sg.name as group_name,
                sg.course,
                f.name as faculty_name,
                t.name as type_name,
                pr.name as program_name,
                isp.status,
                isp.doc_number,
                isp.doc_date,
                isp.start_date,
                isp.end_date
            FROM ido_student_programs isp
            LEFT JOIN persons p ON isp.person_id = p.id
            LEFT JOIN stud_cards sc ON isp.stud_id = sc.id
            LEFT JOIN stud_groups sg ON sc.group_id = sg.id
            LEFT JOIN faculties f ON sg.faculty_id = f.id
            LEFT JOIN ido_programs pr ON isp.program_id = pr.id
            LEFT JOIN ido_program_types t ON pr.type_id = t.id
            WHERE 1=1
        ";
        
        $params = [];
        
        // Фильтр по периоду
        if (!empty($filters['date_from'])) {
            $sql .= " AND (isp.start_date >= :date_from OR isp.end_date >= :date_from)";
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND (isp.start_date <= :date_to OR isp.end_date <= :date_to)";
            $params[':date_to'] = $filters['date_to'];
        }
        
        // Фильтр по факультету
        if (!empty($filters['faculty_id'])) {
            $sql .= " AND sg.faculty_id = :faculty_id";
            $params[':faculty_id'] = $filters['faculty_id'];
        }
        
        // Фильтр по группе
        if (!empty($filters['group_id'])) {
            $sql .= " AND sg.id = :group_id";
            $params[':group_id'] = $filters['group_id'];
        }
        
        // Фильтр по типу программы
        if (!empty($filters['type_ids'])) {
            $typeIds = is_array($filters['type_ids']) ? $filters['type_ids'] : [$filters['type_ids']];
            $placeholders = implode(',', array_map(function($i) { return ":type_id_$i"; }, range(0, count($typeIds) - 1)));
            $sql .= " AND t.id IN ($placeholders)";
            foreach ($typeIds as $i => $typeId) {
                $params[":type_id_$i"] = $typeId;
            }
        }
        
        // Фильтр по статусу
        if (!empty($filters['statuses'])) {
            $statuses = is_array($filters['statuses']) ? $filters['statuses'] : [$filters['statuses']];
            $placeholders = implode(',', array_map(function($i) { return ":status_$i"; }, range(0, count($statuses) - 1)));
            $sql .= " AND isp.status IN ($placeholders)";
            foreach ($statuses as $i => $status) {
                $params[":status_$i"] = $status;
            }
        }
        
        // Фильтр по наличию документа
        if (isset($filters['has_document']) && $filters['has_document'] !== '') {
            if ($filters['has_document']) {
                $sql .= " AND isp.doc_number IS NOT NULL AND isp.doc_number != ''";
            } else {
                $sql .= " AND (isp.doc_number IS NULL OR isp.doc_number = '')";
            }
        }
        
        $sql .= " ORDER BY isp.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Получить количество записей для пагинации
     */
    public function countSearch($filters = []) {
        $results = $this->search($filters);
        return count($results);
    }
}
