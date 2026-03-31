<?php
get_header();

$active_tab = dracka_get_gallery_tab();
$tabs = [
    'artwork' => 'Artwork',
    'albums'  => 'Albums',
];

$empty_messages = [
    'artwork' => 'No artwork yet.',
    'albums'  => 'No albums yet.',
];

$archive_class = $active_tab === 'albums' ? 'album-grid' : 'artwork-grid';
$card_class = $active_tab === 'albums' ? 'album-card' : 'artwork-card';
?>

<main class="album-archive">
    <h1>Albums</h1>

    <nav class="archive-tabs" aria-label="Gallery sections">
        <?php foreach ($tabs as $tab_slug => $tab_label) : ?>
            <?php
            $tab_classes = 'archive-tab';
            if ($tab_slug === $active_tab) {
                $tab_classes .= ' is-active';
            }
            ?>
            <a class="<?php echo esc_attr($tab_classes); ?>" href="<?php echo esc_url(home_url('/gallery/' . $tab_slug . '/')); ?>">
                <?php echo esc_html($tab_label); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="gallery-search is-hidden">
        <form role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>" aria-label="Gallery search">
            <input type="search" name="s" value="<?php echo esc_attr(get_search_query()); ?>" placeholder="Search the gallery">
            <input type="hidden" name="dracka_scope" value="gallery">
            <button type="submit">Search</button>
        </form>
    </div>

    <?php if (have_posts()) : ?>
        <?php
        $album_elements_by_id = [];

        if ($active_tab === 'albums') {
            global $wp_query, $wpdb;

            $album_ids = wp_list_pluck($wp_query->posts, 'ID');
            $album_ids = array_map('intval', $album_ids);
            $album_ids = array_values(array_filter($album_ids));

            if (!empty($album_ids)) {
                $album_elements_by_id = array_fill_keys($album_ids, 0);

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

                $query_args = array_merge(['dracka_album_id', 'artwork', 'publish'], $album_ids);
                $prepared_query = $wpdb->prepare($query_sql, $query_args);

                if (is_string($prepared_query)) {
                    $album_count_rows = $wpdb->get_results($prepared_query, ARRAY_A);

                    if (is_array($album_count_rows)) {
                        foreach ($album_count_rows as $album_count_row) {
                            $album_id = isset($album_count_row['album_id']) ? (int) $album_count_row['album_id'] : 0;
                            $artwork_count = isset($album_count_row['artwork_count']) ? (int) $album_count_row['artwork_count'] : 0;

                            if ($album_id > 0) {
                                $album_elements_by_id[$album_id] = $artwork_count;
                            }
                        }
                    }
                }
            }
        }
        ?>
        <div class="<?php echo esc_attr($archive_class); ?>">
            <?php while (have_posts()) : the_post(); ?>
                <?php
                $post_type = get_post_type();
                $type_badge = $post_type === 'album' ? 'Album' : 'Artwork';
                $is_standalone = false;
                $album_elements_count = 0;
                $premiere_badge_markup = dracka_get_premiere_badge_markup(get_the_ID(), 10);

                if ($post_type === 'artwork') {
                    $album_id = (int) get_post_meta(get_the_ID(), 'dracka_album_id', true);
                    $is_standalone = $album_id <= 0;
                } elseif ($post_type === 'album') {
                    $album_elements_count = isset($album_elements_by_id[get_the_ID()]) ? (int) $album_elements_by_id[get_the_ID()] : 0;
                }
                ?>
                <article <?php post_class($card_class); ?>>
                    <?php if ($premiere_badge_markup !== '') : ?>
                        <div class="card-badges card-badges--ribbon"><?php echo wp_kses_post($premiere_badge_markup); ?></div>
                    <?php endif; ?>
                    <?php if ($post_type === 'album') : ?>
                        <?php if (has_post_thumbnail()) : ?>
                            <a class="album-thumb" href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr(get_the_title()); ?>"><?php the_post_thumbnail('medium'); ?></a>
                        <?php endif; ?>
                        <h2 class="album-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                        <?php
                        $album_elements_label = sprintf(
                            /* translators: %s: artwork count */
                            _n('Includes (%s element)', 'Includes (%s elements)', $album_elements_count, 'dracka'),
                            number_format_i18n($album_elements_count)
                        );
                        ?>
                        <p class="album-card__meta"><?php echo esc_html($album_elements_label); ?></p>
                    <?php else : ?>
                        <?php if (has_post_thumbnail()) : ?>
                            <a class="album-thumb" href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr(get_the_title()); ?>"><?php the_post_thumbnail('medium'); ?></a>
                        <?php endif; ?>
                        <div class="album-excerpt"><?php the_excerpt(); ?></div>
                    <?php endif; ?>
                </article>
            <?php endwhile; ?>
        </div>

        <?php the_posts_pagination(); ?>
    <?php else : ?>
        <p><?php echo esc_html($empty_messages[$active_tab]); ?></p>
    <?php endif; ?>
</main>

<?php
get_footer();
