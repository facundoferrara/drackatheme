<?php
get_header();

$artwork_id = get_the_ID();
$album_id   = (int) get_post_meta($artwork_id, 'dracka_album_id', true);
$album_link = $album_id ? get_permalink($album_id) : '';
$nav        = dracka_get_artwork_navigation($artwork_id);
$has_nav    = $nav['previous'] !== null || $nav['next'] !== null;

// Build current artwork image data for the embedded JSON block.
$thumb_id      = (int) get_post_thumbnail_id($artwork_id);
$src_data      = $thumb_id > 0 ? wp_get_attachment_image_src($thumb_id, 'large') : false;
$current_image = [
    'src'    => is_array($src_data) ? ($src_data[0] ?? '') : '',
    'srcset' => $thumb_id > 0 ? (wp_get_attachment_image_srcset($thumb_id, 'large') ?: '') : '',
    'sizes'  => $thumb_id > 0 ? (wp_get_attachment_image_sizes($thumb_id, 'large') ?: '') : '',
    'alt'    => get_the_title($artwork_id),
];
?>

<?php if ($has_nav) : ?>
<script id="artwork-nav-data" type="application/json">
<?php echo wp_json_encode([
    'restBase'  => esc_url_raw(rest_url('dracka/v1/artwork-nav/')),
    'current'   => [
        'id'    => $artwork_id,
        'url'   => get_permalink($artwork_id),
        'title' => get_the_title($artwork_id),
        'image' => $current_image,
    ],
    'previous'  => $nav['previous'] ? [
        'id'    => $nav['previous']['id'],
        'url'   => $nav['previous']['url'],
        'title' => $nav['previous']['title'],
        'image' => [
            'src'    => $nav['previous']['image_src'],
            'srcset' => $nav['previous']['image_srcset'],
            'sizes'  => $nav['previous']['image_sizes'],
            'alt'    => $nav['previous']['title'],
        ],
    ] : null,
    'next'      => $nav['next'] ? [
        'id'    => $nav['next']['id'],
        'url'   => $nav['next']['url'],
        'title' => $nav['next']['title'],
        'image' => [
            'src'    => $nav['next']['image_src'],
            'srcset' => $nav['next']['image_srcset'],
            'sizes'  => $nav['next']['image_sizes'],
            'alt'    => $nav['next']['title'],
        ],
    ] : null,
]); ?>
</script>
<?php endif; ?>

<main class="artwork-single">
    <?php if (have_posts()) : ?>
        <?php while (have_posts()) : the_post(); ?>
            <article <?php post_class(); ?>>
                <h1 class="artwork-single__title"><?php the_title(); ?></h1>

                <?php if ($album_link) : ?>
                    <p class="artwork-album">Album: <a href="<?php echo esc_url($album_link); ?>"><?php echo esc_html(get_the_title($album_id)); ?></a></p>
                <?php endif; ?>

                <?php if ($has_nav) : ?>
                <div class="artwork-nav"
                     data-artwork-id="<?php echo esc_attr($artwork_id); ?>"
                     data-prev-id="<?php echo esc_attr($nav['previous'] ? $nav['previous']['id'] : ''); ?>"
                     data-next-id="<?php echo esc_attr($nav['next'] ? $nav['next']['id'] : ''); ?>">
                    <button
                        class="artwork-nav__arrow artwork-nav__arrow--prev"
                        aria-label="<?php esc_attr_e('Previous artwork', 'dracka'); ?>"
                        <?php if (!$nav['previous']) echo 'disabled aria-disabled="true"'; ?>>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>
                    </button>
                    <div class="artwork-nav__frame">
                        <?php if (has_post_thumbnail()) : ?>
                            <?php the_post_thumbnail('large', ['class' => 'artwork-nav__img', 'loading' => 'eager']); ?>
                        <?php endif; ?>
                    </div>
                    <button
                        class="artwork-nav__arrow artwork-nav__arrow--next"
                        aria-label="<?php esc_attr_e('Next artwork', 'dracka'); ?>"
                        <?php if (!$nav['next']) echo 'disabled aria-disabled="true"'; ?>>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
                    </button>
                </div>
                <?php else : ?>
                    <?php if (has_post_thumbnail()) : ?>
                        <div class="artwork-thumb"><?php the_post_thumbnail('large'); ?></div>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="artwork-content">
                    <?php the_content(); ?>
                </div>
            </article>
        <?php endwhile; ?>
    <?php endif; ?>
</main>

<?php get_template_part('template-parts/comments-box', null, ['initially_open' => true]); ?>

<?php
get_footer();
