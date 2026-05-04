<?php
/**
 * Middleware для проверки прав доступа
 */

class AuthMiddleware {
    
    /**
     * Проверка аутентификации пользователя
     */
    public static function isAuthenticated() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    /**
     * Получить текущего пользователя
     */
    public static function getCurrentUser() {
        if (!self::isAuthenticated()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'login' => $_SESSION['user_login'] ?? '',
            'roles' => $_SESSION['user_roles'] ?? [],
        ];
    }
    
    /**
     * Проверка наличия роли у пользователя
     */
    public static function hasRole($role) {
        if (!self::isAuthenticated()) {
            return false;
        }
        
        $userRoles = $_SESSION['user_roles'] ?? [];
        return in_array($role, $userRoles);
    }
    
    /**
     * Проверка наличия хотя бы одной из ролей
     */
    public static function hasAnyRole($roles) {
        if (!self::isAuthenticated()) {
            return false;
        }
        
        $userRoles = $_SESSION['user_roles'] ?? [];
        return !empty(array_intersect($roles, $userRoles));
    }
    
    /**
     * Требовать наличие роли (перенаправление при отсутствии)
     */
    public static function requireRole($role, $redirectUrl = '/login') {
        if (!self::hasRole($role)) {
            if (self::isAjaxRequest()) {
                http_response_code(403);
                echo json_encode(['error' => 'Доступ запрещён']);
                exit;
            }
            header('Location: ' . $redirectUrl);
            exit;
        }
    }
    
    /**
     * Требовать наличие хотя бы одной из ролей
     */
    public static function requireAnyRole($roles, $redirectUrl = '/login') {
        if (!self::hasAnyRole($roles)) {
            if (self::isAjaxRequest()) {
                http_response_code(403);
                echo json_encode(['error' => 'Доступ запрещён']);
                exit;
            }
            header('Location: ' . $redirectUrl);
            exit;
        }
    }
    
    /**
     * Проверка права доступа через конфигурацию
     */
    public static function hasPermission($permission) {
        if (!self::isAuthenticated()) {
            return false;
        }
        
        $config = require __DIR__ . '/../config/ido_config.php';
        $userRoles = $_SESSION['user_roles'] ?? [];
        
        foreach ($userRoles as $role) {
            if (isset($config['permissions'][$role][$permission])) {
                return $config['permissions'][$role][$permission] === true;
            }
        }
        
        return false;
    }
    
    /**
     * Требовать наличие права доступа
     */
    public static function requirePermission($permission, $redirectUrl = '/login') {
        if (!self::hasPermission($permission)) {
            if (self::isAjaxRequest()) {
                http_response_code(403);
                echo json_encode(['error' => 'Доступ запрещён']);
                exit;
            }
            header('Location: ' . $redirectUrl);
            exit;
        }
    }
    
    /**
     * Проверка AJAX запроса
     */
    private static function isAjaxRequest() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    }
    
    /**
     * Получить ID текущего пользователя
     */
    public static function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
}
