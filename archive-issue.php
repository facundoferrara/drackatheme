<?php
get_header();

$active_tab = dracka_get_library_tab();
if ($active_tab === 'series') {
    $active_tab = 'issues';
}
?>

<main class="issue-archive">
    <h1>Issues</h1>

    <?php get_template_part('template-parts/archive-tabs'); ?>

    <?php if (have_posts()) : ?>
        <div class="issue-grid">
            <?php while (have_posts()) : the_post(); ?>
                <?php $premiere_badge_markup = dracka_get_premiere_badge_markup(get_the_ID(), 10); ?>
                <article <?php post_class('issue-card'); ?>>
                    <?php if ($premiere_badge_markup !== '') : ?>
                        <div class="card-badges card-badges--ribbon"><?php echo wp_kses_post($premiere_badge_markup); ?></div>
                    <?php endif; ?>
                    <a href="<?php the_permalink(); ?>" class="issue-card__link">
                        <?php if (has_post_thumbnail()) : ?>
                            <div class="issue-card__thumb"><?php the_post_thumbnail('large'); ?></div>
                        <?php endif; ?>
                        <div class="issue-card__overlay">
                            <h2 class="issue-card__title"><?php the_title(); ?></h2>
                        </div>
                    </a>
                </article>
            <?php endwhile; ?>
        </div>

        <?php the_posts_pagination(); ?>
    <?php else : ?>
        <p>No issues yet.</p>
    <?php endif; ?>
</main>

<?php
get_footer();
