<?php
/**
 * Конфигурация модуля ИДО
 */

return [
    // Настройки подключения к БД (использует существующее подключение ЕИС)
    'database' => [
        'charset' => 'cp1251',
        'collation' => 'cp1251_general_ci',
    ],
    
    // Статусы записей в ido_student_programs
    'statuses' => [
        1 => 'Записан',
        2 => 'В процессе',
        3 => 'Успешно завершил',
        4 => 'Не завершил',
        5 => 'Отчислен',
    ],
    
    // Роли доступа
    'roles' => [
        'ido_admin' => 1,
        'ido_operator' => 2,
        'dekanat_read' => 3,
        'analyst' => 4,
    ],
    
    // Права доступа по ролям
    'permissions' => [
        'ido_admin' => [
            'programs CRUD' => true,
            'program_types CRUD' => true,
            'student_programs CRUD' => true,
            'reports view' => true,
            'reports export' => true,
            'audit view' => true,
        ],
        'ido_operator' => [
            'programs CRUD' => false,
            'program_types CRUD' => false,
            'student_programs CRUD' => true,
            'reports view' => true,
            'reports export' => true,
            'audit view' => false,
        ],
        'dekanat_read' => [
            'programs CRUD' => false,
            'program_types CRUD' => false,
            'student_programs CRUD' => false,
            'reports view' => true,
            'reports export' => false,
            'audit view' => false,
        ],
        'analyst' => [
            'programs CRUD' => false,
            'program_types CRUD' => false,
            'student_programs CRUD' => false,
            'reports view' => true,
            'reports export' => true,
            'audit view' => false,
        ],
    ],
    
    // Настройки экспорта
    'export' => [
        'formats' => ['xlsx', 'csv'],
        'max_rows' => 5000,
        'filename_prefix' => 'IDO_report',
    ],
    
    // Настройки пагинации
    'pagination' => [
        'per_page' => 50,
    ],
    
    // Логирование
    'logging' => [
        'enabled' => true,
        'retention_days' => 1095, // 3 года
    ],
];
