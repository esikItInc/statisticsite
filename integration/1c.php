<?php
// URL WSDL файла, который предоставил 1С-разработчик
$wsdl = 'http://10.0.0.10/umc_1c/ws/pgWebsite?wsdl';

try {
    // Создаем SOAP клиент
    $client = new SoapClient($wsdl, [
        'trace' => 1,           // для отладки (позволяет смотреть запросы/ответы)
        'exceptions' => true,   // чтобы ошибки бросались в исключения
        'login' => 'МалаховЕГ',     // если требуется авторизация
        'password' => '33504', // если требуется авторизация
    ]);

    // Вызов метода SOAP-сервиса (пример)
    $params = [
        'param1' => 'значение1',
        'param2' => 'значение2',
    ];

    $response = $client->HelloWorld();

    // Выводим ответ
    var_dump($response);

} catch (SoapFault $fault) {
    echo "SOAP Error: " . $fault->getMessage();
}
?>

