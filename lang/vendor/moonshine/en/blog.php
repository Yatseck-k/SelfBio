<?php

return [
    'resource' => [
        'title' => 'Blog',
    ],
    'fields'   => [
        'title'        => 'Title',
        'slug'         => 'Slug',
        'preview'      => 'Preview',
        'body'         => 'Post Content',
        'image'        => 'Cover Image',
        'published_at' => 'Publication Date',
    ],
    'hints'    => [
        'title'        => 'Main post title',
        'slug'         => 'For pretty URLs, latin characters and dashes',
        'preview'      => 'Short description/preview in the feed',
        'body'         => 'Full text with markup (Markdown/HTML supported)',
        'image'        => 'Main image for the post',
        'published_at' => 'When to show the post in blog (Y-m-d H:i)',
    ],
];
