/**
 * JavaScript приложение модуля ИДО
 */

// Глобальные переменные
let selectedStudent = null;
const API_BASE = '../backend/api/api.php';

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    loadProgramTypes();
    loadReportData();
    setupSearchHandler();
});

/**
 * Настройка обработчика поиска студентов
 */
function setupSearchHandler() {
    const searchInput = document.getElementById('studentSearch');
    const resultsDiv = document.getElementById('searchResults');
    let debounceTimer;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        const query = this.value.trim();
        
        if (query.length < 2) {
            resultsDiv.style.display = 'none';
            return;
        }
        
        debounceTimer = setTimeout(() => {
            searchStudents(query);
        }, 300);
    });
    
    // Закрытие результатов при клике вне
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !resultsDiv.contains(e.target)) {
            resultsDiv.style.display = 'none';
        }
    });
}

/**
 * Поиск студентов через API
 */
async function searchStudents(query) {
    try {
        const response = await fetch(`${API_BASE}?action=students_search&q=${encodeURIComponent(query)}`);
        const result = await response.json();
        
        const resultsDiv = document.getElementById('searchResults');
        
        if (result.success && result.data.length > 0) {
            resultsDiv.innerHTML = result.data.map(student => `
                <div class="search-result-item" onclick="selectStudent(${student.person_id}, ${student.stud_id})">
                    <strong>${escapeHtml(student.fio)}</strong><br>
                    <small>Группа: ${escapeHtml(student.group_name)} | Зачётка: ${escapeHtml(student.nzk)}</small>
                </div>
            `).join('');
            resultsDiv.style.display = 'block';
        } else {
            resultsDiv.innerHTML = '<div class="search-result-item">Студенты не найдены</div>';
            resultsDiv.style.display = 'block';
        }
    } catch (error) {
        console.error('Ошибка поиска:', error);
    }
}

/**
 * Выбор студента из результатов поиска
 */
function selectStudent(personId, studId) {
    selectedStudent = { person_id: personId, stud_id: studId };
    document.getElementById('searchResults').style.display = 'none';
    openStudentCard();
}

/**
 * Открытие карточки студента
 */
async function openStudentCard() {
    if (!selectedStudent) {
        alert('Пожалуйста, выберите студента из результатов поиска');
        return;
    }
    
    try {
        // Загрузка информации о студенте
        const response = await fetch(`${API_BASE}?action=students_search&person_id=${selectedStudent.person_id}`);
        const result = await response.json();
        
        if (result.success && result.data.length > 0) {
            const student = result.data[0];
            document.getElementById('modalTitle').textContent = student.fio;
            document.getElementById('studentInfo').innerHTML = `
                <h3>${escapeHtml(student.fio)}</h3>
                <p><strong>Группа:</strong> ${escapeHtml(student.group_name)}</p>
                <p><strong>Курс:</strong> ${student.course}</p>
                <p><strong>Факультет:</strong> ${escapeHtml(student.faculty_name)}</p>
                <p><strong>Зачётная книжка:</strong> ${escapeHtml(student.nzk)}</p>
            `;
            
            // Загрузка программ студента
            await loadStudentPrograms(selectedStudent.person_id);
            
            document.getElementById('studentCardModal').style.display = 'block';
        }
    } catch (error) {
        console.error('Ошибка загрузки карточки:', error);
        alert('Ошибка загрузки данных студента');
    }
}

/**
 * Загрузка программ студента
 */
async function loadStudentPrograms(personId) {
    try {
        const response = await fetch(`${API_BASE}?action=student_programs_list&person_id=${personId}`);
        const result = await response.json();
        
        const listDiv = document.getElementById('studentProgramsList');
        
        if (result.success && result.data.length > 0) {
            listDiv.innerHTML = `
                <table>
                    <thead>
                        <tr>
                            <th>Программа</th>
                            <th>Тип</th>
                            <th>Статус</th>
                            <th>Документ</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${result.data.map(program => `
                            <tr>
                                <td>${escapeHtml(program.program_name)}</td>
                                <td>${escapeHtml(program.type_name)}</td>
                                <td><span class="status-badge status-${program.status}">${escapeHtml(program.status_text)}</span></td>
                                <td>${program.doc_number ? escapeHtml(program.doc_number) : '—'}</td>
                                <td class="actions-cell">
                                    <button class="btn btn-primary" onclick="editStudentProgram(${program.id})">✎</button>
                                    <button class="btn btn-danger" onclick="deleteStudentProgram(${program.id})">🗑</button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
        } else {
            listDiv.innerHTML = '<p style="color: #666; text-align: center;">Программы не найдены</p>';
        }
    } catch (error) {
        console.error('Ошибка загрузки программ:', error);
    }
}

/**
 * Загрузка типов программ для справочника
 */
async function loadProgramTypes() {
    try {
        const response = await fetch(`${API_BASE}?action=program_types_list`);
        const result = await response.json();
        
        if (result.success) {
            // Обновление фильтра в панели фильтров
            const filterSelect = document.getElementById('filterType');
            filterSelect.innerHTML = result.data.map(type => 
                `<option value="${type.id}">${escapeHtml(type.name)}</option>`
            ).join('');
            
            // Сохранение для использования в форме добавления
            window.programTypes = result.data;
        }
    } catch (error) {
        console.error('Ошибка загрузки типов программ:', error);
    }
}

/**
 * Загрузка списка программ для формы добавления
 */
async function loadProgramsForSelect() {
    try {
        const response = await fetch(`${API_BASE}?action=programs_list`);
        const result = await response.json();
        
        const select = document.getElementById('programSelect');
        if (result.success && result.data.length > 0) {
            select.innerHTML = '<option value="">Выберите программу</option>' + 
                result.data.map(program => 
                    `<option value="${program.id}">${escapeHtml(program.name)} (${escapeHtml(program.type_name)})</option>`
                ).join('');
        }
    } catch (error) {
        console.error('Ошибка загрузки программ:', error);
    }
}

/**
 * Открытие формы добавления программы
 */
async function openAddProgramForm() {
    if (!selectedStudent) {
        alert('Студент не выбран');
        return;
    }
    
    document.getElementById('programStudId').value = selectedStudent.stud_id;
    document.getElementById('programPersonId').value = selectedStudent.person_id;
    
    // Очистка формы
    document.getElementById('addProgramForm').reset();
    document.getElementById('programSelect').innerHTML = '<option value="">Загрузка...</option>';
    
    await loadProgramsForSelect();
    
    document.getElementById('addProgramModal').style.display = 'block';
}

/**
 * Сохранение программы студента
 */
async function saveStudentProgram(event) {
    event.preventDefault();
    
    const data = {
        stud_id: document.getElementById('programStudId').value,
        person_id: document.getElementById('programPersonId').value,
        program_id: document.getElementById('programSelect').value,
        status: document.getElementById('programStatus').value,
        start_date: document.getElementById('programStartDate').value || null,
        end_date: document.getElementById('programEndDate').value || null,
        doc_number: document.getElementById('programDocNumber').value || null,
        doc_date: document.getElementById('programDocDate').value || null,
        comment: document.getElementById('programComment').value || null,
    };
    
    try {
        const response = await fetch(`${API_BASE}?action=student_program_create`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Программа успешно добавлена');
            closeModal('addProgramModal');
            await loadStudentPrograms(selectedStudent.person_id);
        } else {
            alert('Ошибка: ' + (result.error || 'Неизвестная ошибка'));
        }
    } catch (error) {
        console.error('Ошибка сохранения:', error);
        alert('Ошибка сохранения данных');
    }
}

/**
 * Редактирование программы студента
 */
async function editStudentProgram(id) {
    try {
        const response = await fetch(`${API_BASE}?action=student_programs_list`);
        const result = await response.json();
        
        if (result.success) {
            const program = result.data.find(p => p.id == id);
            if (program) {
                // Заполнение формы данными
                document.getElementById('programStudId').value = program.stud_id;
                document.getElementById('programPersonId').value = program.person_id;
                
                await loadProgramsForSelect();
                document.getElementById('programSelect').value = program.program_id;
                document.getElementById('programStatus').value = program.status;
                document.getElementById('programStartDate').value = program.start_date || '';
                document.getElementById('programEndDate').value = program.end_date || '';
                document.getElementById('programDocNumber').value = program.doc_number || '';
                document.getElementById('programDocDate').value = program.doc_date || '';
                document.getElementById('programComment').value = program.comment || '';
                
                // Сохраняем ID для обновления
                document.getElementById('addProgramForm').dataset.editId = id;
                
                document.getElementById('addProgramModal').style.display = 'block';
            }
        }
    } catch (error) {
        console.error('Ошибка загрузки данных:', error);
    }
}

/**
 * Удаление программы студента
 */
async function deleteStudentProgram(id) {
    if (!confirm('Вы уверены, что хотите удалить эту запись?')) {
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}?action=student_program_delete&id=${id}`, {
            method: 'GET'
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Запись удалена');
            await loadStudentPrograms(selectedStudent.person_id);
        } else {
            alert('Ошибка: ' + (result.error || 'Неизвестная ошибка'));
        }
    } catch (error) {
        console.error('Ошибка удаления:', error);
        alert('Ошибка удаления данных');
    }
}

/**
 * Загрузка данных отчёта
 */
async function loadReportData() {
    const reportDiv = document.getElementById('reportContent');
    reportDiv.innerHTML = '<div class="loading">Загрузка данных...</div>';
    
    const filters = getReportFilters();
    const params = new URLSearchParams(filters);
    
    try {
        const response = await fetch(`${API_BASE}?action=report_data&${params.toString()}`);
        const result = await response.json();
        
        if (result.success && result.data.length > 0) {
            reportDiv.innerHTML = `
                <table>
                    <thead>
                        <tr>
                            <th>ФИО</th>
                            <th>Группа</th>
                            <th>Курс</th>
                            <th>Факультет</th>
                            <th>Тип программы</th>
                            <th>Программа</th>
                            <th>Статус</th>
                            <th>Документ</th>
                            <th>Дата выдачи</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${result.data.map(record => `
                            <tr>
                                <td>${escapeHtml(record.fio)}</td>
                                <td>${escapeHtml(record.group_name)}</td>
                                <td>${record.course || '—'}</td>
                                <td>${escapeHtml(record.faculty_name)}</td>
                                <td>${escapeHtml(record.type_name)}</td>
                                <td>${escapeHtml(record.program_name)}</td>
                                <td><span class="status-badge status-${record.status}">${escapeHtml(record.status_text)}</span></td>
                                <td>${record.doc_number ? escapeHtml(record.doc_number) : '—'}</td>
                                <td>${formatDate(record.doc_date)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
                <p style="margin-top: 15px; color: #666;">Всего записей: ${result.data.length}</p>
            `;
        } else {
            reportDiv.innerHTML = '<p style="text-align: center; color: #666;">Данные не найдены</p>';
        }
    } catch (error) {
        console.error('Ошибка загрузки отчёта:', error);
        reportDiv.innerHTML = '<div class="error-message">Ошибка загрузки данных</div>';
    }
}

/**
 * Получение фильтров отчёта
 */
function getReportFilters() {
    const filters = {};
    
    const dateFrom = document.getElementById('filterDateFrom').value;
    const dateTo = document.getElementById('filterDateTo').value;
    const hasDocument = document.getElementById('filterHasDocument').value;
    
    if (dateFrom) filters.date_from = dateFrom;
    if (dateTo) filters.date_to = dateTo;
    if (hasDocument !== '') filters.has_document = hasDocument;
    
    // Множественный выбор типов
    const typeSelect = document.getElementById('filterType');
    const selectedTypes = Array.from(typeSelect.selectedOptions).map(opt => opt.value);
    if (selectedTypes.length > 0) {
        filters.type_ids = selectedTypes.join(',');
    }
    
    // Статусы
    const statusCheckboxes = document.querySelectorAll('#statusFilters input:checked');
    const selectedStatuses = Array.from(statusCheckboxes).map(cb => cb.value);
    if (selectedStatuses.length > 0 && selectedStatuses.length < 5) {
        filters.statuses = selectedStatuses.join(',');
    }
    
    return filters;
}

/**
 * Экспорт отчёта
 */
function exportReport(format) {
    const filters = getReportFilters();
    filters.format = format;
    
    const params = new URLSearchParams(filters);
    window.location.href = `${API_BASE}?action=report_export&${params.toString()}`;
}

/**
 * Показать панель фильтров
 */
function showFiltersPanel() {
    document.getElementById('filtersPanel').classList.add('active');
}

/**
 * Сброс фильтров
 */
function resetFilters() {
    document.getElementById('filterDateFrom').value = '';
    document.getElementById('filterDateTo').value = '';
    document.getElementById('filterHasDocument').value = '';
    document.getElementById('filterType').selectedIndex = -1;
    
    document.querySelectorAll('#statusFilters input').forEach(cb => {
        cb.checked = true;
    });
    
    loadReportData();
}

/**
 * Закрытие модального окна
 */
function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    
    // Очистка данных формы
    if (modalId === 'addProgramModal') {
        delete document.getElementById('addProgramForm').dataset.editId;
    }
}

/**
 * Закрытие по ESC
 */
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal').forEach(modal => {
            modal.style.display = 'none';
        });
    }
});

/**
 * Экранирование HTML
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Форматирование даты
 */
function formatDate(dateStr) {
    if (!dateStr) return '—';
    const date = new Date(dateStr);
    return date.toLocaleDateString('ru-RU');
}
