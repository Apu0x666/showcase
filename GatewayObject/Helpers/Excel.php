<?php

use Ep\App\Core\Response\XlsxResponse;
use PhpOffice\PhpSpreadsheet\Cell\Hyperlink;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LossLog_GatewayObject_Excel_Helper
{
    /**
     * @param array<string, mixed>  $data
     * @param array<string, string> $addData
     *
     * @return XlsxResponse
     *
     * @throws PhpOffice\PhpSpreadsheet\Exception
     */
    public function makeExcel(array $data, array $addData): XlsxResponse
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->setActiveSheetIndex(0);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($addData['title']);

        if (empty($data)) {
            $sheet->setCellValue('A1', 'Нет данных или у вас нет прав на экспорт данных выбранных объектов.');

            return new XlsxResponse($spreadsheet, $addData['filename']);
        }

        // Общая информация
        $sheet->setCellValue('A1', 'Общая информация')->mergeCells('A1:I1');
        $sheet->setCellValue('A2', '№')->mergeCells('A2:A3')->getColumnDimension('A')->setWidth(6);
        $sheet->setCellValue('B2', 'Дата')->mergeCells('B2:B3')->getColumnDimension('B')->setWidth(15);
        $sheet->setCellValue('C2', 'Подразделение')->mergeCells('C2:C3')->getColumnDimension('C')->setWidth(20);
        $sheet->setCellValue('D2', 'Лу')->mergeCells('D2:D3')->getColumnDimension('D')->setWidth(6);
        $sheet->setCellValue('E2', 'Установка')->mergeCells('E2:E3')->getColumnDimension('E')->setWidth(17);
        $sheet->setCellValue('F2', 'Технологический процесс')->mergeCells('F2:F3')
            ->getColumnDimension('F')->setWidth(20);
        $sheet->setCellValue('G2', 'Проектная производительность');
        $sheet->setCellValue('G3', $addData['unit'])->getColumnDimension('G')->setWidth(14);
        $sheet->setCellValue('H2', 'МДП');
        $sheet->setCellValue('H3', $addData['unit'])->getColumnDimension('H')->setWidth(14);
        $sheet->setCellValue('I2', 'Потенциал');
        $sheet->setCellValue('I3', $addData['unit'])->getColumnDimension('I')->setWidth(14);

        // Плановые потери
        $sheet->setCellValue('J1', 'Плановые потери')->mergeCells('J1:U1');
        $sheet->setCellValue('J2', 'План');
        $sheet->setCellValue('J3', $addData['unit'])->getColumnDimension('J')->setWidth(14);
        $sheet->setCellValue('K2', 'Потенциал минус План');
        $sheet->setCellValue('K3', $addData['unit'])->getColumnDimension('K')->setWidth(19);
        $sheet->setCellValue('L2', '№')->mergeCells('L2:L3')->getColumnDimension('L')->setWidth(6);
        $sheet->setCellValue('M2', 'Декомпозиция отклонения');
        $sheet->setCellValue('M3', $addData['unit'])->getColumnDimension('M')->setWidth(19);
        $sheet->setCellValue('N2', 'Непосредственная причина')->mergeCells('N2:N3')->getColumnDimension('N')
            ->setWidth(30);
        $sheet->setCellValue('O2', 'Комментарий')->mergeCells('O2:O3')->getColumnDimension('O')->setWidth(20);
        $sheet->setCellValue('P2', 'Коренная причина')->mergeCells('P2:P3')->getColumnDimension('P')->setWidth(20);
        $sheet->setCellValue('Q2', 'Загруженные документы')->mergeCells('Q2:Q3')->getColumnDimension('Q')->setWidth(20);
        $sheet->setCellValue('R2', 'Связанный документ
')->mergeCells('R2:R3')->getColumnDimension('R')->setWidth(20);
        $sheet->setCellValue('S2', 'ID внутренней причины')->mergeCells('S2:S3')->getColumnDimension('S')->setWidth(32);
        $sheet->setCellValue('T2', 'ID внешней причины
')->mergeCells('T2:T3')->getColumnDimension('T')->setWidth(32);
        $sheet->setCellValue('U2', 'ID последствия')->mergeCells('U2:U3')->getColumnDimension('U')->setWidth(32);

        // Недовыпуск продукции от потенциала
        $sheet->setCellValue('V1', 'Недовыпуск продукции от потенциала')->mergeCells('V1:AA1');
        $sheet->setCellValue('V2', 'Нефть');
        $sheet->setCellValue('V3', 'т')->getColumnDimension('V')->setWidth(12);
        $sheet->setCellValue('W2', 'СГК');
        $sheet->setCellValue('W3', 'т')->getColumnDimension('W')->setWidth(12);
        $sheet->setCellValue('X2', 'ПТ');
        $sheet->setCellValue('X3', 'т')->getColumnDimension('X')->setWidth(12);
        $sheet->setCellValue('Y2', 'БТ');
        $sheet->setCellValue('Y3', 'т')->getColumnDimension('Y')->setWidth(12);
        $sheet->setCellValue('Z2', 'Гелий');
        $sheet->setCellValue('Z3', 'кг')->getColumnDimension('Z')->setWidth(12);
        $sheet->setCellValue('AA2', 'Экономические последствия млн.руб')->mergeCells('AA2:AA3')
            ->getColumnDimension('AA')->setWidth(17);

        // Анализ выполнения плана
        $sheet->setCellValue('AB1', 'Анализ выполнения плана')->mergeCells('AB1:AM1');
        $sheet->setCellValue('AB2', 'Факт');
        $sheet->setCellValue('AB3', $addData['unit'])->getColumnDimension('AB')->setWidth(12);
        $sheet->setCellValue('AC2', 'План минус Факт');
        $sheet->setCellValue('AC3', $addData['unit'])->getColumnDimension('AC')->setWidth(12);
        $sheet->setCellValue('AD2', '№')->mergeCells('AD2:AD3')->getColumnDimension('AD')->setWidth(6);
        $sheet->setCellValue('AE2', 'Декомпозиция отклонения');
        $sheet->setCellValue('AE3', $addData['unit'])->getColumnDimension('AE')->setWidth(19);
        $sheet->setCellValue('AF2', 'Непосредственная причина')->mergeCells('AF2:AF3')->getColumnDimension('AF')
            ->setWidth(30);
        $sheet->setCellValue('AG2', 'Комментарий')->mergeCells('AG2:AG3')->getColumnDimension('AG')->setWidth(20);
        $sheet->setCellValue('AH2', 'Коренная причина')->mergeCells('AH2:AH3');
        $sheet->setCellValue('AI2', 'Загруженные документы')->mergeCells('AI2:AI3')->getColumnDimension('AI')
            ->setWidth(20);
        $sheet->setCellValue('AJ2', 'Связанный документ')->mergeCells('AJ2:AJ3')->getColumnDimension('AJ')
            ->setWidth(20);
        $sheet->setCellValue('AK2', 'ID внутренней причины')->mergeCells('AK2:AK3')
            ->getColumnDimension('AK')->setWidth(20);
        $sheet->setCellValue('AL2', 'ID внешней причины
')->mergeCells('AL2:AL3')->getColumnDimension('AL')->setWidth(20);
        $sheet->setCellValue('AM2', 'ID последствия')->mergeCells('AM2:AM3')->getColumnDimension('AM')->setWidth(20);

        // Недовыпуск продукции от плана
        $sheet->setCellValue('AN1', 'Недовыпуск продукции от плана')->mergeCells('AN1:AS1');
        $sheet->setCellValue('AN2', 'Нефть');
        $sheet->setCellValue('AN3', 'т')->getColumnDimension('AN')->setWidth(12);
        $sheet->setCellValue('AO2', 'СГК');
        $sheet->setCellValue('AO3', 'т')->getColumnDimension('AO')->setWidth(12);
        $sheet->setCellValue('AP2', 'ПТ');
        $sheet->setCellValue('AP3', 'т')->getColumnDimension('AP')->setWidth(12);
        $sheet->setCellValue('AQ2', 'БТ');
        $sheet->setCellValue('AQ3', 'т')->getColumnDimension('AQ')->setWidth(12);
        $sheet->setCellValue('AR2', 'Гелий');
        $sheet->setCellValue('AR3', 'кг')->getColumnDimension('AR')->setWidth(12);
        $sheet->setCellValue('AS2', 'Экономические последствия млн.руб')->mergeCells('AS2:AS3')
            ->getColumnDimension('AS')->setWidth(17);

        $rowIndex = 4;

        foreach ($data['linkedList'] as $item) {
            $mergeRows = max(
                count($item['decompositionPlanItems']),
                count($item['decompositionFactItems']),
                1
            );

            // Объединяем A-K
            for ($col = 'A'; $col <= 'K'; $col++) {
                $sheet->mergeCells($col . $rowIndex . ':' . $col . ($rowIndex + $mergeRows - 1));
            }

            $sheet->mergeCells('AB' . $rowIndex . ':AB' . ($rowIndex + $mergeRows - 1));
            $sheet->mergeCells('AC' . $rowIndex . ':AC' . ($rowIndex + $mergeRows - 1));

            $sheet->setCellValue('A' . $rowIndex, $item['id']);
            $sheet->setCellValue('B' . $rowIndex, $item['date'] ?? '');
            $sheet->setCellValue('C' . $rowIndex, $item['object']['workshop']['workshop'] ?? '');
            $sheet->setCellValue('D' . $rowIndex, $item['object']['workshop']['licensedAreas']['code'] ?? '');
            $sheet->setCellValue('E' . $rowIndex, $item['object']['object'] ?? '');
            $sheet->setCellValue('F' . $rowIndex, $item['object']['techProcess']['techProcessName'] ?? '');
            $sheet->setCellValue('G' . $rowIndex, $item['performance'] ?? '');
            $sheet->setCellValue('H' . $rowIndex, $item['mdp'] ?? '');
            $sheet->setCellValue(
                'I' . $rowIndex,
                ($item['performance'] > $item['mdp']) ?
                        $item['performance'] : $item['mdp']
            );
            $sheet->setCellValue('J' . $rowIndex, $item['plan'] ?? '');
            $sheet->setCellValue(
                'K' . $rowIndex,
                ($item['performance'] > $item['mdp']) ?
                    $item['performance'] - $item['plan'] :
                    $item['mdp'] - $item['plan']
            );

            // Заполняем AB и AC только один раз
            $sheet->setCellValue('AB' . $rowIndex, $item['fact'] ?? '');
            $plan = $item['plan'] ?? 0;
            $fact = $item['fact'] ?? 0;
            $difference = $plan - $fact;
            $sheet->setCellValue('AC' . $rowIndex, $difference);

            foreach ($item['decompositionPlanItems'] as $i => $decompositionPlanItem) {
                $position = $rowIndex + $i;
                $sheet->setCellValue('L' . $position, $decompositionPlanItem['id'] ?? '');
                $sheet->setCellValue('M' . $position, $decompositionPlanItem['deviation'] ?? '');
                $sheet->setCellValue('N' . $position, $decompositionPlanItem['immediateCause']['name'] ?? '');
                $sheet->setCellValue('O' . $position, $decompositionPlanItem['comment'] ?? '');
                $sheet->setCellValue('P' . $position, $decompositionPlanItem['rootCause']['name'] ?? '');

                if ($decompositionPlanItem['document'] !== 0) {
                    $document = $this->makeAtrLink($decompositionPlanItem['document']);
                    $sheet->setCellValue('R' . $position, $document);

                    // Добавление ссылки
                    if (!empty($decompositionPlanItem['document']['docLink'])) {
                        $docLink = trim($decompositionPlanItem['document']['docLink']);
                        $sheet->getCell('R' . $position)->setHyperlink(new Hyperlink($docLink));
                    }
                }

                $sheet->setCellValue('S' . $position, $decompositionPlanItem['idIn'] ?? '');
                $sheet->setCellValue('T' . $position, $decompositionPlanItem['idOut'] ?? '');
                $sheet->setCellValue('U' . $position, $decompositionPlanItem['idConsequence'] ?? '');
                $sheet->setCellValue('V' . $position, isset($decompositionPlanItem['oil']) ?
                    number_format($decompositionPlanItem['oil'], 2) : '');
                $sheet->setCellValue('W' . $position, isset($decompositionPlanItem['sgk']) ?
                    number_format($decompositionPlanItem['sgk'], 2) : '');
                $sheet->setCellValue('X' . $position, isset($decompositionPlanItem['pt']) ?
                    number_format($decompositionPlanItem['pt'], 2) : '');
                $sheet->setCellValue('Y' . $position, isset($decompositionPlanItem['bt']) ?
                    number_format($decompositionPlanItem['bt'], 2) : '');
                $sheet->setCellValue('Z' . $position, isset($decompositionPlanItem['helium']) ?
                    number_format($decompositionPlanItem['helium'], 2) : '');
                $sheet->setCellValue('AA' . $position, isset($decompositionPlanItem['economicLoss']) ?
                    number_format($decompositionPlanItem['economicLoss'], 2) . ' ₽' : '');
            }

            foreach ($item['decompositionFactItems'] as $k => $decompositionFactItem) {
                $position = $rowIndex + $k;
                $sheet->setCellValue('AD' . $position, $decompositionFactItem['id'] ?? '');
                $sheet->setCellValue('AE' . $position, $decompositionFactItem['deviation'] ?? '');
                $sheet->setCellValue('AF' . $position, $decompositionFactItem['immediateCause']['name'] ?? '');
                $sheet->setCellValue('AG' . $position, $decompositionFactItem['comment'] ?? '');
                $sheet->setCellValue('AH' . $position, $decompositionFactItem['rootCause']['name'] ?? '');
                $sheet->setCellValue('AI' . $position, $decompositionFactItem['uploadDocs'][0] ?? '');

                if ($decompositionFactItem['document'] !== 0) {
                    $document = $this->makeAtrLink($decompositionFactItem['document']);
                    $sheet->setCellValue('AJ' . $position, $document);

                    // Добавление ссылки
                    if (!empty($decompositionFactItem['document']['docLink'])) {
                        $docLink = trim($decompositionFactItem['document']['docLink']);
                        $sheet->getCell('AJ' . $position)->setHyperlink(new Hyperlink($docLink));
                    }
                }

                $sheet->setCellValue('AK' . $position, $decompositionFactItem['idIn'] ?? '');
                $sheet->setCellValue('AL' . $position, $decompositionFactItem['idOut'] ?? '');
                $sheet->setCellValue('AM' . $position, $decompositionFactItem['idConsequence'] ?? '');
                $sheet->setCellValue('AS' . $position, isset($decompositionFactItem['economicLoss']) ?
                    number_format($decompositionFactItem['economicLoss'], 2) . ' ₽' : '');
                $sheet->setCellValue('AN' . $position, isset($decompositionFactItem['oil']) ?
                    number_format($decompositionFactItem['oil'], 2) : '');
                $sheet->setCellValue('AO' . $position, isset($decompositionFactItem['sgk']) ?
                    number_format($decompositionFactItem['sgk'], 2) : '');
                $sheet->setCellValue('AR' . $position, isset($decompositionFactItem['helium']) ?
                    number_format($decompositionFactItem['helium'], 2) : '');
                $sheet->setCellValue('AP' . $position, isset($decompositionFactItem['pt']) ?
                    number_format($decompositionFactItem['pt'], 2) : '');
                $sheet->setCellValue('AQ' . $position, isset($decompositionFactItem['bt']) ?
                    number_format($decompositionFactItem['bt'], 2) : '');
            }

            $rowIndex += $mergeRows;
        }

        $sheet->getRowDimension(1)->setRowHeight(24);
        $sheet->getRowDimension(2)->setRowHeight(36);
        ExcelHelper::setPageStyles($sheet, 'AS', 3);

        $this->customStylesLikeAtWeb($sheet);

        return new XlsxResponse($spreadsheet, $addData['filename']);
    }

    /**
     * @param array<string, mixed> $document
     *
     * @return string
     */
    private function makeAtrLink(array $document): string
    {
        $documentValue = '';

        if ($document && isset($document['docNum'])) {
            $documentValue = 'АТР №' . $document['docNum'];

            if (isset($document['atrDate']) && $document['atrDate'] !== '0000-00-00 00:00:00') {
                // Пытаемся создать объект даты из строки формата MySQL
                $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $document['atrDate']);

                // Если дата корректна и создание прошло успешно
                if ($date !== false) {
                    $documentValue .= ' от ' . $date->format('d.m.Y');
                }
            }
        }

        return $documentValue;
    }

    private function customStylesLikeAtWeb(Worksheet $sheet): void
    {
        // Общая информация
        $sheet->getStyle('A2:I4')->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color'    => ['argb' => 'E9ECEB'],
            ],
            'font' => [
                'color' => ['argb' => '333E39'],
            ],
        ]);

        // Плановые потери
        $sheet->getStyle('J2:U4')->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color'    => ['argb' => 'CCFFFF'],
            ],
            'font' => [
                'color' => ['argb' => '333E39'],
            ],
        ]);

        // Плановые потери + Недовыпуск продукции от потенциала
        $sheet->getStyle('J2:AA4')->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color'    => ['argb' => 'CCFFFF'],
            ],
            'font' => [
                'color' => ['argb' => '333E39'],
            ],
        ]);

        // Анализ выполнения плана + Недовыпуск продукции от плана
        $sheet->getStyle('AB2:AS4')->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color'    => ['argb' => 'CCFFCC'],
            ],
            'font' => [
                'color' => ['argb' => '333E39'],
            ],
        ]);

        // Задаем стиль границ для всех ячеек
        $styleArray = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['argb' => 'AAAAAA'],
                ],
            ],
        ];

        // Применяем стиль ко всему диапазону используемых ячеек
        $sheet->getStyle($sheet->calculateWorksheetDimension())->applyFromArray($styleArray);
    }
}
