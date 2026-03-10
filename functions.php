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
        '0.1',
        true
    );
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
            'name'           => 'dracka/latest-issues',
            'editor_script'  => 'dracka-latest-issues-block-editor',
            'editor_js'      => '/js/blocks/latest-issues.js',
            'render_cb'      => 'dracka_render_latest_issues_block',
            'default_title'  => 'Latest Issues',
            'default_label'  => 'Go to library',
            'default_url'    => '/library/issues/',
        ],
        'artwork' => [
            'name'           => 'dracka/latest-artwork',
            'editor_script'  => 'dracka-latest-artwork-block-editor',
            'editor_js'      => '/js/blocks/latest-artwork.js',
            'render_cb'      => 'dracka_render_latest_artwork_block',
            'default_title'  => 'Latest Artwork',
            'default_label'  => 'Go to gallery',
            'default_url'    => '/gallery/artwork/',
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
            'api_version'     => 2,
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
    $sort_mode = sanitize_key((string) $sort_mode);

    return in_array($sort_mode, ['newest', 'manual'], true) ? $sort_mode : 'newest';
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
 * @return array{total: int, effective: int}
 */
function dracka_get_effective_cap($post_type, $max_items_cap)
{
    $total = (int) wp_count_posts($post_type)->publish;
    $effective = $max_items_cap > 0 ? min($max_items_cap, $total) : $total;

    return compact('total', 'effective');
}

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
 * Supports issues (with title overlay) and artwork (image only).
 *
 * @param int    $post_id      Post ID.
 * @param string $content_type Content type slug ('issue' or 'artwork').
 * @return string
 */
function dracka_render_content_card_markup($post_id, $content_type)
{
    $post_id = (int) $post_id;

    if (!$post_id || get_post_status($post_id) !== 'publish') {
        return '';
    }

    $css_prefix = $content_type === 'artwork' ? 'dracka-artwork' : 'dracka-issues';
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
 * Renders a dynamic "Latest Content" collapsible block.
 *
 * Shared implementation for both issues and artwork homepage blocks.
 * Each block type passes its content_type to control query, markup,
 * REST endpoint, and CSS class prefixes.
 *
 * @param string              $content_type Post type slug ('issue' or 'artwork').
 * @param array<string, mixed> $attributes  Block attributes.
 * @param array<string, string> $defaults   Default labels/URLs for this content type.
 * @return string
 */
function dracka_render_latest_content_block($content_type, $attributes, $defaults)
{
    $css_prefix = $content_type === 'artwork' ? 'dracka-artwork' : 'dracka-issues';
    $rest_slug  = $content_type === 'artwork' ? 'artwork' : 'issues';

    $title = isset($attributes['title']) ? sanitize_text_field($attributes['title']) : $defaults['title'];
    $initial_count = isset($attributes['initialCount']) ? max(1, (int) $attributes['initialCount']) : 8;
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

    $next_offset = $initial_render_count;
    $has_more = $next_offset < $effective_cap;
    $reached_cap = !$has_more && $total_published > $effective_cap;
    $content_id = wp_unique_id('dracka-latest-' . $rest_slug . '-content-');

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
            aria-expanded="false"
            aria-controls="<?php echo esc_attr($content_id); ?>">
            <span class="dracka-collapsible__arrow" aria-hidden="true"></span>
            <span class="dracka-collapsible__title"><?php echo esc_html($title); ?></span>
        </button>

        <div id="<?php echo esc_attr($content_id); ?>" class="dracka-collapsible__content" hidden>
            <div class="<?php echo esc_attr($css_prefix); ?>-grid" data-content-grid>
                <?php
                while ($initial_query->have_posts()) {
                    $initial_query->the_post();
                    echo dracka_render_content_card_markup((int) get_the_ID(), $content_type);
                }
                wp_reset_postdata();
                ?>
            </div>

            <?php if ($has_more) : ?>
                <button type="button" class="<?php echo esc_attr($css_prefix); ?>-show-more" data-show-more><?php echo esc_html($show_more_label); ?></button>
            <?php elseif ($reached_cap) : ?>
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
        'title'    => 'Latest Issues',
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
        'title'    => 'Latest Artwork',
        'go_label' => 'Go to gallery',
        'go_url'   => '/gallery/artwork/',
    ]);
}

/**
 * Registers REST routes used by dynamic frontend components.
 *
 * Both issues and artwork endpoints share the same argument schema
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
 * @param string          $content_type Post type slug ('issue' or 'artwork').
 * @return WP_REST_Response
 */
function dracka_rest_get_latest_content($request, $content_type)
{
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
        'supports'     => ['title', 'thumbnail'],
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
        'supports'     => ['title', 'editor', 'excerpt', 'thumbnail', 'page-attributes'],
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
        'supports'     => ['title', 'editor', 'excerpt', 'thumbnail'],
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
        'supports'     => ['title', 'editor', 'excerpt', 'thumbnail', 'page-attributes'],
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
}

add_action('init', 'dracka_register_content_types');

/**
 * Registers Series taxonomies for genre and publication status.
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

    register_taxonomy('dracka_series_status', ['series'], [
        'labels'            => [
            'name'          => 'Statuses',
            'singular_name' => 'Status',
            'search_items'  => 'Search Statuses',
            'all_items'     => 'All Statuses',
            'edit_item'     => 'Edit Status',
            'update_item'   => 'Update Status',
            'add_new_item'  => 'Add New Status',
            'new_item_name' => 'New Status Name',
            'menu_name'     => 'Status',
        ],
        'public'            => true,
        'show_ui'           => true,
        'show_admin_column' => true,
        'show_in_rest'      => true,
        'hierarchical'      => false,
        'rewrite'           => ['slug' => 'series-status'],
    ]);
}
add_action('init', 'dracka_register_series_taxonomies');

/**
 * Ensures default status terms exist for series status taxonomy.
 *
 * @return void
 */
function dracka_seed_series_status_terms()
{
    $taxonomy = 'dracka_series_status';

    if (!taxonomy_exists($taxonomy)) {
        return;
    }

    $default_statuses = ['Ongoing', 'Coming Soon', 'Cancelled', 'Finalized'];

    foreach ($default_statuses as $status_label) {
        if (!term_exists($status_label, $taxonomy)) {
            wp_insert_term($status_label, $taxonomy);
        }
    }
}
add_action('init', 'dracka_seed_series_status_terms', 20);

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
        '^gallery/(albums|artwork|standalones)/?$',
        'index.php?post_type=album&dracka_gallery_tab=$matches[1]',
        'top'
    );

    add_rewrite_rule(
        '^gallery/(albums|artwork|standalones)/page/([0-9]{1,})/?$',
        'index.php?post_type=album&dracka_gallery_tab=$matches[1]&paged=$matches[2]',
        'top'
    );

    // Flush rewrite rules when rule set version changes
    $rewrite_rules_version = '2026-02-19';
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
    $allowed_tabs = ['albums', 'artwork', 'standalones'];

    if (!is_string($tab) || !in_array($tab, $allowed_tabs, true)) {
        return 'albums';
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
        'dracka_issue_pdf',
        'PDF Flipbook',
        'dracka_render_issue_pdf_metabox',
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
 * all published series sorted by title, and renders a select input plus
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
    $series_posts = get_posts([
        'post_type'      => 'series',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ]);

    echo '<select name="dracka_series_id" style="width:100%">';
    echo '<option value="">No series (standalone)</option>';
    foreach ($series_posts as $series) {
        $selected = $current_series === (int) $series->ID ? ' selected' : '';
        echo '<option value="' . esc_attr($series->ID) . '"' . $selected . '>' . esc_html($series->post_title) . '</option>';
    }
    echo '</select>';

    echo '<p style="margin-top:10px">';
    echo '<label for="dracka_series_order" style="display:block;margin-bottom:4px">Series order</label>';
    echo '<input type="number" id="dracka_series_order" name="dracka_series_order" value="' . esc_attr($current_order) . '" min="0" step="1" style="width:100%">';
    echo '</p>';
}

/**
 * Renders the issue metabox for PDF attachment selection/replacement.
 *
 * Shows the currently attached PDF (if any) with a link to view it,
 * and provides a media picker for replacing or attaching a new PDF.
 *
 * @param WP_Post $post Current issue post being edited.
 * @return void
 */
function dracka_render_issue_pdf_metabox($post)
{
    wp_nonce_field('dracka_save_issue_pdf', 'dracka_issue_pdf_nonce');

    $attachment_id = dracka_get_issue_pdf($post->ID);
    $pdf_url = dracka_get_issue_pdf_url($post->ID);

    echo '<div style="margin-bottom:15px; padding-bottom:15px; border-bottom:1px solid #eee">';
    echo '<p style="margin:0 0 8px 0; font-size:12px; color:#666"><strong>Current Flipbook PDF:</strong></p>';
    if ($attachment_id && $pdf_url) {
        $attachment = get_post($attachment_id);
        echo '<p style="margin:0 0 8px 0; word-break:break-word">';
        echo '<a href="' . esc_url($pdf_url) . '" target="_blank" style="color:#0073aa; text-decoration:underline">';
        echo esc_html($attachment->post_title ?? 'PDF File');
        echo '</a>';
        echo '</p>';

        echo '<button type="button" class="button button-small button-link-delete" onclick="dracka_clear_pdf_field()" style="margin-top:5px">';
        echo 'Remove PDF';
        echo '</button>';
    } else {
        echo '<p style="margin:0; color:#999; font-style:italic">No PDF selected</p>';
    }
    echo '</div>';

    echo '<div>';
    echo '<p style="margin:0 0 8px 0; font-size:12px; color:#666"><strong>Change PDF:</strong></p>';
    echo '<input type="hidden" id="dracka_issue_pdf_id" name="dracka_issue_pdf_id" value="' . esc_attr($attachment_id) . '" />';

    // Render media uploader button
    echo '<button type="button" class="button button-primary" id="dracka_upload_pdf_btn" style="width:100%; margin-bottom:10px">';
    echo 'Select PDF from Media Library';
    echo '</button>';
    echo '<p style="margin:8px 0 0 0; font-size:11px; color:#999">';
    echo '1. Click the button to open Media Library<br>';
    echo '2. Select a PDF file<br>';
    echo '3. Click "Update" button below to save';
    echo '</p>';

    // Inline script to handle media picker
    echo '<script type="text/javascript">';
    echo 'var drackaIssuePdfFrame;';
    echo 'document.addEventListener("DOMContentLoaded", function() {';
    echo 'var btn = document.getElementById("dracka_upload_pdf_btn");';
    echo 'if (btn) {';
    echo 'btn.addEventListener("click", function(e) {';
    echo 'e.preventDefault();';
    echo 'if (drackaIssuePdfFrame) {';
    echo 'drackaIssuePdfFrame.open();';
    echo 'return;';
    echo '}';
    echo 'drackaIssuePdfFrame = wp.media({';
    echo 'title: "Select PDF",';
    echo 'library: { type: "application/pdf" },';
    echo 'button: { text: "Use this PDF" },';
    echo 'multiple: false,';
    echo '});';
    echo 'drackaIssuePdfFrame.on("select", function() {';
    echo 'var attachment = drackaIssuePdfFrame.state().get("selection").first().toJSON();';
    echo 'if (attachment.mime === "application/pdf") {';
    echo 'document.getElementById("dracka_issue_pdf_id").value = attachment.id;';
    echo 'alert("PDF selected: " + attachment.filename + ". Click \\"Update\\" to save.");';
    echo '} else {';
    echo 'alert("Please select a PDF file.");';
    echo '}';
    echo '});';
    echo 'drackaIssuePdfFrame.open();';
    echo '});';
    echo '}';
    echo '});';
    echo 'function dracka_clear_pdf_field() {';
    echo 'if (confirm("Remove the PDF from this issue?")) {';
    echo 'document.getElementById("dracka_issue_pdf_id").value = "";';
    echo 'alert("PDF removed. Click \\"Update\\" to save.");';
    echo '}';
    echo '}';
    echo '</script>';
    echo '</div>';
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
    $album_posts = get_posts([
        'post_type'      => 'album',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
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

        // Save Issue PDF (independent of series nonce)
        if (isset($_POST['dracka_issue_pdf_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['dracka_issue_pdf_nonce'])), 'dracka_save_issue_pdf')) {
            if (isset($_POST['dracka_issue_pdf_id'])) {
                $attachment_id = (int) $_POST['dracka_issue_pdf_id'];
                if ($attachment_id > 0 && dracka_is_valid_issue_pdf($attachment_id)) {
                    dracka_set_issue_pdf($post_id, $attachment_id);
                } else {
                    dracka_set_issue_pdf($post_id, 0);
                }
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
            update_post_meta($post_id, DRACKA_LOGO_ACTIVE_META_KEY, '1');
            dracka_deactivate_other_logo_animation_posts($post_id);
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
 * @return array<int>
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
 * @return array<int>
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
 * @return array{svg_url:string, animation_urls:array<int, string>}
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

        if ($tab === 'artwork') {
            $query->set('post_type', 'artwork');
            $query->set('orderby', 'date');
            $query->set('order', 'DESC');
        } elseif ($tab === 'standalones') {
            $query->set('post_type', 'artwork');
            $query->set('orderby', 'date');
            $query->set('order', 'DESC');
            $query->set('meta_query', [
                'relation' => 'OR',
                [
                    'key'     => 'dracka_album_id',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key'     => 'dracka_album_id',
                    'value'   => '',
                    'compare' => '=',
                ],
            ]);
        } else {
            $query->set('post_type', 'album');
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
        $target = home_url('/gallery/albums/');
        if ($paged > 1) {
            $target = home_url('/gallery/albums/page/' . $paged . '/');
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

/**
 * Issue PDF Module: Stores and manages PDF attachments for issue posts.
 *
 * This module handles the creation of issues from PDFs, storage of PDF
 * attachment metadata, and rendering of DearFlip flipbooks on issue pages.
 */

const DRACKA_ISSUE_PDF_META_KEY = 'dracka_issue_pdf_attachment_id';
const DRACKA_ISSUE_PDF_NONCE_ACTION = 'dracka_create_issue_from_pdf';
const DRACKA_ISSUE_PDF_NONCE_FIELD = 'dracka_issue_pdf_nonce';

/**
 * Retrieves the attachment ID stored for an issue's PDF.
 *
 * @param int $issue_id Issue post ID.
 * @return int Attachment ID, or 0 if not set.
 */
function dracka_get_issue_pdf($issue_id)
{
    return (int) get_post_meta($issue_id, DRACKA_ISSUE_PDF_META_KEY, true);
}

/**
 * Updates the attachment ID for an issue's PDF.
 *
 * @param int $issue_id Issue post ID.
 * @param int $attachment_id Media attachment ID, or 0 to remove.
 * @return bool True on success, false on failure.
 */
function dracka_set_issue_pdf($issue_id, $attachment_id)
{
    $attachment_id = (int) $attachment_id;

    if ($attachment_id > 0) {
        return (bool) update_post_meta($issue_id, DRACKA_ISSUE_PDF_META_KEY, $attachment_id);
    } else {
        return (bool) delete_post_meta($issue_id, DRACKA_ISSUE_PDF_META_KEY);
    }
}

/**
 * Validates that an attachment is a PDF.
 *
 * @param int $attachment_id Attachment post ID.
 * @return bool True if valid PDF, false otherwise.
 */
function dracka_is_valid_issue_pdf($attachment_id)
{
    if (!$attachment_id) {
        return false;
    }

    $mime_type = get_post_mime_type($attachment_id);
    return $mime_type === 'application/pdf';
}

/**
 * Retrieves the URL of an issue's PDF attachment.
 *
 * @param int $issue_id Issue post ID.
 * @return string PDF URL, or empty string if not set or invalid.
 */
function dracka_get_issue_pdf_url($issue_id)
{
    $attachment_id = dracka_get_issue_pdf($issue_id);

    if (!$attachment_id || !dracka_is_valid_issue_pdf($attachment_id)) {
        return '';
    }

    return wp_get_attachment_url($attachment_id);
}

/**
 * Registers the admin action pages and handles PDF-to-Issue creation flow.
 *
 * This adds a "Create Issue from PDF" admin page under Issues post type
 * and registers both the display handler and form submission handler.
 *
 * @return void
 */
function dracka_register_create_issue_from_pdf_page()
{
    add_action('admin_init', 'dracka_handle_create_issue_from_pdf_form');
    add_action('admin_menu', 'dracka_add_create_issue_from_pdf_menu');
}
add_action('init', 'dracka_register_create_issue_from_pdf_page');

/**
 * Adds the "Create Issue from PDF" admin menu item under Issues.
 *
 * @return void
 */
function dracka_add_create_issue_from_pdf_menu()
{
    add_submenu_page(
        'edit.php?post_type=issue',
        'Create from PDF',
        'Create from PDF',
        'manage_options',
        'dracka-create-issue-from-pdf',
        'dracka_render_create_issue_from_pdf_page'
    );
}

/**
 * Handles the form submission for PDF-to-Issue creation.
 *
 * Validates nonce and permissions, checks for uploaded file, validates
 * PDF MIME type, creates the issue post, and attaches the PDF.
 * Redirects with success/error messages based on outcome.
 *
 * @return void
 */
function dracka_handle_create_issue_from_pdf_form()
{
    // Only run on POST to the right action and on admin
    if (!is_admin() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    if (!isset($_POST[DRACKA_ISSUE_PDF_NONCE_FIELD]) || !isset($_POST['post_type']) || sanitize_key(wp_unslash($_POST['post_type'])) !== 'issue') {
        return;
    }

    $nonce = sanitize_text_field(wp_unslash($_POST[DRACKA_ISSUE_PDF_NONCE_FIELD]));

    if (!wp_verify_nonce($nonce, DRACKA_ISSUE_PDF_NONCE_ACTION)) {
        wp_die('Nonce verification failed');
    }

    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }

    // Check for file upload
    if (empty($_FILES['dracka_pdf_file'])) {
        wp_safe_redirect(add_query_arg('dracka_error', 'no_file', admin_url('admin.php?page=dracka-create-issue-from-pdf')));
        exit;
    }

    $file = $_FILES['dracka_pdf_file'];

    // Validate MIME type using server-side check instead of client-reported type
    $file_type_info = wp_check_filetype(basename($file['name']), ['pdf' => 'application/pdf']);

    if (empty($file_type_info['type'])) {
        wp_safe_redirect(add_query_arg('dracka_error', 'invalid_type', admin_url('admin.php?page=dracka-create-issue-from-pdf')));
        exit;
    }

    // Require WP_Filesystem for file handling
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    // Handle the file upload via WordPress Media
    $attachment_id = media_handle_upload('dracka_pdf_file', 0);

    if (is_wp_error($attachment_id)) {
        wp_safe_redirect(add_query_arg('dracka_error', 'upload_failed', admin_url('admin.php?page=dracka-create-issue-from-pdf')));
        exit;
    }

    // Get issue title from form input
    $issue_title = isset($_POST['dracka_issue_title']) ? sanitize_text_field(wp_unslash($_POST['dracka_issue_title'])) : '';

    if (empty($issue_title)) {
        // Fallback to filename (without extension)
        $file_name = sanitize_file_name($file['name']);
        $issue_title = pathinfo($file_name, PATHINFO_FILENAME);
    }

    // Create the issue post
    $issue_id = wp_insert_post([
        'post_type'   => 'issue',
        'post_title'  => $issue_title,
        'post_status' => 'draft',
        'post_author' => get_current_user_id(),
    ]);

    if (is_wp_error($issue_id)) {
        // Clean up uploaded file if post creation fails
        wp_delete_attachment($attachment_id, true);
        wp_safe_redirect(add_query_arg('dracka_error', 'post_creation_failed', admin_url('admin.php?page=dracka-create-issue-from-pdf')));
        exit;
    }

    // Attach PDF to the issue
    dracka_set_issue_pdf($issue_id, $attachment_id);

    // Redirect to the new issue for editing, with success message
    wp_safe_redirect(add_query_arg('dracka_success', '1', get_edit_post_link($issue_id, 'raw')));
    exit;
}

/**
 * Renders the "Create Issue from PDF" admin page.
 *
 * Displays a form for uploading/selecting a PDF and entering an issue title.
 * Shows success/error messages from query parameters.
 *
 * @return void
 */
function dracka_render_create_issue_from_pdf_page()
{
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }

    $error_map = [
        'no_file'                => 'Please select a PDF file to upload.',
        'invalid_type'           => 'The selected file must be a PDF.',
        'upload_failed'          => 'File upload failed. Please try again.',
        'post_creation_failed'   => 'Failed to create the issue post. Please try again.',
    ];

    $error = isset($_GET['dracka_error']) ? sanitize_text_field(wp_unslash($_GET['dracka_error'])) : '';
    $success = isset($_GET['dracka_success']) ? true : false;
?>
    <div class="wrap">
        <h1>Create Issue from PDF</h1>

        <?php if ($success) : ?>
            <div class="notice notice-success is-dismissible">
                <p>Issue created successfully! You can now edit it below to set the series, order, and add additional content.</p>
            </div>
        <?php endif; ?>

        <?php if ($error && isset($error_map[$error])) : ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo esc_html($error_map[$error]); ?></p>
            </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field(DRACKA_ISSUE_PDF_NONCE_ACTION, DRACKA_ISSUE_PDF_NONCE_FIELD); ?>
            <input type="hidden" name="post_type" value="issue" />

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="dracka_pdf_file">PDF File *</label></th>
                    <td>
                        <input type="file" id="dracka_pdf_file" name="dracka_pdf_file" accept="application/pdf" required />
                        <p class="description">Upload a PDF file to convert into an issue.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="dracka_issue_title">Issue Title</label></th>
                    <td>
                        <input type="text" id="dracka_issue_title" name="dracka_issue_title" class="regular-text" />
                        <p class="description">Leave empty to use the PDF filename. You can edit the title after creation.</p>
                    </td>
                </tr>
            </table>

            <?php submit_button('Create Issue'); ?>
        </form>

        <hr />

        <h2>Next Steps</h2>
        <ol>
            <li>Upload your PDF and create the issue.</li>
            <li>Edit the issue to assign it to a series and set the series order.</li>
            <li>Add any additional content like description or featured image.</li>
            <li>Publish the issue when ready.</li>
        </ol>
    </div>
<?php
}
