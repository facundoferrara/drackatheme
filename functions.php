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
    register_sidebar([
        'name'          => __('Footer Content', 'dracka'),
        'id'            => 'footer-content',
        'description'   => __('Editable footer block area.', 'dracka'),
        'before_widget' => '<div class="footer-widget">',
        'after_widget'  => '</div>',
        'before_title'  => '<h2 class="footer-widget-title">',
        'after_title'   => '</h2>',
    ]);
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
            '0.1',
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
                    'default' => 8,
                ],
                'increment' => [
                    'type'    => 'number',
                    'default' => 8,
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
        '0.1',
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
        $total = (int) wp_count_posts($post_type)->publish;
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

    if (!is_string($post_type) || !in_array($post_type, ['issue', 'artwork', 'post'], true)) {
        return;
    }

    $cache_key = dracka_get_effective_cap_cache_key($post_type);
    wp_cache_delete($cache_key, 'dracka_theme');
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
    return dracka_get_latest_content_query_args($offset, $limit, 'issue', $sort_mode);
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

    if (!$post_id || get_post_status($post_id) !== 'publish') {
        return '';
    }

    if ($content_type === 'artwork') {
        $css_prefix = 'dracka-artwork';
    } elseif ($content_type === 'post') {
        $css_prefix = 'dracka-newsletter';
    } else {
        $css_prefix = 'dracka-issues';
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
            '<article class="%1$s-card"><a href="%2$s" class="%1$s-card__link">%3$s</a></article>',
            esc_attr($css_prefix),
            esc_url($permalink),
            $thumbnail
        );
    }

    if ($content_type === 'post') {
        $excerpt = trim((string) wp_strip_all_tags((string) get_the_excerpt($post_id)));
        $excerpt_html = '';

        if ($excerpt !== '') {
            $excerpt_html = sprintf(
                '<span class="%1$s-card__excerpt">%2$s</span>',
                esc_attr($css_prefix),
                esc_html($excerpt)
            );
        }

        return sprintf(
            '<article class="%1$s-card"><a href="%2$s" class="%1$s-card__link"><span class="%1$s-card__media">%3$s<span class="%1$s-card__title">%4$s</span></span>%5$s</a></article>',
            esc_attr($css_prefix),
            esc_url($permalink),
            $thumbnail,
            esc_html($title),
            $excerpt_html
        );
    }

    return sprintf(
        '<article class="%1$s-card"><a href="%2$s" class="%1$s-card__link">%3$s<span class="%1$s-card__title">%4$s</span></a></article>',
        esc_attr($css_prefix),
        esc_url($permalink),
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
    $initial_count = isset($attributes['initialCount']) ? max(1, (int) $attributes['initialCount']) : 8;

    if ($is_newsletter) {
        $initial_count = min($initial_count, 3);
    }

    $increment = isset($attributes['increment']) ? max(1, (int) $attributes['increment']) : 8;
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
    $initially_open = $is_newsletter;

    if ($is_newsletter) {
        $see_all_markup = sprintf(
            '<div class="dracka-newsletter-card__action"><a class="dracka-newsletter-see-all" href="%1$s">%2$s</a></div>',
            esc_url($go_to_library_url),
            esc_html($go_to_library_label)
        );

        $last_card_index = count($initial_cards) - 1;

        if ($last_card_index >= 0) {
            $initial_cards[$last_card_index] = str_replace(
                'class="dracka-newsletter-card"',
                'class="dracka-newsletter-card dracka-newsletter-card--with-action"',
                $initial_cards[$last_card_index]
            );

            $initial_cards[$last_card_index] = str_replace(
                '</article>',
                $see_all_markup . '</article>',
                $initial_cards[$last_card_index]
            );
        }

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
            <div class="<?php echo esc_attr($css_prefix); ?>-grid" data-content-grid>
                <?php echo $initial_cards_html; ?>
            </div>

            <?php if (!$is_newsletter && $has_more) : ?>
                <button type="button" class="<?php echo esc_attr($css_prefix); ?>-show-more" data-show-more><?php echo esc_html($show_more_label); ?></button>
            <?php elseif (!$is_newsletter && $reached_cap) : ?>
                <a class="<?php echo esc_attr($css_prefix); ?>-go-library" href="<?php echo esc_url($go_to_library_url); ?>"><?php echo esc_html($go_to_library_label); ?></a>
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
    return dracka_render_latest_content_block('issue', $attributes, [
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
    $ticker_line = implode($separator, array_fill(0, $repeat_count, $base_ticker_line));
    $ticker_line = wp_kses($ticker_line, $allowed_html);

    ob_start();
?>
    <section class="dracka-news-ticker" aria-label="News ticker" style="--dracka-news-ticker-duration: <?php echo esc_attr((string) $speed_seconds); ?>s;">
        <div class="dracka-news-ticker__viewport">
            <div class="dracka-news-ticker__track">
                <div class="dracka-news-ticker__line dracka-news-ticker__line--primary"><?php echo $ticker_line; ?></div>
                <div class="dracka-news-ticker__line dracka-news-ticker__line--clone" aria-hidden="true"><?php echo $ticker_line; ?></div>
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
            'default'           => 8,
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
        'issues'  => 'issue',
        'artwork' => 'artwork',
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
    if (!in_array($content_type, ['issue', 'artwork', 'post'], true)) {
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
}
add_action('customize_register', 'dracka_customize_register');

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
        $values[$key] = get_theme_mod('dracka_' . $key, $default);
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
        'ongoing'     => 'Ongoing',
        'coming-soon' => 'Coming Soon',
        'cancelled'   => 'Cancelled',
        'finalized'   => 'Finalized',
    ];
}

/**
 * Returns public-facing statuses for Series frontend listings.
 *
 * @return array<int, string>
 */
function dracka_get_series_public_statuses()
{
    return array_merge(['publish'], array_keys(dracka_get_series_custom_statuses()));
}

/**
 * Returns statuses that should appear in Issue->Series relation selectors.
 *
 * @return array<int, string>
 */
function dracka_get_series_editable_statuses()
{
    return array_values(array_unique(array_merge(
        dracka_get_series_public_statuses(),
        ['draft', 'pending', 'future', 'private']
    )));
}

/**
 * Registers custom editorial statuses for Series posts.
 *
 * @return void
 */
function dracka_register_series_post_statuses()
{
    foreach (dracka_get_series_custom_statuses() as $status_slug => $status_label) {
        register_post_status($status_slug, [
            'label'                     => $status_label,
            'label_count'               => _n_noop(
                $status_label . ' <span class="count">(%s)</span>',
                $status_label . ' <span class="count">(%s)</span>'
            ),
            'public'                    => true,
            'internal'                  => false,
            'protected'                 => false,
            'private'                   => false,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'show_in_rest'              => true,
            'date_floating'             => false,
        ]);
    }
}
add_action('init', 'dracka_register_series_post_statuses', 20);

/**
 * Prevents custom Series statuses from being assigned to other post types.
 *
 * @param array<string, mixed> $data Prepared post data.
 * @param array<string, mixed> $postarr Raw submitted post data.
 * @return array<string, mixed>
 */
function dracka_limit_series_custom_status_scope($data, $postarr)
{
    $custom_statuses = array_keys(dracka_get_series_custom_statuses());

    if (($data['post_type'] ?? '') !== 'series' && in_array($data['post_status'] ?? '', $custom_statuses, true)) {
        $data['post_status'] = 'draft';
    }

    return $data;
}
add_filter('wp_insert_post_data', 'dracka_limit_series_custom_status_scope', 10, 2);

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
}
add_action('add_meta_boxes', 'dracka_add_relationship_metaboxes');

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

    $series_author = (string) get_post_meta($post->ID, DRACKA_SERIES_AUTHOR_META_KEY, true);
    $publication_year = (string) get_post_meta($post->ID, DRACKA_SERIES_YEAR_META_KEY, true);
    $series_description = (string) get_post_meta($post->ID, DRACKA_SERIES_DESCRIPTION_META_KEY, true);

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
 * It prints a nonce, fetches current linkage/order metadata, queries
 * editable series statuses sorted by title, and renders a select input plus
 * numeric order field for manual sequence control.
 *
 * @param WP_Post $post Current issue post being edited.
 * @return void
 */
function dracka_render_series_metabox($post)
{
    wp_nonce_field('dracka_save_series_link', 'dracka_series_nonce');

    $current_series = (int) get_post_meta($post->ID, 'dracka_series_id', true);
    $current_order = get_post_meta($post->ID, 'dracka_series_order', true);
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

    echo '<select name="dracka_series_id" style="width:100%">';
    echo '<option value="">No series (standalone)</option>';
    foreach ($series_posts as $series) {
        $status_object = get_post_status_object($series->post_status);
        $status_label = $status_object ? $status_object->label : ucfirst((string) $series->post_status);
        $series_option_label = $series->post_title;

        if ($series->post_status !== 'publish') {
            $series_option_label .= ' (' . $status_label . ')';
        }

        $selected = $current_series === (int) $series->ID ? ' selected' : '';
        echo '<option value="' . esc_attr($series->ID) . '"' . $selected . '>' . esc_html($series_option_label) . '</option>';
    }
    echo '</select>';

    echo '<p style="margin-top:10px">';
    echo '<label for="dracka_series_order" style="display:block;margin-bottom:4px">Series order</label>';
    echo '<input type="number" id="dracka_series_order" name="dracka_series_order" value="' . esc_attr($current_order) . '" min="0" step="1" style="width:100%">';
    echo '</p>';
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

        // Save series link and order (if nonce is valid)
        if (isset($_POST['dracka_series_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['dracka_series_nonce'])), 'dracka_save_series_link')) {
            $series_id = isset($_POST['dracka_series_id']) ? (int) $_POST['dracka_series_id'] : 0;
            if ($series_id > 0) {
                update_post_meta($post_id, 'dracka_series_id', $series_id);
            } else {
                delete_post_meta($post_id, 'dracka_series_id');
            }

            $order_raw = isset($_POST['dracka_series_order']) ? trim(wp_unslash($_POST['dracka_series_order'])) : '';
            if ($order_raw !== '') {
                update_post_meta($post_id, 'dracka_series_order', (int) $order_raw);
            } else {
                delete_post_meta($post_id, 'dracka_series_order');
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
            $author = isset($_POST['dracka_series_author']) ? sanitize_text_field(wp_unslash($_POST['dracka_series_author'])) : '';
            $description = isset($_POST['dracka_series_description']) ? sanitize_textarea_field(wp_unslash($_POST['dracka_series_description'])) : '';
            $year_raw = isset($_POST['dracka_publication_year']) ? trim(wp_unslash($_POST['dracka_publication_year'])) : '';

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
            $query->set('post_type', 'issue');
            $query->set('orderby', 'date');
            $query->set('order', 'DESC');
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
        } else {
            $query->set('post_type', 'series');
            $query->set('orderby', 'date');
            $query->set('order', 'DESC');
            $query->set('post_status', dracka_get_series_public_statuses());
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
        'x.com'         => 'x',
        'twitter.com'   => 'x',
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
