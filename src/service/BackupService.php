<?php
declare(strict_types=1);

namespace plugin\backup\service;

use plugin\backup\model\PluginBackupRecord;
use think\admin\Exception;
use think\admin\extend\CodeExtend;
use think\admin\Service;
use think\admin\Storage;

/**
 * 数据库备份服务（纯 PHP 实现，无需 shell 命令）
 * @class BackupService
 * @package plugin\backup\service
 */
class BackupService extends Service
{
    /**
     * 备份文件存储根目录
     */
    protected string $backupPath = '';

    /**
     * 当前数据库配置
     */
    protected array $dbConfig = [];

    /**
     * PDO 连接实例
     */
    protected ?\PDO $pdo = null;

    /**
     * 服务初始化
     */
    protected function initialize(): void
    {
        $this->dbConfig = config('database.connections.mysql');
        $this->backupPath = $this->detectBackupPath();
    }

    /**
     * 自动检测可写的备份目录
     * @return string 可写的绝对路径（末尾带 DIRECTORY_SEPARATOR）
     */
    protected function detectBackupPath(): string
    {
        $candidates = [];

        // 优先级 1：系统配置的自定义路径
        $custom = rtrim((string) sysconf('data.backup_path'), '/\\');
        if ($custom !== '') {
            $candidates[] = $custom;
        }

        // 优先级 2：runtime/database（常规运行目录）
        $candidates[] = runtime_path() . 'database';

        // 优先级 3：public/runtime/database（网站根目录可访问到）
        $candidates[] = root_path() . 'public' . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'database';

        foreach ($candidates as $path) {
            $dir = rtrim($path, '/\\') . DIRECTORY_SEPARATOR;
            if (\is_dir($dir) && \is_writable($dir)) {
                return $dir;
            }
            if (!\is_dir($dir)) {
                if (@\mkdir($dir, 0755, true) && \is_writable($dir)) {
                    return $dir;
                }
            }
        }

        // 最后的兜底：runtime_path
        $fallback = runtime_path() . 'database' . DIRECTORY_SEPARATOR;
        if (!\is_dir($fallback)) {
            @\mkdir($fallback, 0755, true);
        }
        return $fallback;
    }

    /**
     * 验证对象存储配置是否完整
     * @param string $type 存储类型
     * @return bool 配置完整返回 true，有缺失返回 false
     */
    protected function validateStorageConfig(string $type): bool
    {
        $requiredKeys = [
            'alioss' => ['storage.alioss_bucket', 'storage.alioss_access_key', 'storage.alioss_secret_key'],
            'qiniu'  => ['storage.qiniu_bucket', 'storage.qiniu_access_key', 'storage.qiniu_secret_key'],
            'txcos'  => ['storage.txcos_bucket', 'storage.txcos_access_key', 'storage.txcos_secret_key'],
            'upyun'  => ['storage.upyun_bucket', 'storage.upyun_access_key', 'storage.upyun_secret_key'],
            'alist'  => ['storage.alist_http_domain', 'storage.alist_savepath', 'storage.alist_username', 'storage.alist_password'],
        ];

        if (!isset($requiredKeys[$type])) {
            return false;
        }

        foreach ($requiredKeys[$type] as $key) {
            $value = trim((string) sysconf($key));
            if ($value === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * 获取 PDO 连接
     */
    protected function getPdo(): \PDO
    {
        if ($this->pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $this->dbConfig['hostname'] ?? '127.0.0.1',
                $this->dbConfig['hostport'] ?? '3306',
                $this->dbConfig['database'] ?? '',
                $this->dbConfig['charset'] ?? 'utf8mb4'
            );
            $this->pdo = new \PDO(
                $dsn,
                $this->dbConfig['username'] ?? '',
                $this->dbConfig['password'] ?? '',
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_TIMEOUT => 60]
            );
        }
        return $this->pdo;
    }

    /**
     * 执行数据库备份（纯 PHP）
     * @param string $name 备份名称
     * @return array ['code' => 0|1, 'info' => string, 'data' => []]
     */
    public function backup(string $name = ''): array
    {
        if (empty($name)) {
            $name = CodeExtend::uniqidDate(16, 'DB');
        }

        $dbname = $this->dbConfig['database'] ?? '';
        $filename = $name . '_' . \date('YmdHis') . '.sql';
        $filepath = $this->backupPath . $filename;

        try {
            $pdo = $this->getPdo();

            // 获取所有表
            $stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
            $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            if (empty($tables)) {
                return [0, '数据库中没有任何表', []];
            }

            $sql = "-- -----------------------------\n";
            $sql .= "-- 备份文件\n";
            $sql .= "-- 数据库: {$dbname}\n";
            $sql .= "-- 备份时间: " . \date('Y-m-d H:i:s') . "\n";
            $sql .= "-- -----------------------------\n\n";
            $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

            foreach ($tables as $table) {
                $sql .= "-- -----------------------------\n";
                $sql .= "-- 表结构: {$table}\n";
                $sql .= "-- -----------------------------\n";
                $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";

                // 获取建表语句
                $createStmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
                $createRow = $createStmt->fetch(\PDO::FETCH_ASSOC);
                $sql .= $createRow['Create Table'] . ";\n\n";

                // 导出数据
                $sql .= "-- -----------------------------\n";
                $sql .= "-- 数据: {$table}\n";
                $sql .= "-- -----------------------------\n";

                $countStmt = $pdo->query("SELECT COUNT(*) FROM `{$table}`");
                $total = (int) $countStmt->fetch(\PDO::FETCH_COLUMN);

                if ($total > 0) {
                    $batchSize = 500;
                    $offset = 0;
                    while ($offset < $total) {
                        $rows = $pdo->query("SELECT * FROM `{$table}` LIMIT {$batchSize} OFFSET {$offset}");
                        while ($row = $rows->fetch(\PDO::FETCH_ASSOC)) {
                            $values = [];
                            foreach ($row as $val) {
                                if ($val === null) {
                                    $values[] = 'NULL';
                                } else {
                                    $values[] = $pdo->quote((string) $val);
                                }
                            }
                            $cols = '`' . \implode('`, `', \array_keys($row)) . '`';
                            $vals = \implode(', ', $values);
                            $sql .= "INSERT INTO `{$table}` ({$cols}) VALUES ({$vals});\n";
                        }
                        $offset += $batchSize;
                    }
                }
                $sql .= "\n";
            }

            $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

            $written = \file_put_contents($filepath, $sql);
            if ($written === false) {
                return [0, '写入备份文件失败', []];
            }

            $filesize = \filesize($filepath);
            $tablesCount = \count($tables);

            // 写入数据库记录（先不加 storage_type 和 storage_url）
            $recordId = PluginBackupRecord::mk()->insertGetId([
                'name'      => $name,
                'file'      => $filename,
                'size'      => $filesize,
                'path'      => $filepath,
                'tables'    => $tablesCount,
                'status'    => 1,
                'create_at' => \date('Y-m-d H:i:s'),
            ]);

            // 检查是否需要同步到对象存储
            $storageType = trim((string) sysconf('data.backup_storage_type'));
            $storageUrl = '';
            if ($storageType !== '' && $storageType !== 'local') {
                if (!$this->validateStorageConfig($storageType)) {
                    // 配置缺失，跳过上传，仅写入记录
                    PluginBackupRecord::mk()->where('id', $recordId)->update([
                        'storage_type' => $storageType,
                        'storage_url'  => '',
                    ]);
                } else {
                    // 配置验证通过，尝试上传到对象存储
                    $content = \file_get_contents($filepath);
                    if ($content !== false) {
                        try {
                            $key = 'database/' . $filename;
                            $info = Storage::instance($storageType)->set($key, $content);
                            $storageUrl = $info['url'] ?? '';
                        } catch (Exception $e) {
                            // 上传失败不影响备份记录，仅记录错误
                            $storageUrl = '';
                        }
                    }
                    PluginBackupRecord::mk()->where('id', $recordId)->update([
                        'storage_type' => $storageType,
                        'storage_url'  => $storageUrl,
                    ]);
                }
            }

            return [1, "备份成功，文件: {$filename}", ['file' => $filename, 'size' => $filesize, 'tables' => $tablesCount, 'storage_url' => $storageUrl]];
        } catch (\Throwable $e) {
            PluginBackupRecord::mk()->insertGetId([
                'name'      => $name,
                'file'      => $filename,
                'size'      => 0,
                'path'      => $filepath,
                'tables'    => 0,
                'status'    => 0,
                'create_at' => \date('Y-m-d H:i:s'),
            ]);
            return [0, "备份失败: " . $e->getMessage(), []];
        }
    }

    /**
     * 执行数据库还原（纯 PHP）
     * @param int $id 备份记录ID
     * @return array ['code' => 0|1, 'info' => string]
     */
    public function restore(int $id): array
    {
        $record = PluginBackupRecord::mk()->find($id);
        if (empty($record)) {
            return [0, '备份记录不存在'];
        }
        if ($record['status'] != 1) {
            return [0, '该备份文件无效，无法还原'];
        }
        $filepath = $record['path'];
        if (!\file_exists($filepath)) {
            return [0, "备份文件不存在: {$filepath}"];
        }

        try {
            $pdo = $this->getPdo();

            $sql = \file_get_contents($filepath);
            if ($sql === false) {
                return [0, '读取备份文件失败'];
            }

            // 分割 SQL 语句（处理 DELIMITER 和存储过程的情况，这里做简化处理）
            $statements = $this->splitSqlStatements($sql);

            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            $count = 0;
            foreach ($statements as $stmt) {
                $stmt = \trim($stmt);
                if (empty($stmt)) {
                    continue;
                }
                $pdo->exec($stmt);
                $count++;
            }
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

            return [1, "还原成功，共执行 {$count} 条 SQL 语句"];
        } catch (\Throwable $e) {
            return [0, "还原失败: " . $e->getMessage()];
        }
    }

    /**
     * 分割 SQL 文件为单条语句（状态机解析，正确处理数据值中的分号）
     */
    protected function splitSqlStatements(string $sql): array
    {
        // 先去掉所有 -- 行注释，避免注释混入语句块导致 DROP TABLE 等被吞掉
        $sql = \preg_replace('/^\s*--.*/m', '', $sql);

        $statements = [];
        $len = \strlen($sql);
        $current = '';
        $inString = false;
        $stringChar = "'";

        for ($i = 0; $i < $len; $i++) {
            $char = $sql[$i];

            // 转义字符：\' 或 \\'
            if ($char === '\\' && $i + 1 < $len) {
                $current .= $char . $sql[++$i];
                continue;
            }

            // 进入 / 退出字符串
            if ($char === "'" && !$inString) {
                $inString = true;
                $stringChar = "'";
                $current .= $char;
            } elseif ($char === "'" && $inString && $stringChar === "'") {
                // 单引号字符串中，检查是否是转义的单引号（''）
                if ($i + 1 < $len && $sql[$i + 1] === "'") {
                    $current .= "''";
                    $i++; // 多消费一个单引号
                } else {
                    $inString = false;
                    $current .= $char;
                }
            } elseif ($char === '"' && !$inString) {
                $inString = true;
                $stringChar = '"';
                $current .= $char;
            } elseif ($char === '"' && $inString && $stringChar === '"') {
                if ($i + 1 < $len && $sql[$i + 1] === '"') {
                    $current .= '""';
                    $i++;
                } else {
                    $inString = false;
                    $current .= $char;
                }
            } elseif ($char === '`' && !$inString) {
                $inString = true;
                $stringChar = '`';
                $current .= $char;
            } elseif ($char === '`' && $inString && $stringChar === '`') {
                if ($i + 1 < $len && $sql[$i + 1] === '`') {
                    $current .= '``';
                    $i++;
                } else {
                    $inString = false;
                    $current .= $char;
                }
            } elseif ($char === ';' && !$inString) {
                $stmt = \trim($current);
                if (!empty($stmt)) {
                    $statements[] = $stmt;
                }
                $current = '';
            } else {
                $current .= $char;
            }
        }

        // 处理最后一条（文件末尾可能没有分号）
        $stmt = \trim($current);
        if (!empty($stmt)) {
            $statements[] = $stmt;
        }

        return $statements;
    }

    /**
     * 删除备份文件及记录
     * @param int $id 备份记录ID
     * @return array ['code' => 0|1, 'info' => string]
     */
    public function delete(int $id): array
    {
        $record = PluginBackupRecord::mk()->find($id);
        if (empty($record)) {
            return [0, '备份记录不存在'];
        }

        // 删除本地文件
        if (!empty($record['path']) && \file_exists($record['path'])) {
            \unlink($record['path']);
        }

        // 如果设置了远程存储，同步删除远程文件
        $storageType = trim($record['storage_type'] ?? '');
        if ($storageType !== '' && $this->validateStorageConfig($storageType)) {
            try {
                Storage::instance($storageType)->del('database/' . $record['file']);
            } catch (Exception $e) {
                // 远程删除失败不影响本地删除流程
            }
        }

        PluginBackupRecord::mk()->where('id', $id)->delete();

        return [1, '删除成功'];
    }

    /**
     * 扫描备份目录中的文件
     * @return array
     */
    public function scanLocalFiles(): array
    {
        $files = [];
        if (\is_dir($this->backupPath)) {
            $items = \glob($this->backupPath . '*.sql');
            foreach ($items as $file) {
                $files[] = [
                    'name' => \basename($file),
                    'path' => $file,
                    'size' => \filesize($file),
                    'time' => \filemtime($file),
                ];
            }
        }
        return $files;
    }

    /**
     * 清理孤立的备份文件（无数据库记录的）
     * @return int 删除的文件数量
     */
    public function cleanOrphanFiles(): int
    {
        $records = PluginBackupRecord::mk()->column('path', 'id');
        $count = 0;
        foreach ($this->scanLocalFiles() as $file) {
            if (!\in_array($file['path'], $records) && \file_exists($file['path'])) {
                \unlink($file['path']);
                $count++;
            }
        }
        return $count;
    }
}
