<?php

declare(strict_types=1);

use Ep\App\Modules\LossLog\Datareon\LossLogProcessor;
use LossLog\LossLog_Interface;
use Monolog\Level;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

class LossLogExportXmlDatareon_Helper
{
    private string $datareon;

    /**
     * @throws Exception
     */
    public function __construct(bool $prod = false)
    {
        $config = AppConfig::getInstance();

        if (empty($config->getParam('datareon'))) {
            throw new Exception('Отсутствует конфиг!');
        }

        if ($prod) {
            $this->datareon = $config->getParam('datareon.prod');
        } else {
            $this->datareon = $config->getParam('datareon.test');
        }
    }

    private function getDatareon(): string
    {
        return $this->datareon;
    }

    /**
     * @param array<string, mixed> $input
     * @param LossLog_Interface    $model
     *
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function exportProcessing(array $input, LossLog_Interface $model): array
    {
        $results = [];

        // Если это изменение или удаление документа, сначала отправляем запрос на удаление
        if (
            isset($input['operation_type'])
            && ($input['operation_type'] === 'change' || $input['operation_type'] === 'delete')
        ) {
            // Создаем копию входных данных для запроса на удаление
            $deleteInput = $input;
            $deleteInput['operation_type'] = 'delete';

            if (!empty($input['old_document_id']) && !empty($input['document'])) {
                // устанавливаем документ для удаления
                $deleteInput['document'] = $input['old_document_id'];
            }
            // Отправляем запрос на удаление
            $preparedDataForDelete = (new LossLogPrepareDataForToir_Helper())->forToir($deleteInput, $model);
            $lossLogProcessor = new LossLogProcessor(
                LossLogPrepareDataForToir_Helper::class,
                'xml',
            );

            $xmlDataForDelete = $lossLogProcessor->encode(
                $preparedDataForDelete,
                [
                    XmlEncoder::CDATA_WRAPPING         => true,
                    XmlEncoder::CDATA_WRAPPING_PATTERN => '/./',
                ]
            );

            $deleteResult = $this->sendXmlToTOiR($xmlDataForDelete);
            $results['delete'] = $deleteResult;
            $results['delete']['message'] = 'Обновление в TOiR3: удаление документа АТР/АРП из декомпозиции';

            // Если это только удаление, не продолжаем дальше
            if ($input['operation_type'] === 'delete') {
                return $results;
            }
        }

        $input['operation_type'] = 'create';
        // Отправляем основной запрос
        $preparedDataForToir = (new LossLogPrepareDataForToir_Helper())->forToir($input, $model);
        $lossLogProcessor = new LossLogProcessor(
            LossLogPrepareDataForToir_Helper::class,
            'xml',
        );

        $xmlData = $lossLogProcessor->encode(
            $preparedDataForToir,
            [
                XmlEncoder::CDATA_WRAPPING         => true,
                XmlEncoder::CDATA_WRAPPING_PATTERN => '/./',
            ]
        );

        $results['main'] = $this->sendXmlToTOiR($xmlData);
        $results['main']['message'] = 'Обновление в TOiR3: добавлен новый документ АТР/АРП в декомпозицию';

        return $results;
    }

    /**
     * @param string $xml
     *
     * @return array<string, mixed>
     */
    public function sendXmlToTOiR(string $xml): array
    {
        try {
            $method = 'sendMessage';
            $url = $this->getDatareon() . '/' . $method;

            $token = md5(microtime() . 'id' . time());
            $token = substr($token, 0, 8)
                . '-' . substr($token, 8, 4)
                . '-' . substr($token, 12, 4)
                . '-' . substr($token, 16, 4)
                . '-' . substr($token, 20);

            $xmlMessage = sprintf(
                '<Message xmlns="http://esb.axelot.ru">
                        <Body>%s</Body>
                        <ClassId>1103</ClassId>
                        <Id>%s</Id>
                        <Type>DTP</Type>
                        <Properties>
                            <MessageProperty>
                                <Name>Получатели</Name>
                                <Value>
                                    <StringValues>
                                        <string xmlns="http://schemas.microsoft.com/2003/10/Serialization/Arrays">
                                        TOiR3
                                        </string>
                                    </StringValues>
                                    <Type>String</Type>
                                </Value>
                            </MessageProperty>
                        </Properties>
                    </Message>',
                base64_encode($xml),
                $token
            );

            $ch = curl_init($url);

            if (!$ch) {
                throw new Exception('Ошибка инициализации cURL', Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $xmlMessage,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/xml'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_VERBOSE        => true,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            $status = $curlError
                ? null
                : ($httpCode >= 100 && $httpCode < 600 ? $httpCode : null);

            // Логируем информацию о запросе и ответе
            elkLog('Отправка XML в TOiR', 'info', [
                'xml_data'      => $xml,
                'toir_response' => [
                    'http_code' => $httpCode,
                    'error'     => $curlError,
                    'response'  => $response,
                ],
            ]);

            return [
                'errorMessage' => $curlError,
                'status'       => $status,
            ];
        } catch (Exception $e) {
            elkLog('Ошибка при отправке XML в TOiR: ' . $e->getMessage(), Level::Error, [
                'exception' => [
                    'message' => $e->getMessage(),
                    'code'    => $e->getCode(),
                    'file'    => $e->getFile(),
                    'line'    => $e->getLine(),
                ],
            ]);

            return [
                'errorMessage' => $e->getMessage(),
                'status'       => null,
            ];
        }
    }
}
