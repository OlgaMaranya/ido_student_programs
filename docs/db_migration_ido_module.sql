-- ============================================================================
-- Миграция: Создание модуля «Учёт дополнительных квалификаций ИДО»
-- Версия: 1.0
-- СУБД: MySQL 8.0
-- Кодировка: cp1251
-- ============================================================================

-- 1. Справочник типов программ ДПО
CREATE TABLE IF NOT EXISTS `ido_program_types` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор типа',
  `name` varchar(250) CHARACTER SET cp1251 COLLATE cp1251_general_ci NOT NULL COMMENT 'Название типа программы',
  `code` varchar(20) DEFAULT NULL COMMENT 'Внутренний префикс/код',
  `hours_min` smallint DEFAULT NULL COMMENT 'Рекомендуемый мин. объём часов',
  `active` int NOT NULL DEFAULT '1' COMMENT '1-активен, 0-скрыт из интерфейса',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=cp1251 COMMENT='Справочник типов программ ДПО';

-- 2. Справочник конкретных программ/курсов ИДО
CREATE TABLE IF NOT EXISTS `ido_programs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `type_id` int NOT NULL COMMENT 'Ссылка на ido_program_types.id',
  `code` varchar(20) DEFAULT NULL COMMENT 'Внутренний код программы',
  `name` varchar(250) CHARACTER SET cp1251 COLLATE cp1251_general_ci NOT NULL COMMENT 'Полное наименование программы',
  `hours` smallint DEFAULT '0' COMMENT 'Объём программы в часах',
  `doc_template` varchar(100) DEFAULT NULL COMMENT 'Код утверждённого шаблона документа',
  `active` int NOT NULL DEFAULT '1' COMMENT '1-активна, 0-архив/скрыта',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_prog_type_id` (`type_id`),
  CONSTRAINT `fk_prog_type_id` FOREIGN KEY (`type_id`) REFERENCES `ido_program_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=cp1251 COMMENT='Справочник программ ИДО';

-- 3. Учёт прохождения программ студентами/физлицами
-- Примечание: Таблицы stud_cards и persons должны существовать в ЕИС
CREATE TABLE IF NOT EXISTS `ido_student_programs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `stud_id` int NOT NULL COMMENT 'Ссылка на stud_cards.id (текущий статус)',
  `person_id` int NOT NULL COMMENT 'Ссылка на persons.id (сквозная идентификация)',
  `program_id` int NOT NULL COMMENT 'Ссылка на ido_programs.id',
  `start_date` date DEFAULT NULL COMMENT 'Дата зачисления на программу',
  `end_date` date DEFAULT NULL COMMENT 'Дата окончания/выдачи документа',
  `status` tinyint NOT NULL DEFAULT '1' COMMENT '1-записан, 2-в процессе, 3-успешно завершил, 4-не завершил, 5-отчислен',
  `doc_number` varchar(50) DEFAULT NULL COMMENT 'Номер итогового документа',
  `doc_date` date DEFAULT NULL COMMENT 'Дата выдачи документа',
  `comment` text CHARACTER SET cp1251 COLLATE cp1251_general_ci COMMENT 'Примечание оператора/ИДО',
  `created_by` int NOT NULL COMMENT 'user_id создавшего запись',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_isp_stud` (`stud_id`),
  KEY `fk_isp_person` (`person_id`),
  KEY `fk_isp_program` (`program_id`),
  CONSTRAINT `fk_isp_stud` FOREIGN KEY (`stud_id`) REFERENCES `stud_cards` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_isp_person` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_isp_program` FOREIGN KEY (`program_id`) REFERENCES `ido_programs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=cp1251 COMMENT='Учёт прохождения программ ИДО студентами вуза';

-- 4. Журнал изменений (аудит)
CREATE TABLE IF NOT EXISTS `ido_audit_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `record_id` int NOT NULL COMMENT 'id из ido_student_programs',
  `action` varchar(10) NOT NULL COMMENT 'INSERT, UPDATE, DELETE',
  `user_id` int NOT NULL COMMENT 'Кто выполнил действие',
  `old_data` json DEFAULT NULL,
  `new_data` json DEFAULT NULL,
  `event_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `record_id` (`record_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=cp1251 COMMENT='Журнал изменений в ИДО';

-- ============================================================================
-- Начальное наполнение справочников (Приложение А ТЗ)
-- ============================================================================

INSERT INTO `ido_program_types` (`id`, `name`, `code`, `hours_min`, `active`) VALUES
(1, 'Повышение квалификации', 'PK', 16, 1),
(2, 'Профессиональная переподготовка', 'PP', 250, 1),
(3, 'Профессиональное обучение (рабочая профессия)', 'PO', 100, 1),
(4, 'Тематические/целевые курсы', 'TC', 8, 1),
(5, 'Адаптационные программы', 'AP', 36, 1),
(6, 'Микроквалификация', 'MK', 16, 1)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- ============================================================================
-- Конец миграции
-- ============================================================================
