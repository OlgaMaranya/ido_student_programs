<?php
/**
 * Сервис для работы со студентами (поиск в ЕИС)
 */

class StudentSearchService {
    private $db;
    
    public function __construct($pdo) {
        $this->db = $pdo;
    }
    
    /**
     * Поиск студентов по ФИО, зачётке, группе или факультету
     * @param string $query Поисковый запрос
     * @param array $filters Дополнительные фильтры
     * @return array Результаты поиска
     */
    public function search($query = '', $filters = []) {
        $sql = "
            SELECT 
                p.id as person_id,
                CONCAT(p.surname, ' ', p.name, ' ', p.patronymic) as fio,
                p.surname,
                p.name,
                p.patronymic,
                sc.id as stud_id,
                sc.nzk,
                sg.id as group_id,
                sg.name as group_name,
                sg.course,
                f.id as faculty_id,
                f.name as faculty_name
            FROM persons p
            INNER JOIN stud_cards sc ON p.id = sc.person_id
            INNER JOIN stud_groups sg ON sc.group_id = sg.id
            INNER JOIN faculties f ON sg.faculty_id = f.id
            WHERE sc.status = 1 AND p.is_deleted = 0
        ";
        
        $params = [];
        
        // Поиск по ФИО
        if (!empty($query)) {
            $sql .= " AND (
                CONCAT(p.surname, ' ', p.name, ' ', p.patronymic) LIKE :query
                OR p.surname LIKE :query
                OR p.name LIKE :query
                OR p.patronymic LIKE :query
            )";
            $params[':query'] = "%$query%";
        }
        
        // Поиск по номеру зачётной книжки
        if (!empty($filters['nzk'])) {
            $sql .= " AND sc.nzk LIKE :nzk";
            $params[':nzk'] = "%" . $filters['nzk'] . "%";
        }
        
        // Фильтр по группе
        if (!empty($filters['group_id'])) {
            $sql .= " AND sg.id = :group_id";
            $params[':group_id'] = $filters['group_id'];
        }
        
        // Фильтр по факультету
        if (!empty($filters['faculty_id'])) {
            $sql .= " AND sg.faculty_id = :faculty_id";
            $params[':faculty_id'] = $filters['faculty_id'];
        }
        
        // Фильтр по курсу
        if (!empty($filters['course'])) {
            $sql .= " AND sg.course = :course";
            $params[':course'] = $filters['course'];
        }
        
        $sql .= " ORDER BY p.surname ASC, p.name ASC, p.patronymic ASC";
        $sql .= " LIMIT 50";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Получить информацию о студенте по person_id
     */
    public function getByPersonId($personId) {
        $stmt = $this->db->prepare("
            SELECT 
                p.id as person_id,
                CONCAT(p.surname, ' ', p.name, ' ', p.patronymic) as fio,
                p.surname,
                p.name,
                p.patronymic,
                sc.id as stud_id,
                sc.nzk,
                sg.id as group_id,
                sg.name as group_name,
                sg.course,
                f.id as faculty_id,
                f.name as faculty_name
            FROM persons p
            INNER JOIN stud_cards sc ON p.id = sc.person_id
            INNER JOIN stud_groups sg ON sc.group_id = sg.id
            INNER JOIN faculties f ON sg.faculty_id = f.id
            WHERE p.id = :person_id AND sc.status = 1
            LIMIT 1
        ");
        $stmt->execute([':person_id' => $personId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Получить информацию о студенте по stud_id
     */
    public function getByStudId($studId) {
        $stmt = $this->db->prepare("
            SELECT 
                p.id as person_id,
                CONCAT(p.surname, ' ', p.name, ' ', p.patronymic) as fio,
                p.surname,
                p.name,
                p.patronymic,
                sc.id as stud_id,
                sc.nzk,
                sg.id as group_id,
                sg.name as group_name,
                sg.course,
                f.id as faculty_id,
                f.name as faculty_name
            FROM stud_cards sc
            INNER JOIN persons p ON sc.person_id = p.id
            INNER JOIN stud_groups sg ON sc.group_id = sg.id
            INNER JOIN faculties f ON sg.faculty_id = f.id
            WHERE sc.id = :stud_id
            LIMIT 1
        ");
        $stmt->execute([':stud_id' => $studId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
