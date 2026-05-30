<?php

/**
 * Registers core theme capabilities and navigation menu locations.
 *
 * This runs during theme setup and enables document title management,
 * featured images, HTML5 markup support, and two menu slots used by
 * the header/footer templates.
 *
 * @return void
 */
function dracka_setup()
{
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('widgets');
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption']);
    register_nav_menus([
        'primary' => 'Primary Menu',
        'social'  => 'Social Menu',
    ]);
}

add_action('after_setup_theme', 'dracka_setup');

/**
 * Force comments open for all comic-library post types.
 *
 * Posts created before 'comments' was added to their CPT supports have
 * comment_status='closed' in the DB. This filter overrides that so every
 * issue, series, artwork, and album always accepts comments.
 */
add_filter('comments_open', function (bool $open, int $post_id): bool {
    $comic_types = ['series', 'issue', 'artwork', 'album'];
    if (in_array(get_post_type($post_id), $comic_types, true)) {
        return true;
    }
    return $open;
}, 10, 2);

/**
 * Default new posts in comic-library types to have comments open.
 */
add_filter('default_comment_status', function (string $status, string $post_type): string {
    $comic_types = ['series', 'issue', 'artwork', 'album'];
    if (in_array($post_type, $comic_types, true)) {
        return 'open';
    }
    return $status;
}, 10, 2);

/**
 * Registers widget areas used by the theme.
 *
 * Footer widgets are block-editor compatible and allow managing
 * footer content from Appearance > Widgets.
 *
 * @return void
 */
function dracka_register_sidebars()
{
    $footer_areas = [
        'footer-top-left'     => __('Footer: Top Left', 'dracka'),
        'footer-top-right'    => __('Footer: Top Right', 'dracka'),
        'footer-center-left'  => __('Footer: Center Left', 'dracka'),
        'footer-center-right' => __('Footer: Center Right', 'dracka'),
        'footer-bottom-left'  => __('Footer: Bottom Left', 'dracka'),
        'footer-bottom-right' => __('Footer: Bottom Right', 'dracka'),
    ];

    foreach ($footer_areas as $id => $name) {
        register_sidebar([
            'name'          => $name,
            'id'            => $id,
            'description'   => sprintf(__('Footer grid cell: %s.', 'dracka'), $name),
            'before_widget' => '<div class="footer-widget" id="%1$s">',
            'after_widget'  => '</div>',
            'before_title'  => '<h2 class="footer-cell__title">',
            'after_title'   => '</h2>',
        ]);
    }
}
add_action('widgets_init', 'dracka_register_sidebars');

/**
 * Enqueues frontend stylesheet/script assets and dynamic palette CSS.
 *
 * The function first loads the main theme stylesheet, injects runtime
 * CSS variables derived from Customizer values, and then enqueues the
 * main JavaScript bundle in the footer.
 *
 * @return void
 */
function dracka_enqueue_assets()
{
    $style_path    = get_template_directory() . '/style.css';
    $style_version = file_exists($style_path) ? (string) filemtime($style_path) : '0.1';
    $script_path   = get_template_directory() . '/js/main.js';
    $script_version = file_exists($script_path) ? (string) filemtime($script_path) : '0.1';

    wp_enqueue_style(
        'dracka-style',
        get_stylesheet_uri(),
        [],
        $style_version
    );

    dracka_add_customizer_css();

    wp_enqueue_script(
        'dracka-main',
        get_template_directory_uri() . '/js/main.js',
        [],
        $script_version,
        true
    );

    if (is_singular('artwork')) {
        $artwork_nav_path    = get_template_directory() . '/js/artwork-nav.js';
        $artwork_nav_version = file_exists($artwork_nav_path) ? (string) filemtime($artwork_nav_path) : '0.1';
        wp_enqueue_script(
            'dracka-artwork-nav',
            get_template_directory_uri() . '/js/artwork-nav.js',
            [],
            $artwork_nav_version,
            true
        );
    }

    if (is_singular() && comments_open()) {
        wp_enqueue_script('comment-reply');
    }
}
add_action('wp_enqueue_scripts', 'dracka_enqueue_assets');

/**
 * Registers custom editor blocks for the theme.
 *
 * Creates the dynamic "Latest Issues" and "Latest Artwork" blocks
 * via a shared configuration map.
 *
 * @return void
 */
function dracka_register_blocks()
{
    $blocks = [
        'issue' => [
            'name'           => 'dracka/library',
            'editor_script'  => 'dracka-latest-issues-block-editor',
            'editor_js'      => '/js/blocks/latest-issues.js',
            'render_cb'      => 'dracka_render_latest_issues_block',
            'default_title'  => 'Library',
            'default_label'  => 'Go to library',
            'default_url'    => '/library/issues/',
        ],
        'artwork' => [
            'name'           => 'dracka/gallery',
            'editor_script'  => 'dracka-latest-artwork-block-editor',
            'editor_js'      => '/js/blocks/latest-artwork.js',
            'render_cb'      => 'dracka_render_latest_artwork_block',
            'default_title'  => 'Gallery',
            'default_label'  => 'Go to gallery',
            'default_url'    => '/gallery/artwork/',
        ],
        'newsletter' => [
            'name'           => 'dracka/newsletter',
            'editor_script'  => 'dracka-newsletter-block-editor',
            'editor_js'      => '/js/blocks/newsletter.js',
            'render_cb'      => 'dracka_render_newsletter_block',
            'default_title'  => 'Newsletter',
            'default_label'  => 'Go to blog',
            'default_url'    => '/blog/',
        ],
    ];

    foreach ($blocks as $block_config) {
        wp_register_script(
            $block_config['editor_script'],
            get_template_directory_uri() . $block_config['editor_js'],
            ['wp-blocks', 'wp-element', 'wp-i18n', 'wp-block-editor', 'wp-components'],
            (string) filemtime(get_template_directory() . $block_config['editor_js']),
            true
        );

        register_block_type($block_config['name'], [
            'api_version'     => 3,
            'editor_script'   => $block_config['editor_script'],
            'render_callback' => $block_config['render_cb'],
            'attributes'      => [
                'title' => [
                    'type'    => 'string',
                    'default' => $block_config['default_title'],
                ],
                'initialCount' => [
                    'type'    => 'number',
                    'default' => 12,
                ],
                'increment' => [
                    'type'    => 'number',
                    'default' => 12,
                ],
                'showMoreLabel' => [
                    'type'    => 'string',
                    'default' => 'Show more',
                ],
                'maxItemsCap' => [
                    'type'    => 'number',
                    'default' => 0,
                ],
                'sortMode' => [
                    'type'    => 'string',
                    'default' => 'newest',
                ],
                'goToLibraryLabel' => [
                    'type'    => 'string',
                    'default' => $block_config['default_label'],
                ],
                'goToLibraryUrl' => [
                    'type'    => 'string',
                    'default' => $block_config['default_url'],
                ],
            ],
        ]);
    }

    wp_register_script(
        'dracka-news-ticker-block-editor',
        get_template_directory_uri() . '/js/blocks/news-ticker.js',
        ['wp-blocks', 'wp-element', 'wp-i18n', 'wp-block-editor', 'wp-components'],
        (string) filemtime(get_template_directory() . '/js/blocks/news-ticker.js'),
        true
    );

    register_block_type('dracka/news-ticker', [
        'api_version'     => 3,
        'editor_script'   => 'dracka-news-ticker-block-editor',
        'render_callback' => 'dracka_render_news_ticker_block',
        'attributes'      => [
            'speedSeconds' => [
                'type'    => 'number',
                'default' => 28,
            ],
        ],
    ]);
}
add_action('init', 'dracka_register_blocks');

/**
 * Normalizes supported sort mode values for latest content queries.
 *
 * @param string $sort_mode Raw sort mode.
 * @return string
 */
function dracka_normalize_latest_sort_mode($sort_mode)
{
    if (!is_string($sort_mode) && !is_numeric($sort_mode)) {
        return 'newest';
    }

    $sort_mode = sanitize_key((string) $sort_mode);

    return in_array($sort_mode, ['newest', 'manual'], true) ? $sort_mode : 'newest';
}

/**
 * Builds cache key for effective post count by post type.
 *
 * @param string $post_type Post type slug.
 * @return string
 */
function dracka_get_effective_cap_cache_key($post_type)
{
    return 'dracka_post_count_' . sanitize_key((string) $post_type);
}

/**
 * Returns the max number of related posts loaded in admin relation dropdowns.
 *
 * This prevents unbounded metabox queries from loading very large datasets
 * into memory. Sites with unusually large catalogs can raise the cap with:
 * add_filter('dracka_admin_relation_posts_limit', fn () => 5000);
 *
 * @return int
 */
function dracka_get_admin_relation_posts_limit()
{
    $default_limit = 1000;
    $limit = (int) apply_filters('dracka_admin_relation_posts_limit', $default_limit);

    return max(100, $limit);
}

/**
 * Builds query args for latest content (issues, artwork, etc).
 *
 * @param int $offset Number of posts to skip.
 * @param int $limit Number of posts to return.
 * @param string $post_type Post type to query.
 * @param string $sort_mode Sorting mode.
 * @return array<string, mixed>
 */
function dracka_get_latest_content_query_args($offset, $limit, $post_type, $sort_mode = 'newest')
{
    if ($post_type === 'library') {
        return dracka_get_library_preview_query_args($offset, $limit, $sort_mode);
    }

    $sort_mode = dracka_normalize_latest_sort_mode($sort_mode);

    $query_args = [
        'post_type'      => $post_type,
        'post_status'    => 'publish',
        'posts_per_page' => $limit,
        'offset'         => $offset,
        'no_found_rows'  => true,
    ];

    if ($sort_mode === 'manual') {
        $query_args['orderby'] = [
            'menu_order' => 'ASC',
            'date'       => 'DESC',
        ];
    } else {
        $query_args['orderby'] = 'date';
        $query_args['order'] = 'DESC';
    }

    return $query_args;
}

/**
 * Calculates effective cap for content query.
 *
 * @param string $post_type Post type to count.
 * @param int $max_items_cap Maximum items to show (0 = unlimited).
 * @return array
 */
function dracka_get_effective_cap($post_type, $max_items_cap)
{
    $cache_key = dracka_get_effective_cap_cache_key($post_type);
    $total = wp_cache_get($cache_key, 'dracka_theme');

    if ($total === false) {
        if ($post_type === 'library') {
            $total = dracka_get_library_preview_total_count();
        } else {
            $total = (int) wp_count_posts($post_type)->publish;
        }

        wp_cache_set($cache_key, $total, 'dracka_theme', MINUTE_IN_SECONDS * 10);
    } else {
        $total = (int) $total;
    }

    $effective = $max_items_cap > 0 ? min($max_items_cap, $total) : $total;

    return compact('total', 'effective');
}

/**
 * Invalidates cached post counts used for latest-content caps.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function dracka_invalidate_effective_cap_cache($post_id)
{
    $post_type = get_post_type((int) $post_id);

    if (!is_string($post_type) || !in_array($post_type, ['series', 'issue', 'artwork', 'post'], true)) {
        return;
    }

    $cache_key = dracka_get_effective_cap_cache_key($post_type);
    wp_cache_delete($cache_key, 'dracka_theme');

    if (in_array($post_type, ['series', 'issue'], true)) {
        wp_cache_delete(dracka_get_effective_cap_cache_key('library'), 'dracka_theme');
    }
}
add_action('save_post', 'dracka_invalidate_effective_cap_cache');
add_action('deleted_post', 'dracka_invalidate_effective_cap_cache');
add_action('trashed_post', 'dracka_invalidate_effective_cap_cache');

/**
 * Builds query args for latest issue listings.
 *
 * @param int $offset Number of posts to skip.
 * @param int $limit Number of posts to return.
 * @param string $sort_mode Sorting mode.
 * @return array<string, mixed>
 */
function dracka_get_latest_issue_query_args($offset, $limit, $sort_mode = 'newest')
{
    return dracka_get_latest_content_query_args($offset, $limit, 'library', $sort_mode);
}

/**
 * Builds query args for the homepage library preview.
 *
 * Includes standalone issues and series posts only.
 *
 * @param int $offset Number of posts to skip.
 * @param int $limit Number of posts to return.
 * @param string $sort_mode Sorting mode.
 * @return array<string, mixed>
 */
function dracka_get_library_preview_query_args($offset, $limit, $sort_mode = 'newest')
{
    $sort_mode = dracka_normalize_latest_sort_mode($sort_mode);

    $series_statuses = dracka_get_series_accepted_statuses();
    if (!is_array($series_statuses)) {
        $series_statuses = [];
    }

    $query_args = [
        'post_type'      => ['issue', 'series'],
        'post_status'    => $series_statuses,
        'posts_per_page' => $limit,
        'offset'         => $offset,
        'no_found_rows'  => true,
        // Keep issues limited to standalones while allowing non-standalone series posts through.
        'meta_query'     => [
            'relation' => 'AND',
            [
                'relation' => 'OR',
                [
                    'key'     => 'dracka_series_id',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key'     => 'dracka_series_id',
                    'value'   => '',
                    'compare' => '=',
                ],
                [
                    'key'     => 'dracka_series_id',
                    'value'   => '0',
                    'compare' => '=',
                ],
            ],
            [
                'relation' => 'OR',
                [
                    'key'     => DRACKA_SERIES_IS_STANDALONE_META_KEY,
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key'     => DRACKA_SERIES_IS_STANDALONE_META_KEY,
                    'value'   => '1',
                    'compare' => '!=',
                ],
            ],
        ],
    ];

    if ($sort_mode === 'manual') {
        $query_args['orderby'] = [
            'menu_order' => 'ASC',
            'date'       => 'DESC',
        ];
    } else {
        $query_args['orderby'] = 'date';
        $query_args['order'] = 'DESC';
    }

    return $query_args;
}

/**
 * Counts total items eligible for the homepage library preview.
 *
 * @return int
 */
function dracka_get_library_preview_total_count()
{
    $cache_key = dracka_get_effective_cap_cache_key('library');
    $total = wp_cache_get($cache_key, 'dracka_theme');

    if ($total !== false) {
        return (int) $total;
    }

    $count_query_args = dracka_get_library_preview_query_args(0, 1, 'newest');
    $count_query_args['no_found_rows'] = false;

    $count_query = new WP_Query($count_query_args);
    $total = (int) $count_query->found_posts;
    wp_reset_postdata();

    wp_cache_set($cache_key, $total, 'dracka_theme', MINUTE_IN_SECONDS * 10);

    return $total;
}

/**
 * Returns a map of album ID => published artwork count for the given album IDs.
 *
 * Fetches all counts in a single batched query to avoid N+1 lookups in archive
 * templates. Returns an array keyed by album ID with 0 as the default count.
 *
 * @param int[] $album_ids List of album post IDs to count artwork for.
 * @return array<int, int>
 */
function dracka_get_album_artwork_counts(array $album_ids): array
{
    global $wpdb;

    $album_ids = array_values(array_filter(array_map('intval', $album_ids)));

    if (empty($album_ids)) {
        return [];
    }

    $counts = array_fill_keys($album_ids, 0);

    $placeholders = implode(', ', array_fill(0, count($album_ids), '%d'));
    $query_sql = "
        SELECT CAST(pm.meta_value AS UNSIGNED) AS album_id, COUNT(p.ID) AS artwork_count
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        WHERE pm.meta_key = %s
          AND p.post_type = %s
          AND p.post_status = %s
          AND CAST(pm.meta_value AS UNSIGNED) IN ($placeholders)
        GROUP BY CAST(pm.meta_value AS UNSIGNED)
    ";

    $query_args   = array_merge(['dracka_album_id', 'artwork', 'publish'], $album_ids);
    $prepared_sql = $wpdb->prepare($query_sql, $query_args);

    if (!is_string($prepared_sql)) {
        return $counts;
    }

    $rows = $wpdb->get_results($prepared_sql, ARRAY_A);

    if (!is_array($rows)) {
        return $counts;
    }

    foreach ($rows as $row) {
        $album_id      = isset($row['album_id'])      ? (int) $row['album_id']      : 0;
        $artwork_count = isset($row['artwork_count']) ? (int) $row['artwork_count'] : 0;

        if ($album_id > 0) {
            $counts[$album_id] = $artwork_count;
        }
    }

    return $counts;
}

/**
 * Checks whether a post is within the premiere window.
 *
 * A post is considered "premiere" when it is publicly visible and its
 * publish date is not older than the configured day window.
 *
 * @param int $post_id Post ID.
 * @param int $days    Premiere window in days.
 * @return bool
 */
function dracka_is_post_premiere($post_id, $days = 10)
{
    $post_id = (int) $post_id;
    $days = max(1, (int) $days);

    if ($post_id <= 0) {
        return false;
    }

    $post = get_post($post_id);

    if (!$post instanceof WP_Post) {
        return false;
    }

    $post_status = (string) $post->post_status;
    $post_type = (string) $post->post_type;

    if (!$post_status || !$post_type) {
        return false;
    }

    if ($post_type === 'series') {
        $allowed_series_statuses = dracka_get_series_accepted_statuses();
        if (!is_array($allowed_series_statuses) || !in_array($post_status, $allowed_series_statuses, true)) {
            return false;
        }
    } elseif ($post_status !== 'publish') {
        return false;
    }

    $published_timestamp = (int) get_post_time('U', true, $post);

    if ($published_timestamp <= 0 && !empty($post->post_date_gmt) && $post->post_date_gmt !== '0000-00-00 00:00:00') {
        $published_timestamp = (int) mysql2date('U', $post->post_date_gmt, false);
    }

    if ($published_timestamp <= 0 && !empty($post->post_date) && $post->post_date !== '0000-00-00 00:00:00') {
        $published_timestamp = (int) mysql2date('U', $post->post_date, false);
    }

    $current_timestamp = time();

    if ($published_timestamp <= 0 || $current_timestamp <= 0) {
        return false;
    }

    $age_in_seconds = $current_timestamp - $published_timestamp;
    $window_in_seconds = DAY_IN_SECONDS * $days;

    if ($age_in_seconds < 0) {
        return false;
    }

    return $age_in_seconds <= $window_in_seconds;
}

/**
 * Returns the premiere badge markup for a post when applicable.
 *
 * @param int $post_id Post ID.
 * @param int $days    Premiere window in days.
 * @return string
 */
function dracka_get_premiere_badge_markup($post_id, $days = 10)
{
    if (!dracka_is_post_premiere($post_id, $days)) {
        return '';
    }

    $library_post_types = ['issue', 'series'];
    $post_type = (string) get_post_type($post_id);
    $label = in_array($post_type, $library_post_types, true) ? 'PREMIERE' : 'NEW';

    return '<span class="content-badge content-badge--premiere"><span class="content-badge__label">' . $label . '</span></span>';
}

/**
 * Renders a single content card for the collapsible homepage grid.
 *
 * Supports issues, artwork, and newsletter post cards.
 *
 * @param int    $post_id      Post ID.
 * @param string $content_type Content type slug ('issue', 'artwork', or 'post').
 * @return string
 */
function dracka_render_content_card_markup($post_id, $content_type)
{
    $post_id = (int) $post_id;

    if (!$post_id) {
        return '';
    }

    $post_type = get_post_type($post_id);
    $post_status = get_post_status($post_id);

    if ($post_type === 'series') {
        $allowed_series_statuses = dracka_get_series_accepted_statuses();
        if (!is_array($allowed_series_statuses) || !in_array($post_status, $allowed_series_statuses, true)) {
            return '';
        }
    } elseif ($post_status !== 'publish') {
        return '';
    }

    if ($content_type === 'artwork') {
        $css_prefix = 'dracka-artwork';
    } elseif ($content_type === 'post') {
        $css_prefix = 'dracka-newsletter';
    } else {
        $css_prefix = 'dracka-issues';
    }

    $badge_markup = '';
    $premiere_enabled_types = ['issue', 'series', 'artwork', 'album'];

    if (in_array((string) $post_type, $premiere_enabled_types, true)) {
        $premiere_badge = dracka_get_premiere_badge_markup($post_id, 10);
        if ($premiere_badge !== '') {
            $badge_markup = '<div class="card-badges card-badges--ribbon dracka-card-badges">' . $premiere_badge . '</div>';
        }
    }

    $title = get_the_title($post_id);
    $permalink = get_permalink($post_id);
    $thumbnail = get_the_post_thumbnail(
        $post_id,
        'large',
        [
            'class'   => $css_prefix . '-card__image',
            'loading' => 'lazy',
            'alt'     => $title,
        ]
    );

    if (!$thumbnail) {
        $thumbnail = '<span class="' . esc_attr($css_prefix) . '-card__placeholder" aria-hidden="true"></span>';
    }

    if ($content_type === 'artwork') {
        return sprintf(
            '<article class="%1$s-card"><a href="%2$s" class="%1$s-card__link">%3$s%4$s</a></article>',
            esc_attr($css_prefix),
            esc_url($permalink),
            $badge_markup,
            $thumbnail
        );
    }

    if ($content_type === 'post') {
        $excerpt = wp_trim_words(trim(wp_strip_all_tags(get_the_excerpt($post_id))), 27, '&hellip;');
        $excerpt_html = '';

        if ($excerpt !== '') {
            $excerpt_html = sprintf(
                '<span class="%1$s-card__excerpt">%2$s</span>',
                esc_attr($css_prefix),
                esc_html($excerpt)
            );
        }

        return sprintf(
            '<article class="%1$s-card"><a href="%2$s" class="%1$s-card__link"><span class="%1$s-card__media">%3$s<span class="%1$s-card__overlay"><span class="%1$s-card__title">%4$s</span>%5$s</span></span></a></article>',
            esc_attr($css_prefix),
            esc_url($permalink),
            $thumbnail,
            esc_html($title),
            $excerpt_html
        );
    }

    return sprintf(
        '<article class="%1$s-card"><a href="%2$s" class="%1$s-card__link">%3$s%4$s<span class="%1$s-card__title">%5$s</span></a></article>',
        esc_attr($css_prefix),
        esc_url($permalink),
        $badge_markup,
        $thumbnail,
        esc_html($title)
    );
}

/**
 * Wrapper for issue card rendering.
 *
 * @param int $issue_id Issue post ID.
 * @return string
 */
function dracka_render_issue_card_markup($issue_id)
{
    return dracka_render_content_card_markup($issue_id, 'issue');
}

/**
 * Wrapper for artwork card rendering.
 *
 * @param int $artwork_id Artwork post ID.
 * @return string
 */
function dracka_render_artwork_card_markup($artwork_id)
{
    return dracka_render_content_card_markup($artwork_id, 'artwork');
}

/**
 * Wrapper for newsletter card rendering.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function dracka_render_newsletter_card_markup($post_id)
{
    return dracka_render_content_card_markup($post_id, 'post');
}

/**
 * Renders a dynamic "Latest Content" collapsible block.
 *
 * Shared implementation for issues, artwork, and newsletter homepage blocks.
 * Each block type passes its content_type to control query, markup,
 * REST endpoint, and CSS class prefixes.
 *
 * @param string              $content_type Post type slug ('issue', 'artwork', or 'post').
 * @param array<string, mixed> $attributes  Block attributes.
 * @param array<string, string> $defaults   Default labels/URLs for this content type.
 * @return string
 */
function dracka_render_latest_content_block($content_type, $attributes, $defaults)
{
    $is_newsletter = $content_type === 'post';

    if ($content_type === 'artwork') {
        $css_prefix = 'dracka-artwork';
        $rest_slug  = 'artwork';
    } elseif ($content_type === 'post') {
        $css_prefix = 'dracka-newsletter';
        $rest_slug  = 'newsletter';
    } else {
        $css_prefix = 'dracka-issues';
        $rest_slug  = 'issues';
    }

    $title = isset($attributes['title']) ? sanitize_text_field($attributes['title']) : $defaults['title'];
    $initial_count = isset($attributes['initialCount']) ? max(1, (int) $attributes['initialCount']) : 12;

    if ($is_newsletter) {
        $initial_count = min($initial_count, 3);
    }

    $increment = isset($attributes['increment']) ? max(1, (int) $attributes['increment']) : 12;
    $show_more_label = isset($attributes['showMoreLabel']) ? sanitize_text_field($attributes['showMoreLabel']) : 'Show more';
    $max_items_cap = isset($attributes['maxItemsCap']) ? max(0, (int) $attributes['maxItemsCap']) : 0;
    $sort_mode = isset($attributes['sortMode']) ? dracka_normalize_latest_sort_mode($attributes['sortMode']) : 'newest';
    $go_to_library_label = isset($attributes['goToLibraryLabel']) ? sanitize_text_field($attributes['goToLibraryLabel']) : $defaults['go_label'];
    $go_to_library_url = isset($attributes['goToLibraryUrl']) ? esc_url_raw($attributes['goToLibraryUrl']) : $defaults['go_url'];

    if (!$go_to_library_url) {
        $go_to_library_url = $defaults['go_url'];
    }

    $cap_info = dracka_get_effective_cap($content_type, $max_items_cap);
    $total_published = $cap_info['total'];
    $effective_cap = $cap_info['effective'];

    // Do not render any wrapper markup when there is no content.
    if ($effective_cap < 1 || $total_published < 1) {
        return '';
    }

    $initial_render_count = min($initial_count, $effective_cap);
    $initial_query = new WP_Query(dracka_get_latest_content_query_args(0, $initial_render_count, $content_type, $sort_mode));

    if (!$initial_query->have_posts()) {
        return '';
    }

    $initial_cards = [];

    while ($initial_query->have_posts()) {
        $initial_query->the_post();
        $initial_cards[] = dracka_render_content_card_markup((int) get_the_ID(), $content_type);
    }
    wp_reset_postdata();

    $initial_cards = array_values(array_filter($initial_cards));

    if (empty($initial_cards)) {
        return '';
    }

    $next_offset = $initial_render_count;
    $has_more = $next_offset < $effective_cap;
    $reached_cap = !$has_more && $total_published > $effective_cap;
    $content_id = wp_unique_id('dracka-latest-' . $rest_slug . '-content-');
    $initially_open = true;

    if ($is_newsletter) {
        $see_all_markup = sprintf(
            '<div class="dracka-newsletter-card__action"><a class="dracka-newsletter-see-all" href="%1$s">%2$s</a></div>',
            esc_url($go_to_library_url),
            esc_html($go_to_library_label)
        );

        $has_more = false;
        $reached_cap = false;
    }

    $initial_cards_html = wp_kses_post(implode('', $initial_cards));

    ob_start();
?>
    <section
        class="dracka-collapsible dracka-latest-<?php echo esc_attr($rest_slug); ?>-block"
        data-collapsible
        data-load-url="<?php echo esc_url(rest_url('dracka/v1/' . $rest_slug)); ?>"
        data-show-more-label="<?php echo esc_attr($show_more_label); ?>"
        data-go-library-label="<?php echo esc_attr($go_to_library_label); ?>"
        data-go-library-url="<?php echo esc_url($go_to_library_url); ?>"
        data-loading-label="<?php echo esc_attr__('Loading...', 'dracka'); ?>"
        data-sort-mode="<?php echo esc_attr($sort_mode); ?>"
        data-max-items-cap="<?php echo esc_attr((string) $max_items_cap); ?>"
        data-increment="<?php echo esc_attr((string) $increment); ?>"
        data-next-offset="<?php echo esc_attr((string) $next_offset); ?>">
        <button
            type="button"
            class="dracka-collapsible__toggle"
            aria-expanded="<?php echo $initially_open ? 'true' : 'false'; ?>"
            aria-controls="<?php echo esc_attr($content_id); ?>">
            <span class="dracka-collapsible__arrow" aria-hidden="true"></span>
            <span class="dracka-collapsible__title"><?php echo esc_html($title); ?></span>
        </button>

        <div
            id="<?php echo esc_attr($content_id); ?>"
            class="dracka-collapsible__content<?php echo $initially_open ? ' is-open' : ''; ?>"
            <?php if (!$initially_open) : ?>hidden<?php endif; ?>
            <?php if ($initially_open) : ?>style="max-height: none; opacity: 1;" <?php endif; ?>>
            <?php if ($is_newsletter) : ?>
            <div class="dracka-newsletter-grid-wrap">
                <div class="<?php echo esc_attr($css_prefix); ?>-grid" data-content-grid>
                    <?php echo $initial_cards_html; ?>
                </div>
                <?php echo wp_kses_post($see_all_markup); ?>
            </div>
            <?php else : ?>
            <div class="<?php echo esc_attr($css_prefix); ?>-grid" data-content-grid>
                <?php echo $initial_cards_html; ?>
            </div>

            <?php if ($has_more) : ?>
                <button type="button" class="<?php echo esc_attr($css_prefix); ?>-show-more" data-show-more><?php echo esc_html($show_more_label); ?></button>
            <?php elseif ($reached_cap) : ?>
                <a class="<?php echo esc_attr($css_prefix); ?>-go-library" href="<?php echo esc_url($go_to_library_url); ?>"><?php echo esc_html($go_to_library_label); ?></a>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>
<?php
    return (string) ob_get_clean();
}

/**
 * Render callback for the Latest Issues block.
 *
 * @param array<string, mixed> $attributes Block attributes.
 * @return string
 */
function dracka_render_latest_issues_block($attributes)
{
    return dracka_render_latest_content_block('library', $attributes, [
        'title'    => 'Library',
        'go_label' => 'Go to library',
        'go_url'   => '/library/issues/',
    ]);
}

/**
 * Render callback for the Latest Artwork block.
 *
 * @param array<string, mixed> $attributes Block attributes.
 * @return string
 */
function dracka_render_latest_artwork_block($attributes)
{
    return dracka_render_latest_content_block('artwork', $attributes, [
        'title'    => 'Gallery',
        'go_label' => 'Go to gallery',
        'go_url'   => '/gallery/artwork/',
    ]);
}

/**
 * Render callback for the Newsletter block.
 *
 * @param array<string, mixed> $attributes Block attributes.
 * @return string
 */
function dracka_render_newsletter_block($attributes)
{
    return dracka_render_latest_content_block('post', $attributes, [
        'title'    => 'Newsletter',
        'go_label' => 'See all',
        'go_url'   => '/blog/',
    ]);
}

/**
 * Render callback for the News Ticker block.
 *
 * @param array<string, mixed> $attributes Block attributes.
 * @return string
 */
function dracka_render_news_ticker_block($attributes)
{
    $speed_seconds = isset($attributes['speedSeconds']) ? (int) $attributes['speedSeconds'] : 28;
    $speed_seconds = max(8, min(120, $speed_seconds));

    $query = new WP_Query([
        'post_type'      => 'ticker',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => [
            'menu_order' => 'ASC',
            'date'       => 'DESC',
        ],
        'order'          => 'ASC',
        'no_found_rows'  => true,
        'meta_query'     => [
            [
                'key'     => DRACKA_TICKER_ACTIVE_META_KEY,
                'value'   => '1',
                'compare' => '=',
            ],
        ],
    ]);

    if (!$query->have_posts()) {
        return '';
    }

    $allowed_html = [
        'a' => [
            'href'       => true,
            'target'     => true,
            'rel'        => true,
            'class'      => true,
            'aria-label' => true,
        ],
        'strong' => [],
        'em'     => [],
        'b'      => [],
        'i'      => [],
        'span'   => [
            'class'       => true,
            'aria-hidden' => true,
        ],
    ];

    $ticker_items = [];

    while ($query->have_posts()) {
        $query->the_post();

        $content = (string) get_the_content(null, false, get_the_ID());
        $content = apply_filters('the_content', $content);
        $content = (string) wp_kses($content, $allowed_html);
        $content = trim((string) preg_replace('/\s+/', ' ', $content));

        if ($content === '') {
            continue;
        }

        $ticker_items[] = '<span class="dracka-news-ticker__item">' . $content . '</span>';
    }
    wp_reset_postdata();

    if (empty($ticker_items)) {
        return '';
    }

    $separator = '<span class="dracka-news-ticker__separator" aria-hidden="true">&bull;</span>';
    $base_ticker_line = implode($separator, $ticker_items);
    $repeat_count = max(2, (int) ceil(8 / max(1, count($ticker_items))));
    $ticker_line = implode($separator, array_fill(0, $repeat_count, $base_ticker_line)) . $separator;
    $ticker_line = wp_kses($ticker_line, $allowed_html);

    ob_start();
?>
    <section class="dracka-news-ticker" aria-label="News ticker" data-news-ticker data-speed-seconds="<?php echo esc_attr((string) $speed_seconds); ?>" style="--dracka-news-ticker-duration: <?php echo esc_attr((string) $speed_seconds); ?>s;">
        <div class="dracka-news-ticker__viewport">
            <div class="dracka-news-ticker__track" data-news-ticker-track>
                <div class="dracka-news-ticker__line" data-news-ticker-line><?php echo $ticker_line; ?></div>
            </div>
        </div>
    </section>
<?php
    return (string) ob_get_clean();
}

/**
 * Registers REST routes used by dynamic frontend components.
 *
 * Issues, artwork, and newsletter endpoints share the same argument schema
 * and are handled by a single callback with a content_type parameter.
 *
 * @return void
 */
function dracka_register_rest_routes()
{
    $shared_args = [
        'offset' => [
            'sanitize_callback' => 'absint',
            'default'           => 0,
        ],
        'limit' => [
            'sanitize_callback' => 'absint',
            'default'           => 12,
        ],
        'max' => [
            'sanitize_callback' => 'absint',
            'default'           => 0,
        ],
        'sort' => [
            'sanitize_callback' => 'sanitize_key',
            'default'           => 'newest',
        ],
    ];

    $endpoints = [
        'issues'     => 'library',
        'artwork'    => 'artwork',
        'newsletter' => 'post',
    ];

    foreach ($endpoints as $route_slug => $content_type) {
        register_rest_route('dracka/v1', '/' . $route_slug, [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => function (WP_REST_Request $request) use ($content_type) {
                return dracka_rest_get_latest_content($request, $content_type);
            },
            'permission_callback' => '__return_true',
            'args'                => $shared_args,
        ]);
    }

    register_rest_route('dracka/v1', '/artwork-nav/(?P<id>[\d]+)', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => function (WP_REST_Request $request): WP_REST_Response|WP_Error {
            $id   = (int) $request->get_param('id');
            $post = get_post($id);
            if (
                !($post instanceof WP_Post)
                || $post->post_type !== 'artwork'
                || $post->post_status !== 'publish'
            ) {
                return new WP_Error('dracka_not_found', 'Artwork not found.', ['status' => 404]);
            }

            /**
             * Converts a raw navigation item (from dracka_get_artwork_navigation) into
             * the JS-expected shape: { id, url, title, image: { src, srcset, sizes, alt } }.
             *
             * @param array<string,mixed>|null $item
             * @return array<string,mixed>|null
             */
            $format_item = function (?array $item): ?array {
                if ($item === null) {
                    return null;
                }
                $item_id  = (int) $item['id'];
                $album_id = (int) get_post_meta($item_id, 'dracka_album_id', true);
                return [
                    'id'      => $item_id,
                    'url'     => $item['url'],
                    'title'   => $item['title'],
                    'content' => apply_filters('the_content', get_post_field('post_content', $item_id)),
                    'album'   => $album_id ? [
                        'url'   => get_permalink($album_id),
                        'title' => get_the_title($album_id),
                    ] : null,
                    'image' => [
                        'src'    => $item['image_src']    ?? '',
                        'srcset' => $item['image_srcset'] ?? '',
                        'sizes'  => $item['image_sizes']  ?? '',
                        'alt'    => $item['title'],
                    ],
                ];
            };

            $nav           = dracka_get_artwork_navigation($id);
            $thumb_id      = (int) get_post_thumbnail_id($id);
            $src_data      = $thumb_id > 0 ? wp_get_attachment_image_src($thumb_id, 'large') : false;
            $current_album = (int) get_post_meta($id, 'dracka_album_id', true);

            return new WP_REST_Response([
                'current' => [
                    'id'      => $id,
                    'url'     => get_permalink($id),
                    'title'   => get_the_title($id),
                    'content' => apply_filters('the_content', get_post_field('post_content', $id)),
                    'album'   => $current_album ? [
                        'url'   => get_permalink($current_album),
                        'title' => get_the_title($current_album),
                    ] : null,
                    'image'   => [
                        'src'    => is_array($src_data) ? ($src_data[0] ?? '') : '',
                        'srcset' => $thumb_id > 0 ? (wp_get_attachment_image_srcset($thumb_id, 'large') ?: '') : '',
                        'sizes'  => $thumb_id > 0 ? (wp_get_attachment_image_sizes($thumb_id, 'large') ?: '') : '',
                        'alt'    => get_the_title($id),
                    ],
                ],
                'previous' => $format_item($nav['previous']),
                'next'     => $format_item($nav['next']),
            ], 200);
        },
        'permission_callback' => '__return_true',
        'args'                => [
            'id' => [
                'sanitize_callback' => 'absint',
                'validate_callback' => function ($val) { return is_numeric($val) && (int) $val > 0; },
            ],
        ],
    ]);

    register_rest_route('dracka/v1', '/artwork-comments/(?P<id>[\d]+)', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => function (WP_REST_Request $request): WP_REST_Response|WP_Error {
            $id   = (int) $request->get_param('id');
            $post = get_post($id);
            if (
                !($post instanceof WP_Post)
                || $post->post_type !== 'artwork'
                || $post->post_status !== 'publish'
            ) {
                return new WP_Error('dracka_not_found', 'Artwork not found.', ['status' => 404]);
            }

            global $post;
            $post = get_post($id);
            setup_postdata($post);

            ob_start();
            get_template_part('template-parts/comments-box', null, ['initially_open' => true]);
            $html = ob_get_clean();

            wp_reset_postdata();

            return new WP_REST_Response(['html' => $html], 200);
        },
        'permission_callback' => '__return_true',
        'args'                => [
            'id' => [
                'sanitize_callback' => 'absint',
                'validate_callback' => function ($val) { return is_numeric($val) && (int) $val > 0; },
            ],
        ],
    ]);
}
add_action('rest_api_init', 'dracka_register_rest_routes');

/**
 * Shared REST callback returning latest content cards in chunks.
 *
 * @param WP_REST_Request $request      Active REST request.
 * @param string          $content_type Post type slug ('issue', 'artwork', or 'post').
 * @return WP_REST_Response|WP_Error
 */
function dracka_rest_get_latest_content($request, $content_type)
{
    if (!in_array($content_type, ['library', 'issue', 'artwork', 'post'], true)) {
        return new WP_Error('dracka_invalid_content_type', 'Unsupported content type.', ['status' => 400]);
    }

    $offset = max(0, (int) $request->get_param('offset'));
    $limit = (int) $request->get_param('limit');
    $limit = max(1, min(24, $limit));
    $max_items_cap = max(0, (int) $request->get_param('max'));
    $sort_mode = dracka_normalize_latest_sort_mode((string) $request->get_param('sort'));

    $cap_info = dracka_get_effective_cap($content_type, $max_items_cap);
    $total_published = $cap_info['total'];
    $effective_cap = $cap_info['effective'];

    if ($offset >= $effective_cap) {
        return rest_ensure_response([
            'items_html'   => '',
            'count'        => 0,
            'next_offset'  => $effective_cap,
            'has_more'     => false,
            'reached_cap'  => $total_published > $effective_cap,
            'total'        => $total_published,
        ]);
    }

    $remaining = $effective_cap - $offset;
    $query_limit = min($limit, $remaining);
    $query = new WP_Query(dracka_get_latest_content_query_args($offset, $query_limit, $content_type, $sort_mode));

    $items_html = '';

    while ($query->have_posts()) {
        $query->the_post();
        $items_html .= dracka_render_content_card_markup((int) get_the_ID(), $content_type);
    }
    wp_reset_postdata();

    $rendered_count = (int) $query->post_count;
    $next_offset = min($effective_cap, $offset + $rendered_count);
    $has_more = $next_offset < $effective_cap;
    $reached_cap = !$has_more && $total_published > $effective_cap;

    return rest_ensure_response([
        'items_html'   => $items_html,
        'count'        => $rendered_count,
        'next_offset'  => $next_offset,
        'has_more'     => $has_more,
        'reached_cap'  => $reached_cap,
        'total'        => $total_published,
    ]);
}

/**
 * Returns the fallback color palette used by the theme.
 *
 * These values are used both as Customizer defaults and as a runtime
 * fallback when a site has not stored a custom palette setting yet.
 *
 * @return array<string, string>
 */
function dracka_get_palette_defaults()
{
    return [
        'color_bg'      => '#0b0c0d',
        'color_surface' => '#141618',
        'color_panel'   => '#1f2326',
        'color_text'    => '#e9eaec',
        'color_muted'   => '#9aa1a8',
        'color_accent'  => '#6e7c88',
    ];
}

/**
 * Renders an inline SVG eye icon for view counts.
 *
 * The icon uses currentColor so it inherits the parent's text color,
 * allowing it to be styled dynamically via CSS variables (--color-muted).
 *
 * @return string SVG markup as a string.
 */
function dracka_render_eye_icon()
{
    return '<svg class="dracka-eye-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 4C6 4 1.27 8.47 1 14c.27 5.53 5 10 11 10s10.73-4.47 11-10c-.27-5.53-5-10-11-10zm0 16c-3.86 0-7.41-2.53-8-6c.59-3.47 4.14-6 8-6s7.41 2.53 8 6c-.59 3.47-4.14 6-8 6zm0-10c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4z" fill="currentColor"/></svg>';
}

/**
 * Registers Customizer settings and controls for the theme palette.
 *
 * It creates a dedicated section, loops over known palette keys,
 * registers each setting with hex-color sanitization, and attaches a
 * color picker control to expose each setting in the UI.
 *
 * @param WP_Customize_Manager $wp_customize Active customizer manager instance.
 * @return void
 */
function dracka_customize_register($wp_customize)
{
    $defaults = dracka_get_palette_defaults();

    $wp_customize->add_section('dracka_palette', [
        'title'    => 'Dracka Palette',
        'priority' => 30,
    ]);

    $controls = [
        'color_bg'      => 'Background',
        'color_surface' => 'Surface',
        'color_panel'   => 'Panel',
        'color_text'    => 'Text',
        'color_muted'   => 'Muted',
        'color_accent'  => 'Accent',
    ];

    foreach ($controls as $key => $label) {
        $setting_id = 'dracka_' . $key;

        $wp_customize->add_setting($setting_id, [
            'default'           => $defaults[$key],
            'sanitize_callback' => 'sanitize_hex_color',
        ]);

        $wp_customize->add_control(new WP_Customize_Color_Control(
            $wp_customize,
            $setting_id,
            [
                'label'   => $label,
                'section' => 'dracka_palette',
            ]
        ));
    }

    // Info Panel section
    $wp_customize->add_section('dracka_info_panel', [
        'title'       => __('Info Panel', 'dracka'),
        'priority'    => 40,
        'description' => __('Content shown in the mobile info panel. Leave all columns empty to hide the panel button in the header.', 'dracka'),
    ]);

    for ($col_num = 1; $col_num <= 3; $col_num++) {
        $col_label  = sprintf(__('Column %d', 'dracka'), $col_num);
        $title_id   = 'dracka_info_col' . $col_num . '_title';
        $content_id = 'dracka_info_col' . $col_num . '_content';

        $wp_customize->add_setting($title_id, [
            'default'           => '',
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        $wp_customize->add_control($title_id, [
            'label'   => $col_label . ' — ' . __('Heading', 'dracka'),
            'section' => 'dracka_info_panel',
            'type'    => 'text',
        ]);

        $wp_customize->add_setting($content_id, [
            'default'           => '',
            'sanitize_callback' => 'wp_kses_post',
        ]);

        $wp_customize->add_control($content_id, [
            'label'       => $col_label . ' — ' . __('Content', 'dracka'),
            'section'     => 'dracka_info_panel',
            'type'        => 'textarea',
            'description' => __('HTML allowed: &lt;a&gt;, &lt;p&gt;, &lt;br&gt;, &lt;strong&gt;, &lt;em&gt;, &lt;ul&gt;, &lt;li&gt;', 'dracka'),
        ]);
    }

    // Comments section
    $wp_customize->add_section('dracka_comments', [
        'title'       => __('Comments', 'dracka'),
        'priority'    => 50,
        'description' => __('Settings for the comment threads across the site.', 'dracka'),
    ]);

    $wp_customize->add_setting('dracka_default_avatar', [
        'default'           => 0,
        'sanitize_callback' => 'absint',
        'transport'         => 'refresh',
    ]);

    $wp_customize->add_control(
        new WP_Customize_Media_Control($wp_customize, 'dracka_default_avatar', [
            'label'       => __('Default Comment Avatar', 'dracka'),
            'description' => __('Shown when a commenter has no Gravatar. If empty, WordPress uses its built-in default.', 'dracka'),
            'section'     => 'dracka_comments',
            'mime_type'   => 'image',
        ])
    );
}
add_action('customize_register', 'dracka_customize_register');

/**
 * Filters avatar data to use the theme's custom default avatar image.
 *
 * When a commenter has no Gravatar the WP avatar pipeline still resolves a URL
 * (usually a Gravatar mystery-person placeholder). This filter swaps that URL
 * for the image the site owner has chosen in Appearance → Customize → Comments,
 * leaving real Gravatars untouched.
 *
 * Hooked to `pre_get_avatar_data` at priority 10 so it runs before any Gravatar
 * network request is attempted.
 *
 * @param array           $args        Avatar data array (url, size, default, etc.).
 * @param mixed           $id_or_email User ID, WP_User object, or email string.
 * @return array
 */
function dracka_custom_default_avatar($args, $id_or_email)
{
    $attachment_id = (int) get_theme_mod('dracka_default_avatar', 0);
    if (!$attachment_id) {
        return $args;
    }

    // Determine whether this commenter has a real Gravatar.
    // At the pre_get_avatar_data stage `found_avatar` is not yet populated,
    // so we probe the Gravatar CDN once and cache the result per email hash.
    $has_gravatar = false;
    $email        = '';

    if (is_string($id_or_email)) {
        $email = $id_or_email;
    } elseif ($id_or_email instanceof WP_User) {
        $email = $id_or_email->user_email;
    } elseif ($id_or_email instanceof WP_Comment) {
        $email = $id_or_email->comment_author_email;
    }

    if ($email !== '') {
        $hash      = md5(strtolower(trim($email)));
        $cache_key = 'dracka_gravatar_' . $hash;
        $cached    = get_transient($cache_key);

        if ($cached === false) {
            // Probe Gravatar once; d=404 returns 404 when no account exists.
            $response = wp_remote_head(
                'https://www.gravatar.com/avatar/' . $hash . '?d=404',
                ['timeout' => 2, 'redirection' => 0]
            );
            $has_gravatar = (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200);
            set_transient($cache_key, $has_gravatar ? '1' : '0', DAY_IN_SECONDS);
        } else {
            $has_gravatar = ($cached === '1');
        }
    }

    if (!$has_gravatar) {
        $size = isset($args['size']) ? (int) $args['size'] : 96;
        $url  = wp_get_attachment_image_url($attachment_id, [$size, $size]);
        if ($url) {
            $args['url']          = $url;
            $args['found_avatar'] = true;
        }
    }

    return $args;
}
add_filter('pre_get_avatar_data', 'dracka_custom_default_avatar', 10, 2);

/**
 * Outputs CSS custom properties from Customizer palette values.
 *
 * The function resolves each color from theme mods (with defaults),
 * builds a :root declaration block, and injects it inline so all theme
 * styles can consume the variables without additional files.
 *
 * @return void
 */
function dracka_add_customizer_css()
{
    $defaults = dracka_get_palette_defaults();
    $values = [];

    foreach ($defaults as $key => $default) {
        $raw = get_theme_mod('dracka_' . $key, $default);
        $values[$key] = sanitize_hex_color($raw) ?: $default;
    }

    $custom_css = ':root{'
        . '--color-bg:' . $values['color_bg'] . ';'
        . '--color-surface:' . $values['color_surface'] . ';'
        . '--color-panel:' . $values['color_panel'] . ';'
        . '--color-text:' . $values['color_text'] . ';'
        . '--color-muted:' . $values['color_muted'] . ';'
        . '--color-accent:' . $values['color_accent'] . ';'
        . '}';

    wp_add_inline_style('dracka-style', $custom_css);
}

/**
 * Returns the column data for the mobile info panel from Customizer settings.
 *
 * @return array<int, array{title: string, content: string}>
 */
function dracka_get_info_panel_columns(): array
{
    $columns = [];

    for ($i = 1; $i <= 3; $i++) {
        $columns[] = [
            'title'   => (string) get_theme_mod('dracka_info_col' . $i . '_title', ''),
            'content' => (string) get_theme_mod('dracka_info_col' . $i . '_content', ''),
        ];
    }

    return $columns;
}

/**
 * Returns true if at least one info panel column has content.
 *
 * Used to conditionally show the info panel trigger button in the header.
 *
 * @return bool
 */
function dracka_info_panel_has_content(): bool
{
    foreach (dracka_get_info_panel_columns() as $col) {
        if ($col['content'] !== '') {
            return true;
        }
    }

    return false;
}

/**
 * Renders the info panel column layout for the mobile info overlay.
 *
 * Only columns with content are output. Nothing is rendered when all
 * columns are empty.
 *
 * @return void
 */
function dracka_render_info_panel_columns(): void
{
    $allowed_html = [
        'a'      => ['href' => true, 'target' => true, 'rel' => true],
        'p'      => [],
        'br'     => [],
        'strong' => [],
        'em'     => [],
        'ul'     => [],
        'ol'     => [],
        'li'     => [],
    ];

    $columns = dracka_get_info_panel_columns();
    $has_any = false;

    foreach ($columns as $col) {
        if ($col['content'] !== '') {
            $has_any = true;
            break;
        }
    }

    if (!$has_any) {
        return;
    }

    echo '<div class="info-panel-columns">';

    foreach ($columns as $col) {
        if ($col['content'] === '') {
            continue;
        }

        echo '<div class="info-panel-col">';

        if ($col['title'] !== '') {
            echo '<h3 class="info-panel-col__title">' . esc_html($col['title']) . '</h3>';
        }

        echo '<div class="info-panel-col__content">' . wp_kses($col['content'], $allowed_html) . '</div>';
        echo '</div>';
    }

    echo '</div>';
}

/**
 * Registers custom post types that power library and gallery content.
 *
 * It defines labels, visibility, archives, slugs, editor support, and
 * REST availability for series/issues and albums/artwork pairs.
 *
 * @return void
 */
function dracka_register_content_types()
{
    register_post_type('series', [
        'labels' => [
            'name'          => 'Series',
            'singular_name' => 'Series',
            'add_new_item'  => 'Add New Series',
            'edit_item'     => 'Edit Series',
            'view_item'     => 'View Series',
        ],
        'public'       => true,
        'has_archive'  => 'library',
        'rewrite'      => ['slug' => 'series'],
        'menu_icon'    => 'dashicons-book-alt',
        'show_in_rest' => true,
        'supports'     => ['title', 'thumbnail', 'comments'],
    ]);

    register_post_type('issue', [
        'labels' => [
            'name'          => 'Issues',
            'singular_name' => 'Issue',
            'add_new_item'  => 'Add New Issue',
            'edit_item'     => 'Edit Issue',
            'view_item'     => 'View Issue',
        ],
        'public'       => true,
        'has_archive'  => 'issues',
        'rewrite'      => ['slug' => 'issue'],
        'menu_icon'    => 'dashicons-book',
        'show_in_rest' => true,
        'supports'     => ['title', 'editor', 'excerpt', 'thumbnail', 'page-attributes', 'comments'],
    ]);

    register_post_type('album', [
        'labels' => [
            'name'          => 'Albums',
            'singular_name' => 'Album',
            'add_new_item'  => 'Add New Album',
            'edit_item'     => 'Edit Album',
            'view_item'     => 'View Album',
        ],
        'public'       => true,
        'has_archive'  => 'gallery',
        'rewrite'      => ['slug' => 'album'],
        'menu_icon'    => 'dashicons-format-gallery',
        'show_in_rest' => true,
        'supports'     => ['title', 'editor', 'excerpt', 'thumbnail', 'comments'],
    ]);

    register_post_type('artwork', [
        'labels' => [
            'name'          => 'Artwork',
            'singular_name' => 'Artwork',
            'add_new_item'  => 'Add New Artwork',
            'edit_item'     => 'Edit Artwork',
            'view_item'     => 'View Artwork',
        ],
        'public'       => true,
        'has_archive'  => 'artwork',
        'rewrite'      => ['slug' => 'artwork'],
        'menu_icon'    => 'dashicons-format-image',
        'show_in_rest' => true,
        'supports'     => ['title', 'editor', 'excerpt', 'thumbnail', 'page-attributes', 'comments'],
    ]);

    register_post_type('logo_animation', [
        'labels' => [
            'name'               => 'Logo Animations',
            'singular_name'      => 'Logo Animation',
            'add_new_item'       => 'Add New Logo Animation',
            'edit_item'          => 'Edit Logo Animation',
            'view_item'          => 'View Logo Animation',
            'menu_name'          => 'Logo Animations',
            'all_items'          => 'All Logo Animations',
        ],
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_admin_bar'   => true,
        'show_in_nav_menus'   => false,
        'exclude_from_search' => true,
        'menu_icon'           => 'dashicons-format-image',
        'show_in_rest'        => false,
        'supports'            => ['title', 'revisions'],
    ]);

    register_post_type('ticker', [
        'labels' => [
            'name'          => 'Ticker Items',
            'singular_name' => 'Ticker Item',
            'add_new_item'  => 'Add New Ticker Item',
            'edit_item'     => 'Edit Ticker Item',
            'view_item'     => 'View Ticker Item',
        ],
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_admin_bar'   => true,
        'show_in_nav_menus'   => false,
        'exclude_from_search' => true,
        'menu_icon'           => 'dashicons-megaphone',
        'show_in_rest'        => true,
        'supports'            => ['title', 'editor', 'revisions', 'page-attributes'],
    ]);
}

add_action('init', 'dracka_register_content_types');

/**
 * Registers Series taxonomies.
 *
 * @return void
 */
function dracka_register_series_taxonomies()
{
    register_taxonomy('dracka_series_genre', ['series'], [
        'labels'            => [
            'name'          => 'Genres',
            'singular_name' => 'Genre',
            'search_items'  => 'Search Genres',
            'all_items'     => 'All Genres',
            'edit_item'     => 'Edit Genre',
            'update_item'   => 'Update Genre',
            'add_new_item'  => 'Add New Genre',
            'new_item_name' => 'New Genre Name',
            'menu_name'     => 'Genre',
        ],
        'public'            => true,
        'show_ui'           => true,
        'show_admin_column' => true,
        'show_in_rest'      => true,
        'hierarchical'      => false,
        'rewrite'           => ['slug' => 'series-genre'],
    ]);
}
add_action('init', 'dracka_register_series_taxonomies');

/**
 * Returns custom status slugs/labels used by Series posts.
 *
 * @return array<string, string>
 */
function dracka_get_series_custom_statuses()
{
    return [
        'ongoing'    => 'Ongoing',
        'upcoming'   => 'Upcoming',
        'hiatus'     => 'Hiatus',
        'cancelled'  => 'Cancelled',
        'standalone' => 'Standalone',
        'finished'   => 'Finished',
    ];
}

/**
 * Returns legacy-to-canonical series status aliases.
 *
 * @return array<string, string>
 */
function dracka_get_series_status_aliases()
{
    return [
        'publish'     => 'ongoing',
        'coming-soon' => 'upcoming',
        'finalized'   => 'finished',
    ];
}

/**
 * Returns canonical series status slug.
 *
 * @param string $status_slug Status slug.
 * @return string
 */
function dracka_normalize_series_status_slug($status_slug)
{
    $status_slug = (string) $status_slug;
    $status_aliases = dracka_get_series_status_aliases();

    return $status_aliases[$status_slug] ?? $status_slug;
}

/**
 * Returns human-readable label for a series status.
 *
 * @param string $status_slug Status slug.
 * @return string
 */
function dracka_get_series_status_label($status_slug)
{
    $normalized_status = dracka_normalize_series_status_slug((string) $status_slug);
    $custom_statuses = dracka_get_series_custom_statuses();

    if (isset($custom_statuses[$normalized_status])) {
        return $custom_statuses[$normalized_status];
    }

    return ucfirst(str_replace('-', ' ', $normalized_status));
}

/**
 * Returns valid story-status slugs for Series posts.
 *
 * @return array<int, string>
 */
function dracka_get_series_public_statuses()
{
    return array_keys(dracka_get_series_custom_statuses());
}

/**
 * Returns post_status values used when querying series in WP_Query.
 * All series are now stored with post_status = 'publish'.
 *
 * @return array<int, string>
 */
function dracka_get_series_accepted_statuses()
{
    return ['publish', 'draft', 'pending', 'future', 'private'];
}

/**
 * Returns post_status values used in issue->series selectors.
 *
 * @return array<int, string>
 */
function dracka_get_series_editable_statuses()
{
    return ['publish', 'draft', 'pending', 'future', 'private'];
}

/**
 * Runs a one-time migration that reads the old post_status-based story
 * status from each series post and writes it to the dracka_series_story_status
 * meta field, then normalises all series post_status values to 'publish'.
 *
 * Guarded by a transient so it only runs once per environment.
 *
 * @return void
 */
function dracka_migrate_series_story_status()
{
    if (get_transient('dracka_series_status_migrated')) {
        return;
    }

    // Legacy post_status values that carried story-status meaning.
    $legacy_statuses = [
        'ongoing',
        'upcoming',
        'hiatus',
        'cancelled',
        'discontinued',
        'finished',
        'publish',
        'coming-soon',
        'finalized',
    ];

    // Map old slugs → new canonical story-status slugs.
    $migration_map = [
        'publish'      => 'ongoing',
        'coming-soon'  => 'upcoming',
        'finalized'    => 'finished',
        'discontinued' => 'cancelled',
        // canonical slugs are identity-mapped below
    ];

    $series_posts = get_posts([
        'post_type'      => 'series',
        'post_status'    => $legacy_statuses,
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ]);

    foreach ($series_posts as $series_id) {
        // Only write meta if it has not already been set.
        $existing = get_post_meta((int) $series_id, DRACKA_SERIES_STORY_STATUS_META_KEY, true);
        if ($existing !== '') {
            // Already migrated; just ensure post_status is publish.
            wp_update_post(['ID' => (int) $series_id, 'post_status' => 'publish']);
            continue;
        }

        $raw_status = get_post_field('post_status', (int) $series_id);
        $story_status = $migration_map[$raw_status] ?? $raw_status;

        // Fall back to 'ongoing' for any unknown slug.
        $valid_slugs = array_keys(dracka_get_series_custom_statuses());
        if (!in_array($story_status, $valid_slugs, true)) {
            $story_status = 'ongoing';
        }

        update_post_meta((int) $series_id, DRACKA_SERIES_STORY_STATUS_META_KEY, $story_status);
        wp_update_post(['ID' => (int) $series_id, 'post_status' => 'publish']);
    }

    set_transient('dracka_series_status_migrated', '1', 0); // 0 = never auto-expires
}
add_action('admin_init', 'dracka_migrate_series_story_status');

/**
 * Allows SVG uploads for privileged content editors.
 *
 * @param array<string, string> $mimes Existing allowed MIME map.
 * @return array<string, string>
 */
function dracka_allow_svg_uploads($mimes)
{
    if (current_user_can('manage_options') || current_user_can('edit_others_posts')) {
        $mimes['svg'] = 'image/svg+xml';
        $mimes['svgz'] = 'image/svg+xml';
    }

    return $mimes;
}
add_filter('upload_mimes', 'dracka_allow_svg_uploads');

const DRACKA_LOGO_SVG_META_KEY = 'dracka_logo_svg_attachment_id';
const DRACKA_LOGO_SOURCE_META_KEY = 'dracka_logo_source_attachment_id';
const DRACKA_LOGO_WEBP_META_KEY = 'dracka_logo_webp_attachment_ids';
const DRACKA_LOGO_ACTIVE_META_KEY = 'dracka_logo_is_active';
const DRACKA_TICKER_ACTIVE_META_KEY = 'dracka_ticker_is_active';
const DRACKA_SERIES_SPLASH_META_KEY = 'dracka_series_splash_attachment_id';
const DRACKA_SERIES_AUTHOR_META_KEY = 'dracka_series_author';
const DRACKA_SERIES_DESCRIPTION_META_KEY = 'dracka_series_description';
const DRACKA_SERIES_YEAR_META_KEY = 'dracka_publication_year';
const DRACKA_ISSUE_NUMBER_META_KEY = 'dracka_series_order';
const DRACKA_SERIES_RATING_META_KEY = 'dracka_series_rating';
const DRACKA_SERIES_GATE_TITLE_META_KEY = 'dracka_series_gate_title';
const DRACKA_SERIES_GATE_BODY_META_KEY = 'dracka_series_gate_body';
const DRACKA_SERIES_STORY_STATUS_META_KEY = 'dracka_series_story_status';
const DRACKA_ALBUM_RATING_META_KEY = 'dracka_album_rating';
const DRACKA_ALBUM_GATE_TITLE_META_KEY = 'dracka_album_gate_title';
const DRACKA_ALBUM_GATE_BODY_META_KEY = 'dracka_album_gate_body';
const DRACKA_SERIES_IS_STANDALONE_META_KEY = 'dracka_series_is_standalone';
const DRACKA_SERIES_STANDALONE_FLIPBOOK_META_KEY = 'dracka_series_standalone_flipbook_id';

/**
 * Enqueues media scripts for issue and series editor screens.
 *
 * @param string $hook_suffix Current admin page hook suffix.
 * @return void
 */
function dracka_enqueue_issue_series_admin_media($hook_suffix)
{
    if (!in_array($hook_suffix, ['post-new.php', 'post.php'], true)) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || !in_array($screen->post_type, ['issue', 'series'], true)) {
        return;
    }

    wp_enqueue_media();
}
add_action('admin_enqueue_scripts', 'dracka_enqueue_issue_series_admin_media');

/**
 * Enqueues media scripts for logo animation editor screens.
 *
 * @param string $hook_suffix Current admin page hook suffix.
 * @return void
 */
function dracka_enqueue_logo_animation_admin_media($hook_suffix)
{
    if (!in_array($hook_suffix, ['post-new.php', 'post.php'], true)) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'logo_animation') {
        return;
    }

    wp_enqueue_media();
}
add_action('admin_enqueue_scripts', 'dracka_enqueue_logo_animation_admin_media');

/**
 * Retrieves logo source attachment ID with backward compatibility fallback.
 *
 * @param int $post_id Post ID.
 * @return int Attachment ID or 0.
 */
function dracka_get_logo_source_attachment_id($post_id)
{
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return 0;
    }

    $source_id = (int) get_post_meta($post_id, DRACKA_LOGO_SOURCE_META_KEY, true);

    if ($source_id > 0) {
        return $source_id;
    }

    return (int) get_post_meta($post_id, DRACKA_LOGO_SVG_META_KEY, true);
}

/**
 * Sets logo source attachment ID with migration to new key.
 *
 * @param int $post_id Post ID.
 * @param int $attachment_id Attachment ID or 0 to remove.
 * @return bool
 */
function dracka_set_logo_source_attachment_id($post_id, $attachment_id)
{
    $post_id = (int) $post_id;
    $attachment_id = (int) $attachment_id;

    if ($post_id <= 0) {
        return false;
    }

    delete_post_meta($post_id, DRACKA_LOGO_SVG_META_KEY);

    if ($attachment_id > 0) {
        return (bool) update_post_meta($post_id, DRACKA_LOGO_SOURCE_META_KEY, $attachment_id);
    } else {
        return (bool) delete_post_meta($post_id, DRACKA_LOGO_SOURCE_META_KEY);
    }
}

/**
 * Registers custom query vars used by archive tab routing.
 *
 * @param array $vars Existing public query vars.
 * @return array
 */
function dracka_register_query_vars($vars)
{
    $vars[] = 'dracka_library_tab';
    $vars[] = 'dracka_gallery_tab';

    return $vars;
}
add_filter('query_vars', 'dracka_register_query_vars');

/**
 * Adds rewrite rules for library/gallery tabbed archive URLs.
 *
 * @return void
 */
function dracka_add_tab_rewrite_rules()
{
    add_rewrite_rule(
        '^library/(series|issues|standalones)/?$',
        'index.php?post_type=series&dracka_library_tab=$matches[1]',
        'top'
    );

    add_rewrite_rule(
        '^library/(series|issues|standalones)/page/([0-9]{1,})/?$',
        'index.php?post_type=series&dracka_library_tab=$matches[1]&paged=$matches[2]',
        'top'
    );

    add_rewrite_rule(
        '^gallery/(albums|artwork)/?$',
        'index.php?post_type=album&dracka_gallery_tab=$matches[1]',
        'top'
    );

    add_rewrite_rule(
        '^gallery/(albums|artwork)/page/([0-9]{1,})/?$',
        'index.php?post_type=album&dracka_gallery_tab=$matches[1]&paged=$matches[2]',
        'top'
    );

    // Legacy support for removed standalone gallery tab.
    add_rewrite_rule(
        '^gallery/standalones/?$',
        'index.php?post_type=album&dracka_gallery_tab=artwork',
        'top'
    );

    add_rewrite_rule(
        '^gallery/standalones/page/([0-9]{1,})/?$',
        'index.php?post_type=album&dracka_gallery_tab=artwork&paged=$matches[1]',
        'top'
    );

    // Flush rewrite rules when rule set version changes
    $rewrite_rules_version = '2026-03-24';
    if (get_option('dracka_rewrite_rules_version') !== $rewrite_rules_version) {
        flush_rewrite_rules();
        update_option('dracka_rewrite_rules_version', $rewrite_rules_version);
    }
}
add_action('init', 'dracka_add_tab_rewrite_rules', 11);

/**
 * Resolves the active library tab from the request.
 *
 * @return string
 */
function dracka_get_library_tab()
{
    $tab = get_query_var('dracka_library_tab');
    $allowed_tabs = ['series', 'issues', 'standalones'];

    if (!is_string($tab) || !in_array($tab, $allowed_tabs, true)) {
        return 'series';
    }

    return $tab;
}

/**
 * Resolves the active gallery tab from the request.
 *
 * @return string
 */
function dracka_get_gallery_tab()
{
    $tab = get_query_var('dracka_gallery_tab');
    $allowed_tabs = ['albums', 'artwork'];

    if (!is_string($tab) || !in_array($tab, $allowed_tabs, true)) {
        return 'artwork';
    }

    return $tab;
}

/**
 * Adds admin metaboxes for parent-child content relationships.
 *
 * Issues get a series selector and artworks get an album selector so
 * editors can define hierarchical links directly from edit screens.
 *
 * @return void
 */
function dracka_add_relationship_metaboxes()
{
    add_meta_box(
        'dracka_series_link',
        'Series',
        'dracka_render_series_metabox',
        ['issue'],
        'side',
        'default'
    );

    add_meta_box(
        'dracka_issue_access',
        'Issue Access',
        'dracka_render_issue_access_metabox',
        ['issue'],
        'side',
        'default'
    );

    add_meta_box(
        'dracka_album_link',
        'Album',
        'dracka_render_album_metabox',
        ['artwork'],
        'side',
        'default'
    );

    add_meta_box(
        'dracka_series_details',
        'Series Details',
        'dracka_render_series_details_metabox',
        ['series'],
        'normal',
        'high'
    );

    if (function_exists('use_block_editor_for_post_type') && use_block_editor_for_post_type('series')) {
        add_meta_box(
            'dracka_series_splash_fallback',
            'Splash',
            'dracka_render_series_splash_metabox',
            ['series'],
            'side',
            'high'
        );
    }

    add_meta_box(
        'dracka_logo_animation_media',
        'Logo Media',
        'dracka_render_logo_animation_metabox',
        ['logo_animation'],
        'normal',
        'default'
    );

    add_meta_box(
        'dracka_ticker_settings',
        'Ticker Settings',
        'dracka_render_ticker_metabox',
        ['ticker'],
        'side',
        'default'
    );

    add_meta_box(
        'dracka_album_details',
        'Album Details',
        'dracka_render_album_details_metabox',
        ['album'],
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'dracka_add_relationship_metaboxes');

/**
 * Renders album details metabox fields (audience rating + age-gate text).
 *
 * @param WP_Post $post Current album post.
 * @return void
 */
function dracka_render_album_details_metabox($post)
{
    wp_nonce_field('dracka_save_album_details', 'dracka_album_details_nonce');

    $album_rating = (string) get_post_meta($post->ID, DRACKA_ALBUM_RATING_META_KEY, true);
    $gate_title   = (string) get_post_meta($post->ID, DRACKA_ALBUM_GATE_TITLE_META_KEY, true);
    $gate_body    = (string) get_post_meta($post->ID, DRACKA_ALBUM_GATE_BODY_META_KEY, true);

    if (!in_array($album_rating, ['everyone', '16', '18'], true)) {
        $album_rating = 'everyone';
    }

    echo '<p>';
    echo '<label for="dracka_album_rating" style="display:block;margin-bottom:4px"><strong>Audience Rating</strong></label>';
    echo '<select id="dracka_album_rating" name="dracka_album_rating" style="width:100%">';
    echo '<option value="everyone"' . selected($album_rating, 'everyone', false) . '>Everyone (E)</option>';
    echo '<option value="16"'       . selected($album_rating, '16',       false) . '>+16</option>';
    echo '<option value="18"'       . selected($album_rating, '18',       false) . '>+18</option>';
    echo '</select>';
    echo '</p>';

    $gate_fields_style = in_array($album_rating, ['16', '18'], true) ? '' : 'display:none';

    echo '<div id="dracka_album_gate_fields" style="' . esc_attr($gate_fields_style) . '">';

    echo '<p>';
    echo '<label for="dracka_album_gate_title" style="display:block;margin-bottom:4px"><strong>Age Gate — Headline</strong></label>';
    echo '<input type="text" id="dracka_album_gate_title" name="dracka_album_gate_title" value="' . esc_attr($gate_title) . '" style="width:100%" placeholder="e.g. Are you over 16?">';
    echo '</p>';

    echo '<p>';
    echo '<label for="dracka_album_gate_body" style="display:block;margin-bottom:4px"><strong>Age Gate — Body</strong></label>';
    echo '<textarea id="dracka_album_gate_body" name="dracka_album_gate_body" rows="3" style="width:100%" placeholder="e.g. This album contains mature content...">' . esc_textarea($gate_body) . '</textarea>';
    echo '</p>';

    echo '</div>';

    echo '<script type="text/javascript">';
    echo 'document.addEventListener("DOMContentLoaded", function() {';
    echo 'var ratingSelect = document.getElementById("dracka_album_rating");';
    echo 'var gateFields   = document.getElementById("dracka_album_gate_fields");';
    echo 'if (ratingSelect && gateFields) {';
    echo '  ratingSelect.addEventListener("change", function() {';
    echo '    gateFields.style.display = (ratingSelect.value === "16" || ratingSelect.value === "18") ? "" : "none";';
    echo '  });';
    echo '}';
    echo '});';
    echo '</script>';
}

// ---------------------------------------------------------------------------
// Age Gate – resolver and front-end render helpers
// ---------------------------------------------------------------------------

/**
 * Returns the series ID that governs the age gate for the current page.
 *
 * On a single series page the queried object IS the series.
 * On a single issue page the governing series is the one linked via meta.
 *
 * @return int Series post ID, or 0 when not applicable.
 */
function dracka_get_gate_series_id(): int
{
    if (is_singular('series')) {
        return (int) get_queried_object_id();
    }

    if (is_singular('issue')) {
        $series_id = (int) get_post_meta(get_queried_object_id(), 'dracka_series_id', true);
        return $series_id > 0 ? $series_id : 0;
    }

    return 0;
}

/**
 * Returns the age-gate configuration for the given series, or null when no gate is required.
 *
 * @param int $series_id Series post ID.
 * @return array{series_id:int,rating:string,gate_title:string,gate_body:string}|null
 */
function dracka_get_series_gate_config(int $series_id): ?array
{
    if ($series_id <= 0) {
        return null;
    }

    $rating = (string) get_post_meta($series_id, DRACKA_SERIES_RATING_META_KEY, true);

    if (!in_array($rating, ['16', '18'], true)) {
        return null;
    }

    $gate_title = (string) get_post_meta($series_id, DRACKA_SERIES_GATE_TITLE_META_KEY, true);
    $gate_body  = (string) get_post_meta($series_id, DRACKA_SERIES_GATE_BODY_META_KEY, true);

    if ($gate_title === '') {
        $gate_title = $rating === '18'
            ? 'Are you over 18?'
            : 'Are you over 16?';
    }

    if ($gate_body === '') {
        $gate_body = $rating === '18'
            ? 'This series contains mature content intended for readers aged 18 and above. You must be 18 or older to continue.'
            : 'This series contains mature content intended for readers aged 16 and above. You must be 16 or older to continue.';
    }

    return [
        'series_id'  => $series_id,
        'rating'     => $rating,
        'gate_title' => $gate_title,
        'gate_body'  => $gate_body,
    ];
}

/**
 * Outputs the age-gate overlay markup for a guarded series page.
 *
 * Emits nothing when no gate is required for the current page.
 *
 * @return void
 */
/**
 * Returns the album ID that governs the age gate for the current page.
 *
 * @return int Album post ID, or 0 when not applicable.
 */
function dracka_get_gate_album_id(): int
{
    if (is_singular('album')) {
        return (int) get_queried_object_id();
    }

    return 0;
}

/**
 * Returns the age-gate configuration for the given album, or null when no gate is required.
 *
 * @param int $album_id Album post ID.
 * @return array{series_id:int,rating:string,gate_title:string,gate_body:string}|null
 */
function dracka_get_album_gate_config(int $album_id): ?array
{
    if ($album_id <= 0) {
        return null;
    }

    $rating = (string) get_post_meta($album_id, DRACKA_ALBUM_RATING_META_KEY, true);

    if (!in_array($rating, ['16', '18'], true)) {
        return null;
    }

    $gate_title = (string) get_post_meta($album_id, DRACKA_ALBUM_GATE_TITLE_META_KEY, true);
    $gate_body  = (string) get_post_meta($album_id, DRACKA_ALBUM_GATE_BODY_META_KEY, true);

    if ($gate_title === '') {
        $gate_title = $rating === '18'
            ? 'Are you over 18?'
            : 'Are you over 16?';
    }

    if ($gate_body === '') {
        $gate_body = $rating === '18'
            ? 'This album contains mature content intended for viewers aged 18 and above. You must be 18 or older to continue.'
            : 'This album contains mature content intended for viewers aged 16 and above. You must be 16 or older to continue.';
    }

    return [
        'series_id'  => $album_id,
        'rating'     => $rating,
        'gate_title' => $gate_title,
        'gate_body'  => $gate_body,
    ];
}

function dracka_render_age_gate(): void
{
    $series_id = dracka_get_gate_series_id();
    $config    = dracka_get_series_gate_config($series_id);

    if ($config === null) {
        $album_id = dracka_get_gate_album_id();
        $config   = dracka_get_album_gate_config($album_id);
    }

    if ($config === null) {
        return;
    }

    $home_url   = esc_url(home_url('/'));
    $series_id  = (int) $config['series_id'];
    $rating     = esc_attr($config['rating']);
    $gate_title = esc_html($config['gate_title']);
    $gate_body  = esc_html($config['gate_body']);
?>
    <div
        class="age-gate"
        id="dracka-age-gate"
        data-series-id="<?php echo $series_id; ?>"
        data-rating="<?php echo $rating; ?>"
        data-home-url="<?php echo $home_url; ?>"
        aria-modal="true"
        role="dialog"
        aria-labelledby="age-gate-title"
        aria-describedby="age-gate-body">
        <div class="age-gate__panel">
            <div class="age-gate__rating-badge" aria-hidden="true">+<?php echo $rating; ?></div>
            <h2 class="age-gate__title" id="age-gate-title"><?php echo $gate_title; ?></h2>
            <p class="age-gate__body" id="age-gate-body"><?php echo $gate_body; ?></p>
            <div class="age-gate__actions">
                <button class="age-gate__btn age-gate__btn--confirm" type="button" id="dracka-age-gate-confirm">
                    Yes, I&rsquo;m over <?php echo $rating; ?>
                </button>
                <a class="age-gate__btn age-gate__btn--decline" href="<?php echo $home_url; ?>">
                    No, take me back
                </a>
            </div>
        </div>
        <div class="age-gate__backdrop" aria-hidden="true"></div>
    </div>
<?php
}

/**
 * Renders splash selector above title in classic series editor.
 *
 * @param WP_Post $post Post being edited.
 * @return void
 */
function dracka_render_series_splash_before_title($post)
{
    if (!$post instanceof WP_Post || $post->post_type !== 'series') {
        return;
    }

    if (function_exists('use_block_editor_for_post_type') && use_block_editor_for_post_type('series')) {
        return;
    }

    echo '<div class="postbox" style="margin: 16px 0 12px">';
    echo '<div class="postbox-header"><h2 class="hndle">Splash</h2></div>';
    echo '<div class="inside">';
    dracka_render_series_splash_metabox($post);
    echo '</div>';
    echo '</div>';
}
add_action('edit_form_top', 'dracka_render_series_splash_before_title');

/**
 * Renders series details metabox fields.
 *
 * @param WP_Post $post Current series post.
 * @return void
 */
function dracka_render_series_details_metabox($post)
{
    wp_nonce_field('dracka_save_series_details', 'dracka_series_details_nonce');

    $series_author      = (string) get_post_meta($post->ID, DRACKA_SERIES_AUTHOR_META_KEY, true);
    $publication_year   = (string) get_post_meta($post->ID, DRACKA_SERIES_YEAR_META_KEY, true);
    $series_description = (string) get_post_meta($post->ID, DRACKA_SERIES_DESCRIPTION_META_KEY, true);
    $series_rating      = (string) get_post_meta($post->ID, DRACKA_SERIES_RATING_META_KEY, true);
    $gate_title         = (string) get_post_meta($post->ID, DRACKA_SERIES_GATE_TITLE_META_KEY, true);
    $gate_body          = (string) get_post_meta($post->ID, DRACKA_SERIES_GATE_BODY_META_KEY, true);
    $story_status       = (string) get_post_meta($post->ID, DRACKA_SERIES_STORY_STATUS_META_KEY, true);
    $is_standalone      = (string) get_post_meta($post->ID, DRACKA_SERIES_IS_STANDALONE_META_KEY, true);
    $standalone_book_id = (int) get_post_meta($post->ID, DRACKA_SERIES_STANDALONE_FLIPBOOK_META_KEY, true);

    if ($story_status === '' || !array_key_exists($story_status, dracka_get_series_custom_statuses())) {
        $story_status = 'ongoing';
    }
    if (!in_array($series_rating, ['everyone', '16', '18'], true)) {
        $series_rating = 'everyone';
    }

    // Story Status dropdown — locked to 'standalone' when Is Standalone is checked.
    $custom_statuses = dracka_get_series_custom_statuses();
    echo '<p>';
    echo '<label for="dracka_series_story_status" style="display:block;margin-bottom:4px"><strong>Story Status</strong></label>';
    echo '<select id="dracka_series_story_status" name="dracka_series_story_status" style="width:100%">';
    foreach ($custom_statuses as $slug => $label) {
        if ($slug === 'standalone') continue; // only selectable via Is Standalone checkbox
        echo '<option value="' . esc_attr($slug) . '"' . selected($story_status, $slug, false) . '>' . esc_html($label) . '</option>';
    }
    echo '<option value="standalone"' . selected($story_status, 'standalone', false) . ' disabled style="color:#999">Standalone (set via checkbox below)</option>';
    echo '</select>';
    echo '</p>';

    // Is Standalone toggle.
    $standalone_checked = $is_standalone === '1' ? ' checked' : '';
    echo '<p>';
    echo '<label style="display:flex;align-items:center;gap:8px;cursor:pointer">';
    echo '<input type="checkbox" id="dracka_series_is_standalone" name="dracka_series_is_standalone" value="1"' . $standalone_checked . '>';
    echo '<strong>Is Standalone</strong>';
    echo '</label>';
    echo '<span class="description" style="display:block;margin-top:4px">A standalone has a single comic instead of a list of issues.</span>';
    echo '</p>';

    // DearFlip book selector — shown only when Is Standalone is checked.
    $books = dracka_get_dearflip_book_options();
    $standalone_fields_style = $is_standalone === '1' ? '' : 'display:none';
    echo '<div id="dracka_series_standalone_fields" style="' . esc_attr($standalone_fields_style) . ';padding:12px;background:#f8f8f8;border:1px solid #ddd;border-radius:3px;margin-bottom:12px">';
    echo '<label for="dracka_series_standalone_flipbook_id" style="display:block;margin-bottom:4px"><strong>DearFlip Book</strong></label>';
    echo '<select id="dracka_series_standalone_flipbook_id" name="dracka_series_standalone_flipbook_id" style="width:100%">';
    echo '<option value="">Select a book</option>';
    if (!empty($books)) {
        foreach ($books as $book) {
            $status_suffix = (!empty($book['status']) && $book['status'] !== 'publish') ? ' (' . $book['status'] . ')' : '';
            echo '<option value="' . esc_attr($book['id']) . '"' . selected($standalone_book_id, (int) $book['id'], false) . '>' . esc_html($book['label'] . $status_suffix) . '</option>';
        }
    }
    echo '</select>';
    if (empty($books)) {
        echo '<p class="description" style="margin-top:6px">No DearFlip books found. Activate DearFlip and create at least one book.</p>';
    }
    echo '</div>';

    echo '<hr style="margin:12px 0">';

    echo '<p>';
    echo '<label for="dracka_series_author" style="display:block;margin-bottom:4px"><strong>Series Author</strong></label>';
    echo '<input type="text" id="dracka_series_author" name="dracka_series_author" value="' . esc_attr($series_author) . '" style="width:100%" placeholder="Credit the comic author/artist">';
    echo '</p>';

    echo '<p>';
    echo '<label for="dracka_publication_year" style="display:block;margin-bottom:4px"><strong>Publication Year</strong></label>';
    echo '<input type="number" id="dracka_publication_year" name="dracka_publication_year" value="' . esc_attr($publication_year) . '" min="1000" max="9999" step="1" style="width:100%" placeholder="YYYY">';
    echo '</p>';

    echo '<p>';
    echo '<label for="dracka_series_description" style="display:block;margin-bottom:4px"><strong>Description</strong></label>';
    echo '<textarea id="dracka_series_description" name="dracka_series_description" rows="6" style="width:100%" placeholder="Series description, plot, lore...">' . esc_textarea($series_description) . '</textarea>';
    echo '</p>';

    echo '<hr style="margin:16px 0">';

    echo '<p>';
    echo '<label for="dracka_series_rating" style="display:block;margin-bottom:4px"><strong>Audience Rating</strong></label>';
    echo '<select id="dracka_series_rating" name="dracka_series_rating" style="width:100%">';
    echo '<option value="everyone"' . selected($series_rating, 'everyone', false) . '>Everyone (E)</option>';
    echo '<option value="16"'       . selected($series_rating, '16',       false) . '>+16</option>';
    echo '<option value="18"'       . selected($series_rating, '18',       false) . '>+18</option>';
    echo '</select>';
    echo '</p>';

    $gate_fields_style = in_array($series_rating, ['16', '18'], true) ? '' : 'display:none';

    echo '<div id="dracka_series_gate_fields" style="' . esc_attr($gate_fields_style) . '">';

    echo '<p>';
    echo '<label for="dracka_series_gate_title" style="display:block;margin-bottom:4px"><strong>Age Gate — Headline</strong></label>';
    echo '<input type="text" id="dracka_series_gate_title" name="dracka_series_gate_title" value="' . esc_attr($gate_title) . '" style="width:100%" placeholder="e.g. Are you over 16?">';
    echo '</p>';

    echo '<p>';
    echo '<label for="dracka_series_gate_body" style="display:block;margin-bottom:4px"><strong>Age Gate — Body</strong></label>';
    echo '<textarea id="dracka_series_gate_body" name="dracka_series_gate_body" rows="4" style="width:100%" placeholder="e.g. This series contains mature content intended for readers aged 16 and above.">' . esc_textarea($gate_body) . '</textarea>';
    echo '</p>';

    echo '</div>';

    echo '<script type="text/javascript">';
    echo 'document.addEventListener("DOMContentLoaded", function() {';
    // Rating/gate toggle
    echo 'var ratingSelect = document.getElementById("dracka_series_rating");';
    echo 'var gateFields   = document.getElementById("dracka_series_gate_fields");';
    echo 'if (ratingSelect && gateFields) {';
    echo '  ratingSelect.addEventListener("change", function() {';
    echo '    gateFields.style.display = (ratingSelect.value === "16" || ratingSelect.value === "18") ? "" : "none";';
    echo '  });';
    echo '}';
    // Standalone toggle
    echo 'var standaloneChk    = document.getElementById("dracka_series_is_standalone");';
    echo 'var standaloneFields = document.getElementById("dracka_series_standalone_fields");';
    echo 'var statusSelect     = document.getElementById("dracka_series_story_status");';
    echo 'if (standaloneChk && standaloneFields && statusSelect) {';
    echo '  function syncStandalone() {';
    echo '    var checked = standaloneChk.checked;';
    echo '    standaloneFields.style.display = checked ? "" : "none";';
    echo '    if (checked) {';
    echo '      statusSelect.value = "standalone";';
    echo '      statusSelect.disabled = true;';
    echo '    } else {';
    echo '      if (statusSelect.value === "standalone") { statusSelect.value = "ongoing"; }';
    echo '      statusSelect.disabled = false;';
    echo '    }';
    echo '  }';
    echo '  standaloneChk.addEventListener("change", syncStandalone);';
    echo '  syncStandalone();';
    echo '}';
    echo '});';
    echo '</script>';
}

/**
 * Renders splash image selector for series.
 *
 * @param WP_Post $post Current series post.
 * @return void
 */
function dracka_render_series_splash_metabox($post)
{
    wp_nonce_field('dracka_save_series_splash', 'dracka_series_splash_nonce');

    $attachment_id = (int) get_post_meta($post->ID, DRACKA_SERIES_SPLASH_META_KEY, true);
    $preview = $attachment_id > 0 ? wp_get_attachment_image($attachment_id, 'medium', false, ['style' => 'display:block;width:100%;height:auto;border-radius:3px']) : '';

    echo '<input type="hidden" id="dracka_series_splash_id" name="dracka_series_splash_id" value="' . esc_attr($attachment_id) . '">';
    echo '<div id="dracka_series_splash_preview" style="margin-bottom:10px">';
    echo $preview ?: '<div style="padding:12px;border:1px dashed #ccd0d4;border-radius:3px;color:#666">No splash image selected.</div>';
    echo '</div>';
    echo '<p style="display:flex;gap:8px">';
    echo '<button type="button" class="button button-primary" id="dracka_select_series_splash_btn">Select Splash</button>';
    echo '<button type="button" class="button" id="dracka_remove_series_splash_btn">Remove Splash</button>';
    echo '</p>';

    echo '<script type="text/javascript">';
    echo 'document.addEventListener("DOMContentLoaded", function() {';
    echo 'var selectBtn = document.getElementById("dracka_select_series_splash_btn");';
    echo 'var removeBtn = document.getElementById("dracka_remove_series_splash_btn");';
    echo 'var input = document.getElementById("dracka_series_splash_id");';
    echo 'var preview = document.getElementById("dracka_series_splash_preview");';
    echo 'var frame;';
    echo 'if (!selectBtn || !removeBtn || !input || !preview) { return; }';

    echo 'selectBtn.addEventListener("click", function(e) {';
    echo 'e.preventDefault();';
    echo 'if (frame) { frame.open(); return; }';
    echo 'frame = wp.media({ title: "Select Splash", library: { type: "image" }, button: { text: "Use this image" }, multiple: false });';
    echo 'frame.on("select", function() {';
    echo 'var attachment = frame.state().get("selection").first().toJSON();';
    echo 'if (!attachment || attachment.type !== "image") { alert("Please select an image file."); return; }';
    echo 'input.value = attachment.id;';
    echo 'var src = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;';
    echo 'preview.innerHTML = "<img src=\"" + src + "\" alt=\"Series splash\" style=\"display:block;width:100%;height:auto;border-radius:3px\">";';
    echo '});';
    echo 'frame.open();';
    echo '});';

    echo 'removeBtn.addEventListener("click", function(e) {';
    echo 'e.preventDefault();';
    echo 'input.value = "";';
    echo 'preview.innerHTML = "<div style=\"padding:12px;border:1px dashed #ccd0d4;border-radius:3px;color:#666\">No splash image selected.</div>";';
    echo '});';
    echo '});';
    echo '</script>';
}

/**
 * Renders the issue metabox that links an issue to a series.
 *
 * It prints a nonce, fetches current linkage/number metadata, queries
 * editable series statuses sorted by title, and renders a select input plus
 * issue number field for chapter sequencing.
 *
 * @param WP_Post $post Current issue post being edited.
 * @return void
 */
function dracka_get_series_issue_numbers($series_id, $exclude_post_id = 0): array
{
    $series_id = (int) $series_id;
    $exclude_post_id = (int) $exclude_post_id;

    if ($series_id <= 0) {
        return [];
    }

    $issue_ids = get_posts([
        'post_type'      => 'issue',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'meta_query'     => [
            [
                'key'     => 'dracka_series_id',
                'value'   => $series_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ],
        ],
    ]);
    $issue_ids = is_array($issue_ids) ? $issue_ids : [];

    $issue_numbers = [];

    foreach ($issue_ids as $issue_id) {
        $issue_id = (int) $issue_id;

        if ($exclude_post_id > 0 && $issue_id === $exclude_post_id) {
            continue;
        }

        $number = (int) get_post_meta($issue_id, DRACKA_ISSUE_NUMBER_META_KEY, true);
        if ($number > 0) {
            $issue_numbers[$number] = true;
        }
    }

    $numbers = array_keys($issue_numbers);
    sort($numbers, SORT_NUMERIC);

    return $numbers;
}

/**
 * Builds series-scoped navigation data for a single issue page.
 *
 * Returns ordered published issues in the same series and navigation targets
 * relative to the current issue position.
 *
 * @param int $issue_id Current issue post ID.
 * @return array<string, mixed>
 */
function dracka_get_issue_series_navigation($issue_id): array
{
    $navigation = [
        'series_id' => 0,
        'issues'    => [],
        'previous'  => null,
        'next'      => null,
        'last'      => null,
    ];

    $issue_id = (int) $issue_id;
    if ($issue_id <= 0) {
        return $navigation;
    }

    $series_id = (int) get_post_meta($issue_id, 'dracka_series_id', true);
    $issue_number = (int) get_post_meta($issue_id, DRACKA_ISSUE_NUMBER_META_KEY, true);

    if ($series_id <= 0 || $issue_number <= 0) {
        return $navigation;
    }

    $issue_query = new WP_Query([
        'post_type'      => 'issue',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'no_found_rows'  => true,
        'meta_query'     => [
            [
                'key'     => 'dracka_series_id',
                'value'   => $series_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ],
        ],
        'dracka_sort_by_issue_number' => true,
        'dracka_issue_number_direction' => 'ASC',
    ]);

    $issues = [];
    $current_index = -1;

    if ($issue_query->have_posts()) {
        while ($issue_query->have_posts()) {
            $issue_query->the_post();

            $listed_issue_id = (int) get_the_ID();
            $listed_issue_number = (int) get_post_meta($listed_issue_id, DRACKA_ISSUE_NUMBER_META_KEY, true);

            if ($listed_issue_number <= 0) {
                continue;
            }

            $issues[] = [
                'id'     => $listed_issue_id,
                'number' => $listed_issue_number,
                'title'  => get_the_title($listed_issue_id),
                'url'    => get_permalink($listed_issue_id),
                'date'   => get_the_date('', $listed_issue_id),
            ];

            if ($listed_issue_id === $issue_id) {
                $current_index = count($issues) - 1;
            }
        }
    }

    wp_reset_postdata();

    if ($current_index < 0 || count($issues) === 0) {
        return $navigation;
    }

    $navigation['series_id'] = $series_id;
    $navigation['issues'] = $issues;
    $navigation['previous'] = $current_index > 0 ? $issues[$current_index - 1] : null;
    $navigation['next'] = $current_index < count($issues) - 1 ? $issues[$current_index + 1] : null;
    $navigation['last'] = $current_index < count($issues) - 1 ? $issues[count($issues) - 1] : null;

    return $navigation;
}

/**
 * Builds album-scoped (or global standalone) navigation data for a single artwork page.
 *
 * When the artwork belongs to an album, navigation is scoped to sibling artworks in that
 * album ordered by menu_order ASC then date DESC (same as the album grid query).
 * When the artwork is standalone (no album), navigation is global among all standalone
 * artworks ordered by date ASC.
 *
 * Each item in 'artworks', 'previous', and 'next' contains:
 *   id, url, title, image_src, image_srcset, image_sizes
 *
 * @param int $artwork_id Current artwork post ID.
 * @return array<string, mixed>
 */
function dracka_get_artwork_navigation(int $artwork_id): array
{
    $navigation = [
        'context_id' => 0,
        'is_album'   => false,
        'previous'   => null,
        'next'       => null,
    ];

    $artwork_id = (int) $artwork_id;
    if ($artwork_id <= 0) {
        return $navigation;
    }

    $album_id = (int) get_post_meta($artwork_id, 'dracka_album_id', true);

    /**
     * Resolves image data for a given artwork post ID.
     *
     * @param int $id Artwork post ID.
     * @return array{id:int,url:string,title:string,image_src:string,image_srcset:string,image_sizes:string}
     */
    $make_item = function (int $id) use ($artwork_id): array {
        $thumb_id  = (int) get_post_thumbnail_id($id);
        $image_src = '';
        $srcset    = '';
        $sizes     = '';
        if ($thumb_id > 0) {
            $src_data  = wp_get_attachment_image_src($thumb_id, 'large');
            $image_src = is_array($src_data) ? (string) ($src_data[0] ?? '') : '';
            $srcset    = (string) (wp_get_attachment_image_srcset($thumb_id, 'large') ?: '');
            $sizes     = (string) (wp_get_attachment_image_sizes($thumb_id, 'large') ?: '');
        }
        return [
            'id'          => $id,
            'url'         => (string) get_permalink($id),
            'title'       => (string) get_the_title($id),
            'image_src'   => $image_src,
            'image_srcset' => $srcset,
            'image_sizes'  => $sizes,
        ];
    };

    if ($album_id > 0) {
        // --- Album-scoped navigation ---
        $query = new WP_Query([
            'post_type'      => 'artwork',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'no_found_rows'  => true,
            'meta_query'     => [
                [
                    'key'     => 'dracka_album_id',
                    'value'   => $album_id,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ],
            ],
            'orderby'        => ['menu_order' => 'ASC', 'date' => 'DESC'],
        ]);

        $ids           = [];
        $current_index = -1;

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $ids[] = (int) get_the_ID();
                if ((int) get_the_ID() === $artwork_id) {
                    $current_index = count($ids) - 1;
                }
            }
        }

        wp_reset_postdata();

        if ($current_index < 0) {
            return $navigation;
        }

        $navigation['context_id'] = $album_id;
        $navigation['is_album']   = true;
        $navigation['previous']   = $current_index > 0
            ? $make_item($ids[$current_index - 1])
            : null;
        $navigation['next']       = $current_index < count($ids) - 1
            ? $make_item($ids[$current_index + 1])
            : null;
    } else {
        // --- Global standalone navigation (artworks with no album), ordered by date ASC ---
        $query = new WP_Query([
            'post_type'      => 'artwork',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'no_found_rows'  => true,
            'orderby'        => 'date',
            'order'          => 'ASC',
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'     => 'dracka_album_id',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key'     => 'dracka_album_id',
                    'value'   => ['', '0'],
                    'compare' => 'IN',
                ],
            ],
        ]);

        $ids           = [];
        $current_index = -1;

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $ids[] = (int) get_the_ID();
                if ((int) get_the_ID() === $artwork_id) {
                    $current_index = count($ids) - 1;
                }
            }
        }

        wp_reset_postdata();

        if ($current_index < 0) {
            return $navigation;
        }

        $navigation['context_id'] = 0;
        $navigation['is_album']   = false;
        $navigation['previous']   = $current_index > 0
            ? $make_item($ids[$current_index - 1])
            : null;
        $navigation['next']       = $current_index < count($ids) - 1
            ? $make_item($ids[$current_index + 1])
            : null;
    }

    return $navigation;
}

/**
 * Returns the smallest unused positive issue number for a series.
 *
 * @param int $series_id Series post ID.
 * @param int $exclude_post_id Optional issue post ID to skip.
 * @return int
 */
function dracka_get_next_available_series_issue_number($series_id, $exclude_post_id = 0): int
{
    $numbers = dracka_get_series_issue_numbers($series_id, $exclude_post_id);
    if (!is_array($numbers)) {
        return 1;
    }

    $candidate = 1;

    foreach ($numbers as $number) {
        if ((int) $number === $candidate) {
            $candidate++;
            continue;
        }

        if ((int) $number > $candidate) {
            break;
        }
    }

    return $candidate;
}

/**
 * Checks whether an issue number is already used within a series.
 *
 * @param int $series_id Series post ID.
 * @param int $issue_number Proposed issue number.
 * @param int $exclude_post_id Optional issue post ID to skip.
 * @return bool
 */
function dracka_is_series_issue_number_taken($series_id, $issue_number, $exclude_post_id = 0): bool
{
    $issue_number = (int) $issue_number;

    if ($issue_number <= 0) {
        return false;
    }

    $numbers = dracka_get_series_issue_numbers($series_id, $exclude_post_id);
    if (!is_array($numbers)) {
        return false;
    }

    return in_array($issue_number, $numbers, true);
}

/**
 * Stores a one-time admin notice for issue number validation failures.
 *
 * @param int    $post_id Issue post ID.
 * @param string $message Notice message.
 * @return void
 */
function dracka_set_issue_number_validation_notice($post_id, $message)
{
    $user_id = get_current_user_id();
    if ($user_id <= 0) {
        return;
    }

    set_transient(
        'dracka_issue_number_notice_' . $user_id,
        [
            'post_id' => (int) $post_id,
            'message' => (string) $message,
        ],
        120
    );
}

function dracka_render_series_metabox($post)
{
    wp_nonce_field('dracka_save_series_link', 'dracka_series_nonce');

    $current_series = (int) get_post_meta($post->ID, 'dracka_series_id', true);
    $current_order = get_post_meta($post->ID, DRACKA_ISSUE_NUMBER_META_KEY, true);
    $relation_posts_limit = dracka_get_admin_relation_posts_limit();
    $series_posts = get_posts([
        'post_type'      => 'series',
        'post_status'    => dracka_get_series_editable_statuses(),
        'posts_per_page' => $relation_posts_limit,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'no_found_rows'  => true,
    ]);

    if ($current_series > 0) {
        $listed_series_ids = array_map('intval', wp_list_pluck($series_posts, 'ID'));

        if (!in_array($current_series, $listed_series_ids, true)) {
            $current_series_post = get_post($current_series);

            if ($current_series_post instanceof WP_Post && $current_series_post->post_type === 'series' && !in_array($current_series_post->post_status, ['trash', 'auto-draft'], true)) {
                $series_posts[] = $current_series_post;

                usort($series_posts, static function ($left, $right) {
                    return strcasecmp($left->post_title, $right->post_title);
                });
            }
        }
    }

    $default_issue_number = '';
    if ((string) $current_order !== '') {
        $default_issue_number = (string) (int) $current_order;
    } elseif ($current_series > 0) {
        $default_issue_number = (string) dracka_get_next_available_series_issue_number($current_series, (int) $post->ID);
    }

    // Batch-load next available issue numbers for all listed series in one query
    // to avoid an N+1 pattern (one dracka_get_next_available_series_issue_number()
    // call per series option).
    global $wpdb;
    $all_series_ids = array_values(array_filter(array_map('intval', wp_list_pluck($series_posts, 'ID'))));
    $next_issue_map = [];

    if (!empty($all_series_ids)) {
        $placeholders = implode(', ', array_fill(0, count($all_series_ids), '%d'));
        $query_args   = array_merge(
            [DRACKA_ISSUE_NUMBER_META_KEY, 'issue', (int) $post->ID],
            $all_series_ids
        );
        $batch_sql = $wpdb->prepare(
            "SELECT CAST(pm_s.meta_value AS UNSIGNED) AS series_id,
                    CAST(pm_n.meta_value AS UNSIGNED) AS issue_number
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_s
                     ON pm_s.post_id = p.ID AND pm_s.meta_key = 'dracka_series_id'
             INNER JOIN {$wpdb->postmeta} pm_n
                     ON pm_n.post_id = p.ID AND pm_n.meta_key = %s
             WHERE p.post_type = %s
               AND p.post_status NOT IN ('trash', 'auto-draft')
               AND p.ID != %d
               AND CAST(pm_s.meta_value AS UNSIGNED) IN ($placeholders)
               AND CAST(pm_n.meta_value AS UNSIGNED) > 0",
            $query_args
        );

        $series_numbers = array_fill_keys($all_series_ids, []);

        if (is_string($batch_sql)) {
            $rows = $wpdb->get_results($batch_sql, ARRAY_A);

            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $s_id = (int) ($row['series_id'] ?? 0);
                    $num  = (int) ($row['issue_number'] ?? 0);

                    if ($num > 0 && array_key_exists($s_id, $series_numbers)) {
                        $series_numbers[$s_id][] = $num;
                    }
                }
            }
        }

        foreach ($all_series_ids as $sid) {
            $numbers   = $series_numbers[$sid];
            sort($numbers, SORT_NUMERIC);
            $candidate = 1;

            foreach ($numbers as $taken) {
                if ($taken === $candidate) {
                    $candidate++;
                } elseif ($taken > $candidate) {
                    break;
                }
            }

            $next_issue_map[$sid] = $candidate;
        }
    }

    echo '<select name="dracka_series_id" style="width:100%">';
    echo '<option value="">No series (standalone)</option>';
    foreach ($series_posts as $series) {
        $selected = $current_series === (int) $series->ID ? ' selected' : '';
        $series_story_status = (string) get_post_meta((int) $series->ID, DRACKA_SERIES_STORY_STATUS_META_KEY, true);
        if ($series_story_status === '' || !array_key_exists($series_story_status, dracka_get_series_custom_statuses())) {
            $series_story_status = 'ongoing';
        }
        $series_option_label = $series->post_title;
        if ($series_story_status !== 'ongoing') {
            $series_option_label .= ' (' . dracka_get_series_custom_statuses()[$series_story_status] . ')';
        }
        $next_issue_number = $next_issue_map[(int) $series->ID] ?? dracka_get_next_available_series_issue_number((int) $series->ID, (int) $post->ID);
        echo '<option value="' . esc_attr($series->ID) . '" data-next-issue="' . esc_attr($next_issue_number) . '"' . $selected . '>' . esc_html($series_option_label) . '</option>';
    }
    echo '</select>';
    echo '<p style="margin-top:10px">';
    echo '<label for="dracka_series_order" style="display:block;margin-bottom:4px">Issue number</label>';
    echo '<input type="number" id="dracka_series_order" name="dracka_series_order" value="' . esc_attr($default_issue_number) . '" min="1" step="1" style="width:100%">';
    echo '<span class="description" id="dracka_issue_number_hint" style="display:block;margin-top:4px">Issue number is required for series-linked issues.</span>';
    echo '</p>';

    echo '<script type="text/javascript">';
    echo 'document.addEventListener("DOMContentLoaded", function() {';
    echo 'var seriesField = document.querySelector("select[name=\"dracka_series_id\"]");';
    echo 'var numberField = document.getElementById("dracka_series_order");';
    echo 'var hint = document.getElementById("dracka_issue_number_hint");';
    echo 'if (!seriesField || !numberField || !hint) { return; }';
    echo 'var updateHint = function() {';
    echo 'var selectedOption = seriesField.options[seriesField.selectedIndex];';
    echo 'var nextIssue = selectedOption ? selectedOption.getAttribute("data-next-issue") : "";';
    echo 'if (seriesField.value === "") {';
    echo 'hint.textContent = "Optional for standalone issues.";';
    echo 'return;';
    echo '}';
    echo 'if ((numberField.value || "").trim() === "" && nextIssue) {';
    echo 'numberField.value = nextIssue;';
    echo '}';
    echo 'hint.textContent = nextIssue ? ("Suggested next issue number: " + nextIssue) : "Issue number is required for series-linked issues.";';
    echo '};';
    echo 'seriesField.addEventListener("change", updateHint);';
    echo 'updateHint();';
    echo '});';
    echo '</script>';
}

/**
 * Renders the issue metabox for access mode and lock configuration.
 *
 * @param WP_Post $post Current issue post being edited.
 * @return void
 */
function dracka_render_issue_access_metabox($post)
{
    wp_nonce_field('dracka_save_issue_access', 'dracka_issue_access_nonce');

    $mode = dracka_get_issue_access_mode($post->ID);
    $book_id = (int) get_post_meta($post->ID, DRACKA_ISSUE_FLIPBOOK_ID_META_KEY, true);
    $patreon_url = (string) get_post_meta($post->ID, DRACKA_ISSUE_PATREON_URL_META_KEY, true);
    $lock_image_id = (int) get_post_meta($post->ID, DRACKA_ISSUE_PATREON_IMAGE_META_KEY, true);
    $lock_image_preview = $lock_image_id > 0
        ? wp_get_attachment_image($lock_image_id, 'medium', false, ['style' => 'display:block;width:100%;height:auto;border-radius:3px'])
        : '';
    $books = dracka_get_dearflip_book_options();

    echo '<p style="margin-top:0">';
    echo '<label for="dracka_issue_access_mode" style="display:block;margin-bottom:4px"><strong>Access Mode</strong></label>';
    echo '<select id="dracka_issue_access_mode" name="dracka_issue_access_mode" style="width:100%">';
    echo '<option value="flipbook"' . selected($mode, 'flipbook', false) . '>Flipbook</option>';
    echo '<option value="patreon"' . selected($mode, 'patreon', false) . '>Patreon Lock</option>';
    echo '</select>';
    echo '</p>';

    echo '<div id="dracka_issue_flipbook_fields" style="margin:12px 0">';
    echo '<label for="dracka_issue_flipbook_id" style="display:block;margin-bottom:4px"><strong>DearFlip Book</strong></label>';
    echo '<select id="dracka_issue_flipbook_id" name="dracka_issue_flipbook_id" style="width:100%">';
    echo '<option value="">Select a book</option>';

    if (!empty($books)) {
        foreach ($books as $book) {
            $status_suffix = '';
            if (!empty($book['status']) && $book['status'] !== 'publish') {
                $status_suffix = ' (' . $book['status'] . ')';
            }

            echo '<option value="' . esc_attr($book['id']) . '"' . selected($book_id, (int) $book['id'], false) . '>';
            echo esc_html($book['label'] . $status_suffix);
            echo '</option>';
        }
    }

    echo '</select>';
    if (empty($books)) {
        echo '<p class="description" style="margin-top:6px">No DearFlip books found. Activate DearFlip and create at least one book.</p>';
    } else {
        echo '<p class="description" style="margin-top:6px">Choose an existing DearFlip book to render on this issue page.</p>';
    }
    echo '</div>';

    echo '<div id="dracka_issue_patreon_fields" style="margin:12px 0">';
    echo '<label for="dracka_issue_patreon_url" style="display:block;margin-bottom:4px"><strong>Patreon URL</strong></label>';
    echo '<input type="url" id="dracka_issue_patreon_url" name="dracka_issue_patreon_url" value="' . esc_attr($patreon_url) . '" style="width:100%" placeholder="https://www.patreon.com/your-page">';

    echo '<p style="margin:10px 0 6px"><strong>Lock Image</strong></p>';
    echo '<input type="hidden" id="dracka_issue_patreon_image_id" name="dracka_issue_patreon_image_id" value="' . esc_attr($lock_image_id) . '">';
    echo '<div id="dracka_issue_patreon_image_preview" style="margin-bottom:10px">';
    echo $lock_image_preview ?: '<div style="padding:12px;border:1px dashed #ccd0d4;border-radius:3px;color:#666">No image selected.</div>';
    echo '</div>';
    echo '<p style="display:flex;gap:8px">';
    echo '<button type="button" class="button button-primary" id="dracka_select_issue_patreon_image_btn">Select Image</button>';
    echo '<button type="button" class="button" id="dracka_remove_issue_patreon_image_btn">Remove Image</button>';
    echo '</p>';
    echo '<p class="description">Image shown before the Patreon CTA link.</p>';
    echo '</div>';

    echo '<script type="text/javascript">';
    echo 'document.addEventListener("DOMContentLoaded", function() {';
    echo 'var modeField = document.getElementById("dracka_issue_access_mode");';
    echo 'var flipbookFields = document.getElementById("dracka_issue_flipbook_fields");';
    echo 'var patreonFields = document.getElementById("dracka_issue_patreon_fields");';
    echo 'var imageInput = document.getElementById("dracka_issue_patreon_image_id");';
    echo 'var imagePreview = document.getElementById("dracka_issue_patreon_image_preview");';
    echo 'var selectImageBtn = document.getElementById("dracka_select_issue_patreon_image_btn");';
    echo 'var removeImageBtn = document.getElementById("dracka_remove_issue_patreon_image_btn");';
    echo 'var frame;';
    echo 'if (!modeField || !flipbookFields || !patreonFields || !imageInput || !imagePreview || !selectImageBtn || !removeImageBtn) {';
    echo 'return;';
    echo '}';

    echo 'function updateModeFields() {';
    echo 'var isPatreon = modeField.value === "patreon";';
    echo 'patreonFields.style.display = isPatreon ? "block" : "none";';
    echo 'flipbookFields.style.display = isPatreon ? "none" : "block";';
    echo '}';

    echo 'modeField.addEventListener("change", updateModeFields);';
    echo 'updateModeFields();';

    echo 'selectImageBtn.addEventListener("click", function(e) {';
    echo 'e.preventDefault();';
    echo 'if (frame) { frame.open(); return; }';
    echo 'frame = wp.media({ title: "Select Lock Image", library: { type: "image" }, button: { text: "Use this image" }, multiple: false });';
    echo 'frame.on("select", function() {';
    echo 'var attachment = frame.state().get("selection").first().toJSON();';
    echo 'if (!attachment || attachment.type !== "image") { alert("Please select an image file."); return; }';
    echo 'imageInput.value = String(attachment.id);';
    echo 'var src = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;';
    echo 'imagePreview.innerHTML = "<img src=\\"" + src + "\\" alt=\\"Patreon lock image\\" style=\\"display:block;width:100%;height:auto;border-radius:3px\\">";';
    echo '});';
    echo 'frame.open();';
    echo '});';

    echo 'removeImageBtn.addEventListener("click", function(e) {';
    echo 'e.preventDefault();';
    echo 'imageInput.value = "";';
    echo 'imagePreview.innerHTML = "<div style=\\"padding:12px;border:1px dashed #ccd0d4;border-radius:3px;color:#666\\">No image selected.</div>";';
    echo '});';
    echo '});';
    echo '</script>';
}

/**
 * Renders the logo animation metabox with SVG + WEBP controls.
 *
 * @param WP_Post $post Current logo animation post being edited.
 * @return void
 */
function dracka_render_logo_animation_metabox($post)
{
    wp_nonce_field('dracka_save_logo_animation', 'dracka_logo_animation_nonce');

    $svg_attachment_id = dracka_get_logo_source_attachment_id($post->ID);
    $webp_attachment_ids = dracka_get_logo_animation_attachment_ids($post->ID, DRACKA_LOGO_WEBP_META_KEY);
    $is_active = (bool) get_post_meta($post->ID, DRACKA_LOGO_ACTIVE_META_KEY, true);

    $svg_attachment = $svg_attachment_id > 0 ? get_post($svg_attachment_id) : null;
    $svg_label = $svg_attachment ? ($svg_attachment->post_title ?: basename((string) get_attached_file($svg_attachment_id))) : 'No SVG selected';

    $webp_labels = [];
    foreach ($webp_attachment_ids as $attachment_id) {
        $attachment = get_post($attachment_id);
        if (!$attachment) {
            continue;
        }

        $webp_labels[] = $attachment->post_title ?: basename((string) get_attached_file($attachment_id));
    }

    echo '<p><strong>Active Logo (SVG or PNG)</strong></p>';
    echo '<input type="hidden" id="dracka_logo_svg_id" name="dracka_logo_svg_id" value="' . esc_attr($svg_attachment_id) . '">';
    echo '<p id="dracka_logo_svg_label" style="margin:0 0 10px;color:#666">' . esc_html($svg_label) . '</p>';
    echo '<p style="display:flex;gap:8px">';
    echo '<button type="button" class="button button-primary" id="dracka_select_logo_svg_btn">Select Logo File</button>';
    echo '<button type="button" class="button" id="dracka_remove_logo_svg_btn">Remove SVG</button>';
    echo '</p>';

    echo '<hr>';

    echo '<p><strong>Animations (WEBP Pool)</strong></p>';
    echo '<input type="hidden" id="dracka_logo_webp_ids" name="dracka_logo_webp_ids" value="' . esc_attr(implode(',', $webp_attachment_ids)) . '">';
    echo '<p id="dracka_logo_webp_label" style="margin:0 0 10px;color:#666">';
    echo !empty($webp_labels)
        ? esc_html(sprintf('%d selected: %s', count($webp_labels), implode(', ', $webp_labels)))
        : 'No WEBP animations selected';
    echo '</p>';
    echo '<p style="display:flex;gap:8px">';
    echo '<button type="button" class="button button-primary" id="dracka_select_logo_webp_btn">Select WEBPs</button>';
    echo '<button type="button" class="button" id="dracka_remove_logo_webp_btn">Clear WEBPs</button>';
    echo '</p>';

    echo '<hr>';

    echo '<label style="display:inline-flex;align-items:center;gap:8px">';
    echo '<input type="checkbox" name="dracka_logo_is_active" value="1"' . checked($is_active, true, false) . '>';
    echo '<span>Is Active Logo</span>';
    echo '</label>';
    echo '<p class="description" style="margin-top:8px">Saving this as active will automatically deactivate all other logo animations.</p>';

    echo '<script type="text/javascript">';
    echo 'document.addEventListener("DOMContentLoaded", function() {';
    echo 'var svgInput = document.getElementById("dracka_logo_svg_id");';
    echo 'var svgLabel = document.getElementById("dracka_logo_svg_label");';
    echo 'var webpInput = document.getElementById("dracka_logo_webp_ids");';
    echo 'var webpLabel = document.getElementById("dracka_logo_webp_label");';
    echo 'var svgFrame;';
    echo 'var webpFrame;';

    echo 'function updateWebpLabel(selection) {';
    echo 'if (!selection || selection.length === 0) {';
    echo 'webpLabel.textContent = "No WEBP animations selected";';
    echo 'return;';
    echo '}';
    echo 'var names = selection.map(function(item) { return item.filename || item.title || ("#" + item.id); });';
    echo 'webpLabel.textContent = selection.length + " selected: " + names.join(", ");';
    echo '}';

    echo 'var svgSelectBtn = document.getElementById("dracka_select_logo_svg_btn");';
    echo 'if (svgSelectBtn) {';
    echo 'svgSelectBtn.addEventListener("click", function(e) {';
    echo 'e.preventDefault();';
    echo 'if (svgFrame) { svgFrame.open(); return; }';
    echo 'svgFrame = wp.media({ title: "Select Logo File", library: { type: "image" }, button: { text: "Use this file" }, multiple: false });';
    echo 'svgFrame.on("select", function() {';
    echo 'var attachment = svgFrame.state().get("selection").first().toJSON();';
    echo 'if (attachment.mime !== "image/svg+xml" && attachment.mime !== "image/png") { alert("Please select an SVG or PNG file."); return; }';
    echo 'svgInput.value = attachment.id;';
    echo 'svgLabel.textContent = attachment.filename || attachment.title || ("#" + attachment.id);';
    echo '});';
    echo 'svgFrame.open();';
    echo '});';
    echo '}';

    echo 'var svgRemoveBtn = document.getElementById("dracka_remove_logo_svg_btn");';
    echo 'if (svgRemoveBtn) {';
    echo 'svgRemoveBtn.addEventListener("click", function(e) {';
    echo 'e.preventDefault();';
    echo 'svgInput.value = "";';
    echo 'svgLabel.textContent = "No SVG selected";';
    echo '});';
    echo '}';

    echo 'var webpSelectBtn = document.getElementById("dracka_select_logo_webp_btn");';
    echo 'if (webpSelectBtn) {';
    echo 'webpSelectBtn.addEventListener("click", function(e) {';
    echo 'e.preventDefault();';
    echo 'if (webpFrame) { webpFrame.open(); return; }';
    echo 'webpFrame = wp.media({ title: "Select WEBP Animations", library: { type: "image" }, button: { text: "Use selected WEBPs" }, multiple: true });';
    echo 'webpFrame.on("select", function() {';
    echo 'var selection = webpFrame.state().get("selection").toJSON();';
    echo 'var valid = selection.filter(function(item) { return item.mime === "image/webp"; });';
    echo 'var ids = valid.map(function(item) { return item.id; });';
    echo 'webpInput.value = ids.join(",");';
    echo 'updateWebpLabel(valid);';
    echo 'if (selection.length !== valid.length) { alert("Only WEBP files were kept."); }';
    echo '});';
    echo 'webpFrame.open();';
    echo '});';
    echo '}';

    echo 'var webpRemoveBtn = document.getElementById("dracka_remove_logo_webp_btn");';
    echo 'if (webpRemoveBtn) {';
    echo 'webpRemoveBtn.addEventListener("click", function(e) {';
    echo 'e.preventDefault();';
    echo 'webpInput.value = "";';
    echo 'updateWebpLabel([]);';
    echo '});';
    echo '}';
    echo '});';
    echo '</script>';
}

/**
 * Renders ticker settings metabox with active toggle.
 *
 * @param WP_Post $post Current ticker post being edited.
 * @return void
 */
function dracka_render_ticker_metabox($post)
{
    wp_nonce_field('dracka_save_ticker_settings', 'dracka_ticker_settings_nonce');

    $is_active = get_post_meta($post->ID, DRACKA_TICKER_ACTIVE_META_KEY, true) === '1';

    echo '<label style="display:inline-flex;align-items:center;gap:8px">';
    echo '<input type="checkbox" name="dracka_ticker_is_active" value="1"' . checked($is_active, true, false) . '>';
    echo '<span>Show this ticker item</span>';
    echo '</label>';
    echo '<p class="description" style="margin-top:8px">Only active and published ticker items are shown in the News Ticker block.</p>';
}

/**
 * Renders the artwork metabox that links artwork to an album.
 *
 * It prints a nonce, loads the currently linked album, fetches all
 * published albums ordered by title, and renders a dropdown for
 * selecting the parent album (or standalone state).
 *
 * @param WP_Post $post Current artwork post being edited.
 * @return void
 */
function dracka_render_album_metabox($post)
{
    wp_nonce_field('dracka_save_album_link', 'dracka_album_nonce');

    $current_album = (int) get_post_meta($post->ID, 'dracka_album_id', true);
    $relation_posts_limit = dracka_get_admin_relation_posts_limit();
    $album_posts = get_posts([
        'post_type'      => 'album',
        'post_status'    => 'publish',
        'posts_per_page' => $relation_posts_limit,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'no_found_rows'  => true,
    ]);

    echo '<select name="dracka_album_id" style="width:100%">';
    echo '<option value="">No album (standalone)</option>';
    foreach ($album_posts as $album) {
        $selected = $current_album === (int) $album->ID ? ' selected' : '';
        echo '<option value="' . esc_attr($album->ID) . '"' . $selected . '>' . esc_html($album->post_title) . '</option>';
    }
    echo '</select>';
}

/**
 * Persists relationship metadata submitted from issue/artwork edit forms.
 *
 * The handler exits on autosave, validates post-type-specific nonces and
 * permissions, sanitizes incoming IDs/order values, then updates or
 * removes metadata keys so links remain explicit and clean.
 *
 * @param int $post_id Post ID being saved.
 * @return void
 */
function dracka_save_relationship_meta($post_id)
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
    $post_type = isset($_POST['post_type']) ? sanitize_key(wp_unslash($_POST['post_type'])) : '';

    if ($post_type === 'issue') {
        if (!current_user_can('edit_post', $post_id)) return;

        // Save series link and issue number (if nonce is valid)
        if (isset($_POST['dracka_series_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['dracka_series_nonce'])), 'dracka_save_series_link')) {
            $series_id = isset($_POST['dracka_series_id']) ? (int) $_POST['dracka_series_id'] : 0;
            if ($series_id > 0) {
                update_post_meta($post_id, 'dracka_series_id', $series_id);
            } else {
                delete_post_meta($post_id, 'dracka_series_id');
            }

            $order_raw = isset($_POST['dracka_series_order']) ? trim(wp_unslash($_POST['dracka_series_order'])) : '';
            if ($order_raw !== '') {
                update_post_meta($post_id, DRACKA_ISSUE_NUMBER_META_KEY, max(1, (int) $order_raw));
            } else {
                delete_post_meta($post_id, DRACKA_ISSUE_NUMBER_META_KEY);
            }
        }

        // Save issue access mode and related fields.
        if (isset($_POST['dracka_issue_access_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['dracka_issue_access_nonce'])), 'dracka_save_issue_access')) {
            $mode = isset($_POST['dracka_issue_access_mode'])
                ? sanitize_key(wp_unslash($_POST['dracka_issue_access_mode']))
                : 'flipbook';

            if (!in_array($mode, ['flipbook', 'patreon'], true)) {
                $mode = 'flipbook';
            }

            update_post_meta($post_id, DRACKA_ISSUE_ACCESS_MODE_META_KEY, $mode);

            if ($mode === 'flipbook') {
                $book_id = isset($_POST['dracka_issue_flipbook_id']) ? (int) $_POST['dracka_issue_flipbook_id'] : 0;
                if ($book_id > 0) {
                    update_post_meta($post_id, DRACKA_ISSUE_FLIPBOOK_ID_META_KEY, $book_id);
                } else {
                    delete_post_meta($post_id, DRACKA_ISSUE_FLIPBOOK_ID_META_KEY);
                }

                delete_post_meta($post_id, DRACKA_ISSUE_PATREON_URL_META_KEY);
                delete_post_meta($post_id, DRACKA_ISSUE_PATREON_IMAGE_META_KEY);
            } else {
                $patreon_url = isset($_POST['dracka_issue_patreon_url'])
                    ? esc_url_raw(wp_unslash($_POST['dracka_issue_patreon_url']))
                    : '';
                if ($patreon_url !== '') {
                    update_post_meta($post_id, DRACKA_ISSUE_PATREON_URL_META_KEY, $patreon_url);
                } else {
                    delete_post_meta($post_id, DRACKA_ISSUE_PATREON_URL_META_KEY);
                }

                $lock_image_id = isset($_POST['dracka_issue_patreon_image_id']) ? (int) $_POST['dracka_issue_patreon_image_id'] : 0;
                if ($lock_image_id > 0 && dracka_is_valid_image_attachment($lock_image_id)) {
                    update_post_meta($post_id, DRACKA_ISSUE_PATREON_IMAGE_META_KEY, $lock_image_id);
                } else {
                    delete_post_meta($post_id, DRACKA_ISSUE_PATREON_IMAGE_META_KEY);
                }

                delete_post_meta($post_id, DRACKA_ISSUE_FLIPBOOK_ID_META_KEY);
            }
        }
    }

    if ($post_type === 'artwork') {
        if (!isset($_POST['dracka_album_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['dracka_album_nonce'])), 'dracka_save_album_link')) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $album_id = isset($_POST['dracka_album_id']) ? (int) $_POST['dracka_album_id'] : 0;
        if ($album_id > 0) {
            update_post_meta($post_id, 'dracka_album_id', $album_id);
        } else {
            delete_post_meta($post_id, 'dracka_album_id');
        }
    }

    if ($post_type === 'album') {
        if (!isset($_POST['dracka_album_details_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['dracka_album_details_nonce'])), 'dracka_save_album_details')) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $rating_raw = isset($_POST['dracka_album_rating']) ? sanitize_key(wp_unslash($_POST['dracka_album_rating'])) : 'everyone';
        if (!in_array($rating_raw, ['everyone', '16', '18'], true)) {
            $rating_raw = 'everyone';
        }

        update_post_meta($post_id, DRACKA_ALBUM_RATING_META_KEY, $rating_raw);

        if (in_array($rating_raw, ['16', '18'], true)) {
            $gate_title = isset($_POST['dracka_album_gate_title']) ? sanitize_text_field(wp_unslash($_POST['dracka_album_gate_title'])) : '';
            $gate_body  = isset($_POST['dracka_album_gate_body'])  ? sanitize_textarea_field(wp_unslash($_POST['dracka_album_gate_body'])) : '';

            if ($gate_title !== '') {
                update_post_meta($post_id, DRACKA_ALBUM_GATE_TITLE_META_KEY, $gate_title);
            } else {
                delete_post_meta($post_id, DRACKA_ALBUM_GATE_TITLE_META_KEY);
            }

            if ($gate_body !== '') {
                update_post_meta($post_id, DRACKA_ALBUM_GATE_BODY_META_KEY, $gate_body);
            } else {
                delete_post_meta($post_id, DRACKA_ALBUM_GATE_BODY_META_KEY);
            }
        } else {
            delete_post_meta($post_id, DRACKA_ALBUM_GATE_TITLE_META_KEY);
            delete_post_meta($post_id, DRACKA_ALBUM_GATE_BODY_META_KEY);
        }
    }

    if ($post_type === 'logo_animation') {
        if (!isset($_POST['dracka_logo_animation_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['dracka_logo_animation_nonce'])), 'dracka_save_logo_animation')) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $svg_attachment_id = isset($_POST['dracka_logo_svg_id']) ? (int) $_POST['dracka_logo_svg_id'] : 0;
        if ($svg_attachment_id > 0 && dracka_is_valid_logo_source($svg_attachment_id)) {
            dracka_set_logo_source_attachment_id($post_id, $svg_attachment_id);
        } else {
            dracka_set_logo_source_attachment_id($post_id, 0);
        }

        $raw_webp_ids = isset($_POST['dracka_logo_webp_ids']) ? sanitize_text_field(wp_unslash($_POST['dracka_logo_webp_ids'])) : '';
        $webp_ids = dracka_parse_attachment_ids_csv($raw_webp_ids);
        $valid_webp_ids = [];

        foreach ($webp_ids as $attachment_id) {
            if (dracka_is_valid_logo_webp($attachment_id)) {
                $valid_webp_ids[] = $attachment_id;
            }
        }

        $valid_webp_ids = array_values(array_unique($valid_webp_ids));

        if (!empty($valid_webp_ids)) {
            update_post_meta($post_id, DRACKA_LOGO_WEBP_META_KEY, $valid_webp_ids);
        } else {
            delete_post_meta($post_id, DRACKA_LOGO_WEBP_META_KEY);
        }

        $is_active = isset($_POST['dracka_logo_is_active']) ? '1' : '';

        if ($is_active === '1') {
            dracka_deactivate_other_logo_animation_posts($post_id);
            update_post_meta($post_id, DRACKA_LOGO_ACTIVE_META_KEY, '1');
        } else {
            delete_post_meta($post_id, DRACKA_LOGO_ACTIVE_META_KEY);
        }
    }

    if ($post_type === 'series') {
        if (!current_user_can('edit_post', $post_id)) return;

        if (isset($_POST['dracka_series_splash_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['dracka_series_splash_nonce'])), 'dracka_save_series_splash')) {
            $attachment_id = isset($_POST['dracka_series_splash_id']) ? (int) $_POST['dracka_series_splash_id'] : 0;
            if ($attachment_id > 0 && dracka_is_valid_image_attachment($attachment_id)) {
                update_post_meta($post_id, DRACKA_SERIES_SPLASH_META_KEY, $attachment_id);
            } else {
                delete_post_meta($post_id, DRACKA_SERIES_SPLASH_META_KEY);
            }
        }

        if (isset($_POST['dracka_series_details_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['dracka_series_details_nonce'])), 'dracka_save_series_details')) {
            $author      = isset($_POST['dracka_series_author'])      ? sanitize_text_field(wp_unslash($_POST['dracka_series_author']))      : '';
            $description = isset($_POST['dracka_series_description']) ? sanitize_textarea_field(wp_unslash($_POST['dracka_series_description'])) : '';
            $year_raw    = isset($_POST['dracka_publication_year'])   ? trim(wp_unslash($_POST['dracka_publication_year']))                  : '';

            $rating_raw = isset($_POST['dracka_series_rating']) ? sanitize_key(wp_unslash($_POST['dracka_series_rating'])) : 'everyone';
            if (!in_array($rating_raw, ['everyone', '16', '18'], true)) {
                $rating_raw = 'everyone';
            }

            // Story status + standalone flag.
            $is_standalone = isset($_POST['dracka_series_is_standalone']) ? '1' : '';
            $story_status_raw = isset($_POST['dracka_series_story_status']) ? sanitize_key(wp_unslash($_POST['dracka_series_story_status'])) : 'ongoing';
            if ($is_standalone === '1') {
                $story_status_raw = 'standalone';
            } elseif (!array_key_exists($story_status_raw, dracka_get_series_custom_statuses()) || $story_status_raw === 'standalone') {
                $story_status_raw = 'ongoing';
            }
            update_post_meta($post_id, DRACKA_SERIES_STORY_STATUS_META_KEY, $story_status_raw);

            if ($is_standalone === '1') {
                update_post_meta($post_id, DRACKA_SERIES_IS_STANDALONE_META_KEY, '1');
                $standalone_book_id = isset($_POST['dracka_series_standalone_flipbook_id']) ? (int) $_POST['dracka_series_standalone_flipbook_id'] : 0;
                if ($standalone_book_id > 0) {
                    update_post_meta($post_id, DRACKA_SERIES_STANDALONE_FLIPBOOK_META_KEY, $standalone_book_id);
                } else {
                    delete_post_meta($post_id, DRACKA_SERIES_STANDALONE_FLIPBOOK_META_KEY);
                }
            } else {
                delete_post_meta($post_id, DRACKA_SERIES_IS_STANDALONE_META_KEY);
                delete_post_meta($post_id, DRACKA_SERIES_STANDALONE_FLIPBOOK_META_KEY);
            }

            if ($author !== '') {
                update_post_meta($post_id, DRACKA_SERIES_AUTHOR_META_KEY, $author);
            } else {
                delete_post_meta($post_id, DRACKA_SERIES_AUTHOR_META_KEY);
            }

            if ($description !== '') {
                update_post_meta($post_id, DRACKA_SERIES_DESCRIPTION_META_KEY, $description);
            } else {
                delete_post_meta($post_id, DRACKA_SERIES_DESCRIPTION_META_KEY);
            }

            if (preg_match('/^\d{4}$/', $year_raw) === 1) {
                update_post_meta($post_id, DRACKA_SERIES_YEAR_META_KEY, $year_raw);
            } else {
                delete_post_meta($post_id, DRACKA_SERIES_YEAR_META_KEY);
            }

            update_post_meta($post_id, DRACKA_SERIES_RATING_META_KEY, $rating_raw);

            if (in_array($rating_raw, ['16', '18'], true)) {
                $gate_title = isset($_POST['dracka_series_gate_title']) ? sanitize_text_field(wp_unslash($_POST['dracka_series_gate_title'])) : '';
                $gate_body  = isset($_POST['dracka_series_gate_body'])  ? sanitize_textarea_field(wp_unslash($_POST['dracka_series_gate_body'])) : '';

                if ($gate_title !== '') {
                    update_post_meta($post_id, DRACKA_SERIES_GATE_TITLE_META_KEY, $gate_title);
                } else {
                    delete_post_meta($post_id, DRACKA_SERIES_GATE_TITLE_META_KEY);
                }

                if ($gate_body !== '') {
                    update_post_meta($post_id, DRACKA_SERIES_GATE_BODY_META_KEY, $gate_body);
                } else {
                    delete_post_meta($post_id, DRACKA_SERIES_GATE_BODY_META_KEY);
                }
            } else {
                delete_post_meta($post_id, DRACKA_SERIES_GATE_TITLE_META_KEY);
                delete_post_meta($post_id, DRACKA_SERIES_GATE_BODY_META_KEY);
            }
        }

        // Quick-edit save for series story status and standalone flag.
        $valid_quick_edit = isset($_POST['dracka_series_quick_edit_nonce'])
            && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['dracka_series_quick_edit_nonce'])), 'dracka_quick_edit_series');

        if ($valid_quick_edit) {
            $qe_is_standalone = isset($_POST['dracka_series_is_standalone']) ? '1' : '';
            $qe_story_status  = isset($_POST['dracka_series_story_status']) ? sanitize_key(wp_unslash($_POST['dracka_series_story_status'])) : 'ongoing';
            if ($qe_is_standalone === '1') {
                $qe_story_status = 'standalone';
            } elseif (!array_key_exists($qe_story_status, dracka_get_series_custom_statuses()) || $qe_story_status === 'standalone') {
                $qe_story_status = 'ongoing';
            }
            update_post_meta($post_id, DRACKA_SERIES_STORY_STATUS_META_KEY, $qe_story_status);
            if ($qe_is_standalone === '1') {
                update_post_meta($post_id, DRACKA_SERIES_IS_STANDALONE_META_KEY, '1');
            } else {
                delete_post_meta($post_id, DRACKA_SERIES_IS_STANDALONE_META_KEY);
                delete_post_meta($post_id, DRACKA_SERIES_STANDALONE_FLIPBOOK_META_KEY);
            }
        }
    }

    if ($post_type === 'ticker') {
        $valid_settings_nonce = isset($_POST['dracka_ticker_settings_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['dracka_ticker_settings_nonce'])), 'dracka_save_ticker_settings');
        $valid_quick_edit_nonce = isset($_POST['_inline_edit']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_inline_edit'])), 'inlineeditnonce');

        if (!$valid_settings_nonce && !$valid_quick_edit_nonce) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $is_active = isset($_POST['dracka_ticker_is_active']) ? '1' : '';

        if ($is_active === '1') {
            update_post_meta($post_id, DRACKA_TICKER_ACTIVE_META_KEY, '1');
        } else {
            delete_post_meta($post_id, DRACKA_TICKER_ACTIVE_META_KEY);
        }
    }
}
add_action('save_post', 'dracka_save_relationship_meta');

/**
 * Enforces mandatory, unique issue numbers for series-linked issues.
 *
 * @param array<string, mixed> $data Prepared post data.
 * @param array<string, mixed> $postarr Raw submitted post data.
 * @return array<string, mixed>
 */
function dracka_enforce_series_issue_number_rules($data, $postarr)
{
    if (($data['post_type'] ?? '') !== 'issue') {
        return $data;
    }

    // Enforce on publish-like states so drafts can be prepared incrementally.
    $blocking_statuses = ['publish', 'future', 'private', 'pending'];
    if (!in_array($data['post_status'] ?? '', $blocking_statuses, true)) {
        return $data;
    }

    $post_id = isset($postarr['ID']) ? (int) $postarr['ID'] : 0;
    $has_series_form_payload = isset($_POST['dracka_series_nonce'])
        && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['dracka_series_nonce'])), 'dracka_save_series_link');
    $has_access_form_payload = isset($_POST['dracka_issue_access_nonce'])
        && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['dracka_issue_access_nonce'])), 'dracka_save_issue_access');

    $series_id = $has_series_form_payload
        ? (isset($_POST['dracka_series_id']) ? (int) $_POST['dracka_series_id'] : 0)
        : (int) get_post_meta($post_id, 'dracka_series_id', true);

    if ($series_id <= 0) {
        $data['post_status'] = 'draft';
        dracka_set_issue_number_validation_notice(
            $post_id,
            'Issues must be linked to a published series before they can be published.'
        );

        return $data;
    }

    $series_post = get_post($series_id);

    if (
        !($series_post instanceof WP_Post)
        || $series_post->post_type !== 'series'
        || $series_post->post_status !== 'publish'
    ) {
        $data['post_status'] = 'draft';
        dracka_set_issue_number_validation_notice(
            $post_id,
            'This issue cannot be published until its parent series is published.'
        );

        return $data;
    }

    // Issues require a series issue number.
    $issue_number_raw = $has_series_form_payload
        ? (isset($_POST['dracka_series_order']) ? trim(wp_unslash($_POST['dracka_series_order'])) : '')
        : trim((string) get_post_meta($post_id, DRACKA_ISSUE_NUMBER_META_KEY, true));
    $next_available_number = dracka_get_next_available_series_issue_number($series_id, $post_id);

    if ($issue_number_raw === '' || !preg_match('/^\d+$/', $issue_number_raw) || (int) $issue_number_raw <= 0) {
        $data['post_status'] = 'draft';
        dracka_set_issue_number_validation_notice(
            $post_id,
            sprintf(
                'Issue number is required for series-linked issues. Next available number: %d.',
                $next_available_number
            )
        );

        return $data;
    }

    $issue_number = (int) $issue_number_raw;
    if (dracka_is_series_issue_number_taken($series_id, $issue_number, $post_id)) {
        $data['post_status'] = 'draft';
        dracka_set_issue_number_validation_notice(
            $post_id,
            sprintf(
                'Issue number %1$d is already in use for this series. Next available number: %2$d.',
                $issue_number,
                $next_available_number
            )
        );

        return $data;
    }

    $access_mode = 'flipbook';
    if ($has_access_form_payload && isset($_POST['dracka_issue_access_mode'])) {
        $access_mode = sanitize_key(wp_unslash($_POST['dracka_issue_access_mode']));
    } else {
        $access_mode = dracka_get_issue_access_mode($post_id);
    }

    if (!in_array($access_mode, ['flipbook', 'patreon'], true)) {
        $access_mode = 'flipbook';
    }

    if ($access_mode === 'patreon') {
        $patreon_url = $has_access_form_payload
            ? (isset($_POST['dracka_issue_patreon_url']) ? trim(esc_url_raw(wp_unslash($_POST['dracka_issue_patreon_url']))) : '')
            : trim((string) get_post_meta($post_id, DRACKA_ISSUE_PATREON_URL_META_KEY, true));

        $lock_image_id = $has_access_form_payload
            ? (isset($_POST['dracka_issue_patreon_image_id']) ? (int) $_POST['dracka_issue_patreon_image_id'] : 0)
            : (int) get_post_meta($post_id, DRACKA_ISSUE_PATREON_IMAGE_META_KEY, true);

        if ($patreon_url === '' || !wp_http_validate_url($patreon_url)) {
            $data['post_status'] = 'draft';
            dracka_set_issue_number_validation_notice(
                $post_id,
                'Patreon access mode requires a valid Patreon URL before this issue can be published.'
            );

            return $data;
        }

        if ($lock_image_id <= 0 || !dracka_is_valid_image_attachment($lock_image_id)) {
            $data['post_status'] = 'draft';
            dracka_set_issue_number_validation_notice(
                $post_id,
                'Patreon access mode requires a lock image before this issue can be published.'
            );

            return $data;
        }
    } else {
        $flipbook_id = $has_access_form_payload
            ? (isset($_POST['dracka_issue_flipbook_id']) ? (int) $_POST['dracka_issue_flipbook_id'] : 0)
            : (int) get_post_meta($post_id, DRACKA_ISSUE_FLIPBOOK_ID_META_KEY, true);

        if ($flipbook_id <= 0) {
            $data['post_status'] = 'draft';
            dracka_set_issue_number_validation_notice(
                $post_id,
                'Flipbook access mode requires selecting a DearFlip book before this issue can be published.'
            );
        }
    }

    return $data;
}
add_filter('wp_insert_post_data', 'dracka_enforce_series_issue_number_rules', 20, 2);

/**
 * Renders one-time admin notices for issue number validation failures.
 *
 * @return void
 */
function dracka_render_issue_number_validation_notice()
{
    $user_id = get_current_user_id();
    if ($user_id <= 0) {
        return;
    }

    $transient_key = 'dracka_issue_number_notice_' . $user_id;
    $notice = get_transient($transient_key);
    if (!is_array($notice) || empty($notice['message'])) {
        return;
    }

    delete_transient($transient_key);

    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html((string) $notice['message']) . '</p></div>';
}
add_action('admin_notices', 'dracka_render_issue_number_validation_notice');

/**
 * Applies stable issue-number sorting for series issue listings.
 *
 * Numbered issues are sorted first, then unnumbered legacy issues are pushed
 * to the end and ordered by publication date.
 *
 * @param array<string, string> $clauses SQL clauses for the query.
 * @param WP_Query              $query Query being prepared.
 * @return array<string, string>
 */
function dracka_sort_series_issues_by_issue_number($clauses, $query)
{
    if (!($query instanceof WP_Query) || !$query->get('dracka_sort_by_issue_number')) {
        return $clauses;
    }

    global $wpdb;

    $direction = strtoupper((string) $query->get('dracka_issue_number_direction'));
    $direction = $direction === 'DESC' ? 'DESC' : 'ASC';

    $meta_key = DRACKA_ISSUE_NUMBER_META_KEY;
    $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS dracka_issue_number_pm ON ({$wpdb->posts}.ID = dracka_issue_number_pm.post_id AND dracka_issue_number_pm.meta_key = '" . esc_sql($meta_key) . "')";
    $clauses['orderby'] = "CASE WHEN dracka_issue_number_pm.meta_value IS NULL OR dracka_issue_number_pm.meta_value = '' THEN 1 ELSE 0 END ASC, CAST(dracka_issue_number_pm.meta_value AS UNSIGNED) {$direction}, {$wpdb->posts}.post_date {$direction}";

    return $clauses;
}
add_filter('posts_clauses', 'dracka_sort_series_issues_by_issue_number', 10, 2);

/**
 * Validates that an attachment is an image.
 *
 * @param int $attachment_id Attachment ID.
 * @return bool
 */
function dracka_is_valid_image_attachment($attachment_id)
{
    if ($attachment_id <= 0) {
        return false;
    }

    $mime_type = get_post_mime_type($attachment_id);

    return is_string($mime_type) && strpos($mime_type, 'image/') === 0;
}

/**
 * Parses a comma-separated attachment ID list into normalized integers.
 *
 * @param string $raw_ids Comma-separated attachment IDs.
 * @return array
 */
function dracka_parse_attachment_ids_csv($raw_ids)
{
    $parts = array_filter(array_map('trim', explode(',', (string) $raw_ids)));

    $ids = [];
    foreach ($parts as $part) {
        if (!ctype_digit($part)) {
            continue;
        }

        $ids[] = (int) $part;
    }

    return array_values(array_filter($ids, static function ($id) {
        return $id > 0;
    }));
}

/**
 * Reads and normalizes stored attachment ID arrays from post meta.
 *
 * @param int    $post_id Post ID.
 * @param string $meta_key Meta key that stores IDs.
 * @return array
 */
function dracka_get_logo_animation_attachment_ids($post_id, $meta_key)
{
    $raw_value = get_post_meta((int) $post_id, $meta_key, true);

    if (is_array($raw_value)) {
        $ids = array_map('intval', $raw_value);
    } else {
        $ids = dracka_parse_attachment_ids_csv((string) $raw_value);
    }

    $ids = array_values(array_filter($ids, static function ($id) {
        return $id > 0;
    }));

    return array_values(array_unique($ids));
}

/**
 * Validates that an attachment is a supported active logo source.
 *
 * @param int $attachment_id Attachment ID.
 * @return bool
 */
function dracka_is_valid_logo_source($attachment_id)
{
    if (!$attachment_id) {
        return false;
    }

    $mime_type = get_post_mime_type($attachment_id);

    return in_array($mime_type, ['image/svg+xml', 'image/png'], true);
}

/**
 * Validates that an attachment is a WEBP animation source.
 *
 * @param int $attachment_id Attachment ID.
 * @return bool
 */
function dracka_is_valid_logo_webp($attachment_id)
{
    if (!$attachment_id) {
        return false;
    }

    return get_post_mime_type($attachment_id) === 'image/webp';
}

/**
 * Ensures only one logo animation post remains active.
 *
 * This function is called before setting the current post active so the active
 * flag remains exclusive across all logo animation posts.
 *
 * @param int $active_post_id Active logo post ID.
 * @return void
 */
function dracka_deactivate_other_logo_animation_posts($active_post_id)
{
    $active_post_id = (int) $active_post_id;
    if ($active_post_id <= 0) {
        return;
    }

    $other_active_posts = get_posts([
        'post_type'      => 'logo_animation',
        'post_status'    => ['publish', 'future', 'draft', 'pending', 'private'],
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'post__not_in'   => [$active_post_id],
        'meta_query'     => [
            [
                'key'   => DRACKA_LOGO_ACTIVE_META_KEY,
                'value' => '1',
            ],
        ],
    ]);

    foreach ($other_active_posts as $post_id) {
        delete_post_meta((int) $post_id, DRACKA_LOGO_ACTIVE_META_KEY);
    }
}

/**
 * Resolves the currently active logo animation post ID.
 *
 * @return int
 */
function dracka_get_active_logo_animation_post_id()
{
    $active_posts = get_posts([
        'post_type'      => 'logo_animation',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'   => DRACKA_LOGO_ACTIVE_META_KEY,
                'value' => '1',
            ],
        ],
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);

    if (!empty($active_posts)) {
        return (int) $active_posts[0];
    }

    $fallback_posts = get_posts([
        'post_type'      => 'logo_animation',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);

    return !empty($fallback_posts) ? (int) $fallback_posts[0] : 0;
}

/**
 * Returns logo animation payload for frontend header rendering.
 *
 * @return array
 */
function dracka_get_active_logo_animation_data()
{
    $post_id = dracka_get_active_logo_animation_post_id();
    if ($post_id <= 0) {
        return [
            'svg_url'        => '',
            'animation_urls' => [],
        ];
    }

    $svg_attachment_id = dracka_get_logo_source_attachment_id($post_id);
    $svg_url = '';

    if ($svg_attachment_id > 0 && dracka_is_valid_logo_source($svg_attachment_id)) {
        $svg_url = (string) wp_get_attachment_url($svg_attachment_id);
    }

    $animation_urls = [];
    $animation_ids = dracka_get_logo_animation_attachment_ids($post_id, DRACKA_LOGO_WEBP_META_KEY);

    foreach ($animation_ids as $attachment_id) {
        if (!dracka_is_valid_logo_webp($attachment_id)) {
            continue;
        }

        $url = wp_get_attachment_url($attachment_id);
        if (is_string($url) && $url !== '') {
            $animation_urls[] = $url;
        }
    }

    return [
        'svg_url'        => $svg_url,
        'animation_urls' => array_values(array_unique($animation_urls)),
    ];
}

/**
 * Registers custom columns for Logo Animations admin list.
 *
 * @param array<string, string> $columns Existing list columns.
 * @return array<string, string>
 */
function dracka_logo_animation_admin_columns($columns)
{
    $new_columns = [];

    foreach ($columns as $key => $label) {
        $new_columns[$key] = $label;

        if ($key === 'title') {
            $new_columns['dracka_logo_active'] = 'Active';
            $new_columns['dracka_logo_svg'] = 'Logo File';
            $new_columns['dracka_logo_webp_count'] = 'WEBP Files';
        }
    }

    return $new_columns;
}
add_filter('manage_logo_animation_posts_columns', 'dracka_logo_animation_admin_columns');

/**
 * Renders custom column values for Logo Animations admin list.
 *
 * @param string $column Column key.
 * @param int    $post_id Current post ID.
 * @return void
 */
function dracka_logo_animation_admin_column_content($column, $post_id)
{
    $post_id = (int) $post_id;

    if ($column === 'dracka_logo_active') {
        $is_active = get_post_meta($post_id, DRACKA_LOGO_ACTIVE_META_KEY, true) === '1';
        echo $is_active ? 'Yes' : 'No';
        return;
    }

    if ($column === 'dracka_logo_svg') {
        $svg_attachment_id = dracka_get_logo_source_attachment_id($post_id);

        if ($svg_attachment_id > 0 && dracka_is_valid_logo_source($svg_attachment_id)) {
            $title = get_the_title($svg_attachment_id);
            $url = wp_get_attachment_url($svg_attachment_id);

            if ($url) {
                echo '<a href="' . esc_url($url) . '" target="_blank">' . esc_html($title ?: 'View file') . '</a>';
            } else {
                echo esc_html($title ?: 'Set');
            }
        } else {
            echo 'Missing';
        }

        return;
    }

    if ($column === 'dracka_logo_webp_count') {
        $webp_ids = dracka_get_logo_animation_attachment_ids($post_id, DRACKA_LOGO_WEBP_META_KEY);
        $valid_count = 0;

        foreach ($webp_ids as $attachment_id) {
            if (dracka_is_valid_logo_webp($attachment_id)) {
                $valid_count++;
            }
        }

        echo (string) $valid_count;
    }
}
add_action('manage_logo_animation_posts_custom_column', 'dracka_logo_animation_admin_column_content', 10, 2);

/**
 * Adds custom columns to ticker admin list.
 *
 * @param array<string, string> $columns Existing list columns.
 * @return array<string, string>
 */
function dracka_ticker_admin_columns($columns)
{
    $new_columns = [];

    foreach ($columns as $key => $label) {
        $new_columns[$key] = $label;

        if ($key === 'title') {
            $new_columns['dracka_ticker_active'] = 'Active';
        }
    }

    return $new_columns;
}
add_filter('manage_ticker_posts_columns', 'dracka_ticker_admin_columns');

/**
 * Renders ticker custom column values.
 *
 * @param string $column Column key.
 * @param int    $post_id Current post ID.
 * @return void
 */
function dracka_ticker_admin_column_content($column, $post_id)
{
    $post_id = (int) $post_id;

    if ($column === 'dracka_ticker_active') {
        $is_active = get_post_meta($post_id, DRACKA_TICKER_ACTIVE_META_KEY, true) === '1';
        echo '<span class="dracka-ticker-active-state" data-active="' . esc_attr($is_active ? '1' : '0') . '">' . ($is_active ? 'Yes' : 'No') . '</span>';
    }
}
add_action('manage_ticker_posts_custom_column', 'dracka_ticker_admin_column_content', 10, 2);

/**
 * Adds custom columns to issue admin list.
 *
 * @param array<string, string> $columns Existing list columns.
 * @return array<string, string>
 */
function dracka_issue_admin_columns($columns)
{
    $new_columns = [];

    foreach ($columns as $key => $label) {
        $new_columns[$key] = $label;

        if ($key === 'title') {
            $new_columns['dracka_issue_author'] = 'Author';
            $new_columns['dracka_issue_genre'] = 'Genre';
            $new_columns['dracka_issue_series'] = 'Series';
        }
    }

    return $new_columns;
}
add_filter('manage_issue_posts_columns', 'dracka_issue_admin_columns');

/**
 * Renders custom column values for issue admin list.
 *
 * @param string $column Column key.
 * @param int    $post_id Current post ID.
 * @return void
 */
function dracka_issue_admin_column_content($column, $post_id)
{
    if (!in_array($column, ['dracka_issue_author', 'dracka_issue_genre', 'dracka_issue_series'], true)) {
        return;
    }

    $post_id = (int) $post_id;
    $series_id = (int) get_post_meta($post_id, 'dracka_series_id', true);

    if ($series_id <= 0 || get_post_type($series_id) !== 'series') {
        echo '&mdash;';
        return;
    }

    if ($column === 'dracka_issue_author') {
        $series_author = (string) get_post_meta($series_id, DRACKA_SERIES_AUTHOR_META_KEY, true);
        echo $series_author !== '' ? esc_html($series_author) : '&mdash;';
        return;
    }

    if ($column === 'dracka_issue_genre') {
        $genre_terms = get_the_terms($series_id, 'dracka_series_genre');

        if (is_wp_error($genre_terms) || empty($genre_terms)) {
            echo '&mdash;';
            return;
        }

        $genre_labels = wp_list_pluck($genre_terms, 'name');
        echo esc_html(implode(', ', $genre_labels));
        return;
    }

    if ($column === 'dracka_issue_series') {
        $series_title = get_the_title($series_id);
        $series_edit_link = get_edit_post_link($series_id);

        if ($series_edit_link) {
            echo '<a href="' . esc_url($series_edit_link) . '">' . esc_html($series_title ?: ('#' . $series_id)) . '</a>';
            return;
        }

        echo esc_html($series_title ?: ('#' . $series_id));
    }
}
add_action('manage_issue_posts_custom_column', 'dracka_issue_admin_column_content', 10, 2);

/**
 * Adds quick edit field for ticker active toggle.
 *
 * @param string $column_name Column key.
 * @param string $post_type Current post type.
 * @return void
 */
function dracka_ticker_quick_edit_custom_box($column_name, $post_type)
{
    if ($post_type !== 'ticker' || $column_name !== 'dracka_ticker_active') {
        return;
    }

    echo '<fieldset class="inline-edit-col-right">';
    echo '<div class="inline-edit-col">';
    echo '<label class="alignleft">';
    echo '<input type="checkbox" name="dracka_ticker_is_active" value="1">';
    echo '<span class="checkbox-title">Active ticker item</span>';
    echo '</label>';
    echo '</div>';
    echo '</fieldset>';
}
add_action('quick_edit_custom_box', 'dracka_ticker_quick_edit_custom_box', 10, 2);

/**
 * Syncs quick edit checkbox value from ticker row state.
 *
 * @return void
 */
function dracka_ticker_quick_edit_script()
{
    $screen = get_current_screen();

    if (!$screen || $screen->base !== 'edit' || $screen->post_type !== 'ticker') {
        return;
    }

?>
    <script type="text/javascript">
        (function($) {
            var originalEdit = inlineEditPost.edit;

            inlineEditPost.edit = function(id) {
                originalEdit.apply(this, arguments);

                var postId = 0;
                if (typeof id === 'object') {
                    postId = parseInt(this.getId(id), 10);
                }

                if (!postId) {
                    return;
                }

                var $postRow = $('#post-' + postId);
                var $editRow = $('#edit-' + postId);
                var activeState = String($postRow.find('.column-dracka_ticker_active .dracka-ticker-active-state').data('active'));
                $editRow.find('input[name="dracka_ticker_is_active"]').prop('checked', activeState === '1');
            };
        })(jQuery);
    </script>
<?php
}
add_action('admin_footer-edit.php', 'dracka_ticker_quick_edit_script');

// ---------------------------------------------------------------------------
// Series admin list columns + quick edit
// ---------------------------------------------------------------------------

/**
 * Adds a Story Status column to the Series post list table.
 *
 * @param array<string, string> $columns Existing columns.
 * @return array<string, string>
 */
function dracka_series_admin_columns($columns)
{
    $new = [];
    foreach ($columns as $key => $label) {
        $new[$key] = $label;
        if ($key === 'title') {
            $new['dracka_series_story_status'] = 'Story Status';
        }
    }
    return $new;
}
add_filter('manage_series_posts_columns', 'dracka_series_admin_columns');

/**
 * Renders the Story Status column cell for a series row.
 *
 * Also outputs a hidden span carrying inline data consumed by the quick-edit JS.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 * @return void
 */
function dracka_series_admin_column_content($column, $post_id)
{
    if ($column !== 'dracka_series_story_status') {
        return;
    }

    $story_status  = (string) get_post_meta($post_id, DRACKA_SERIES_STORY_STATUS_META_KEY, true);
    $is_standalone = (string) get_post_meta($post_id, DRACKA_SERIES_IS_STANDALONE_META_KEY, true);
    if ($story_status === '' || !array_key_exists($story_status, dracka_get_series_custom_statuses())) {
        $story_status = 'ongoing';
    }
    $label = dracka_get_series_custom_statuses()[$story_status];

    echo '<span class="series-status-badge series-status-badge--' . esc_attr($story_status) . '">' . esc_html($label) . '</span>';
    // Hidden data spans consumed by the quick-edit JS below.
    echo '<span class="dracka-series-story-status-data" style="display:none" data-story-status="' . esc_attr($story_status) . '" data-is-standalone="' . esc_attr($is_standalone) . '"></span>';
}
add_action('manage_series_posts_custom_column', 'dracka_series_admin_column_content', 10, 2);

/**
 * Renders the quick-edit fields for series Story Status and Is Standalone.
 *
 * @param string $column_name Column key triggering the quick-edit box.
 * @param string $post_type   Current post type.
 * @return void
 */
function dracka_series_quick_edit_custom_box($column_name, $post_type)
{
    if ($post_type !== 'series' || $column_name !== 'dracka_series_story_status') {
        return;
    }

    wp_nonce_field('dracka_quick_edit_series', 'dracka_series_quick_edit_nonce');

    $custom_statuses = dracka_get_series_custom_statuses();

    echo '<fieldset class="inline-edit-col-left">';
    echo '<div class="inline-edit-col">';

    echo '<label style="display:block;margin-bottom:6px"><span class="title">Story Status</span>';
    echo '<select name="dracka_series_story_status" id="dracka_quick_edit_story_status" style="width:100%;max-width:220px">';
    foreach ($custom_statuses as $slug => $label) {
        if ($slug === 'standalone') continue;
        echo '<option value="' . esc_attr($slug) . '">' . esc_html($label) . '</option>';
    }
    echo '<option value="standalone" disabled style="color:#999">Standalone (use full edit)</option>';
    echo '</select>';
    echo '</label>';

    echo '<label style="display:flex;align-items:center;gap:6px;cursor:pointer">';
    echo '<input type="checkbox" name="dracka_series_is_standalone" id="dracka_quick_edit_is_standalone" value="1">';
    echo '<span class="checkbox-title">Is Standalone</span>';
    echo '</label>';

    echo '</div>';
    echo '</fieldset>';
}
add_action('quick_edit_custom_box', 'dracka_series_quick_edit_custom_box', 10, 2);

/**
 * Outputs the JS that pre-populates series quick-edit fields from column data.
 *
 * @return void
 */
function dracka_series_quick_edit_script()
{
    $screen = get_current_screen();

    if (!$screen || $screen->base !== 'edit' || $screen->post_type !== 'series') {
        return;
    }
?>
    <script type="text/javascript">
        (function($) {
            var originalEdit = inlineEditPost.edit;

            inlineEditPost.edit = function(id) {
                originalEdit.apply(this, arguments);

                var postId = 0;
                if (typeof id === 'object') {
                    postId = parseInt(this.getId(id), 10);
                } else {
                    postId = parseInt(id, 10);
                }
                if (!postId) {
                    return;
                }

                var $dataSpan = $('#post-' + postId).find('.dracka-series-story-status-data');
                var storyStatus = $dataSpan.data('story-status') || 'ongoing';
                var isStandalone = String($dataSpan.data('is-standalone')) === '1';

                var $editRow = $('#edit-' + postId);
                var $statusSel = $editRow.find('select[name="dracka_series_story_status"]');
                var $standaloneChk = $editRow.find('input[name="dracka_series_is_standalone"]');

                $statusSel.val(storyStatus);
                $standaloneChk.prop('checked', isStandalone);

                if (isStandalone) {
                    $statusSel.val('standalone').prop('disabled', true);
                } else {
                    $statusSel.prop('disabled', false);
                }

                $standaloneChk.off('change.dracka').on('change.dracka', function() {
                    if ($(this).is(':checked')) {
                        $statusSel.val('standalone').prop('disabled', true);
                    } else {
                        $statusSel.prop('disabled', false);
                        if ($statusSel.val() === 'standalone') {
                            $statusSel.val('ongoing');
                        }
                    }
                });
            };
        })(jQuery);
    </script>
<?php
}
add_action('admin_footer-edit.php', 'dracka_series_quick_edit_script');

/**
 * Removes relationship meta from children linked to a removed parent.
 *
 * It queries all child posts whose linkage key points to the parent ID,
 * then iterates through configured cleanup keys to delete stale meta on
 * each related child post.
 *
 * @param int    $parent_post_id Parent post ID being deleted/trashed.
 * @param string $child_post_type Child post type to scan.
 * @param string $link_meta_key Meta key that stores the parent ID.
 * @param array  $cleanup_meta_keys Meta keys removed from matched children.
 * @return void
 */
function dracka_cleanup_related_meta_links($parent_post_id, $child_post_type, $link_meta_key, $cleanup_meta_keys)
{
    $linked_child_ids = get_posts([
        'post_type'      => $child_post_type,
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'meta_query'     => [
            [
                'key'     => $link_meta_key,
                'value'   => (int) $parent_post_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ],
        ],
    ]);

    foreach ($linked_child_ids as $child_id) {
        foreach ($cleanup_meta_keys as $meta_key) {
            delete_post_meta($child_id, $meta_key);
        }
    }
}

/**
 * Cleans dependent relationship metadata when parent posts are removed.
 *
 * Based on the parent post type, this resolves a cleanup configuration
 * and delegates to the meta cleanup helper so orphaned links are removed
 * from issues/artworks when their series/album is trashed or deleted.
 *
 * @param int $post_id Parent post ID being removed.
 * @return void
 */
function dracka_cleanup_relationships_on_post_removal($post_id)
{
    $relationships = [
        'series' => [
            'child_post_type' => 'issue',
            'link_meta_key'   => 'dracka_series_id',
            'cleanup_keys'    => ['dracka_series_id', 'dracka_series_order'],
        ],
        'album'  => [
            'child_post_type' => 'artwork',
            'link_meta_key'   => 'dracka_album_id',
            'cleanup_keys'    => ['dracka_album_id'],
        ],
    ];

    $post_type = get_post_type($post_id);
    if (!$post_type || !isset($relationships[$post_type])) return;

    $config = $relationships[$post_type];

    dracka_cleanup_related_meta_links(
        $post_id,
        $config['child_post_type'],
        $config['link_meta_key'],
        $config['cleanup_keys']
    );
}
add_action('before_delete_post', 'dracka_cleanup_relationships_on_post_removal');
add_action('wp_trash_post', 'dracka_cleanup_relationships_on_post_removal');

/**
 * Adjusts the main frontend query for archives and scoped search.
 *
 * It skips admin/non-main queries, expands series archives to include
 * issues, and limits search results to library or gallery post types
 * when a valid scope is provided via the dracka_scope query parameter.
 *
 * @param WP_Query $query Query object about to be executed.
 * @return void
 */
function dracka_adjust_library_query($query)
{
    if (is_admin() || !$query->is_main_query()) return;

    if ($query->is_post_type_archive('series')) {
        $tab = dracka_get_library_tab();

        if ($tab === 'issues') {
            $query->set('post_type', 'issue');
            $query->set('orderby', 'date');
            $query->set('order', 'DESC');
        } elseif ($tab === 'standalones') {
            // Standalone series: series posts with dracka_series_is_standalone = 1.
            $query->set('post_type', 'series');
            $query->set('post_status', 'publish');
            $query->set('orderby', 'date');
            $query->set('order', 'DESC');
            $query->set('meta_query', [[
                'key'     => DRACKA_SERIES_IS_STANDALONE_META_KEY,
                'value'   => '1',
                'compare' => '=',
            ]]);
        } else {
            $query->set('post_type', 'series');
            $query->set('post_status', 'publish');
            $query->set('orderby', 'date');
            $query->set('order', 'DESC');
            $query->set('meta_query', [
                'relation' => 'OR',
                [
                    'key'     => DRACKA_SERIES_IS_STANDALONE_META_KEY,
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key'     => DRACKA_SERIES_IS_STANDALONE_META_KEY,
                    'value'   => '1',
                    'compare' => '!=',
                ],
            ]);
        }
    }

    if ($query->is_post_type_archive('issue')) {
        $tab = dracka_get_library_tab();

        $query->set('post_type', 'issue');
        $query->set('orderby', 'date');
        $query->set('order', 'DESC');

        if ($tab === 'standalones') {
            $query->set('meta_query', [
                'relation' => 'OR',
                [
                    'key'     => 'dracka_series_id',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key'     => 'dracka_series_id',
                    'value'   => '',
                    'compare' => '=',
                ],
            ]);
        }
    }

    if ($query->is_post_type_archive('album')) {
        $tab = dracka_get_gallery_tab();

        if ($tab === 'albums') {
            $query->set('post_type', 'album');
            $query->set('orderby', 'date');
            $query->set('order', 'DESC');
        } else {
            $query->set('post_type', 'artwork');
            $query->set('orderby', 'date');
            $query->set('order', 'DESC');
        }
    }

    if ($query->is_search()) {
        $scope = isset($_GET['dracka_scope']) ? sanitize_text_field(wp_unslash($_GET['dracka_scope'])) : '';

        if ($scope === 'library') {
            $query->set('post_type', ['series', 'issue']);
        } elseif ($scope === 'gallery') {
            $query->set('post_type', ['album', 'artwork']);
        }
    }
}
add_action('pre_get_posts', 'dracka_adjust_library_query');

/**
 * Redirects legacy archive URLs and base section URLs to canonical tab paths.
 *
 * @return void
 */
function dracka_redirect_archive_urls()
{
    if (is_admin() || wp_doing_ajax() || !is_main_query()) {
        return;
    }

    global $wp;

    $request_path = isset($wp->request) ? trim((string) $wp->request, '/') : '';

    if (preg_match('#^gallery/standalones(?:/page/([0-9]{1,}))?$#', $request_path, $matches)) {
        $target = home_url('/gallery/artwork/');

        if (!empty($matches[1])) {
            $target = home_url('/gallery/artwork/page/' . (int) $matches[1] . '/');
        }

        wp_safe_redirect($target, 301);
        exit;
    }

    if (preg_match('#^series(?:/page/([0-9]{1,}))?$#', $request_path, $matches)) {
        $target = home_url('/library/series/');

        if (!empty($matches[1])) {
            $target = home_url('/library/series/page/' . (int) $matches[1] . '/');
        }

        wp_safe_redirect($target, 301);
        exit;
    }

    $paged = max(1, (int) get_query_var('paged'));

    if (is_post_type_archive('issue') && !get_query_var('dracka_library_tab')) {
        $target = home_url('/library/issues/');
        if ($paged > 1) {
            $target = home_url('/library/issues/page/' . $paged . '/');
        }

        wp_safe_redirect($target, 301);
        exit;
    }

    if (is_post_type_archive('artwork') && !get_query_var('dracka_gallery_tab')) {
        $target = home_url('/gallery/artwork/');
        if ($paged > 1) {
            $target = home_url('/gallery/artwork/page/' . $paged . '/');
        }

        wp_safe_redirect($target, 301);
        exit;
    }

    if (is_post_type_archive('series') && !get_query_var('dracka_library_tab')) {
        $target = home_url('/library/series/');
        if ($paged > 1) {
            $target = home_url('/library/series/page/' . $paged . '/');
        }

        wp_safe_redirect($target, 301);
        exit;
    }

    if (is_post_type_archive('album') && !get_query_var('dracka_gallery_tab')) {
        $target = home_url('/gallery/artwork/');
        if ($paged > 1) {
            $target = home_url('/gallery/artwork/page/' . $paged . '/');
        }

        wp_safe_redirect($target, 301);
        exit;
    }
}
add_action('template_redirect', 'dracka_redirect_archive_urls');

/**
 * Replaces social menu link text with matching SVG platform icons.
 *
 * For items in the social menu location, the URL is matched against a
 * domain-to-icon map; when matched, the original item output is replaced
 * with an external-safe anchor containing the inline SVG.
 *
 * @param string   $item_output Existing menu item HTML.
 * @param WP_Post  $item Menu item object.
 * @param int      $depth Menu depth level.
 * @param stdClass $args Menu rendering arguments.
 * @return string
 */
function dracka_social_icons($item_output, $item, $depth, $args)
{
    if ($args->theme_location !== 'social') return $item_output;

    $url = $item->url;
    $icon = '';

    $social_platforms = [
        'facebook.com'  => 'facebook',
        'instagram.com' => 'instagram',
        'bsky.app'      => 'bluesky',
        'bsky.social'   => 'bluesky',
        'youtube.com'   => 'youtube',
        'patreon.com'   => 'patreon',
    ];

    foreach ($social_platforms as $domain => $icon_name) {
        if (strpos($url, $domain) !== false) {
            $icon = dracka_get_svg($icon_name);
            break;
        }
    }

    return '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer" class="menu-link">' . $icon . '</a>';
}
add_filter('walker_nav_menu_start_el', 'dracka_social_icons', 10, 4);

/**
 * Shortcode [dracka_social_links] — renders the Social Menu nav with SVG icons.
 *
 * Usage: add a Shortcode widget to any footer cell and enter [dracka_social_links].
 *
 * @return string
 */
function dracka_social_links_shortcode()
{
    if (!has_nav_menu('social')) {
        return '';
    }

    return wp_nav_menu([
        'theme_location' => 'social',
        'container'      => 'nav',
        'container_class' => 'social-links',
        'container_attr' => ['aria-label' => __('Social links', 'dracka')],
        'menu_class'     => 'social-menu',
        'echo'           => false,
    ]);
}
add_shortcode('dracka_social_links', 'dracka_social_links_shortcode');

/**
 * Shortcode [button url="..." target="..."] — renders a theme-styled CTA button.
 *
 * Attributes:
 *   url    (required) Link href.
 *   target (optional) Link target, e.g. "_blank". Defaults to empty (same tab).
 *
 * Usage: [button url="https://patreon.com/example" target="_blank"]Support us on Patreon[/button]
 *
 * @param array  $atts    Shortcode attributes.
 * @param string $content Button label text.
 * @return string
 */
function dracka_button_shortcode($atts, $content = '')
{
    $atts = shortcode_atts(
        [
            'url'    => '#',
            'target' => '',
        ],
        $atts,
        'button'
    );

    $target = $atts['target'] ? ' target="' . esc_attr($atts['target']) . '"' : '';
    $rel    = $atts['target'] === '_blank' ? ' rel="noopener noreferrer"' : '';
    $label  = $content ? wp_kses_post($content) : __('Learn more', 'dracka');

    return '<a href="' . esc_url($atts['url']) . '" class="btn"' . $target . $rel . '>' . $label . '</a>';
}
add_shortcode('button', 'dracka_button_shortcode');

require get_template_directory() . '/inc/svg-icons.php';
require get_template_directory() . '/inc/theme-settings.php';

/**
 * Issue access module for flipbook and Patreon lock modes.
 */

const DRACKA_ISSUE_ACCESS_MODE_META_KEY = 'dracka_issue_access_mode';
const DRACKA_ISSUE_FLIPBOOK_ID_META_KEY = 'dracka_issue_flipbook_id';
const DRACKA_ISSUE_PATREON_URL_META_KEY = 'dracka_issue_patreon_url';
const DRACKA_ISSUE_PATREON_IMAGE_META_KEY = 'dracka_issue_patreon_image_id';

/**
 * Returns normalized issue access mode.
 *
 * @param int $issue_id Issue post ID.
 * @return string
 */
function dracka_get_issue_access_mode($issue_id)
{
    $mode = (string) get_post_meta((int) $issue_id, DRACKA_ISSUE_ACCESS_MODE_META_KEY, true);
    return in_array($mode, ['flipbook', 'patreon'], true) ? $mode : 'flipbook';
}

/**
 * Returns DearFlip post types to query for book references.
 *
 * @return array
 */
function dracka_get_dearflip_book_post_types()
{
    $default_types = ['dflip', 'dearflip', 'dflip-books', 'flipbook'];
    $post_types = apply_filters('dracka_dearflip_book_post_types', $default_types);

    if (!is_array($post_types)) {
        return [];
    }

    $post_types = array_values(array_unique(array_filter(array_map('sanitize_key', $post_types))));

    return array_values(array_filter($post_types, 'post_type_exists'));
}

/**
 * Extracts source URL from a DearFlip book post.
 *
 * @param WP_Post $book DearFlip book post.
 * @return string
 */
function dracka_extract_dearflip_source_url($book)
{
    if (!$book instanceof WP_Post) {
        return '';
    }

    $meta_keys = apply_filters('dracka_dearflip_source_meta_keys', [
        '_dflip_source',
        'dflip_source',
        'source',
        'pdf_source',
        'pdf_url',
        'attachment_id',
    ]);

    if (is_array($meta_keys)) {
        foreach ($meta_keys as $key) {
            $key = sanitize_key((string) $key);
            if ($key === '') {
                continue;
            }

            $value = get_post_meta($book->ID, $key, true);
            if (is_numeric($value)) {
                $attachment_url = wp_get_attachment_url((int) $value);
                if (is_string($attachment_url) && $attachment_url !== '') {
                    return esc_url_raw($attachment_url);
                }
            }

            if (is_string($value)) {
                $value = trim($value);
                if ($value !== '' && wp_http_validate_url($value)) {
                    return esc_url_raw($value);
                }
            }
        }
    }

    if (preg_match('/\[dflip\s+([^\]]+)\]/i', (string) $book->post_content, $matches) === 1) {
        $attrs = shortcode_parse_atts($matches[1]);
        if (is_array($attrs) && !empty($attrs['source']) && is_string($attrs['source']) && wp_http_validate_url($attrs['source'])) {
            return esc_url_raw($attrs['source']);
        }
    }

    return '';
}

/**
 * Lists available DearFlip books for issue selection.
 *
 * @return array
 */
function dracka_get_dearflip_book_options()
{
    $book_post_types = dracka_get_dearflip_book_post_types();
    if (empty($book_post_types)) {
        return [];
    }

    $books = get_posts([
        'post_type'      => $book_post_types,
        'post_status'    => ['publish', 'draft', 'pending', 'private', 'future'],
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'no_found_rows'  => true,
    ]);

    $options = [];
    foreach ($books as $book) {
        if (!$book instanceof WP_Post) {
            continue;
        }

        $options[] = [
            'id'         => (int) $book->ID,
            'label'      => $book->post_title !== '' ? $book->post_title : 'Untitled Book #' . (int) $book->ID,
            'status'     => (string) $book->post_status,
            'source_url' => dracka_extract_dearflip_source_url($book),
        ];
    }

    return $options;
}
