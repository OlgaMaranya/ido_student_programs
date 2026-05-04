<?php
/**
 * Сервис для экспорта данных в XLSX и CSV
 */

class ExportService {
    
    /**
     * Экспорт в XLSX формат
     * @param array $data Данные для экспорта
     * @param string $filename Имя файла
     * @return string Путь к файлу или false при ошибке
     */
    public function exportToXlsx($data, $filename) {
        // Проверка наличия библиотеки PhpSpreadsheet
        if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            // Простая реализация без внешней библиотеки
            return $this->exportToXlsxSimple($data, $filename);
        }
        
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Заголовки
        if (!empty($data)) {
            $headers = array_keys($data[0]);
            $column = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($column . '1', $header);
                $column++;
            }
            
            // Стилизация заголовков
            $sheet->getStyle('A1:' . $this->getColumnLetter(count($headers)) . '1')
                ->getFont()->setBold(true)
                ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFCCCCCC');
            
            // Данные
            $row = 2;
            foreach ($data as $record) {
                $column = 'A';
                foreach ($record as $value) {
                    $sheet->setCellValue($column . $row, $value);
                    $column++;
                }
                $row++;
            }
            
            // Авто-размер колонок
            foreach (range('A', $this->getColumnLetter(count($headers))) as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
        }
        
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $filePath = sys_get_temp_dir() . '/' . $filename . '.xlsx';
        $writer->save($filePath);
        
        return $filePath;
    }
    
    /**
     * Простая реализация XLSX без внешних библиотек (XML формат)
     */
    private function exportToXlsxSimple($data, $filename) {
        // Создаём простой XML для Excel 2003
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<?mso-application progid="Excel.Sheet"?>' . PHP_EOL;
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . PHP_EOL;
        $xml .= ' xmlns:o="urn:schemas-microsoft-com:office:office"' . PHP_EOL;
        $xml .= ' xmlns:x="urn:schemas-microsoft-com:office:excel"' . PHP_EOL;
        $xml .= ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . PHP_EOL;
        $xml .= ' <Styles>' . PHP_EOL;
        $xml .= '  <Style ss:ID="Default" ss:Name="Normal">' . PHP_EOL;
        $xml .= '   <Alignment ss:Vertical="Bottom"/>' . PHP_EOL;
        $xml .= '   <Borders/>' . PHP_EOL;
        $xml .= '   <Font ss:FontName="Arial"/>' . PHP_EOL;
        $xml .= '   <Interior/>' . PHP_EOL;
        $xml .= '   <NumberFormat/>' . PHP_EOL;
        $xml .= '   <Protection/>' . PHP_EOL;
        $xml .= '  </Style>' . PHP_EOL;
        $xml .= '  <Style ss:ID="sHeader">' . PHP_EOL;
        $xml .= '   <Font ss:Bold="1"/>' . PHP_EOL;
        $xml .= '   <Interior ss:Color="#CCCCCC" ss:Pattern="Solid"/>' . PHP_EOL;
        $xml .= '  </Style>' . PHP_EOL;
        $xml .= ' </Styles>' . PHP_EOL;
        $xml .= ' <Worksheet ss:Name="Report">' . PHP_EOL;
        $xml .= '  <Table>' . PHP_EOL;
        
        // Заголовки
        if (!empty($data)) {
            $xml .= '   <Row>' . PHP_EOL;
            foreach (array_keys($data[0]) as $header) {
                $xml .= '    <Cell ss:StyleID="sHeader"><Data ss:Type="String">' . 
                        htmlspecialchars($header, ENT_XML1, 'UTF-8') . '</Data></Cell>' . PHP_EOL;
            }
            $xml .= '   </Row>' . PHP_EOL;
            
            // Данные
            foreach ($data as $record) {
                $xml .= '   <Row>' . PHP_EOL;
                foreach ($record as $value) {
                    $type = is_numeric($value) ? 'Number' : 'String';
                    $xml .= '    <Cell><Data ss:Type="' . $type . '">' . 
                            htmlspecialchars($value ?? '', ENT_XML1, 'UTF-8') . '</Data></Cell>' . PHP_EOL;
                }
                $xml .= '   </Row>' . PHP_EOL;
            }
        }
        
        $xml .= '  </Table>' . PHP_EOL;
        $xml .= ' </Worksheet>' . PHP_EOL;
        $xml .= '</Workbook>';
        
        $filePath = sys_get_temp_dir() . '/' . $filename . '.xls';
        file_put_contents($filePath, $xml);
        
        return $filePath;
    }
    
    /**
     * Экспорт в CSV формат (UTF-8 с BOM)
     * @param array $data Данные для экспорта
     * @param string $filename Имя файла
     * @return string Путь к файлу
     */
    public function exportToCsv($data, $filename) {
        $filePath = sys_get_temp_dir() . '/' . $filename . '.csv';
        
        $handle = fopen($filePath, 'w');
        
        // Добавляем BOM для корректного отображения кириллицы в Excel
        fwrite($handle, "\xEF\xBB\xBF");
        
        if (!empty($data)) {
            // Заголовки
            fputcsv($handle, array_keys($data[0]), ';');
            
            // Данные
            foreach ($data as $record) {
                fputcsv($handle, array_values($record), ';');
            }
        }
        
        fclose($handle);
        
        return $filePath;
    }
    
    /**
     * Получить букву колонки по номеру
     */
    private function getColumnLetter($num) {
        $letter = '';
        while ($num > 0) {
            $num--;
            $letter = chr(65 + ($num % 26)) . $letter;
            $num = intdiv($num, 26);
        }
        return $letter;
    }
}
