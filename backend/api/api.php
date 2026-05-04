<?php
/**
 * API контроллер для работы со справочниками и программами ИДО
 */

require_once __DIR__ . '/../models/IdoProgramType.php';
require_once __DIR__ . '/../models/IdoProgram.php';
require_once __DIR__ . '/../models/IdoStudentProgram.php';
require_once __DIR__ . '/../models/IdoAuditLog.php';
require_once __DIR__ . '/../services/StudentSearchService.php';
require_once __DIR__ . '/../services/ExportService.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class IdoApiController {
    private $db;
    private $programTypeModel;
    private $programModel;
    private $studentProgramModel;
    private $auditLogModel;
    private $studentSearchService;
    private $exportService;
    
    public function __construct($pdo) {
        $this->db = $pdo;
        $this->programTypeModel = new IdoProgramType($pdo);
        $this->programModel = new IdoProgram($pdo);
        $this->studentProgramModel = new IdoStudentProgram($pdo);
        $this->auditLogModel = new IdoAuditLog($pdo);
        $this->studentSearchService = new StudentSearchService($pdo);
        $this->exportService = new ExportService();
    }
    
    /**
     * Обработка запроса
     */
    public function handleRequest() {
        header('Content-Type: application/json; charset=utf-8');
        
        // Проверка аутентификации
        if (!AuthMiddleware::isAuthenticated()) {
            http_response_code(401);
            echo json_encode(['error' => 'Требуется авторизация']);
            return;
        }
        
        $action = $_GET['action'] ?? '';
        $method = $_SERVER['REQUEST_METHOD'];
        
        try {
            switch ($action) {
                // Справочник типов программ
                case 'program_types_list':
                    $this->getProgramTypes();
                    break;
                case 'program_type_create':
                    $this->createProgramType();
                    break;
                case 'program_type_update':
                    $this->updateProgramType();
                    break;
                case 'program_type_delete':
                    $this->deleteProgramType();
                    break;
                
                // Справочник программ
                case 'programs_list':
                    $this->getPrograms();
                    break;
                case 'program_create':
                    $this->createProgram();
                    break;
                case 'program_update':
                    $this->updateProgram();
                    break;
                case 'program_delete':
                    $this->deleteProgram();
                    break;
                
                // Программы студентов
                case 'student_programs_list':
                    $this->getStudentPrograms();
                    break;
                case 'student_program_create':
                    $this->createStudentProgram();
                    break;
                case 'student_program_update':
                    $this->updateStudentProgram();
                    break;
                case 'student_program_delete':
                    $this->deleteStudentProgram();
                    break;
                
                // Поиск студентов
                case 'students_search':
                    $this->searchStudents();
                    break;
                
                // Отчёты
                case 'report_data':
                    $this->getReportData();
                    break;
                case 'report_export':
                    $this->exportReport();
                    break;
                
                // Аудит
                case 'audit_log':
                    $this->getAuditLog();
                    break;
                
                default:
                    http_response_code(400);
                    echo json_encode(['error' => 'Неизвестное действие']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Ошибка сервера: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Получить список типов программ
     */
    private function getProgramTypes() {
        AuthMiddleware::requireAnyRole(['ido_admin', 'ido_operator', 'dekanat_read', 'analyst']);
        
        $all = isset($_GET['all']) && $_GET['all'] == '1';
        $data = $all ? 
            $this->programTypeModel->getAllTypes() : 
            $this->programTypeModel->getActiveTypes();
        
        echo json_encode(['success' => true, 'data' => $data]);
    }
    
    /**
     * Создать тип программы
     */
    private function createProgramType() {
        AuthMiddleware::requireRole('ido_admin');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Метод не разрешён']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['name'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Название обязательно']);
            return;
        }
        
        $id = $this->programTypeModel->create($data);
        
        // Логирование
        $this->auditLogModel->log($id, 'INSERT', AuthMiddleware::getUserId(), null, $data);
        
        echo json_encode(['success' => true, 'id' => $id]);
    }
    
    /**
     * Обновить тип программы
     */
    private function updateProgramType() {
        AuthMiddleware::requireRole('ido_admin');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Метод не разрешён']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? 0;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID обязателен']);
            return;
        }
        
        $oldData = $this->programTypeModel->getById($id);
        $this->programTypeModel->update($id, $data);
        $newData = $this->programTypeModel->getById($id);
        
        // Логирование
        $this->auditLogModel->log($id, 'UPDATE', AuthMiddleware::getUserId(), $oldData, $newData);
        
        echo json_encode(['success' => true]);
    }
    
    /**
     * Удалить тип программы (мягкое удаление)
     */
    private function deleteProgramType() {
        AuthMiddleware::requireRole('ido_admin');
        
        $id = $_GET['id'] ?? 0;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID обязателен']);
            return;
        }
        
        if (!$this->programTypeModel->canDelete($id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Нельзя удалить тип с активными программами']);
            return;
        }
        
        $oldData = $this->programTypeModel->getById($id);
        $this->programTypeModel->softDelete($id);
        
        // Логирование
        $this->auditLogModel->log($id, 'DELETE', AuthMiddleware::getUserId(), $oldData, null);
        
        echo json_encode(['success' => true]);
    }
    
    /**
     * Получить список программ
     */
    private function getPrograms() {
        AuthMiddleware::requireAnyRole(['ido_admin', 'ido_operator', 'dekanat_read', 'analyst']);
        
        $typeId = $_GET['type_id'] ?? null;
        $all = isset($_GET['all']) && $_GET['all'] == '1';
        
        $data = $all ? 
            $this->programModel->getAllPrograms() : 
            $this->programModel->getActivePrograms($typeId);
        
        echo json_encode(['success' => true, 'data' => $data]);
    }
    
    /**
     * Создать программу
     */
    private function createProgram() {
        AuthMiddleware::requireRole('ido_admin');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Метод не разрешён']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['type_id']) || empty($data['name'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Тип и название обязательны']);
            return;
        }
        
        $id = $this->programModel->create($data);
        
        // Логирование
        $this->auditLogModel->log($id, 'INSERT', AuthMiddleware::getUserId(), null, $data);
        
        echo json_encode(['success' => true, 'id' => $id]);
    }
    
    /**
     * Обновить программу
     */
    private function updateProgram() {
        AuthMiddleware::requireRole('ido_admin');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Метод не разрешён']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? 0;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID обязателен']);
            return;
        }
        
        $oldData = $this->programModel->getById($id);
        $this->programModel->update($id, $data);
        $newData = $this->programModel->getById($id);
        
        // Логирование
        $this->auditLogModel->log($id, 'UPDATE', AuthMiddleware::getUserId(), $oldData, $newData);
        
        echo json_encode(['success' => true]);
    }
    
    /**
     * Удалить программу (мягкое удаление)
     */
    private function deleteProgram() {
        AuthMiddleware::requireRole('ido_admin');
        
        $id = $_GET['id'] ?? 0;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID обязателен']);
            return;
        }
        
        $oldData = $this->programModel->getById($id);
        $this->programModel->softDelete($id);
        
        // Логирование
        $this->auditLogModel->log($id, 'DELETE', AuthMiddleware::getUserId(), $oldData, null);
        
        echo json_encode(['success' => true]);
    }
    
    /**
     * Получить программы студента
     */
    private function getStudentPrograms() {
        AuthMiddleware::requireAnyRole(['ido_admin', 'ido_operator', 'dekanat_read', 'analyst']);
        
        $personId = $_GET['person_id'] ?? 0;
        
        if (!$personId) {
            http_response_code(400);
            echo json_encode(['error' => 'person_id обязателен']);
            return;
        }
        
        $data = $this->studentProgramModel->getByPersonId($personId);
        
        // Добавляем статусы текстом
        $config = require __DIR__ . '/../config/ido_config.php';
        foreach ($data as &$record) {
            $record['status_text'] = $config['statuses'][$record['status']] ?? '';
        }
        
        echo json_encode(['success' => true, 'data' => $data]);
    }
    
    /**
     * Создать запись о программе студента
     */
    private function createStudentProgram() {
        AuthMiddleware::requireAnyRole(['ido_admin', 'ido_operator']);
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Метод не разрешён']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['stud_id']) || empty($data['person_id']) || empty($data['program_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'stud_id, person_id и program_id обязательны']);
            return;
        }
        
        $data['created_by'] = AuthMiddleware::getUserId();
        $id = $this->studentProgramModel->create($data);
        
        // Логирование
        $this->auditLogModel->log($id, 'INSERT', AuthMiddleware::getUserId(), null, $data);
        
        echo json_encode(['success' => true, 'id' => $id]);
    }
    
    /**
     * Обновить запись о программе студента
     */
    private function updateStudentProgram() {
        AuthMiddleware::requireAnyRole(['ido_admin', 'ido_operator']);
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Метод не разрешён']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? 0;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID обязателен']);
            return;
        }
        
        $oldData = $this->studentProgramModel->getById($id);
        $this->studentProgramModel->update($id, $data);
        $newData = $this->studentProgramModel->getById($id);
        
        // Логирование
        $this->auditLogModel->log($id, 'UPDATE', AuthMiddleware::getUserId(), $oldData, $newData);
        
        echo json_encode(['success' => true]);
    }
    
    /**
     * Удалить запись о программе студента
     */
    private function deleteStudentProgram() {
        AuthMiddleware::requireAnyRole(['ido_admin', 'ido_operator']);
        
        $id = $_GET['id'] ?? 0;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID обязателен']);
            return;
        }
        
        $oldData = $this->studentProgramModel->getById($id);
        $this->studentProgramModel->delete($id);
        
        // Логирование
        $this->auditLogModel->log($id, 'DELETE', AuthMiddleware::getUserId(), $oldData, null);
        
        echo json_encode(['success' => true]);
    }
    
    /**
     * Поиск студентов
     */
    private function searchStudents() {
        AuthMiddleware::requireAnyRole(['ido_admin', 'ido_operator', 'dekanat_read', 'analyst']);
        
        $query = $_GET['q'] ?? '';
        $filters = [
            'nzk' => $_GET['nzk'] ?? '',
            'group_id' => $_GET['group_id'] ?? '',
            'faculty_id' => $_GET['faculty_id'] ?? '',
            'course' => $_GET['course'] ?? '',
        ];
        
        $data = $this->studentSearchService->search($query, $filters);
        
        echo json_encode(['success' => true, 'data' => $data]);
    }
    
    /**
     * Получить данные для отчёта
     */
    private function getReportData() {
        AuthMiddleware::requireAnyRole(['ido_admin', 'ido_operator', 'dekanat_read', 'analyst']);
        
        $filters = [
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? '',
            'faculty_id' => $_GET['faculty_id'] ?? '',
            'group_id' => $_GET['group_id'] ?? '',
            'type_ids' => $_GET['type_ids'] ?? [],
            'statuses' => $_GET['statuses'] ?? [],
            'has_document' => isset($_GET['has_document']) ? $_GET['has_document'] : '',
        ];
        
        // Обработка множественных параметров
        if (isset($_GET['type_ids']) && is_string($_GET['type_ids'])) {
            $filters['type_ids'] = explode(',', $_GET['type_ids']);
        }
        if (isset($_GET['statuses']) && is_string($_GET['statuses'])) {
            $filters['statuses'] = explode(',', $_GET['statuses']);
        }
        
        $data = $this->studentProgramModel->search($filters);
        
        // Добавляем текстовые статусы
        $config = require __DIR__ . '/../config/ido_config.php';
        foreach ($data as &$record) {
            $record['status_text'] = $config['statuses'][$record['status']] ?? '';
        }
        
        echo json_encode(['success' => true, 'data' => $data]);
    }
    
    /**
     * Экспорт отчёта
     */
    private function exportReport() {
        AuthMiddleware::requireAnyRole(['ido_admin', 'ido_operator', 'analyst']);
        
        $format = $_GET['format'] ?? 'csv';
        $filters = [
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? '',
            'faculty_id' => $_GET['faculty_id'] ?? '',
            'group_id' => $_GET['group_id'] ?? '',
            'type_ids' => $_GET['type_ids'] ?? [],
            'statuses' => $_GET['statuses'] ?? [],
            'has_document' => isset($_GET['has_document']) ? $_GET['has_document'] : '',
        ];
        
        // Обработка множественных параметров
        if (isset($_GET['type_ids']) && is_string($_GET['type_ids'])) {
            $filters['type_ids'] = explode(',', $_GET['type_ids']);
        }
        if (isset($_GET['statuses']) && is_string($_GET['statuses'])) {
            $filters['statuses'] = explode(',', $_GET['statuses']);
        }
        
        $data = $this->studentProgramModel->search($filters);
        
        if (empty($data)) {
            http_response_code(404);
            echo json_encode(['error' => 'Нет данных для экспорта']);
            return;
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "IDO_report_{$timestamp}";
        
        if ($format === 'xlsx') {
            $filePath = $this->exportService->exportToXlsx($data, $filename);
            $mimeType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        } else {
            $filePath = $this->exportService->exportToCsv($data, $filename);
            $mimeType = 'text/csv; charset=utf-8';
        }
        
        if (file_exists($filePath)) {
            header('Content-Type: ' . $mimeType);
            header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            unlink($filePath); // Удаляем временный файл
            exit;
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Ошибка создания файла']);
        }
    }
    
    /**
     * Получить журнал аудита
     */
    private function getAuditLog() {
        AuthMiddleware::requireRole('ido_admin');
        
        $filters = [
            'user_id' => $_GET['user_id'] ?? '',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? '',
            'action' => $_GET['action_filter'] ?? '',
        ];
        
        $data = $this->auditLogModel->getAll($filters);
        
        echo json_encode(['success' => true, 'data' => $data]);
    }
}

// Инициализация API (если вызывается напрямую)
if (basename($_SERVER['PHP_SELF']) === 'api.php') {
    require_once __DIR__ . '/../../includes/db_connect.php'; // Подключение к БД ЕИС
    
    global $pdo;
    $controller = new IdoApiController($pdo);
    $controller->handleRequest();
}
