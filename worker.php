<?php
require __DIR__ . '/config.php';
require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Mpdf\Mpdf;
use Smalot\PdfParser\Parser;

echo "Воркер запущен...\n";

// --- РАСШИРЕННЫЙ СЛОВАРЬ СИНОНИМОВ ---
$synonyms = [
    'name' => ['наименование', 'товар', 'номенклатура', 'название', 'продукт', 'услуга', 'описание', 'предмет', 'объект', 'позиция', 'ассортимент', 'материал', 'сырье', 'деталь', 'изделие', 'работа', 'наименование товара', 'наименование услуги'],
    'qty' => ['количество', 'объем', 'кол-во', 'шт', 'кг', 'литр', 'уп', 'пачка', 'вес', 'масса', 'число', 'единиц', 'количество (шт)', 'количество (кг)', 'объем (л)', 'кол-во мест', 'штук', 'кол-во товара'],
    'price' => ['цена', 'стоимость', 'цена за ед.', 'цена за единицу', 'сумма', 'расценка', 'тариф', 'прайс', 'стоимость (руб)', 'цена (руб)', 'цена с ндс', 'цена без ндс', 'розничная цена', 'оптовая цена', 'себестоимость', 'наценка'],
    'total' => ['итого', 'всего', 'сумма итого', 'общая сумма', 'итоговая сумма'],
    'unit' => ['ед. изм.', 'единица измерения', 'единица', 'ед', 'мера', 'измерение', 'ед.изм', 'ед-ца', 'единицы', 'упаковка', 'уп.', 'компл', 'м2', 'м3', 'пог.м'],
    'sku' => ['артикул', 'код', 'sku', 'штрихкод', 'штрих-код', 'инн', 'кпп', 'код товара', 'арт', 'арт.', 'номер', 'id', 'серийный номер'],
    'contractor' => ['контрагент', 'поставщик', 'покупатель', 'клиент', 'заказчик', 'исполнитель', 'партнер', 'организация', 'юрлицо', 'физлицо', 'контактное лицо', 'фио'],
    'document' => ['номер', 'дата', 'счет', 'накладная', 'акт', 'договор', 'счет-фактура', '№ документа', '№ счета', 'дата документа', 'номер документа', 'основание'],
    'warehouse' => ['склад', 'адрес', 'город', 'улица', 'дом', 'офис', 'помещение', 'место хранения', 'локация', 'пункт назначения', 'адрес доставки']
];

function findSynonym($header, $synonyms) {
    $header = mb_strtolower(trim($header));
    foreach ($synonyms as $key => $list) {
        if (in_array($header, $list)) {
            return $key;
        }
    }
    return null;
}

// Функция для парсинга PDF (координатный)
function parsePdfTable($pdfPath) {
    $parser = new Parser();
    $pdf = $parser->parseFile($pdfPath);
    $text = $pdf->getText();
    
    $lines = explode("\n", $text);
    $result = [];
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        // Пытаемся разбить на 3 колонки (имя, кол-во, цена)
        // Более гибкая регулярка: ищем числа в конце строки
        if (preg_match('/^(.*?)\s+([\d.,]+(?:\s*[а-яА-Яa-zA-Z]+)?)\s+([\d.,]+(?:\s*[а-яА-Яa-zA-Z]+)?)$/u', $line, $matches)) {
            $result[] = [
                'name' => trim($matches[1]),
                'qty' => trim($matches[2]),
                'price' => trim($matches[3])
            ];
        } else {
            $result[] = [
                'name' => $line,
                'qty' => '',
                'price' => ''
            ];
        }
    }
    return $result;
}

while (true) {
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->query("SELECT * FROM tasks WHERE status = 'pending' ORDER BY id ASC LIMIT 1 FOR UPDATE SKIP LOCKED");
        $task = $stmt->fetch();

        if ($task) {
            $pdo->prepare("UPDATE tasks SET status = 'processing' WHERE id = ?")->execute([$task['id']]);
            $pdo->commit();
            
            echo "Взял задачу #{$task['id']} ({$task['task_type']})\n";

            $tempSource = tempnam(sys_get_temp_dir(), 'src_');
            file_put_contents($tempSource, $task['source_file']);

            $outputName = 'result_' . $task['id'] . '_' . time();
            $outputPath = __DIR__ . '/storage/output/';

            // --- EXCEL -> PDF ---
            if ($task['task_type'] === 'excel_to_pdf') {
                $reader = IOFactory::createReader('Xlsx');
                $spreadsheet = $reader->load($tempSource);
                $sheet = $spreadsheet->getActiveSheet();
                
                $html = '<meta charset="utf-8"><table border="1" cellpadding="5" style="border-collapse: collapse; width: 100%;">';
                foreach ($sheet->getRowIterator() as $row) {
                    $html .= '<tr>';
                    foreach ($row->getCellIterator() as $cell) {
                        $html .= '<td>' . htmlspecialchars($cell->getValue() ?? '') . '</td>';
                    }
                    $html .= '</tr>';
                }
                $html .= '</table>';

                $mpdf = new Mpdf();
                $mpdf->WriteHTML($html);
                $outputName .= '.pdf';
                $mpdf->Output($outputPath . $outputName, 'F');

            // --- EXCEL + EXCEL (Умный маппинг) ---
            } elseif ($task['task_type'] === 'excel_merge') {
                $tempTarget = tempnam(sys_get_temp_dir(), 'tgt_');
                file_put_contents($tempTarget, $task['target_file']);
                
                $reader = IOFactory::createReader('Xlsx');
                $sourceSheet = $reader->load($tempSource)->getActiveSheet();
                $targetSpreadsheet = $reader->load($tempTarget);
                $targetSheet = $targetSpreadsheet->getActiveSheet();

                // Читаем заголовки
                $sourceHeaders = [];
                foreach ($sourceSheet->getRowIterator(1, 1)->current()->getCellIterator() as $cell) {
                    $sourceHeaders[$cell->getColumn()] = (string)$cell->getValue();
                }

                $targetHeaders = [];
                foreach ($targetSheet->getRowIterator(1, 1)->current()->getCellIterator() as $cell) {
                    $targetHeaders[(string)$cell->getValue()] = $cell->getColumn();
                }

                // Строим карту
                $mapping = [];
                foreach ($sourceHeaders as $srcCol => $srcHeader) {
                    $srcKey = findSynonym($srcHeader, $synonyms);
                    if ($srcKey) {
                        foreach ($targetHeaders as $tgtHeader => $tgtCol) {
                            $tgtKey = findSynonym($tgtHeader, $synonyms);
                            if ($tgtKey === $srcKey) {
                                $mapping[$srcCol] = $tgtCol;
                                break;
                            }
                        }
                    } else {
                        if (isset($targetHeaders[$srcHeader])) {
                            $mapping[$srcCol] = $targetHeaders[$srcHeader];
                        }
                    }
                }

                // Если маппинг пустой — копируем по порядку
                if (empty($mapping)) {
                    foreach ($sourceHeaders as $srcCol => $srcHeader) {
                        $mapping[$srcCol] = $srcCol;
                    }
                }

                // Переносим данные
                $rowIndex = 2;
                foreach ($sourceSheet->getRowIterator(2) as $row) {
                    foreach ($mapping as $srcCol => $tgtCol) {
                        $value = $sourceSheet->getCell($srcCol . $row->getRowIndex())->getValue();
                        $targetSheet->setCellValue($tgtCol . $rowIndex, $value);
                    }
                    $rowIndex++;
                }

                // Стили
                $targetSheet->getColumnDimension('A')->setWidth(45);
                $targetSheet->getColumnDimension('B')->setWidth(15);
                $targetSheet->getColumnDimension('C')->setWidth(15);
                
                $styleArray = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                        ],
                    ],
                ];
                $targetSheet->getStyle('A1:C' . $targetSheet->getHighestRow())->applyFromArray($styleArray);

                $outputName .= '.xlsx';
                $writer = new Xlsx($targetSpreadsheet);
                $writer->save($outputPath . $outputName);
                @unlink($tempTarget);
            
            // --- PDF -> EXCEL ---
            } elseif ($task['task_type'] === 'pdf_to_excel') {
                $parsedData = parsePdfTable($tempSource);
                
                $spreadsheet = new Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                
                $sheet->setCellValue('A1', 'Наименование');
                $sheet->setCellValue('B1', 'Количество');
                $sheet->setCellValue('C1', 'Цена');
                $sheet->getStyle('A1:C1')->getFont()->setBold(true);
                $sheet->getStyle('A1:C1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                
                $rowIndex = 2;
                foreach ($parsedData as $row) {
                    $sheet->setCellValue('A' . $rowIndex, $row['name']);
                    $sheet->setCellValue('B' . $rowIndex, $row['qty']);
                    $sheet->setCellValue('C' . $rowIndex, $row['price']);
                    
                    $sheet->getStyle('A' . $rowIndex)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setWrapText(true);
                    $sheet->getStyle('B' . $rowIndex)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle('C' . $rowIndex)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $rowIndex++;
                }
                
                $sheet->getColumnDimension('A')->setWidth(45);
                $sheet->getColumnDimension('B')->setWidth(15);
                $sheet->getColumnDimension('C')->setWidth(15);
                
                $styleArray = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                        ],
                    ],
                ];
                $sheet->getStyle('A1:C' . $sheet->getHighestRow())->applyFromArray($styleArray);
                
                $outputName .= '.xlsx';
                $writer = new Xlsx($spreadsheet);
                $writer->save($outputPath . $outputName);

            // --- 1C -> EXCEL ---
            } elseif ($task['task_type'] === '1c_to_excel') {
                $content = file_get_contents($tempSource);
                $lines = explode("\n", $content);
                
                $spreadsheet = new Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                $row = 1;
                foreach ($lines as $line) {
                    $sheet->setCellValue('A' . $row, $line);
                    $sheet->getStyle('A' . $row)->getAlignment()->setWrapText(true);
                    $row++;
                }
                
                $sheet->getColumnDimension('A')->setWidth(50);
                
                $outputName .= '.xlsx';
                $writer = new Xlsx($spreadsheet);
                $writer->save($outputPath . $outputName);

            // --- EXCEL -> 1C ---
            } elseif ($task['task_type'] === 'excel_to_1c') {
                $reader = IOFactory::createReader('Xlsx');
                $spreadsheet = $reader->load($tempSource);
                $sheet = $spreadsheet->getActiveSheet();
                
                $outputName .= '.txt';
                $content = "1CClientBankExchange\n";
                $content .= "ВерсияФормата=1.03\n";
                $content .= "Кодировка=Windows\n";
                $content .= "Отправитель=СекцияДокумент=Платежное поручение\n";
                
                $row = 1;
                foreach ($sheet->getRowIterator() as $rowObj) {
                    $content .= "Документ" . $row . "=";
                    foreach ($rowObj->getCellIterator() as $cell) {
                        $content .= $cell->getValue() . "|";
                    }
                    $content .= "\n";
                    $row++;
                }
                
                file_put_contents($outputPath . $outputName, $content);
            }

            @unlink($tempSource);

            $pdo->prepare("UPDATE tasks SET status = 'done', result_file = ? WHERE id = ?")->execute([$outputName, $task['id']]);
            echo "Задача #{$task['id']} выполнена!\n";
        } else {
            $pdo->commit();
            sleep(2);
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if (isset($task) && $task) {
            $pdo->prepare("UPDATE tasks SET status = 'error', error_message = ? WHERE id = ?")->execute([$e->getMessage(), $task['id']]);
            echo "ОШИБКА #{$task['id']}: " . $e->getMessage() . "\n";
        }
        sleep(2);
    }
}