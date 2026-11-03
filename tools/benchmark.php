<?php
// Лише консоль: php -d extension=pdo_mysql -d extension=curl tools/benchmark.php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

date_default_timezone_set('Europe/Kyiv');

$options = getopt('', ['work-dir:', 'results:', 'httpd:', 'ab:', 'port:']);
$app = dirname(__DIR__);
require_once __DIR__ . '/../lib/migrations.php';
require_once $app . '/classes/Garden.php';
$work = $options['work-dir'] ?? sys_get_temp_dir() . '/floracare_bench_' . bin2hex(random_bytes(5));
$results = $options['results'] ?? $app . '/results';
$httpd = $options['httpd'] ?? 'C:/xampp/apache/bin/httpd.exe';
$ab = $options['ab'] ?? 'C:/xampp/apache/bin/ab.exe';
$port = (int)($options['port'] ?? 8101);

function ensure(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function run(array $command): string
{
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes);
    ensure(is_resource($process), 'Не вдалося запустити команду.');
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    ensure(proc_close($process) === 0, $output);

    return $output;
}

function copyPhp(string $from, string $to): void
{
    mkdir($to, 0777, true);

    foreach (scandir($from) as $name) {
        if (in_array($name, ['.', '..', 'tools', 'backup', 'results', '.git'], true)) continue;

        $path = $from . '/' . $name;

        if (is_dir($path) && in_array($name, ['classes', 'lib'], true)) {
            copyPhp($path, $to . '/' . $name);
        } elseif (is_file($path) && pathinfo($name, PATHINFO_EXTENSION) === 'php') {
            copy($path, $to . '/' . $name);
        }
    }
}

function sample(string $url): array
{
    $client = curl_init($url);
    curl_setopt_array($client, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 30]);
    $response = curl_exec($client);
    ensure($response !== false, curl_error($client));
    ensure(curl_getinfo($client, CURLINFO_RESPONSE_CODE) === 200, 'Очікувалася HTTP-відповідь 200.');
    $headerSize = curl_getinfo($client, CURLINFO_HEADER_SIZE);
    $body = substr($response, $headerSize);
    $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    ensure(($data['success'] ?? false) === true, 'Помилка API.');
    $headers = [];

    foreach (explode("\r\n", substr($response, 0, $headerSize)) as $line) {
        if (str_contains($line, ':')) {
            [$key, $value] = explode(':', $line, 2);
            $headers[strtolower($key)] = trim($value);
        }
    }

    foreach (['x-execution-ms', 'x-peak-memory-mb', 'x-sql-queries'] as $name) {
        ensure(isset($headers[$name]) && is_numeric($headers[$name]), 'Відсутня метрика ' . $name);
    }

    return [
        'elapsed_ms' => (float)$headers['x-execution-ms'],
        'peak_mb' => (float)$headers['x-peak-memory-mb'],
        'sql_queries' => (int)$headers['x-sql-queries'],
        'cache' => $headers['x-cache'] ?? 'BYPASS',
        'rows' => count($data['data']),
        'body_sha256' => hash('sha256', $body),
    ];
}

function measure(string $label, string $url): array
{
    global $ab, $results;
    $samples = [];
    $metrics = [];

    for ($i = 0; $i < 10; $i++) $samples[] = sample($url);

    foreach (['rows', 'sql_queries', 'cache', 'body_sha256'] as $key) {
        ensure(count(array_unique(array_column($samples, $key))) === 1, 'Несталі дані серії: ' . $label . ', ' . $key);
    }


    $output = run([$ab, '-n', '200', '-c', '20', '-k', $url]);
    file_put_contents($results . '/ab.txt', '===== ' . $label . ' =====' . PHP_EOL . 'ab -n 200 -c 20 -k ' . $url . PHP_EOL . $output . PHP_EOL, FILE_APPEND);

    foreach (['requests_per_second' => 'Requests per second', 'time_per_request_ms' => 'Time per request', 'failed_requests' => 'Failed requests', 'complete_requests' => 'Complete requests', 'concurrency' => 'Concurrency Level'] as $key => $name) {
        ensure(preg_match('/' . $name . ':\s+([\d.]+)/', $output, $match) === 1, 'Немає метрики ' . $name);
        $metrics[$key] = (float)$match[1];
    }

    ensure($metrics['failed_requests'] === 0.0 && !str_contains($output, 'Non-2xx responses:'), 'Помилки AB.');
    ensure($metrics['complete_requests'] === 200.0 && $metrics['concurrency'] === 20.0, 'Некоректні параметри AB.');
    $result = array_merge($samples[0], $metrics, [
        'url' => $url,
        'samples' => $samples,
        'elapsed_ms' => array_sum(array_column($samples, 'elapsed_ms')) / 10,
        'peak_mb' => max(array_column($samples, 'peak_mb')),
    ]);
    $display = $result;
    unset($display['samples']);
    echo $label . ': ' . json_encode($display, JSON_UNESCAPED_UNICODE) . PHP_EOL;

    return $result;
}

ensure(is_file($app . '/benchmarks/original06.php'), 'Відсутній збережений базовий список 06.');
ensure(extension_loaded('pdo_mysql'), 'Увімкніть pdo_mysql для PHP CLI.');
ensure(extension_loaded('curl'), 'Увімкніть curl для PHP CLI: -d extension=curl.');
ensure(is_file($httpd) && is_file($ab), 'Перевірте --httpd та --ab.');
ensure($port >= 1024 && $port <= 65535, 'Порт має бути від 1024 до 65535.');
$socket = @fsockopen('127.0.0.1', $port);
ensure($socket === false, 'Порт зайнятий; задайте інший через --port.');
ensure(!file_exists($work), 'Робоча папка має бути новою.');
mkdir($work, 0777, true);
if (!is_dir($results)) mkdir($results, 0777, true);
$work = str_replace('\\', '/', realpath($work));
$results = str_replace('\\', '/', realpath($results));
file_put_contents($results . '/ab.txt', '');
if (is_file($results . '/summary.json')) unlink($results . '/summary.json');
$config = require $app . '/config.php';
$pdo = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $config['host'], $config['port']), $config['user'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database = 'codex_php07_bench_' . bin2hex(random_bytes(8));
$server = null;

try {
    $schema = str_replace('practicum7', $database, file_get_contents($app . '/sql/schema.sql'));
    $pdo->exec($schema);
    $pdo->exec("SET time_zone = '" . date('P') . "'");
    $insert = $pdo->prepare('INSERT INTO plants(name,watering_interval_days,last_watered_at,is_succulent,sunlight_needs) VALUES (?, ?, DATE_SUB(CURDATE(), INTERVAL ? DAY), 0, ?)');

    for ($i = 0; $i < 500; $i++) {
        $interval = $i % 100 + 1;
        $insert->execute(['Рослина ' . $i, $interval, $i % 25 === 0 ? $interval + 1 : 0, 'Розсіяне світло']);
    }

    $pdo->query('ANALYZE TABLE plants')->fetchAll();

    foreach (['original06', 'nplus', 'after', 'uncached'] as $label) {
        $site = $work . '/' . $label;
        copyPhp($app, $site);
        file_put_contents($site . '/config.php', '<?php return ' . var_export(array_replace($config, ['database' => $database]), true) . ';');
    }

    mkdir($work . '/original06/benchmarks');
    copy($app . '/benchmarks/original06.php', $work . '/original06/benchmarks/original06.php');

    // Варіанти збережено в репозиторії, SQL не генерується замінами рядків.
    foreach (['nplus' => ['BeforeGarden.php', 'BeforeGarden'], 'uncached' => ['UncachedGarden.php', 'UncachedGarden']] as $label => [$relative, $class]) {
        $destination = $work . '/' . $label . '/benchmarks/' . $relative;
        if (!is_dir(dirname($destination))) mkdir(dirname($destination), 0777, true);
        copy($app . '/benchmarks/' . $relative, $destination);
        $file = $work . '/' . $label . '/api.php';
        $api = file_get_contents($file);
        ensure(substr_count($api, 'new Garden($pdo)') === 1, 'Змінився конструктор API.');
        $api = str_replace('new Garden($pdo)', 'new ' . $class . '($pdo)', $api);
        $include = "require_once __DIR__ . '/benchmarks/" . $relative . "';";
        file_put_contents($file, str_replace('<?php', "<?php\n" . $include, $api));
    }

    $apache = str_replace('\\', '/', dirname(dirname($httpd)));
    $php = str_replace('\\', '/', dirname(PHP_BINARY));
    mkdir($work . '/sessions');
    file_put_contents($work . '/php.ini', "extension_dir=\"$php/ext\"\nextension=pdo_mysql\nextension=mbstring\nsession.save_path=\"$work/sessions\"\nsys_temp_dir=\"$work\"\nmax_execution_time=300\nmemory_limit=256M\ndisplay_errors=Off\nlog_errors=On\nerror_log=\"$work/php-error.log\"\n");
    $conf = "ServerRoot \"$apache\"\nServerName 127.0.0.1\nListen 127.0.0.1:$port\nPidFile \"$work/apache.pid\"\nErrorLog \"$work/apache-error.log\"\n";

    foreach (['authz_core', 'authz_host', 'dir', 'mime', 'env'] as $module) $conf .= "LoadModule {$module}_module modules/mod_$module.so\n";

    $conf .= "TypesConfig \"$apache/conf/mime.types\"\nLoadFile \"$php/php8ts.dll\"\nLoadModule php_module \"$php/php8apache2_4.dll\"\nPHPIniDir \"$work\"\nSetEnv FLORACARE_BENCHMARK 1\nThreadsPerChild 32\nKeepAlive On\nMaxKeepAliveRequests 1000\nKeepAliveTimeout 30\nDocumentRoot \"$work\"\n<Directory \"$work\">\nRequire all granted\nAllowOverride None\nOptions None\n</Directory>\n<FilesMatch \"\\.php$\">\nSetHandler application/x-httpd-php\n</FilesMatch>\n";
    file_put_contents($work . '/apache.conf', $conf);
    run([$httpd, '-t', '-f', $work . '/apache.conf']);
    $server = proc_open([$httpd, '-X', '-f', $work . '/apache.conf'], [1 => ['file', $work . '/console.log', 'a'], 2 => ['redirect', 1]], $pipes);
    ensure(is_resource($server), 'Apache не запущено.');

    for ($i = 0; $i < 50; $i++) {
        $socket = @fsockopen('127.0.0.1', $port);
        if ($socket) { fclose($socket); break; }
        usleep(200000);
    }

    $garden = new Garden($pdo);
    $plans = [];
    $capturePlans = function (string $stage) use ($garden, $pdo, &$plans): void {
        foreach (['interval' => ['', 7, false], 'due' => ['', null, true]] as $label => $filters) {
            [$sql, $params] = $garden->getListQuery(...$filters);
            $statement = $pdo->prepare('EXPLAIN ' . $sql);
            $statement->execute($params);
            $plans[$label][$stage] = ['sql' => $sql, 'params' => $params, 'rows' => $statement->fetchAll(PDO::FETCH_ASSOC)];
        }
    };
    $capturePlans('before');
    $summary = ['environment' => [
        'started_at' => date(DATE_ATOM), 'php' => PHP_VERSION,
        'apache' => trim(run([$httpd, '-v'])), 'ab' => trim(run([$ab, '-V'])),
        'mysql' => $pdo->query('SELECT VERSION()')->fetchColumn(),
        'server' => 'Apache WinNT, 32 threads; ab -n 200 -c 20 -k',
        'rows' => 500, 'profile_samples' => 10, 'ab_requests' => 200, 'ab_concurrency' => 20,
    ]];
    $base = 'http://127.0.0.1:' . $port;
    $summary['original06'] = measure('original06', $base . '/original06/benchmarks/original06.php?resource=plants');
    $summary['nplus'] = measure('nplus', $base . '/nplus/api.php?resource=plants');
    $summary['nplus_due'] = measure('nplus_due', $base . '/nplus/api.php?resource=plants&due=1');
    applyIndexes($pdo);
    applyIndexes($pdo); // Перевірка повторного застосування без Duplicate key name.
    $pdo->query('ANALYZE TABLE plants')->fetchAll();
    $capturePlans('after');
    ensure(in_array('idx_plants_next_watering', array_column($plans['due']['after']['rows'], 'key'), true), 'EXPLAIN реального списку поливу не використав індекс дати.');
    ensure(in_array('idx_plants_watering_interval', array_column($plans['interval']['after']['rows'], 'key'), true), 'EXPLAIN інтервалу не використав потрібний індекс.');
    $summary['nplus_indexed'] = measure('nplus_indexed', $base . '/nplus/api.php?resource=plants');
    $url = $base . '/after/api.php?resource=plants';
    $summary['optimized'] = measure('optimized', $url);
    $summary['grouped_due'] = measure('grouped_due', $base . '/uncached/api.php?resource=plants&due=1');
    $summary['cache_cold'] = sample($url . '&due=1');
    $summary['cache_warm'] = measure('cache_warm', $url . '&due=1');
    ensure($summary['cache_cold']['cache'] === 'MISS' && $summary['cache_warm']['cache'] === 'HIT', 'Перевірте кеш.');
    ensure($summary['nplus_indexed']['body_sha256'] === $summary['optimized']['body_sha256'], 'Списки N+1 та GROUP BY відрізняються.');
    ensure($summary['grouped_due']['body_sha256'] === $summary['cache_warm']['body_sha256'], 'Кеш змінив відповідь списку поливу.');
    ensure($summary['grouped_due']['rows'] === 20 && $summary['optimized']['rows'] === 500, 'Некоректні тестові дані.');

    $summary['plans'] = $plans;
    $summary['environment']['due_rows'] = 20;
    $summary['environment']['completed_at'] = date(DATE_ATOM);
    file_put_contents($results . '/summary.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    $pdo->exec('DROP DATABASE IF EXISTS ' . $database);
}
