<?php
get_header();

$active_tab = dracka_get_gallery_tab();

$empty_messages = [
    'artwork' => 'No artwork yet.',
    'albums'  => 'No albums yet.',
];

$archive_class = $active_tab === 'albums' ? 'album-grid' : 'artwork-grid';
$card_class = $active_tab === 'albums' ? 'album-card' : 'artwork-card';
?>

<main class="album-archive">
    <h1><?php echo $active_tab === 'artwork' ? esc_html__('Artwork', 'dracka') : esc_html__('Albums', 'dracka'); ?></h1>

    <?php get_template_part('template-parts/archive-tabs', null, [
        'tabs'       => [
            'artwork' => 'Artwork',
            'albums'  => 'Albums',
        ],
        'base_url'   => '/gallery/',
        'active_tab' => $active_tab,
        'nav_label'  => 'Gallery sections',
    ]); ?>

    <?php if (false) : // Search form — commented out; activate when search is implemented 
    ?>
        <div class="gallery-search is-hidden">
            <form role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>" aria-label="Gallery search">
                <input type="search" name="s" value="<?php echo esc_attr(get_search_query()); ?>" placeholder="Search the gallery">
                <input type="hidden" name="dracka_scope" value="gallery">
                <button type="submit">Search</button>
            </form>
        </div>
    <?php endif; ?>

    <?php if (have_posts()) : ?>
        <?php
        $album_elements_by_id = [];

        if ($active_tab === 'albums') {
            global $wp_query;

            $album_ids = wp_list_pluck($wp_query->posts, 'ID');
            $album_ids = array_map('intval', $album_ids);
            $album_ids = array_values(array_filter($album_ids));

            if (!empty($album_ids)) {
                $album_elements_by_id = dracka_get_album_artwork_counts($album_ids);
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
                        <a href="<?php the_permalink(); ?>" class="album-card__link" aria-label="<?php echo esc_attr(get_the_title()); ?>">
                            <?php if (has_post_thumbnail()) : ?>
                                <div class="album-card__cover"><?php the_post_thumbnail('large'); ?></div>
                            <?php endif; ?>
                            <div class="album-card__overlay">
                                <h2 class="album-card__title"><?php the_title(); ?></h2>
                            </div>
                        </a>
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
