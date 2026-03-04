<?php
get_header();
$album_id = (int) get_post_meta(get_the_ID(), 'dracka_album_id', true);
$album_link = $album_id ? get_permalink($album_id) : '';
?>

<main class="artwork-single">
    <?php if (have_posts()) : ?>
        <?php while (have_posts()) : the_post(); ?>
            <article <?php post_class(); ?>>
                <div class="card-badges">
                    <span class="content-badge">Artwork</span>
                    <?php if (!$album_id) : ?>
                        <span class="content-badge content-badge--muted">Standalone</span>
                    <?php endif; ?>
                </div>
                <h1><?php the_title(); ?></h1>

                <?php if ($album_link) : ?>
                    <p class="artwork-album">Album: <a href="<?php echo esc_url($album_link); ?>"><?php echo esc_html(get_the_title($album_id)); ?></a></p>
                <?php endif; ?>

                <?php if (has_post_thumbnail()) : ?>
                    <div class="artwork-thumb"><?php the_post_thumbnail('large'); ?></div>
                <?php endif; ?>

                <div class="artwork-content">
                    <?php the_content(); ?>
                </div>
            </article>
        <?php endwhile; ?>
    <?php endif; ?>
</main>

<?php
get_footer();
