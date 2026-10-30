<?php

return [
    'version' => 15,
    'name' => 'add core indexes and defaults',
    'up' => static function (\App\Classes\Database $db): void {
        $rows = $db->Query("SELECT table_name, index_name FROM information_schema.statistics
            WHERE table_schema = DATABASE()
                AND table_name IN ('bit_accounts', 'bit_threads', 'bit_posts')")->FetchAll();

        $indexes = [];

        foreach ($rows as $row)
            $indexes[$row['table_name'] . '.' . $row['index_name']] = true;

        $addIndex = static function (string $table, string $index, array $columns, bool $unique = false) use ($db, &$indexes): void {
            if (isset($indexes[$table . '.' . $index]))
                return;

            if ($unique && count($columns) === 1)
            {
                $column = $columns[0];
                $duplicate = $db->Query(
                    'SELECT `' . $column . '` AS duplicate_value FROM `' . $table . '`
                        GROUP BY `' . $column . '` HAVING COUNT(*) > 1 LIMIT 1'
                )->FetchArray();

                if (!empty($duplicate))
                {
                    error_log('BitBoard migration 15: skipped unique index ' . $index . ' because ' . $table . '.' . $column . ' contains duplicates.');
                    return;
                }
            }

            $quoted = array_map(static fn (string $column): string => '`' . $column . '`', $columns);

            try
            {
                $db->Query(
                    'ALTER TABLE `' . $table . '` ADD ' . ($unique ? 'UNIQUE ' : '') . 'INDEX `' . $index . '` ('
                    . implode(', ', $quoted) . ')'
                );

                $indexes[$table . '.' . $index] = true;
            }
            catch (\Throwable $e)
            {
                error_log('BitBoard migration 15: index ' . $index . ' failed: ' . $e->getMessage());
            }
        };

        $addIndex('bit_accounts', 'uniq_accounts_username', ['username'], true);
        $addIndex('bit_accounts', 'uniq_accounts_email', ['email'], true);
        $addIndex('bit_accounts', 'accounts_last_active', ['last_active']);
        $addIndex('bit_threads', 'threads_forum', ['forum_id', 'subforum_id']);
        $addIndex('bit_threads', 'threads_user', ['user_id']);
        $addIndex('bit_posts', 'posts_thread', ['thread_id']);
        $addIndex('bit_posts', 'posts_user', ['user_id']);

        $definitions = [
            'bit_threads' => [
                'is_closed' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'is_pinned' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'prefix_id' => 'INT(6) NOT NULL DEFAULT 0',
                'thread_likes' => 'INT(6) NOT NULL DEFAULT 0',
            ],
            'bit_posts' => [
                'post_likes' => 'INT(6) NOT NULL DEFAULT 0',
            ],
        ];

        $rows = $db->Query("SELECT table_name, column_name, column_default FROM information_schema.columns
            WHERE table_schema = DATABASE()
                AND ((table_name = 'bit_threads' AND column_name IN ('is_closed', 'is_pinned', 'prefix_id', 'thread_likes'))
                    OR (table_name = 'bit_posts' AND column_name = 'post_likes'))")->FetchAll();

        $defaults = [];

        foreach ($rows as $row)
            $defaults[$row['table_name'] . '.' . $row['column_name']] = $row['column_default'];

        foreach ($definitions as $table => $columns)
        {
            foreach ($columns as $column => $definition)
            {
                $key = $table . '.' . $column;

                if (!array_key_exists($key, $defaults) || $defaults[$key] !== null)
                    continue;

                try
                {
                    $db->Query('ALTER TABLE `' . $table . '` MODIFY `' . $column . '` ' . $definition);
                }
                catch (\Throwable $e)
                {
                    error_log('BitBoard migration 15: default for ' . $key . ' failed: ' . $e->getMessage());
                }
            }
        }

        $searchIndexes = [
            'bit_threads' => ['ft_thread_search', 'thread_title, thread_content'],
            'bit_posts' => ['ft_post_search', 'post_content'],
        ];

        foreach ($searchIndexes as $table => [$index, $columns])
        {
            if (\App\Classes\Migrator::IndexExists($db, $table, $index))
                continue;

            try
            {
                $db->Query('ALTER TABLE `' . $table . '` ADD FULLTEXT INDEX `' . $index . '` (' . $columns . ')');
            }
            catch (\Throwable $e)
            {
                // Search falls back to LIKE when the database user cannot add FULLTEXT indexes.
                error_log('BitBoard migration 15: fulltext index ' . $index . ' skipped: ' . $e->getMessage());
            }
        }
    },
];
