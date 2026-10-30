<?php

return [
    'version' => 16,
    'name' => 'add foreign keys and clean orphans',
    'up' => static function (\App\Classes\Database $db): void {
        $cleanup = static function (string $sql) use ($db): int {
            $db->Query($sql);
            return (int) $db->AffectedRows();
        };

        $removed = 0;

        // Remove content whose thread, author, or forum no longer exists.
        $removed += $cleanup('DELETE p FROM bit_posts AS p
            LEFT JOIN bit_threads AS t ON t.id = p.thread_id
            LEFT JOIN bit_accounts AS u ON u.id = p.user_id
            LEFT JOIN bit_accounts AS tu ON tu.id = t.user_id
            LEFT JOIN bit_forums AS f ON f.id = t.forum_id
            WHERE t.id IS NULL OR u.id IS NULL OR tu.id IS NULL OR f.id IS NULL');

        $removed += $cleanup('DELETE t FROM bit_threads AS t
            LEFT JOIN bit_accounts AS u ON u.id = t.user_id
            LEFT JOIN bit_forums AS f ON f.id = t.forum_id
            WHERE u.id IS NULL OR f.id IS NULL');

        // Dependent rows. Parent threads/posts may have been removed above.
        $removed += $cleanup('DELETE l FROM bit_threads_likes AS l
            LEFT JOIN bit_accounts AS a ON a.id = l.user_id
            LEFT JOIN bit_threads AS t ON t.id = l.thread_id
            WHERE a.id IS NULL OR t.id IS NULL');

        $removed += $cleanup('DELETE l FROM bit_posts_likes AS l
            LEFT JOIN bit_accounts AS a ON a.id = l.user_id
            LEFT JOIN bit_posts AS p ON p.id = l.post_id
            WHERE a.id IS NULL OR p.id IS NULL');

        $removed += $cleanup('DELETE e FROM bit_thread_edits AS e
            LEFT JOIN bit_accounts AS a ON a.id = e.user_id
            LEFT JOIN bit_threads AS t ON t.id = e.thread_id
            WHERE a.id IS NULL OR t.id IS NULL');

        $removed += $cleanup('DELETE e FROM bit_post_edits AS e
            LEFT JOIN bit_accounts AS a ON a.id = e.user_id
            LEFT JOIN bit_posts AS p ON p.id = e.post_id
            WHERE a.id IS NULL OR p.id IS NULL');

        $removed += $cleanup('DELETE s FROM bit_thread_subscriptions AS s
            LEFT JOIN bit_accounts AS a ON a.id = s.user_id
            LEFT JOIN bit_threads AS t ON t.id = s.thread_id
            WHERE a.id IS NULL OR t.id IS NULL');

        $removed += $cleanup('DELETE r FROM bit_thread_reads AS r
            LEFT JOIN bit_accounts AS a ON a.id = r.user_id
            LEFT JOIN bit_threads AS t ON t.id = r.thread_id
            WHERE a.id IS NULL OR t.id IS NULL');

        $removed += $cleanup('DELETE s FROM bit_forum_subscriptions AS s
            LEFT JOIN bit_accounts AS a ON a.id = s.user_id
            LEFT JOIN bit_forums AS f ON f.id = s.forum_id
            LEFT JOIN bit_subforums AS sf ON sf.id = s.subforum_id
            WHERE a.id IS NULL OR f.id IS NULL OR (s.subforum_id <> -1 AND sf.id IS NULL)');

        $removed += $cleanup('DELETE n FROM bit_notifications AS n
            LEFT JOIN bit_accounts AS a ON a.id = n.user_id
            LEFT JOIN bit_accounts AS actor ON actor.id = n.actor_id
            WHERE a.id IS NULL OR actor.id IS NULL');

        $removed += $cleanup('DELETE r FROM bit_reports AS r
            LEFT JOIN bit_threads AS t ON t.id = r.thread_id
            LEFT JOIN bit_accounts AS a ON a.id = r.reporter_id
            WHERE t.id IS NULL OR a.id IS NULL');

        $removed += $cleanup('DELETE r FROM bit_reports AS r
            LEFT JOIN bit_posts AS p ON p.id = r.post_id
            WHERE r.post_id > 0 AND p.id IS NULL');

        $removed += $cleanup('UPDATE bit_reports AS r
            LEFT JOIN bit_accounts AS a ON a.id = r.resolved_by
            SET r.resolved_by = NULL
            WHERE r.resolved_by IS NOT NULL AND a.id IS NULL');

        // Clear sentinel references that point at deleted subforums or prefixes.
        // subforum_id uses -1 for "top-level forum thread".
        $removed += $cleanup('UPDATE bit_threads AS t
            LEFT JOIN bit_subforums AS s ON s.id = t.subforum_id
            LEFT JOIN bit_prefixes AS p ON p.id = t.prefix_id
            SET t.subforum_id = IF(t.subforum_id > 0 AND s.id IS NULL, -1, t.subforum_id),
                t.prefix_id = IF(t.prefix_id <> 0 AND p.id IS NULL, 0, t.prefix_id)
            WHERE (t.subforum_id > 0 AND s.id IS NULL) OR (t.prefix_id <> 0 AND p.id IS NULL)');

        $removed += $cleanup('DELETE s FROM bit_subforums AS s
            LEFT JOIN bit_forums AS f ON f.id = s.forum_id
            WHERE f.id IS NULL');

        $removed += $cleanup('DELETE f FROM bit_forums AS f
            LEFT JOIN bit_categories AS c ON c.id = f.category_id
            WHERE c.id IS NULL');

        $fallbackRank = $db->Query('SELECT id FROM bit_ranks
            ORDER BY CASE WHEN id = 2 THEN 0 ELSE 1 END, rank_position ASC, id ASC
            LIMIT 1')->FetchArray();

        if (empty($fallbackRank))
            throw new \RuntimeException('Cannot add the account rank foreign key because no ranks exist.');

        $removed += $cleanup('UPDATE bit_accounts AS a
            LEFT JOIN bit_ranks AS r ON r.id = a.rank_id
            SET a.rank_id = ' . (int) $fallbackRank['id'] . '
            WHERE r.id IS NULL');

        if ($removed > 0)
            error_log('BitBoard migration 16: cleaned ' . $removed . ' orphaned row(s) before adding foreign keys.');

        // Foreign keys require matching signedness. Parent IDs are unsigned.
        $columns = [
            'bit_accounts' => [
                'rank_id' => 'INT(6) UNSIGNED NOT NULL',
            ],
            'bit_forums' => [
                'category_id' => 'INT(6) UNSIGNED NOT NULL',
            ],
            'bit_subforums' => [
                'forum_id' => 'INT(6) UNSIGNED NOT NULL',
            ],
            'bit_threads' => [
                'user_id' => 'INT(6) UNSIGNED NOT NULL',
                'forum_id' => 'INT(6) UNSIGNED NOT NULL',
            ],
            'bit_posts' => [
                'user_id' => 'INT(6) UNSIGNED NOT NULL',
                'thread_id' => 'INT(6) UNSIGNED NOT NULL',
            ],
            'bit_threads_likes' => [
                'user_id' => 'INT(6) UNSIGNED NOT NULL',
                'thread_id' => 'INT(6) UNSIGNED NOT NULL',
            ],
            'bit_posts_likes' => [
                'user_id' => 'INT(6) UNSIGNED NOT NULL',
                'post_id' => 'INT(6) UNSIGNED NOT NULL',
            ],
            'bit_thread_edits' => [
                'thread_id' => 'INT(6) UNSIGNED NOT NULL',
                'user_id' => 'INT(6) UNSIGNED NOT NULL',
            ],
            'bit_post_edits' => [
                'post_id' => 'INT(6) UNSIGNED NOT NULL',
                'user_id' => 'INT(6) UNSIGNED NOT NULL',
            ],
            'bit_thread_subscriptions' => [
                'user_id' => 'INT(6) UNSIGNED NOT NULL',
                'thread_id' => 'INT(6) UNSIGNED NOT NULL',
            ],
            'bit_forum_subscriptions' => [
                'user_id' => 'INT(6) UNSIGNED NOT NULL',
                'forum_id' => 'INT(6) UNSIGNED NOT NULL',
            ],
            'bit_thread_reads' => [
                'user_id' => 'INT(6) UNSIGNED NOT NULL',
                'thread_id' => 'INT(6) UNSIGNED NOT NULL',
            ],
            'bit_notifications' => [
                'user_id' => 'INT(6) UNSIGNED NOT NULL',
                'actor_id' => 'INT(6) UNSIGNED NOT NULL',
            ],
            'bit_reports' => [
                'reporter_id' => 'INT(6) UNSIGNED NOT NULL',
                'thread_id' => 'INT(6) UNSIGNED NOT NULL',
                'resolved_by' => 'INT(6) UNSIGNED NULL DEFAULT NULL',
            ],
        ];

        foreach ($columns as $table => $definitions)
        {
            foreach ($definitions as $column => $definition)
                $db->Query('ALTER TABLE `' . $table . '` MODIFY `' . $column . '` ' . $definition);
        }

        $constraints = [
            ['fk_accounts_rank', 'bit_accounts', 'rank_id', 'bit_ranks', 'id', 'RESTRICT'],
            ['fk_forums_category', 'bit_forums', 'category_id', 'bit_categories', 'id', 'RESTRICT'],
            ['fk_subforums_forum', 'bit_subforums', 'forum_id', 'bit_forums', 'id', 'CASCADE'],
            ['fk_threads_user', 'bit_threads', 'user_id', 'bit_accounts', 'id', 'RESTRICT'],
            ['fk_threads_forum', 'bit_threads', 'forum_id', 'bit_forums', 'id', 'RESTRICT'],
            ['fk_posts_thread', 'bit_posts', 'thread_id', 'bit_threads', 'id', 'CASCADE'],
            ['fk_posts_user', 'bit_posts', 'user_id', 'bit_accounts', 'id', 'RESTRICT'],
            ['fk_thread_likes_user', 'bit_threads_likes', 'user_id', 'bit_accounts', 'id', 'CASCADE'],
            ['fk_thread_likes_thread', 'bit_threads_likes', 'thread_id', 'bit_threads', 'id', 'CASCADE'],
            ['fk_post_likes_user', 'bit_posts_likes', 'user_id', 'bit_accounts', 'id', 'CASCADE'],
            ['fk_post_likes_post', 'bit_posts_likes', 'post_id', 'bit_posts', 'id', 'CASCADE'],
            ['fk_thread_edits_thread', 'bit_thread_edits', 'thread_id', 'bit_threads', 'id', 'CASCADE'],
            ['fk_thread_edits_user', 'bit_thread_edits', 'user_id', 'bit_accounts', 'id', 'RESTRICT'],
            ['fk_post_edits_post', 'bit_post_edits', 'post_id', 'bit_posts', 'id', 'CASCADE'],
            ['fk_post_edits_user', 'bit_post_edits', 'user_id', 'bit_accounts', 'id', 'RESTRICT'],
            ['fk_thread_subs_user', 'bit_thread_subscriptions', 'user_id', 'bit_accounts', 'id', 'CASCADE'],
            ['fk_thread_subs_thread', 'bit_thread_subscriptions', 'thread_id', 'bit_threads', 'id', 'CASCADE'],
            ['fk_forum_subs_user', 'bit_forum_subscriptions', 'user_id', 'bit_accounts', 'id', 'CASCADE'],
            ['fk_forum_subs_forum', 'bit_forum_subscriptions', 'forum_id', 'bit_forums', 'id', 'CASCADE'],
            ['fk_thread_reads_user', 'bit_thread_reads', 'user_id', 'bit_accounts', 'id', 'CASCADE'],
            ['fk_thread_reads_thread', 'bit_thread_reads', 'thread_id', 'bit_threads', 'id', 'CASCADE'],
            ['fk_notifications_user', 'bit_notifications', 'user_id', 'bit_accounts', 'id', 'CASCADE'],
            ['fk_notifications_actor', 'bit_notifications', 'actor_id', 'bit_accounts', 'id', 'CASCADE'],
            ['fk_reports_reporter', 'bit_reports', 'reporter_id', 'bit_accounts', 'id', 'RESTRICT'],
            ['fk_reports_thread', 'bit_reports', 'thread_id', 'bit_threads', 'id', 'CASCADE'],
            ['fk_reports_resolved_by', 'bit_reports', 'resolved_by', 'bit_accounts', 'id', 'SET NULL'],
        ];

        foreach ($constraints as [$name, $table, $column, $referenceTable, $referenceColumn, $onDelete])
        {
            if (\App\Classes\Migrator::ConstraintExists($db, $table, $name))
                continue;

            $db->Query('ALTER TABLE `' . $table . '` ADD CONSTRAINT `' . $name . '`
                FOREIGN KEY (`' . $column . '`) REFERENCES `' . $referenceTable . '` (`' . $referenceColumn . '`)
                ON DELETE ' . $onDelete);
        }
    },
];
