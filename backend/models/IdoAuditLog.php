<?php
/**
 * Модель для работы с журналом аудита ido_audit_log
 */

class IdoAuditLog {
    private $db;
    
    public function __construct($pdo) {
        $this->db = $pdo;
    }
    
    /**
     * Записать событие в журнал аудита
     */
    public function log($recordId, $action, $userId, $oldData = null, $newData = null) {
        $stmt = $this->db->prepare("
            INSERT INTO ido_audit_log (record_id, action, user_id, old_data, new_data)
            VALUES (:record_id, :action, :user_id, :old_data, :new_data)
        ");
        
        $oldJson = $oldData !== null ? json_encode($oldData, JSON_UNESCAPED_UNICODE) : null;
        $newJson = $newData !== null ? json_encode($newData, JSON_UNESCAPED_UNICODE) : null;
        
        return $stmt->execute([
            ':record_id' => $recordId,
            ':action' => $action,
            ':user_id' => $userId,
            ':old_data' => $oldJson,
            ':new_data' => $newJson,
        ]);
    }
    
    /**
     * Получить историю изменений для записи
     */
    public function getByRecordId($recordId) {
        $stmt = $this->db->prepare("
            SELECT id, record_id, action, user_id, old_data, new_data, event_time
            FROM ido_audit_log
            WHERE record_id = :record_id
            ORDER BY event_time DESC
        ");
        $stmt->execute([':record_id' => $recordId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Получить все логи с фильтрацией
     */
    public function getAll($filters = []) {
        $sql = "
            SELECT al.id, al.record_id, al.action, al.user_id, al.old_data, al.new_data, al.event_time,
                   u.login as user_login
            FROM ido_audit_log al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE 1=1
        ";
        
        $params = [];
        
        if (!empty($filters['user_id'])) {
            $sql .= " AND al.user_id = :user_id";
            $params[':user_id'] = $filters['user_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND al.event_time >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND al.event_time <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }
        
        if (!empty($filters['action'])) {
            $sql .= " AND al.action = :action";
            $params[':action'] = $filters['action'];
        }
        
        $sql .= " ORDER BY al.event_time DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Очистить старые логи (старше указанного количества дней)
     */
    public function cleanup($daysToKeep) {
        $stmt = $this->db->prepare("
            DELETE FROM ido_audit_log
            WHERE event_time < DATE_SUB(NOW(), INTERVAL :days DAY)
        ");
        return $stmt->execute([':days' => $daysToKeep]);
    }
}
