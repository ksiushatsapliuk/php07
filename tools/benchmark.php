<?php
// Лише консоль: php -d extension=pdo_mysql -d extension=curl tools/benchmark.php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

date_default_timezone_set('Europe/Kyiv');

$options = getopt('', ['source06:', 'work-dir:', 'results:', 'httpd:', 'ab:', 'port:']);
$app = dirname(__DIR__);
$source = realpath($options['source06'] ?? $app . '/../php06');
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

    file_put_contents($results . '/' . $label . '-samples.json', json_encode($samples, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    $output = run([$ab, '-n', '200', '-c', '20', '-k', $url]);
    file_put_contents($results . '/' . $label . '-ab.txt', $output);

    foreach (['requests_per_second' => 'Requests per second', 'time_per_request_ms' => 'Time per request', 'failed_requests' => 'Failed requests', 'complete_requests' => 'Complete requests', 'concurrency' => 'Concurrency Level'] as $key => $name) {
        ensure(preg_match('/' . $name . ':\s+([\d.]+)/', $output, $match) === 1, 'Немає метрики ' . $name);
        $metrics[$key] = (float)$match[1];
    }

    ensure($metrics['failed_requests'] === 0.0 && !str_contains($output, 'Non-2xx responses:'), 'Помилки AB.');
    ensure($metrics['complete_requests'] === 200.0 && $metrics['concurrency'] === 20.0, 'Некоректні параметри AB.');
    $result = array_merge($samples[0], $metrics, [
        'url' => $url,
        'elapsed_ms' => array_sum(array_column($samples, 'elapsed_ms')) / 10,
        'peak_mb' => max(array_column($samples, 'peak_mb')),
    ]);
    echo $label . ': ' . json_encode($result, JSON_UNESCAPED_UNICODE) . PHP_EOL;

    return $result;
}

ensure($source !== false, 'Не знайдено php06; задайте --source06=шлях.');
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
$config = require $source . '/config.php';
$pdo = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $config['host'], $config['port']), $config['user'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database = 'codex_php07_bench_' . bin2hex(random_bytes(8));
$server = null;

try {
    $schema = str_replace($config['database'], $database, file_get_contents($source . '/schema.sql'));
    $pdo->exec($schema);
    $pdo->exec("SET time_zone = '" . date('P') . "'");
    $insert = $pdo->prepare('INSERT INTO plants(name,watering_interval_days,last_watered_at,is_succulent,sunlight_needs) VALUES (?, ?, DATE_SUB(CURDATE(), INTERVAL ? DAY), 0, ?)');

    for ($i = 0; $i < 500; $i++) $insert->execute(['Рослина ' . $i, $i % 100 + 1, $i % 120, 'Розсіяне світло']);

    $pdo->query('ANALYZE TABLE plants')->fetchAll();

    foreach (['original06' => $source, 'nplus' => $app, 'after' => $app, 'uncached' => $app] as $label => $from) {
        $site = $work . '/' . $label;
        copyPhp($from, $site);
        file_put_contents($site . '/config.php', '<?php return ' . var_export(array_replace($config, ['database' => $database]), true) . ';');
    }

    // Лише тестова копія 06 отримує лічильник і заголовки профілювання.
    foreach (['MeasuredPDO.php', 'Profile.php'] as $name) copy($app . '/lib/' . $name, $work . '/original06/lib/' . $name);
    $file = $work . '/original06/db.php';
    $text = str_replace('new PDO(', 'new MeasuredPDO(', file_get_contents($file));
    file_put_contents($file, str_replace('<?php', "<?php\nrequire_once __DIR__ . '/lib/MeasuredPDO.php';", $text));
    $file = $work . '/original06/api.php';
    file_put_contents($file, str_replace('<?php', "<?php\nrequire_once __DIR__ . '/lib/Profile.php';\nProfile::start();", file_get_contents($file)));
    $file = $work . '/original06/api_common.php';
    $text = preg_replace_callback('/echo (json_encode\([^;]+\));/', fn($m) => '$json = ' . $m[1] . ";\nheader('Content-Length: ' . strlen(\$json));\nProfile::finish(\$GLOBALS['pdo'] ?? null);\necho \$json;", file_get_contents($file));
    file_put_contents($file, $text);

    // Навчальний N+1 існує лише в окремій копії; робочий API не змінюється.
    $file = $work . '/nplus/classes/Garden.php';
    $text = preg_replace('/\$sql = \'SELECT p\.\*, grouped\.same_interval_count.*?grouped\.watering_interval_days = p\.watering_interval_days\';/s', "\$sql = 'SELECT p.* FROM plants p';", file_get_contents($file), 1, $replaced);
    ensure($replaced === 1, 'Змінився GROUP BY: оновіть навчальну заміну.');
    $text = str_replace('return $stmt->fetchAll();', <<<'PHP'
$rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $count = $this->pdo->prepare('SELECT COUNT(*) FROM plants WHERE watering_interval_days = ?');
            $count->execute([$row['watering_interval_days']]);
            $row['same_interval_count'] = (int)$count->fetchColumn();
        }
        return $rows;
PHP, $text);
    file_put_contents($file, str_replace("return \$this->cache->remember(fn() => \$this->getAllPlants('', null, true));", "return \$this->getAllPlants('', null, true);", $text));

    // Контроль кешу: та сама оптимізована логіка, лише без читання/запису кешу.
    $file = $work . '/uncached/classes/Garden.php';
    file_put_contents($file, str_replace("return \$this->cache->remember(fn() => \$this->getAllPlants('', null, true));", "return \$this->getAllPlants('', null, true);", file_get_contents($file)));

    $apache = str_replace('\\', '/', dirname(dirname($httpd)));
    $php = str_replace('\\', '/', dirname(PHP_BINARY));
    mkdir($work . '/sessions');
    file_put_contents($work . '/php.ini', "extension_dir=\"$php/ext\"\nextension=pdo_mysql\nextension=mbstring\nsession.save_path=\"$work/sessions\"\nsys_temp_dir=\"$work\"\nmax_execution_time=300\nmemory_limit=256M\ndisplay_errors=Off\nlog_errors=On\nerror_log=\"$work/php-error.log\"\n");
    $conf = "ServerRoot \"$apache\"\nServerName 127.0.0.1\nListen 127.0.0.1:$port\nPidFile \"$work/apache.pid\"\nErrorLog \"$work/apache-error.log\"\n";

    foreach (['authz_core', 'authz_host', 'dir', 'mime'] as $module) $conf .= "LoadModule {$module}_module modules/mod_$module.so\n";

    $conf .= "TypesConfig \"$apache/conf/mime.types\"\nLoadFile \"$php/php8ts.dll\"\nLoadModule php_module \"$php/php8apache2_4.dll\"\nPHPIniDir \"$work\"\nThreadsPerChild 32\nKeepAlive On\nMaxKeepAliveRequests 1000\nKeepAliveTimeout 30\nDocumentRoot \"$work\"\n<Directory \"$work\">\nRequire all granted\nAllowOverride None\nOptions None\n</Directory>\n<FilesMatch \"\\.php$\">\nSetHandler application/x-httpd-php\n</FilesMatch>\n";
    file_put_contents($work . '/apache.conf', $conf);
    run([$httpd, '-t', '-f', $work . '/apache.conf']);
    $server = proc_open([$httpd, '-X', '-f', $work . '/apache.conf'], [1 => ['file', $work . '/console.log', 'a'], 2 => ['redirect', 1]], $pipes);
    ensure(is_resource($server), 'Apache не запущено.');

    for ($i = 0; $i < 50; $i++) {
        $socket = @fsockopen('127.0.0.1', $port);
        if ($socket) { fclose($socket); break; }
        usleep(200000);
    }

    $plan = fn() => $pdo->query('EXPLAIN SELECT * FROM plants WHERE watering_interval_days = 7')->fetchAll(PDO::FETCH_ASSOC);
    $before = $plan();
    $summary = ['environment' => [
        'started_at' => date(DATE_ATOM), 'php' => PHP_VERSION,
        'apache' => trim(run([$httpd, '-v'])), 'ab' => trim(run([$ab, '-V'])),
        'mysql' => $pdo->query('SELECT VERSION()')->fetchColumn(),
        'server' => 'Apache WinNT, 32 threads; ab -n 200 -c 20 -k',
        'rows' => 500, 'profile_samples' => 10, 'ab_requests' => 200, 'ab_concurrency' => 20,
    ]];
    $base = 'http://127.0.0.1:' . $port;
    $summary['original06'] = measure('original06', $base . '/original06/api.php?resource=plants');
    $summary['nplus'] = measure('nplus', $base . '/nplus/api.php?resource=plants');
    $summary['nplus_due'] = measure('nplus_due', $base . '/nplus/api.php?resource=plants&due=1');
    $pdo->exec('CREATE INDEX idx_plants_watering_interval ON plants(watering_interval_days)');
    $pdo->query('ANALYZE TABLE plants')->fetchAll();
    $after = $plan();
    $summary['nplus_indexed'] = measure('nplus_indexed', $base . '/nplus/api.php?resource=plants');
    $url = $base . '/after/api.php?resource=plants';
    $summary['optimized'] = measure('optimized', $url);
    $summary['grouped_due'] = measure('grouped_due', $base . '/uncached/api.php?resource=plants&due=1');
    $summary['cache_cold'] = sample($url . '&due=1');
    $summary['cache_warm'] = measure('cache_warm', $url . '&due=1');
    ensure($summary['cache_cold']['cache'] === 'MISS' && $summary['cache_warm']['cache'] === 'HIT', 'Перевірте кеш.');
    ensure($summary['nplus_indexed']['body_sha256'] === $summary['optimized']['body_sha256'], 'Списки N+1 та GROUP BY відрізняються.');
    ensure($summary['grouped_due']['body_sha256'] === $summary['cache_warm']['body_sha256'], 'Кеш змінив відповідь списку поливу.');
    ensure($summary['grouped_due']['rows'] === 200 && $summary['optimized']['rows'] === 500, 'Некоректні тестові дані.');

    foreach (['before' => $before, 'after' => $after] as $label => $rows) {
        $lines = [implode("\t", array_keys($rows[0]))];
        foreach ($rows as $row) $lines[] = implode("\t", array_map(fn($v) => $v ?? 'NULL', $row));
        file_put_contents($results . '/explain-' . $label . '.tsv', implode(PHP_EOL, $lines) . PHP_EOL);
        $summary['explain_' . $label] = $rows;
    }

    $summary['environment']['completed_at'] = date(DATE_ATOM);
    file_put_contents($results . '/summary.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    $pdo->exec('DROP DATABASE IF EXISTS ' . $database);
}
