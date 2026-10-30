<?php

return [
    'version' => 13,
    'name' => 'add unique like indexes',
    'up' => static function (\App\Classes\Database $db): void {
        if (\App\Classes\Migrator::TableExists($db, 'bit_threads_likes')
            && !\App\Classes\Migrator::IndexExists($db, 'bit_threads_likes', 'uniq_thread_like'))
        {
            $db->Query('DELETE l1 FROM bit_threads_likes AS l1
                JOIN bit_threads_likes AS l2
                    ON l1.user_id = l2.user_id AND l1.thread_id = l2.thread_id AND l1.id > l2.id');
            $db->Query('ALTER TABLE bit_threads_likes ADD UNIQUE INDEX uniq_thread_like (user_id, thread_id)');
        }

        if (\App\Classes\Migrator::TableExists($db, 'bit_posts_likes')
            && !\App\Classes\Migrator::IndexExists($db, 'bit_posts_likes', 'uniq_post_like'))
        {
            $db->Query('DELETE l1 FROM bit_posts_likes AS l1
                JOIN bit_posts_likes AS l2
                    ON l1.user_id = l2.user_id AND l1.post_id = l2.post_id AND l1.id > l2.id');
            $db->Query('ALTER TABLE bit_posts_likes ADD UNIQUE INDEX uniq_post_like (user_id, post_id)');
        }
    },
];
